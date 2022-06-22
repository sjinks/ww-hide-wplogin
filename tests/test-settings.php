<?php

use WildWolf\WordPress\HideWPLogin\Settings;

/**
 * @psalm-import-type SettingsArray from Settings
 * @uses \WildWolf\WordPress\HideWPLogin\Settings
 */
class Test_Settings extends WP_UnitTestCase /* NOSONAR */ {
	public function setUp(): void {
		parent::setUp();
		delete_option( Settings::OPTION_KEY );
		Settings::instance()->refresh();
	}

	/**
	 * @covers \WildWolf\WordPress\HideWPLogin\Settings::offsetSet
	 */
	public function test_offsetSet(): void {
		$sut = Settings::instance();

		$this->expectException( LogicException::class );
		$sut['slug'] = 'foo';
	}

	/**
	 * @covers \WildWolf\WordPress\HideWPLogin\Settings::offsetUnset
	 */
	public function test_offsetUnset(): void {
		$sut = Settings::instance();

		$this->expectException( LogicException::class );
		unset( $sut['slug'] );
	}

	/**
	 * @covers \WildWolf\WordPress\HideWPLogin\Settings::offsetExists
	 * @dataProvider data_offsetExists
	 */
	public function test_offsetExists( string $offset, bool $expected ): void {
		$sut = Settings::instance();

		$actual = isset( $sut[ $offset ] );
		self::assertSame( $expected, $actual );
	}

	/**
	 * @psalm-return iterable<int,array{string, bool}>
	 */
	public function data_offsetExists(): iterable {
		return [
			[ 'slug', true ],
			[ 'foo', false ],
		];
	}

	/**
	 * @covers \WildWolf\WordPress\HideWPLogin\Settings::offsetGet
	 * @dataProvider data_offsetGet
	 * @param mixed $expected
	 */
	public function test_offsetGet( string $offset, $expected ): void {
		$sut = Settings::instance();

		$actual = $sut[ $offset ];
		self::assertSame( $expected, $actual );
	}

	/**
	 * @psalm-return iterable<int,array{string, mixed}>
	 */
	public function data_offsetGet(): iterable {
		$defaults = Settings::defaults();
		$keys     = array_keys( $defaults );
		$values   = array_values( $defaults );

		return array_map( function ( $key, $value ): array {
			return [ $key, $value ];
		}, $keys, $values);
	}

	/**
	 * @covers \WildWolf\WordPress\HideWPLogin\Settings::as_array
	 * @uses \WildWolf\WordPress\HideWPLogin\Settings::defaults
	 */
	public function test_as_array(): void {
		$sut      = Settings::instance();
		$expected = Settings::defaults();
		$actual   = $sut->as_array();

		self::assertSame( $expected, $actual );
	}

	/**
	 * @covers \WildWolf\WordPress\HideWPLogin\Settings::defaults
	 */
	public function test_defaults(): void {
		$expected = [
			'slug' => '',
		];

		$actual = Settings::defaults();
		self::assertSame( $expected, $actual );
	}

	/**
	 * @covers \WildWolf\WordPress\HideWPLogin\Settings::refresh
	 * @uses \WildWolf\WordPress\HideWPLogin\Settings::defaults
	 * @uses \WildWolf\WordPress\HideWPLogin\Settings::as_array
	 */
	public function test_refresh(): void {
		$sut         = Settings::instance();
		$expected    = Settings::defaults();
		$new         = $expected;
		$new['slug'] = 'xxx';

		update_option( Settings::OPTION_KEY, $new['slug'] );

		$sut->refresh();

		$actual = $sut->as_array();

		self::assertNotSame( $expected, $actual );
		self::assertSame( array_keys( $expected ), array_keys( $actual ) );
		self::assertSame( $actual, $new );
	}

	/**
	 * @psalm-param mixed[] $settings
	 * @uses \WildWolf\WordPress\HideWPLogin\Settings::refresh
	 * @uses \WildWolf\WordPress\HideWPLogin\Settings::defaults
	 * @covers \WildWolf\WordPress\HideWPLogin\Settings::get_slug
	 */
	public function test_get_slug(): void {
		$expected = 'some-slug';

		$sut = Settings::instance();
		update_option( Settings::OPTION_KEY, $expected );
		$sut->refresh();

		$actual = $sut->get_slug();
		self::assertSame( $expected, $actual );
	}
}
