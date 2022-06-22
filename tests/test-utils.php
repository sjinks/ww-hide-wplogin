<?php

use WildWolf\WordPress\HideWPLogin\Utils;

/**
 * @return mixed
 */
function call( callable $c, array $args = null ) {
	return call_user_func_array( $c, $args );
}

class Test_Utils extends WP_UnitTestCase {
	private string $location = '';

	public function setUp(): void {
		parent::setUp();
		$this->reset__SERVER();
	}

	public function test_is_called_from(): void {
		self::assertTrue( class_exists( Utils::class, true ) );

		$actual = call( [ Utils::class, 'is_called_from' ], [ 'call' ] );
		self::assertTrue( $actual );

		$actual = call( [ Utils::class, 'is_called_from' ], [ 'donotcall' ] );
		self::assertFalse( $actual );
	}

	public function test_terminate() {
		$this->expectException( WPDieException::class );
		Utils::terminate();
	}

	/**
	 * @psalm-return iterable<array{string,string,string}>
	 */
	public function data_handle_trailing_slash(): iterable {
		return [
			[ '/%postname%/', '/url/', '/url/' ],
			[ '/%postname%/', '/url', '/url/' ],
			[ '/%postname%', '/url/', '/url' ],
			[ '/%postname%', '/url', '/url' ],
			[ '', '/url', '/url' ],
			[ '', '/url/', '/url' ],
		];
	}

	/**
	 * @dataProvider data_handle_trailing_slash
	 */
	public function test_handle_trailing_slash( string $permalink, string $url, string $expected ): void {
		global $wp_rewrite;

		update_option( 'permalink_structure', $permalink );
		$wp_rewrite->init();

		$actual = Utils::handle_trailing_slash( $url );
		self::assertEquals( $expected, $actual );
	}

	public function wp_redirect_filter( $url ) {
		$this->location = $url;
		return false;
	}

	/**
	 * @psalm-return iterable<array{string, string, string, string}>
	 */
	public function data_redirect_to_login(): iterable {
		return [
			[ '/%postname%/', '/url/', 'a=b', '/url/?a=b' ],
			[ '/%postname%/', '/url', 'a=b', '/url/?a=b' ],
			[ '/%postname%/', '/url/', '', '/url/' ],
			[ '/%postname%/', '/url', '', '/url/' ],
			[ '/%postname%', '/url/', 'a=b', '/url?a=b' ],
			[ '/%postname%', '/url', 'a=b', '/url?a=b' ],
			[ '/%postname%', '/url/', '', '/url' ],
			[ '/%postname%', '/url', '', '/url' ],
		];
	}

	/**
	 * @dataProvider data_redirect_to_login
	 */
	public function test_redirect_to_login( string $perm, string $url, string $qs, string $expected ): void {
		global $wp_rewrite;

		update_option( 'permalink_structure', $perm );
		$wp_rewrite->init();

		self::assertFalse( has_filter( 'wp_redirect', [ $this, 'wp_redirect_filter' ] ) );

		add_filter( 'wp_redirect', [ $this, 'wp_redirect_filter' ] );
		$this->location          = '';
		$_SERVER['QUERY_STRING'] = $qs;
		Utils::redirect_to_login( $url );
		self::assertEquals( $expected, $this->location );
	}

	public function template_redirect_handler() {
		wp_die( 'Success' );
	}

	public function test_template_loader() {
		$this->expectExceptionMessage( 'Success' );
		$this->expectException( WPDieException::class );
		add_action( 'template_redirect', [ $this, 'template_redirect_handler' ], 0 );
		Utils::template_loader();
	}

	/**
	 * @psalm-return iterable<array{string, string, string, bool}>
	 */
	public function data_differ_with_slash(): iterable {
		return [
			[ '', '/a/', '/a/', false ],
			[ '', '/a', '/a/', false ],
			[ '/%postname%/', '/login/', '/login/', false ],
			[ '/%postname%/', '/login/', '/login', true ],
			[ '/%postname%/', '/login', '/login', false ],
		];
	}

	/**
	 * @dataProvider data_differ_with_slash
	 */
	public function test_differ_with_slash( string $ps, string $s1, string $s2, bool $expected ): void {
		global $wp_rewrite;

		update_option( 'permalink_structure', $ps );
		$wp_rewrite->init();

		$actual = Utils::do_permalinks_differ_with_slash( $s1, $s2 );
		self::assertEquals( $expected, $actual );
	}

	/**
	 * @psalm-return iterable<array{string, string, bool}>
	 */
	public function data_is_same_path(): iterable {
		return [
			[ '/wp-login.php', '/wp-login.php', true ],
			[ '/wp%2Dlogin%2Ephp', '/wp-login.php', true ],
			[ '/%D0%BB%D0%BE%D0%B3%D1%96%D0%BD', '/логін', true ],
			[ '/логін', '/%D0%BB%D0%BE%D0%B3%D1%96%D0%BD', true ],
			[ '/логін', '/логін', true ],
			[ '/%D0%BB%D0%BE%D0%B3%D1%96%D0%BD', '/%D0%BB%D0%BE%D0%B3%D1%96%D0%BD', true ],
			[ '/wp%252Dlogin%252Ephp', '/wp-login.php', false ],
			[ '/wp%252Dlogin%252Ephp', '/wp%2Dlogin%2Ephp', true ],
		];
	}

	/**
	 * @dataProvider data_is_same_path
	 */
	public function test_is_same_path( string $s1, string $s2, bool $expected ): void {
		$actual = Utils::is_same_path( $s1, $s2 );
		self::assertEquals( $expected, $actual );
	}

	/**
	 * @psalm-return iterable<array{string, array<string, string>, array<string, string>, array<string, string>, bool}>
	 */
	public function data_is_post_pass_request(): iterable {
		return [
			[ 'GET', [], [], [], false ],
			[ 'GET', [ 'action' => 'postpass' ], [ 'post_password' => '' ], [ 'action' => 'postpass' ], false ],
			[ 'POST', [], [], [], false ],
			[ 'POST', [ 'action' => 'postpass' ], [], [], false ],
			[ 'POST', [ 'action' => 'postpass' ], [], [ 'action' => 'postpass' ], false ],
			[ 'POST', [], [], [ 'action' => 'postpass' ], false ],
			[ 'POST', [ 'action' => 'postpass' ], [ 'post_password' => '' ], [], false ],
			[ 'POST', [], [ 'post_password' => '' ], [ 'action' => 'postpass' ], false ],
			[ 'POST', [ 'action' => 'postpass' ], [ 'post_password' => '' ], [ 'action' => 'postpass' ], true ],
		];
	}

	/**
	 * @dataProvider data_is_post_pass_request
	 */
	public function test_is_post_pass_request( string $rm, array $get, array $post, array $request, bool $expected ): void {
		$_SERVER['REQUEST_METHOD'] = $rm;
		$_GET                      = $get;
		$_POST                     = $post;
		$_REQUEST                  = $request;
		$actual                    = Utils::is_post_pass_request();

		self::assertEquals( $expected, $actual );
	}

	/**
	 * @dataProvider data_get_server_var
	 */
	public function test_get_server_var( string $var, string $expected ): void {
		$actual = Utils::get_server_var( $var );
		self::assertSame( $expected, $actual );
	}

	/**
	 * @psalm-return iterable<array{string, string}>
	 */
	public function data_get_server_var(): iterable {
		return [
			[ 'REQUEST_METHOD', 'GET' ],
			[ 'THIS_VER_DOES_NOT_EXIST', '' ],
		];
	}
}
