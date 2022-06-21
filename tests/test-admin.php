<?php

use WildWolf\WordPress\HideWPLogin\Admin;
use WildWolf\WordPress\HideWPLogin\Plugin;
use WildWolf\WordPress\HideWPLogin\Settings;

class AdminTest extends WP_UnitTestCase {
	// public function testRegisterSettings() {
	// 	global $wp_settings_sections;
	// 	global $wp_settings_fields;
	// 	$copy_s = $wp_settings_sections;
	// 	$copy_f = $wp_settings_fields;

	// 	$wp_settings_sections = [];

	// 	try {
	// 		Admin::instance()->admin_init();

	// 		self::assertArrayHasKey( 'permalink', $wp_settings_sections );
	// 		self::assertArrayHasKey( 'wwhwl-section', $wp_settings_sections['permalink'] );

	// 		self::assertArrayHasKey( 'permalink', $wp_settings_fields );
	// 		self::assertArrayHasKey( 'wwhwl-section', $wp_settings_fields['permalink'] );
	// 		self::assertArrayHasKey( Settings::OPTION_KEY, $wp_settings_fields['permalink']['wwhwl-section'] );
	// 	} finally {
	// 		$wp_settings_sections = $copy_s;
	// 		$wp_settings_fields   = $copy_f;
	// 	}
	// }

	// public function testRegisterSettings2() {
	// 	global $wp_settings_sections;
	// 	global $wp_settings_fields;
	// 	global $wp_rewrite;

	// 	$copy_s = $wp_settings_sections;
	// 	$copy_f = $wp_settings_fields;

	// 	$wp_settings_sections = [];

	// 	try {
	// 		$wp_settings_sections = [];

	// 		update_option( 'permalink_structure', '' );
	// 		$wp_rewrite->init();

	// 		Admin::instance()->admin_init();

	// 		self::assertArrayHasKey( 'permalink', $wp_settings_fields );
	// 		self::assertArrayHasKey( 'wwhwl-section', $wp_settings_fields['permalink'] );
	// 		self::assertArrayHasKey( Settings::OPTION_KEY, $wp_settings_fields['permalink']['wwhwl-section'] );
	// 		self::assertArrayHasKey( 'args', $wp_settings_fields['permalink']['wwhwl-section'][ Settings::OPTION_KEY ] );
	// 		self::assertArrayHasKey( 'after', $wp_settings_fields['permalink']['wwhwl-section'][ Settings::OPTION_KEY ]['args'] );
	// 		self::assertEmpty( $wp_settings_fields['permalink']['wwhwl-section'][ Settings::OPTION_KEY ]['args']['after'] );

	// 		$wp_settings_sections = [];

	// 		update_option( 'permalink_structure', '/%post_name%/' );
	// 		$wp_rewrite->init();

	// 		Admin::instance()->admin_init();

	// 		self::assertArrayHasKey( 'permalink', $wp_settings_fields );
	// 		self::assertArrayHasKey( 'wwhwl-section', $wp_settings_fields['permalink'] );
	// 		self::assertArrayHasKey( Settings::OPTION_KEY, $wp_settings_fields['permalink']['wwhwl-section'] );
	// 		self::assertArrayHasKey( 'args', $wp_settings_fields['permalink']['wwhwl-section'][ Settings::OPTION_KEY ] );
	// 		self::assertArrayHasKey( 'after', $wp_settings_fields['permalink']['wwhwl-section'][ Settings::OPTION_KEY ]['args'] );
	// 		self::assertNotEmpty( $wp_settings_fields['permalink']['wwhwl-section'][ Settings::OPTION_KEY ]['args']['after'] );
	// 	} finally {
	// 		$wp_settings_sections = $copy_s;
	// 		$wp_settings_fields   = $copy_f;
	// 	}
	// }

	public function test_admin_init(): void {
		$inst  = Admin::instance();
		$_POST = [];

		$inst->admin_init();
		self::assertFalse( has_action( 'load-options-permalink.php', [ $inst, 'load_options_permalink' ] ) );
		self::assertEquals( 10, has_action( 'admin_notices', [ $inst, 'admin_notices' ] ) );
		self::assertEquals( 10, has_filter( 'plugin_action_links_ww-hide-wplogin/plugin.php', [ $inst, 'plugin_action_links' ] ) );

		$_POST = [ 'something' => 1 ];
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

	public function testAdminNotices() {
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

	public function test_network_admin_plugin_action_links() {
		if ( is_multisite() ) {
			self::assertTrue( is_plugin_active_for_network( 'ww-hide-wplogin/plugin.php' ) );

			$inst = Admin::instance();
			$inst->admin_init();

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- plugin name does have dashes
			$links = apply_filters( 'network_admin_plugin_action_links_ww-hide-wplogin/plugin.php', [] );
			self::assertNotEmpty( $links );
			self::assertTrue( is_array( $links ) );
			self::assertArrayHasKey( 'settings', $links );
		} else {
			$this->markTestSkipped( 'This test makes sense only with WPMU' );
		}
	}

	public function testGetForbiddenSlugs() {
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

	public function testLoadOptionsPermalink() {
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

	public function testWPMUOptions() {
		if ( is_multisite() ) {
			self::assertTrue( is_plugin_active_for_network( 'ww-hide-wplogin/plugin.php' ) );

			$inst = Admin::instance();
			$inst->admin_init();

			update_site_option( Settings::OPTION_KEY, 'xxx' );

			ob_start();
			do_action( 'wpmu_options' );
			$s = ob_get_clean();

			self::assertStringContainsString( 'value="xxx"', $s );
		} else {
			$this->markTestSkipped( 'This test makes sense only with WPMU' );
		}
	}

	public function testUpdateWPMUOptions() {
		if ( is_multisite() ) {
			self::assertTrue( is_plugin_active_for_network( 'ww-hide-wplogin/plugin.php' ) );

			$inst = Admin::instance();
			$inst->admin_init();

			$_POST[ Settings::OPTION_KEY ] = 'yyy';
			do_action( 'update_wpmu_options' );
			$actual = get_site_option( Settings::OPTION_KEY );

			// phpcs:ignore WordPress.Security
			self::assertArrayHasKey( Settings::OPTION_KEY, $_POST );
			// phpcs:ignore WordPress.Security
			self::assertEquals( $_POST[ Settings::OPTION_KEY ], $actual );
		} else {
			$this->markTestSkipped( 'This test makes sense only with WPMU' );
		}
	}
}
