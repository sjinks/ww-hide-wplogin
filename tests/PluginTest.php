<?php
use WildWolf\HideWPLogin\Plugin;

class PluginTest extends WP_UnitTestCase
{
	public function testInstance()
	{
		$i1 = Plugin::instance();
		$i2 = Plugin::instance();
		$this->assertSame($i1, $i2);
	}

	public function testSiteURL()
	{
		$url = site_url('wp-login.php');
		$this->assertContains('wp-login.php', $url);

		update_option(Plugin::OPTION_NAME, 'LOGIN');
		$url = site_url('wp-login.php');
		$this->assertNotContains('wp-login.php', $url);
		$this->assertContains('LOGIN', $url);
	}

	public function testLoginURL()
	{
		$url = wp_login_url();
		$this->assertContains('wp-login.php', $url);

		update_option(Plugin::OPTION_NAME, 'LOGIN');
		$url = wp_login_url();
		$this->assertNotContains('wp-login.php', $url);
		$this->assertContains('LOGIN', $url);
	}

	public function testLoginURL_admin()
	{
		global $current_screen;

		$this->assertFalse(is_admin());
		$current_screen = new class {
			public function in_admin()
			{
				return true;
			}
		};

		try {
			$url = wp_login_url();
			$this->assertContains('wp-login.php', $url);

			update_option(Plugin::OPTION_NAME, 'LOGIN');
			$url = wp_login_url();
			$this->assertNotContains('wp-login.php', $url);
			$this->assertContains('LOGIN', $url);
		}
		finally {
			$current_screen = null;
		}
	}

	public function testWelcomeEmail()
	{
		reset_phpmailer_instance();

		if (is_multisite()) {
			delete_option(Plugin::OPTION_NAME);
			$this->assertTrue(wpmu_welcome_notification(1, 1, 'password', 'title'));

			$actual = tests_retrieve_phpmailer_instance()->get_sent()->body;
			$this->assertContains('wp-login.php', $actual);

			reset_phpmailer_instance();
			update_option(Plugin::OPTION_NAME, 'LOGIN');
			$this->assertTrue(wpmu_welcome_notification(1, 1, 'pAssword', 'title'));

			$actual = tests_retrieve_phpmailer_instance()->get_sent()->body;
			$this->assertNotContains('wp-login.php', $actual);
		}
		else {
			$this->markTestSkipped("This test makes sense only with WPMU");
		}
	}

	public function testConstruct()
	{
		$inst = Plugin::instance();
		$this->assertEquals(10, has_action('init', [$inst, 'init']));
	}

	public function testInit()
	{
		global $wp_registered_settings;
		$copy = $wp_registered_settings;

		$wp_registered_settings = [];

		remove_all_actions('wp_loaded', 10);
		remove_all_filters('site_option_welcome_email', 10);

		try {
			$this->assertSame([], get_registered_settings());

			$inst = Plugin::instance();
			$inst->init();

			$settings = get_registered_settings();
			$this->assertArrayHasKey(Plugin::OPTION_NAME, $settings);
		}
		finally {
			$wp_registered_settings = $copy;
		}

		$this->assertFalse(has_action('wp_loaded',            [$inst, 'wp_loaded']));
		$this->assertFalse(has_filter('update_welcome_email', [$inst, 'update_welcome_email']));

		$this->assertFalse(has_filter('login_url',            [$inst, 'site_url']));
		$this->assertFalse(has_filter('site_url',             [$inst, 'site_url']));
		$this->assertFalse(has_filter('network_site_url',     [$inst, 'site_url']));
		$this->assertFalse(has_filter('wp_redirect',          [$inst, 'site_url']));

		update_option(Plugin::OPTION_NAME, 'xxx');

		$this->assertEquals(10,  has_action('wp_loaded',            [$inst, 'wp_loaded']));
		$this->assertEquals(10,  has_filter('update_welcome_email', [$inst, 'update_welcome_email']));

		$this->assertEquals(100, has_filter('login_url',            [$inst, 'site_url']));
		$this->assertEquals(100, has_filter('site_url',             [$inst, 'site_url']));
		$this->assertEquals(100, has_filter('network_site_url',     [$inst, 'site_url']));
		$this->assertEquals(100, has_filter('wp_redirect',          [$inst, 'site_url']));
	}

	public function isNewLoginDataProvider()
	{
		return [
			['/%post_name%/', 'LOGIN', '/LOGIN',  [], true],
			['/%post_name%/', 'LOGIN', '/LOGIN/', [], false], // is_new_login() expects path without trailing slash
			['/%post_name%/', 'LOGIN', '/login',  [], false],

			['', 'LOGIN', '',        ['LOGIN' => ''], true],
			['', 'LOGIN', '',        ['login' => ''], false],
			['', 'LOGIN', '/',       ['LOGIN' => ''], false],
			['', 'LOGIN', '/',       ['login' => ''], false],
			['', 'LOGIN', '/post',   ['LOGIN' => ''], false],
		];
	}

	/**
	 * @dataProvider isNewLoginDataProvider
	 * @param string $ps
	 * @param string $slug
	 * @param string $path
	 * @param array $get
	 * @param bool $expected
	 */
	public function testIsNewLogin(string $ps, string $slug, string $path, array $get, bool $expected)
	{
		global $wp_rewrite;

		$_GET = $get;
		update_option(Plugin::OPTION_NAME, $slug);
		update_option('permalink_structure', $ps);
		$wp_rewrite->init();

		$inst   = Plugin::instance();
		$actual = $inst->is_new_login($path);
		$this->assertEquals($expected, $actual);
	}

	public function wpLoadedDataProvider()
	{
		return [
			// If slug is not set, Plugin::wp_loaded() exits early
			['',              '',      '/',       'OK'],
			// If permalink structure and request path do not agree on trailing slash, redirect
			['/%post_name%/', 'LOGIN', '/LOGIN',  'wwhwl_canonical_login_redirect'],
			['/%post_name%',  'LOGIN', '/LOGIN/', 'wwhwl_canonical_login_redirect'],
			// If permalink structure and request path do agree on trailing slash, login
			['/%post_name%/', 'LOGIN', '/LOGIN/', 'wwhwl_new_login'],
			['/%post_name%',  'LOGIN', '/LOGIN',  'wwhwl_new_login'],
			// If the paths does not match the slug, exit
			['/%post_name%/', 'LOGIN', '/post/',  'OK'],
			// Detect wp-login.php
			['/%post_name%/', 'LOGIN', '/wp-login.php',        'wwhwl_wplogin_accessed'],
			['/%post_name%/', 'LOGIN', '/wp%2Dlogin%2Ephp',    'wwhwl_wplogin_accessed'],
			['/%post_name%/', 'LOGIN', '//wp-login.php',       'wwhwl_wplogin_accessed'],
			['/%post_name%/', 'LOGIN', '//wp%2Dlogin%2Ephp',   'wwhwl_wplogin_accessed'],
			['/%post_name%/', 'LOGIN', '/%2Fwp-login.php',     'wwhwl_wplogin_accessed'],
			['/%post_name%/', 'LOGIN', '/%2Fwp%2Dlogin%2Ephp', 'wwhwl_wplogin_accessed'],
		];
	}

	/**
	 * @dataProvider wpLoadedDataProvider
	 * @param string $ps
	 * @param string $slug
	 * @param string $path
	 * @param array $get
	 * @param string $message
	 */
	public function testWPLoaded(string $ps, string $slug, string $path, string $message)
	{
		global $wp_rewrite;

		$_GET = [];
		$_SERVER['REQUEST_URI'] = $path;
		update_option(Plugin::OPTION_NAME, $slug);
		update_option('permalink_structure', $ps);
		$wp_rewrite->init();

		$handler = function() {
			throw new \Exception(current_action());
		};

		add_action('wwhwl_wplogin_accessed',         $handler);
		add_action('wwhwl_canonical_login_redirect', $handler);
		add_action('wwhwl_new_login',                $handler);

		$this->setExpectedException(\Exception::class, $message);

		$inst = Plugin::instance();
		$inst->wp_loaded();
		throw new \Exception('OK');
	}

	public function newLoginUrlDataProvider()
	{
		return [
			['',             'LOGIN', 'http://example.org/?LOGIN'],
			['/%post_name%', 'LOGIN', 'http://example.org/LOGIN'],
		];
	}

	/**
	 * @dataProvider newLoginUrlDataProvider
	 * @param string $ps
	 * @param string $slug
	 * @param string $expected
	 */
	public function testNewLoginUrl(string $ps, string $slug, string $expected)
	{
		global $wp_rewrite;

		update_option(Plugin::OPTION_NAME, $slug);
		update_option('permalink_structure', $ps);
		$wp_rewrite->init();

		$inst   = Plugin::instance();
		$actual = $inst->new_login_url();
		$this->assertEquals($expected, $actual);
	}

	public function rewriteLoginURLDataProvider()
	{
		return [
			['/',                 false, 'http://' . WP_TESTS_DOMAIN . '/'],
			['/',                 true,  'https://' . WP_TESTS_DOMAIN . '/'],
			['/wp-login.php',     true,  'https://' . WP_TESTS_DOMAIN . '/?LOGIN'],
			['/wp-login.php?arg', false, 'http://' . WP_TESTS_DOMAIN . '/?LOGIN&arg'],
			['/wp-login.php?action=postpass', false, 'http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=postpass'],
		];
	}

	/**
	 * @dataProvider rewriteLoginURLDataProvider
	 * @param string $url
	 * @param bool $ssl
	 * @param string $expected
	 */
	public function testRewriteLoginURL(string $url, bool $ssl, string $expected)
	{
		global $wp_rewrite;

		update_option(Plugin::OPTION_NAME, 'LOGIN');
		update_option('permalink_structure', '');
		$wp_rewrite->init();

		if ($ssl) {
			$_SERVER['HTTPS'] = 'on';
		}
		else {
			unset($_SERVER['HTTPS']);
		}

		try {
			$actual = site_url($url);
			$this->assertEquals($expected, $actual);
		}
		finally {
			tests_reset__SERVER();
		}
	}

	public function testInitFilters()
	{
		$inst = Plugin::instance();

		$this->assertFalse(has_action('wp_loaded',            [$inst, 'wp_loaded']));
		$this->assertFalse(has_filter('update_welcome_email', [$inst, 'update_welcome_email']));
		$this->assertFalse(has_filter('login_url',            [$inst, 'site_url']));
		$this->assertFalse(has_filter('site_url',             [$inst, 'site_url']));
		$this->assertFalse(has_filter('network_site_url',     [$inst, 'site_url']));
		$this->assertFalse(has_filter('wp_redirect',          [$inst, 'site_url']));

		update_option(Plugin::OPTION_NAME, 'xxx');

		$this->assertEquals(10,  has_action('wp_loaded',            [$inst, 'wp_loaded']));
		$this->assertEquals(10,  has_filter('update_welcome_email', [$inst, 'update_welcome_email']));
		$this->assertEquals(100, has_filter('login_url',            [$inst, 'site_url']));
		$this->assertEquals(100, has_filter('site_url',             [$inst, 'site_url']));
		$this->assertEquals(100, has_filter('network_site_url',     [$inst, 'site_url']));
		$this->assertEquals(100, has_filter('wp_redirect',          [$inst, 'site_url']));

		update_option(Plugin::OPTION_NAME, '');

		$this->assertFalse(has_action('wp_loaded',            [$inst, 'wp_loaded']));
		$this->assertFalse(has_filter('update_welcome_email', [$inst, 'update_welcome_email']));
		$this->assertFalse(has_filter('login_url',            [$inst, 'site_url']));
		$this->assertFalse(has_filter('site_url',             [$inst, 'site_url']));
		$this->assertFalse(has_filter('network_site_url',     [$inst, 'site_url']));
		$this->assertFalse(has_filter('wp_redirect',          [$inst, 'site_url']));
	}

	public function testGetLoginSlugWPMU()
	{
		$name = Plugin::OPTION_NAME;

		$value = get_site_option($name);
		$this->assertEmpty($value);

		$value = get_option($name);
		$this->assertEmpty($value);

		update_site_option($name, 'xxx');

		if (is_multisite()) {
			$value = get_option($name);
			$this->assertEmpty($value);
		}

		$value = Plugin::get_login_slug();
		$this->assertEquals('xxx', $value);

		update_site_option($name, 'yyy');
		$value = Plugin::get_login_slug();
		$this->assertEquals('yyy', $value);
	}
}
