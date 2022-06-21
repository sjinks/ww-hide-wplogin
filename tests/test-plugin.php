<?php
use WildWolf\WordPress\HideWPLogin\Plugin;
use WildWolf\WordPress\HideWPLogin\Settings;

class PluginTest extends WP_UnitTestCase {
	public function test_site_url(): void {
		$url = site_url( 'wp-login.php' );
		self::assertStringContainsString( 'wp-login.php', $url );

		update_option( Settings::OPTION_KEY, 'LOGIN' );
		$url = site_url( 'wp-login.php' );
		self::assertStringNotContainsString( 'wp-login.php', $url );
		self::assertStringContainsString( 'LOGIN', $url );
	}

	public function test_login_url(): void {
		$url = wp_login_url();
		self::assertStringContainsString( 'wp-login.php', $url );

		update_option( Settings::OPTION_KEY, 'LOGIN' );
		$url = wp_login_url();
		self::assertStringNotContainsString( 'wp-login.php', $url );
		self::assertStringContainsString( 'LOGIN', $url );
	}

	public function test_login_url_admin(): void {
		global $current_screen;

		self::assertFalse( is_admin() );
		$current_screen = new class() {
			public function in_admin() {
				return true;
			}
		};

		try {
			$url = wp_login_url();
			self::assertStringContainsString( 'wp-login.php', $url );

			update_option( Settings::OPTION_KEY, 'LOGIN' );
			$url = wp_login_url();
			self::assertStringNotContainsString( 'wp-login.php', $url );
			self::assertStringContainsString( 'LOGIN', $url );
		} finally {
			$current_screen = null;
		}
	}

	public function test_welcome_email(): void {
		reset_phpmailer_instance();

		if ( is_multisite() ) {
			delete_option( Settings::OPTION_KEY );
			self::assertTrue( wpmu_welcome_notification( 1, 1, 'password', 'title' ) );

			$actual = tests_retrieve_phpmailer_instance()->get_sent()->body;
			self::assertStringContainsString( 'wp-login.php', $actual );

			reset_phpmailer_instance();
			update_option( Settings::OPTION_KEY, 'LOGIN' );
			self::assertTrue( wpmu_welcome_notification( 1, 1, 'pAssword', 'title' ) );

			$actual = tests_retrieve_phpmailer_instance()->get_sent()->body;
			self::assertStringNotContainsString( 'wp-login.php', $actual );
		} else {
			self::markTestSkipped( 'This test makes sense only with WPMU' );
		}
	}

	public function test_init() {
		remove_all_actions( 'wp_loaded', 10 );
		remove_all_filters( 'site_option_welcome_email', 10 );

		$inst = Plugin::instance();
		$inst->init();

		self::assertFalse( has_action( 'wp_loaded', [ $inst, 'wp_loaded' ] ) );
		self::assertFalse( has_filter( 'update_welcome_email', [ $inst, 'update_welcome_email' ] ) );

		self::assertFalse( has_filter( 'login_url', [ $inst, 'site_url' ] ) );
		self::assertFalse( has_filter( 'site_url', [ $inst, 'site_url' ] ) );
		self::assertFalse( has_filter( 'network_site_url', [ $inst, 'site_url' ] ) );
		self::assertFalse( has_filter( 'wp_redirect', [ $inst, 'site_url' ] ) );

		update_option( Settings::OPTION_KEY, 'xxx' );

		self::assertEquals( 10, has_action( 'wp_loaded', [ $inst, 'wp_loaded' ] ) );
		self::assertEquals( 10, has_filter( 'update_welcome_email', [ $inst, 'update_welcome_email' ] ) );

		self::assertEquals( 100, has_filter( 'login_url', [ $inst, 'site_url' ] ) );
		self::assertEquals( 100, has_filter( 'site_url', [ $inst, 'site_url' ] ) );
		self::assertEquals( 100, has_filter( 'network_site_url', [ $inst, 'site_url' ] ) );
		self::assertEquals( 100, has_filter( 'wp_redirect', [ $inst, 'site_url' ] ) );
	}

	/**
	 * @psalm-return iterable<int, array{string, string, string, array<string, string>, bool}>
	 */
	public function data_is_new_login(): iterable {
		return [
			[ '/%post_name%/', 'LOGIN', '/LOGIN', [], true ],
			[ '/%post_name%/', 'LOGIN', '/LOGIN/', [], false ], // is_new_login() expects path without trailing slash
			[ '/%post_name%/', 'LOGIN', '/login', [], false ],

			[ '', 'LOGIN', '', [ 'LOGIN' => '' ], true ],
			[ '', 'LOGIN', '', [ 'login' => '' ], false ],
			[ '', 'LOGIN', '/', [ 'LOGIN' => '' ], false ],
			[ '', 'LOGIN', '/', [ 'login' => '' ], false ],
			[ '', 'LOGIN', '/post', [ 'LOGIN' => '' ], false ],
		];
	}

	/**
	 * @dataProvider data_is_new_login
	 * @global WP_Rewrite $wp_rewrite
	 */
	public function test_is_new_login( string $ps, string $slug, string $path, array $get, bool $expected ): void {
		/** @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		$_GET = $get;
		update_option( Settings::OPTION_KEY, $slug );
		update_option( 'permalink_structure', $ps );
		$wp_rewrite->init();

		$inst   = Plugin::instance();
		$actual = $inst->is_new_login( $path );
		self::assertEquals( $expected, $actual );
	}

	public function data_wp_loaded(): iterable {
		return [
			// If slug is not set, Plugin::wp_loaded() exits early
			[ '', '', '/', 'OK' ],
			// If permalink structure and request path do not agree on trailing slash, redirect
			[ '/%post_name%/', 'LOGIN', '/LOGIN', 'wwhwl_canonical_login_redirect' ],
			[ '/%post_name%', 'LOGIN', '/LOGIN/', 'wwhwl_canonical_login_redirect' ],
			// If permalink structure and request path do agree on trailing slash, login
			[ '/%post_name%/', 'LOGIN', '/LOGIN/', 'wwhwl_new_login' ],
			[ '/%post_name%', 'LOGIN', '/LOGIN', 'wwhwl_new_login' ],
			// If the paths does not match the slug, exit
			[ '/%post_name%/', 'LOGIN', '/post/', 'OK' ],
			// Detect wp-login.php
			[ '/%post_name%/', 'LOGIN', '/wp-login.php', 'wwhwl_wplogin_accessed' ],
			[ '/%post_name%/', 'LOGIN', '/wp%2Dlogin%2Ephp', 'wwhwl_wplogin_accessed' ],
			[ '/%post_name%/', 'LOGIN', '//wp-login.php', 'wwhwl_wplogin_accessed' ],
			[ '/%post_name%/', 'LOGIN', '//wp%2Dlogin%2Ephp', 'wwhwl_wplogin_accessed' ],
			[ '/%post_name%/', 'LOGIN', '/%2Fwp-login.php', 'wwhwl_wplogin_accessed' ],
			[ '/%post_name%/', 'LOGIN', '/%2Fwp%2Dlogin%2Ephp', 'wwhwl_wplogin_accessed' ],
		];
	}

	/**
	 * @dataProvider data_wp_loaded
	 * @global WP_Rewrite $wp_rewrite
	 */
	public function test_wp_loaded( string $ps, string $slug, string $path, string $message ): void {
		/** @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		$_GET                   = [];
		$_SERVER['REQUEST_URI'] = $path;
		update_option( Settings::OPTION_KEY, $slug );
		update_option( 'permalink_structure', $ps );
		$wp_rewrite->init();

		$handler = function() {
			throw new Exception( current_action() );
		};

		add_action( 'wwhwl_wplogin_accessed', $handler );
		add_action( 'wwhwl_canonical_login_redirect', $handler );
		add_action( 'wwhwl_new_login', $handler );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $message );

		$inst = Plugin::instance();
		$inst->wp_loaded();
		throw new Exception( 'OK' );
	}

	/**
	 * @psalm-return iterable<int,array{string, string, string}>
	 */
	public function data_new_login_url(): iterable {
		return [
			[ '', 'LOGIN', 'http://example.org/?LOGIN' ],
			[ '/%post_name%', 'LOGIN', 'http://example.org/LOGIN' ],
		];
	}

	/**
	 * @dataProvider data_new_login_url
	 */
	public function test_new_login_url( string $ps, string $slug, string $expected ): void {
		global $wp_rewrite;

		update_option( Settings::OPTION_KEY, $slug );
		update_option( 'permalink_structure', $ps );
		$wp_rewrite->init();

		$inst   = Plugin::instance();
		$actual = $inst->new_login_url();
		self::assertEquals( $expected, $actual );
	}

	/**
	 * @psalm-return iterable<int,array{string, bool, string}>
	 */
	public function data_rewrite_login_url(): iterable {
		return [
			[ '/', false, 'http://' . WP_TESTS_DOMAIN . '/' ],
			[ '/', true, 'https://' . WP_TESTS_DOMAIN . '/' ],
			[ '/wp-login.php', true, 'https://' . WP_TESTS_DOMAIN . '/?LOGIN' ],
			[ '/wp-login.php?arg', false, 'http://' . WP_TESTS_DOMAIN . '/?LOGIN&arg' ],
			[ '/wp-login.php?action=postpass', false, 'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=postpass' ],
		];
	}

	/**
	 * @dataProvider data_rewrite_login_url
	 * @global WP_Rewrite $wp_rewrite
	 */
	public function test_rewrite_login_url( string $url, bool $ssl, string $expected ): void {
		/** @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		update_option( Settings::OPTION_KEY, 'LOGIN' );
		update_option( 'permalink_structure', '' );
		$wp_rewrite->init();

		if ( $ssl ) {
			$_SERVER['HTTPS'] = 'on';
		} else {
			unset( $_SERVER['HTTPS'] );
		}

		try {
			$actual = site_url( $url );
			self::assertEquals( $expected, $actual );
		} finally {
			tests_reset__SERVER();
		}
	}

	public function test_init_filters(): void {
		$inst = Plugin::instance();

		self::assertFalse( has_action( 'wp_loaded', [ $inst, 'wp_loaded' ] ) );
		self::assertFalse( has_filter( 'update_welcome_email', [ $inst, 'update_welcome_email' ] ) );
		self::assertFalse( has_filter( 'login_url', [ $inst, 'site_url' ] ) );
		self::assertFalse( has_filter( 'site_url', [ $inst, 'site_url' ] ) );
		self::assertFalse( has_filter( 'network_site_url', [ $inst, 'site_url' ] ) );
		self::assertFalse( has_filter( 'wp_redirect', [ $inst, 'site_url' ] ) );

		update_option( Settings::OPTION_KEY, 'xxx' );

		self::assertEquals( 10, has_action( 'wp_loaded', [ $inst, 'wp_loaded' ] ) );
		self::assertEquals( 10, has_filter( 'update_welcome_email', [ $inst, 'update_welcome_email' ] ) );
		self::assertEquals( 100, has_filter( 'login_url', [ $inst, 'site_url' ] ) );
		self::assertEquals( 100, has_filter( 'site_url', [ $inst, 'site_url' ] ) );
		self::assertEquals( 100, has_filter( 'network_site_url', [ $inst, 'site_url' ] ) );
		self::assertEquals( 100, has_filter( 'wp_redirect', [ $inst, 'site_url' ] ) );

		update_option( Settings::OPTION_KEY, '' );

		self::assertFalse( has_action( 'wp_loaded', [ $inst, 'wp_loaded' ] ) );
		self::assertFalse( has_filter( 'update_welcome_email', [ $inst, 'update_welcome_email' ] ) );
		self::assertFalse( has_filter( 'login_url', [ $inst, 'site_url' ] ) );
		self::assertFalse( has_filter( 'site_url', [ $inst, 'site_url' ] ) );
		self::assertFalse( has_filter( 'network_site_url', [ $inst, 'site_url' ] ) );
		self::assertFalse( has_filter( 'wp_redirect', [ $inst, 'site_url' ] ) );
	}

	public function test_get_login_slug_wpmu(): void {
		$name = Settings::OPTION_KEY;

		$value = get_site_option( $name );
		self::assertEmpty( $value );

		$value = get_option( $name );
		self::assertEmpty( $value );

		update_site_option( $name, 'xxx' );

		if ( is_multisite() ) {
			$value = get_option( $name );
			self::assertEmpty( $value );
		}

		$value = Plugin::get_login_slug();
		self::assertEquals( 'xxx', $value );

		update_site_option( $name, 'yyy' );
		$value = Plugin::get_login_slug();
		self::assertEquals( 'yyy', $value );
	}
}
