<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Rest;

use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every REST controller in one place. Extensions add their own
 * controllers by hooking the `flexa_seo_aeo/rest/register_routes` action.
 *
 * A controller class that exists but is never newed up here is silently dead —
 * register every one.
 */
final class RegisterFacade {
	use SingletonTrait;

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new SettingsController() )->register_routes();
		( new PostMetaController() )->register_routes();
		( new ReadinessController() )->register_routes();
		( new DashboardController() )->register_routes();
		( new MigrationController() )->register_routes();
		( new OnboardingController() )->register_routes();
		( new HintsController() )->register_routes();

		do_action( 'flexa_seo_aeo/rest/register_routes' );
	}
}
