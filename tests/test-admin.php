<?php

use WildWolf\WordPress\HideWPLogin\Admin;
use WildWolf\WordPress\HideWPLogin\Settings;

class Test_Admin extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		$this->reset__SERVER();
	}

	public function test_admin_init(): void {
		$inst  = Admin::instance();

		$inst->admin_init();
		self::assertFalse( has_action( 'load-options-permalink.php', [ $inst, 'load_options_permalink' ] ) );
		self::assertEquals( 10, has_action( 'admin_notices', [ $inst, 'admin_notices' ] ) );
		self::assertEquals( 10, has_filter( 'plugin_action_links_ww-hide-wplogin/plugin.php', [ $inst, 'plugin_action_links' ] ) );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$inst->admin_init();
		self::assertEquals( 10, has_action( 'load-options-permalink.php', [ $inst, 'load_options_permalink' ] ) );
	}

	public function test_login_url_authredirect(): void {
		global $current_screen;

		self::assertFalse( is_admin() );
		$current_screen = new class() {
			public function in_admin(): bool {
				return true;
			}
		};

		// phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement -- it throws an exception
		add_filter( 'wp_redirect', function( string $url ) {
			throw new Exception( $url );
		} );

		update_option( Settings::OPTION_KEY, 'LOGIN' );
		$this->expectException( WPDieException::class );
		try {
			auth_redirect();
		} finally {
			$current_screen = null;
		}
	}

	public function test_admin_notices(): void {
		global $pagenow;
		$copy = $pagenow;

		try {
			remove_all_actions( 'admin_notices' );
			$inst = Admin::instance();
			$inst->admin_init();

			delete_transient( 'settings_errors' );
			$e = get_settings_errors();
			self::assertEmpty( $e );

			$pagenow = 'index.php';
			do_action( 'admin_notices' );
			$e = get_settings_errors();
			self::assertEmpty( $e );

			$pagenow = 'options-permalink.php';
			$_GET    = [];
			do_action( 'admin_notices' );
			$e = get_settings_errors();
			self::assertEmpty( $e );

			$_GET = [ 'settings-updated' => 'true' ];
			do_action( 'admin_notices' );
			$e = get_settings_errors();
			self::assertNotEmpty( $e );

			self::assertArrayHasKey( 0, $e );
			self::assertArrayHasKey( 'code', $e[0] );
			self::assertEquals( 'wwhwl_settings_updated', $e[0]['code'] );
		} finally {
			delete_transient( 'settings_errors' );
			$pagenow = $copy;
			$_GET    = [];
		}
	}

	public function test_plugin_action_links(): void {
		$inst = Admin::instance();
		$inst->admin_init();

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- plugin name does have dashes
		$links = apply_filters( 'plugin_action_links_ww-hide-wplogin/plugin.php', [] );
		self::assertNotEmpty( $links );
		self::assertTrue( is_array( $links ) );
		self::assertArrayHasKey( 'settings', $links );
	}

	public function test_get_forbidden_slugs(): void {
		$_POST = [ Settings::OPTION_KEY => 'p' ];

		try {
			delete_option( Settings::OPTION_KEY );
			$inst = Admin::instance();
			$inst->load_options_permalink();
			$actual = get_option( Settings::OPTION_KEY );
			self::assertEmpty( $actual );
		} finally {
			$_POST = [];
		}
	}

	public function test_load_options_permalink(): void {
		$_POST = [ Settings::OPTION_KEY => 'login' ];

		try {
			delete_option( Settings::OPTION_KEY );
			$inst = Admin::instance();
			$inst->load_options_permalink();
			$actual = get_option( Settings::OPTION_KEY );
			// phpcs:ignore WordPress.Security
			self::assertArrayHasKey( Settings::OPTION_KEY, $_POST );
			// phpcs:ignore WordPress.Security
			self::assertEquals( $_POST[ Settings::OPTION_KEY ], $actual );
		} finally {
			$_POST = [];
		}
	}
}
