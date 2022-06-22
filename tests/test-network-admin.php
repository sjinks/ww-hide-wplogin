<?php

use WildWolf\WordPress\HideWPLogin\Network_Admin;
use WildWolf\WordPress\HideWPLogin\Settings;

class Test_Network_Admin extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'This test makes sense only with WPMU' );
		}
	}

	public function test_admin_init(): void {
		$inst = Network_Admin::instance();
		$inst->admin_init();
		self::assertEquals( 10, has_filter( 'network_admin_plugin_action_links_ww-hide-wplogin/plugin.php', [ $inst, 'network_admin_plugin_action_links' ] ) );
	}

	public function test_network_admin_plugin_action_links(): void {
		$inst = Network_Admin::instance();
		$inst->admin_init();

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- plugin name does have dashes
		$links = apply_filters( 'network_admin_plugin_action_links_ww-hide-wplogin/plugin.php', [] );
		self::assertNotEmpty( $links );
		self::assertTrue( is_array( $links ) );
		self::assertArrayHasKey( 'settings', $links );
	}

	public function test_wpmu_options(): void {
		$inst = Network_Admin::instance();
		$inst->admin_init();

		update_site_option( Settings::OPTION_KEY, 'xxx' );

		ob_start();
		do_action( 'wpmu_options' );
		$s = ob_get_clean();

		self::assertStringContainsString( 'value="xxx"', $s );
	}

	public function test_update_wpmu_options(): void {
		$inst = Network_Admin::instance();
		$inst->admin_init();

		$_POST[ Settings::OPTION_KEY ] = 'yyy';
		do_action( 'update_wpmu_options' );
		$actual = get_site_option( Settings::OPTION_KEY );

		// phpcs:ignore WordPress.Security
		self::assertArrayHasKey( Settings::OPTION_KEY, $_POST );
		// phpcs:ignore WordPress.Security
		self::assertEquals( $_POST[ Settings::OPTION_KEY ], $actual );
	}
}
