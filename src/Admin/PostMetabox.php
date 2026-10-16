<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Admin;

use Flexa\SeoAeo\Domain\PostMeta;
use Flexa\SeoAeo\Domain\PostMetaRepository;
use Flexa\SeoAeo\Support\Capabilities;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Classic-editor "Flexa SEO" metabox for the per-post SEO overrides. The block
 * editor reads the same fields directly through register_post_meta() (a native
 * sidebar ships with the Phase 4 React app); this box guarantees the classic
 * editor is never left without a way to set them. All values flow through
 * {@see PostMetaRepository} so storage and sanitization stay in one place.
 */
final class PostMetabox {
	use SingletonTrait;

	private const NONCE_ACTION = 'flexa_seo_aeo_post_meta';
	private const NONCE_FIELD  = 'flexa_seo_aeo_post_meta_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post', [ $this, 'save' ], 10, 2 );
	}

	public function add_meta_box( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'title' ) ) {
			return;
		}

		add_meta_box(
			'flexa-seo-aeo-post-meta',
			__( 'Flexa SEO: Search & AI', 'flexa-seo-aeo' ),
			[ $this, 'render' ],
			$post_type,
			'normal',
			'high'
		);
	}

	public function render( WP_Post $post ): void {
		$meta = PostMetaRepository::instance()->get( $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		echo '<div class="flexa-seo-aeo-metabox" style="display:grid;gap:12px;">';

		$this->text_field( 'title', __( 'SEO title', 'flexa-seo-aeo' ), $meta->title, __( 'Leave blank to use the default title template.', 'flexa-seo-aeo' ) );
		$this->textarea_field( 'description', __( 'Meta description', 'flexa-seo-aeo' ), $meta->description, __( 'Leave blank to use the excerpt.', 'flexa-seo-aeo' ) );
		$this->text_field( 'canonical', __( 'Canonical URL', 'flexa-seo-aeo' ), $meta->canonical, __( 'Override only if this content canonicalises elsewhere.', 'flexa-seo-aeo' ) );

		echo '<fieldset style="display:flex;gap:16px;"><legend style="font-weight:600;">' . esc_html__( 'Robots', 'flexa-seo-aeo' ) . '</legend>';
		$this->checkbox_field( 'noindex', __( 'No index', 'flexa-seo-aeo' ), $meta->noindex );
		$this->checkbox_field( 'nofollow', __( 'No follow', 'flexa-seo-aeo' ), $meta->nofollow );
		echo '</fieldset>';

		echo '<details><summary style="cursor:pointer;font-weight:600;">' . esc_html__( 'Social (Open Graph & X)', 'flexa-seo-aeo' ) . '</summary><div style="display:grid;gap:12px;margin-top:12px;">';
		$this->text_field( 'og_title', __( 'Social title', 'flexa-seo-aeo' ), $meta->og_title, '' );
		$this->textarea_field( 'og_description', __( 'Social description', 'flexa-seo-aeo' ), $meta->og_description, '' );
		$this->text_field( 'og_image', __( 'Social image URL', 'flexa-seo-aeo' ), $meta->og_image, __( 'Falls back to the featured image.', 'flexa-seo-aeo' ) );
		echo '</div></details>';

		echo '</div>';
	}

	public function save( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! Capabilities::can_manage() || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		$data = [];
		foreach ( PostMeta::META_KEYS as $field => $meta_key ) {
			unset( $meta_key );
			$input_name = 'flexa_seo_aeo_' . $field;

			if ( in_array( $field, [ 'noindex', 'nofollow' ], true ) ) {
				$data[ $field ] = isset( $_POST[ $input_name ] );
				continue;
			}

			if ( isset( $_POST[ $input_name ] ) ) {
				// Nonce verified above. Each field is sanitized by type in
				// PostMetaRepository::sanitize_field() (esc_url_raw for URLs,
				// sanitize_textarea_field for descriptions, sanitize_text_field
				// otherwise); a blanket sanitizer here would corrupt those, so we
				// only unslash the raw value at this point.
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per-field by type in PostMetaRepository::sanitize_field().
				$data[ $field ] = wp_unslash( (string) $_POST[ $input_name ] );
			}
		}

		PostMetaRepository::instance()->save( $post_id, $data );
	}

	private function text_field( string $field, string $label, string $value, string $help ): void {
		$id = 'flexa_seo_aeo_' . $field;
		printf(
			'<label for="%1$s" style="display:block;"><span style="display:block;font-weight:600;margin-bottom:4px;">%2$s</span><input type="text" class="widefat" id="%1$s" name="%1$s" value="%3$s" /></label>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $value )
		);
		if ( '' !== $help ) {
			printf( '<p class="description" style="margin-top:4px;">%s</p>', esc_html( $help ) );
		}
	}

	private function textarea_field( string $field, string $label, string $value, string $help ): void {
		$id = 'flexa_seo_aeo_' . $field;
		printf(
			'<label for="%1$s" style="display:block;"><span style="display:block;font-weight:600;margin-bottom:4px;">%2$s</span><textarea class="widefat" rows="2" id="%1$s" name="%1$s">%3$s</textarea></label>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_textarea( $value )
		);
		if ( '' !== $help ) {
			printf( '<p class="description" style="margin-top:4px;">%s</p>', esc_html( $help ) );
		}
	}

	private function checkbox_field( string $field, string $label, bool $checked ): void {
		$id = 'flexa_seo_aeo_' . $field;
		printf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%1$s" value="1" %3$s /> %2$s</label>',
			esc_attr( $id ),
			esc_html( $label ),
			checked( $checked, true, false )
		);
	}
}
