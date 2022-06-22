<?php

use WildWolf\WordPress\HideWPLogin\AdminSettings;
use WildWolf\WordPress\HideWPLogin\Settings;

/**
 * @covers \WildWolf\WordPress\HideWPLogin\AdminSettings
 * @uses \WildWolf\WordPress\HideWPLogin\InputFactory
 */
class Test_AdminSettings extends WP_UnitTestCase /* NOSONAR */ {
	public function setUp(): void {
		parent::setUp();
		AdminSettings::instance()->register_settings();
	}

	public function test_settings_registered(): void {
		global $wp_registered_settings;

		self::assertIsArray( $wp_registered_settings );
		self::assertArrayHasKey( Settings::OPTION_KEY, $wp_registered_settings );
		self::assertIsArray( $wp_registered_settings[ Settings::OPTION_KEY ] );
		self::assertArrayHasKey( 'group', $wp_registered_settings[ Settings::OPTION_KEY ] );
		self::assertSame( AdminSettings::OPTION_GROUP, $wp_registered_settings[ Settings::OPTION_KEY ]['group'] );
	}

	public function test_save_option(): void {
		$input    = 'This is awésome!';
		$expected = 'this-is-aw%c3%a9some';

		update_option( Settings::OPTION_KEY, $input );

		$actual = get_option( Settings::OPTION_KEY );
		self::assertSame( $expected, $actual );
	}

	public function test_save_site_option(): void {
		$input    = 'This is awésome!';
		$expected = 'this-is-aw%c3%a9some';

		update_site_option( Settings::OPTION_KEY, $input );

		$actual = get_site_option( Settings::OPTION_KEY );
		self::assertSame( $expected, $actual );
	}
}
