<?php

use WildWolf\HideWPLogin\Utils;

function call(callable $c, array $args = null)
{
	return \call_user_func_array($c, $args);
}

class UtilsTest extends WP_UnitTestCase
{
	private $location = '';

	public function testIsCalledFrom()
	{
		$this->assertTrue(\class_exists(Utils::class, true));

		$actual = call(['\\WildWolf\\HideWPLogin\\Utils', 'isCalledFrom'], ['call']);
		$this->assertTrue($actual);

		$actual = call(['\\WildWolf\\HideWPLogin\\Utils', 'isCalledFrom'], ['donotcall']);
		$this->assertFalse($actual);
	}

	/**
	 * @expectedException \WPDieException
	 */
	public function testTerminate()
	{
		Utils::terminate();
	}

	public function handleTrailingSlashDataProvider()
	{
		return [
			['/%postname%/', '/url/', '/url/'],
			['/%postname%/', '/url',  '/url/'],
			['/%postname%',  '/url/', '/url'],
			['/%postname%',  '/url',  '/url'],
			['',             '/url',  '/url'],
			['',             '/url/', '/url'],
		];
	}

	/**
	 * @dataProvider handleTrailingSlashDataProvider
	 * @param string $permalink
	 * @param string $url
	 * @param string $expected
	 */
	public function testHandleTrailingSlash($permalink, $url, $expected)
	{
		global $wp_rewrite;

		update_option('permalink_structure', $permalink);
		$wp_rewrite->init();

		$actual = Utils::handleTrailingSlash($url);
		$this->assertEquals($expected, $actual);
	}

	public function wp_redirect_filter($url)
	{
		$this->location = $url;
		return false;
	}

	public function redirectToLoginDataProvider()
	{
		return [
			['/%postname%/', '/url/', 'a=b', '/url/?a=b'],
			['/%postname%/', '/url',  'a=b', '/url/?a=b'],
			['/%postname%/', '/url/', '',    '/url/'],
			['/%postname%/', '/url',  '',    '/url/'],
			['/%postname%',  '/url/', 'a=b', '/url?a=b'],
			['/%postname%',  '/url',  'a=b', '/url?a=b'],
			['/%postname%',  '/url/', '',    '/url'],
			['/%postname%',  '/url',  '',    '/url'],
		];
	}

	/**
	 * @dataProvider redirectToLoginDataProvider
	 * @param string $perm
	 * @param string $url
	 * @param string $qs
	 * @param string $expected
	 */
	public function testRedirectToLogin(string $perm, string $url, string $qs, string $expected)
	{
		global $wp_rewrite;

		update_option('permalink_structure', $perm);
		$wp_rewrite->init();

		$this->assertFalse(has_filter('wp_redirect', [$this, 'wp_redirect_filter']));

		add_filter('wp_redirect', [$this, 'wp_redirect_filter']);
		$this->location = '';
		$_SERVER['QUERY_STRING'] = $qs;
 		Utils::redirectToLogin($url);
		$this->assertEquals($expected, $this->location);
	}

	public function template_redirect_handler()
	{
		wp_die('Success');
	}

	/**
	 * @expectedException \WPDieException
	 * @expectedExceptionMessage Success
	 */
	public function testTemplateLoader()
	{
		add_action('template_redirect', [$this, 'template_redirect_handler'], 0);
		Utils::templateLoader();
	}

	public function differWithSlashDataProvider()
	{
		return [
			['',             '/a/',     '/a/',     false],
			['',             '/a',      '/a/',     false],
			['/%postname%/', '/login/', '/login/', false],
			['/%postname%/', '/login/', '/login',  true],
			['/%postname%/', '/login',  '/login',  false],
		];
	}

	/**
	 * @dataProvider differWithSlashDataProvider
	 * @param string $ps
	 * @param string $s1
	 * @param string $s2
	 * @param bool $expected
	 */
	public function testDifferWithSlash(string $ps, string $s1, string $s2, bool $expected)
	{
		global $wp_rewrite;

		update_option('permalink_structure', $ps);
		$wp_rewrite->init();

		$actual = Utils::doPermalinksDifferWithSlash($s1, $s2);
		$this->assertEquals($expected, $actual);
	}

	public function isSamePathDataProvider()
	{
		return [
			['/wp-login.php',                   '/wp-login.php',                   true],
			['/wp%2Dlogin%2Ephp',               '/wp-login.php',                   true],
			['/%D0%BB%D0%BE%D0%B3%D1%96%D0%BD', '/логін',                          true],
			['/логін',                          '/%D0%BB%D0%BE%D0%B3%D1%96%D0%BD', true],
			['/логін',                          '/логін',                          true],
			['/%D0%BB%D0%BE%D0%B3%D1%96%D0%BD', '/%D0%BB%D0%BE%D0%B3%D1%96%D0%BD', true],
			['/wp%252Dlogin%252Ephp',           '/wp-login.php',                   false],
			['/wp%252Dlogin%252Ephp',           '/wp%2Dlogin%2Ephp',               true],
		];
	}

	/**
	 * @dataProvider isSamePathDataProvider
	 * @param string $s1
	 * @param string $s2
	 * @param bool $expected
	 */
	public function testIsSamePath(string $s1, string $s2, bool $expected)
	{
		$actual = Utils::isSamePath($s1, $s2);
		$this->assertEquals($expected, $actual);
	}
}
