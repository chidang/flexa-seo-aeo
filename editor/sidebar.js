/**
 * Flexa SEO — block-editor sidebar for per-post SEO/AEO overrides.
 *
 * Reads and writes the post meta registered by Domain\PostMetaRepository
 * (the `_flexa_seo_aeo_*` keys, exposed via register_post_meta + show_in_rest).
 * Written against the `wp.*` globals so it needs no build step; enqueued with
 * the wp-plugins / wp-editor / wp-components / wp-core-data deps.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.coreData ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var registerPlugin = wp.plugins.registerPlugin;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var apiFetch = wp.apiFetch;
	var comp = wp.components;
	var __ = wp.i18n.__;

	// PluginSidebar moved from wp.editPost to wp.editor in recent WP; support both.
	var editorApi = wp.editor || {};
	var editPostApi = wp.editPost || {};
	var PluginSidebar =
		editorApi.PluginSidebar || editPostApi.PluginSidebar;
	var PluginSidebarMoreMenuItem =
		editorApi.PluginSidebarMoreMenuItem ||
		editPostApi.PluginSidebarMoreMenuItem;

	if ( ! PluginSidebar || ! useEntityProp ) {
		return;
	}

	var PREFIX = '_flexa_seo_aeo_';
	var SIDEBAR = 'flexa-seo-aeo-sidebar';

	var STATUS = {
		pass: { icon: '✓', color: '#16a34a' },
		warn: { icon: '⚠', color: '#d97706' },
		fail: { icon: '✗', color: '#dc2626' },
	};

	/**
	 * How well-founded each check is, mirroring Services\Aeo\Readiness::EVIDENCE.
	 * Shown as a badge next to the check so the score reads as an argued opinion
	 * rather than a measurement: `documented` tracks a vendor's published rule,
	 * `observed` tracks a correlation nobody has committed to, `heuristic` is our
	 * own bet. The full rubric, weights included, is in docs/SCORING.md.
	 */
	var EVIDENCE = {
		documented: {
			label: __( 'Documented', 'flexa-seo-aeo' ),
			title: __( 'Described in a search or AI vendor\u2019s own published guidance.', 'flexa-seo-aeo' ),
			color: '#0f766e',
			background: '#ccfbf1',
		},
		observed: {
			label: __( 'Observed', 'flexa-seo-aeo' ),
			title: __( 'Associated with being quoted in testing and third-party studies, but not documented by any engine.', 'flexa-seo-aeo' ),
			color: '#4338ca',
			background: '#e0e7ff',
		},
		heuristic: {
			label: __( 'Heuristic', 'flexa-seo-aeo' ),
			title: __( 'Our own judgement call. No engine has confirmed it reads this signal.', 'flexa-seo-aeo' ),
			color: '#9a3412',
			background: '#ffedd5',
		},
	};

	var GRADE_LABEL = {
		excellent: __( 'Excellent', 'flexa-seo-aeo' ),
		good: __( 'Good', 'flexa-seo-aeo' ),
		fair: __( 'Fair', 'flexa-seo-aeo' ),
		poor: __( 'Needs work', 'flexa-seo-aeo' ),
	};

	function gradeColor( grade ) {
		if ( grade === 'excellent' || grade === 'good' ) {
			return STATUS.pass.color;
		}
		if ( grade === 'fair' ) {
			return STATUS.warn.color;
		}
		return STATUS.fail.color;
	}

	/**
	 * Small badge naming how well-founded a check is. An unknown level falls back
	 * to the most cautious one, matching what the scorer does server-side.
	 */
	function evidenceBadge( level ) {
		var meta = EVIDENCE[ level ] || EVIDENCE.heuristic;

		return el(
			'span',
			{
				title: meta.title,
				style: {
					marginLeft: '6px',
					padding: '1px 6px',
					borderRadius: '9px',
					fontSize: '10px',
					fontWeight: 600,
					textTransform: 'uppercase',
					letterSpacing: '0.03em',
					color: meta.color,
					background: meta.background,
					whiteSpace: 'nowrap',
				},
			},
			meta.label
		);
	}

	/**
	 * Answer-Engine Readiness score + checklist. Fetches the report from the REST
	 * endpoint on mount and re-fetches after each successful (non-autosave) save,
	 * so the score tracks the content the editor is actually working on.
	 */
	function ReadinessPanel() {
		var postId = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );
		var isSaving = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return editor.isSavingPost() && ! editor.isAutosavingPost();
		}, [] );
		// Whether the current user can change global settings (manage_options).
		// The one-click fixes flip a site-wide toggle via the settings endpoint,
		// so an author who can edit the post but not settings sees the hint only.
		var canManageSettings = useSelect( function ( select ) {
			var core = select( 'core' );
			return !! ( core && core.canUser && core.canUser( 'update', 'settings' ) );
		}, [] );

		var reportState = useState( null );
		var report = reportState[ 0 ];
		var setReport = reportState[ 1 ];
		var loadingState = useState( false );
		var loading = loadingState[ 0 ];
		var setLoading = loadingState[ 1 ];
		// The check id whose site-wide fix is currently being applied.
		var applyingState = useState( null );
		var applying = applyingState[ 0 ];
		var setApplying = applyingState[ 1 ];
		var wasSaving = useRef( false );

		function load() {
			if ( ! postId || ! apiFetch ) {
				return;
			}
			setLoading( true );
			apiFetch( { path: '/flexa-seo-aeo/v1/readiness/' + postId } )
				.then( function ( data ) {
					setReport( data );
					setLoading( false );
				} )
				.catch( function () {
					setLoading( false );
				} );
		}

		// Apply a one-click site-wide fix: POST the toggle patch to the settings
		// endpoint (which partial-merges it), then re-score this post.
		function applyFix( check ) {
			if ( ! check || ! check.fix || ! apiFetch ) {
				return;
			}
			setApplying( check.id );
			apiFetch( {
				path: '/flexa-seo-aeo/v1/settings',
				method: 'POST',
				data: check.fix.patch,
			} )
				.then( function () {
					setApplying( null );
					load();
				} )
				.catch( function () {
					setApplying( null );
				} );
		}

		useEffect( function () {
			load();
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ postId ] );

		useEffect( function () {
			if ( wasSaving.current && ! isSaving ) {
				load();
			}
			wasSaving.current = isSaving;
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ isSaving ] );

		var body = [];

		if ( ! report ) {
			body.push(
				el(
					'p',
					{ key: 'empty', style: { color: '#64748b', margin: 0 } },
					loading
						? __( 'Scoring…', 'flexa-seo-aeo' )
						: __( 'Save the post to calculate its readiness score.', 'flexa-seo-aeo' )
				)
			);
		} else {
			var color = gradeColor( report.grade );

			body.push(
				el(
					'div',
					{
						key: 'score',
						style: {
							display: 'flex',
							alignItems: 'baseline',
							gap: '8px',
							marginBottom: '10px',
						},
					},
					el(
						'span',
						{ style: { fontSize: '28px', fontWeight: 700, color: color, lineHeight: 1 } },
						String( report.score )
					),
					el( 'span', { style: { color: '#64748b' } }, '/ 100' ),
					el(
						'span',
						{ style: { marginLeft: 'auto', fontWeight: 600, color: color } },
						GRADE_LABEL[ report.grade ] || report.grade
					)
				)
			);

			var items = ( report.checks || [] ).map( function ( check ) {
				var meta = STATUS[ check.status ] || STATUS.fail;
				return el(
					'li',
					{
						key: check.id,
						style: {
							display: 'flex',
							gap: '8px',
							padding: '6px 0',
							borderTop: '1px solid #f1f5f9',
						},
					},
					el(
						'span',
						{ style: { color: meta.color, fontWeight: 700, flex: '0 0 auto' }, 'aria-hidden': true },
						meta.icon
					),
					el(
						'span',
						null,
						el( 'span', { style: { fontWeight: 600 } }, check.label ),
						evidenceBadge( check.evidence ),
						el(
							'span',
							{ style: { display: 'block', color: '#64748b', fontSize: '12px', marginTop: '2px' } },
							check.hint
						),
						check.fix && canManageSettings
							? el(
									comp.Button,
									{
										variant: 'secondary',
										isSmall: true,
										isBusy: applying === check.id,
										disabled: applying === check.id,
										onClick: function () {
											applyFix( check );
										},
										style: { marginTop: '6px' },
									},
									applying === check.id
										? __( 'Enabling…', 'flexa-seo-aeo' )
										: check.fix.label +
												' · ' +
												__( 'site-wide', 'flexa-seo-aeo' )
							  )
							: null
					)
				);
			} );

			body.push(
				el(
					'ul',
					{ key: 'checks', style: { listStyle: 'none', margin: 0, padding: 0 } },
					items
				)
			);
		}

		body.push(
			el(
				comp.Button,
				{
					key: 'refresh',
					variant: 'link',
					isBusy: loading,
					disabled: loading || ! postId,
					onClick: load,
					style: { marginTop: '8px' },
				},
				__( 'Re-check readiness', 'flexa-seo-aeo' )
			)
		);

		return el(
			comp.PanelBody,
			{ title: __( 'Answer-Engine Readiness', 'flexa-seo-aeo' ), initialOpen: true },
			body
		);
	}

	function Sidebar() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		var entity = useEntityProp( 'postType', postType, 'meta' );
		var meta = entity[ 0 ] || {};
		var setMeta = entity[ 1 ];

		function set( key, value ) {
			var next = Object.assign( {}, meta );
			next[ PREFIX + key ] = value;
			setMeta( next );
		}
		function str( key ) {
			var v = meta[ PREFIX + key ];
			return typeof v === 'string' ? v : '';
		}
		function bool( key ) {
			return !! meta[ PREFIX + key ];
		}

		function text( key, label, help ) {
			return el( comp.TextControl, {
				__nextHasNoMarginBottom: true,
				label: label,
				help: help,
				value: str( key ),
				onChange: function ( value ) {
					set( key, value );
				},
			} );
		}
		function textarea( key, label, help ) {
			return el( comp.TextareaControl, {
				__nextHasNoMarginBottom: true,
				label: label,
				help: help,
				value: str( key ),
				onChange: function ( value ) {
					set( key, value );
				},
			} );
		}
		function toggle( key, label, help ) {
			return el( comp.ToggleControl, {
				__nextHasNoMarginBottom: true,
				label: label,
				help: help,
				checked: bool( key ),
				onChange: function ( value ) {
					set( key, !! value );
				},
			} );
		}

		return el(
			Fragment,
			null,
			el(
				PluginSidebarMoreMenuItem,
				{ target: SIDEBAR, icon: 'search' },
				__( 'Flexa SEO', 'flexa-seo-aeo' )
			),
			el(
				PluginSidebar,
				{
					name: SIDEBAR,
					title: __( 'Flexa SEO', 'flexa-seo-aeo' ),
					icon: 'search',
				},
				el( ReadinessPanel, { key: 'readiness' } ),
				el(
					comp.PanelBody,
					{ title: __( 'Search Appearance', 'flexa-seo-aeo' ), initialOpen: false },
					text(
						'title',
						__( 'SEO title', 'flexa-seo-aeo' ),
						__( 'Overrides the computed <title>. Supports %%tokens%%.', 'flexa-seo-aeo' )
					),
					textarea(
						'description',
						__( 'Meta description', 'flexa-seo-aeo' ),
						__( 'The snippet search and answer engines may show.', 'flexa-seo-aeo' )
					),
					text(
						'canonical',
						__( 'Canonical URL', 'flexa-seo-aeo' ),
						__( 'Leave blank to use this post’s permalink.', 'flexa-seo-aeo' )
					)
				),
				el(
					comp.PanelBody,
					{ title: __( 'Robots', 'flexa-seo-aeo' ), initialOpen: false },
					toggle(
						'noindex',
						__( 'No index', 'flexa-seo-aeo' ),
						__( 'Ask engines not to index this page (also excludes it from the sitemap and llms.txt).', 'flexa-seo-aeo' )
					),
					toggle(
						'nofollow',
						__( 'No follow', 'flexa-seo-aeo' ),
						__( 'Ask engines not to follow links on this page.', 'flexa-seo-aeo' )
					)
				),
				el(
					comp.PanelBody,
					{ title: __( 'Open Graph', 'flexa-seo-aeo' ), initialOpen: false },
					text( 'og_title', __( 'OG title', 'flexa-seo-aeo' ) ),
					textarea( 'og_description', __( 'OG description', 'flexa-seo-aeo' ) ),
					text(
						'og_image',
						__( 'OG image URL', 'flexa-seo-aeo' ),
						__( 'Falls back to the featured image.', 'flexa-seo-aeo' )
					)
				),
				el(
					comp.PanelBody,
					{ title: __( 'X (Twitter)', 'flexa-seo-aeo' ), initialOpen: false },
					text( 'twitter_title', __( 'Card title', 'flexa-seo-aeo' ) ),
					textarea( 'twitter_description', __( 'Card description', 'flexa-seo-aeo' ) ),
					text( 'twitter_image', __( 'Card image URL', 'flexa-seo-aeo' ) )
				)
			)
		);
	}

	registerPlugin( SIDEBAR, { render: Sidebar, icon: 'search' } );
} )( window.wp );
