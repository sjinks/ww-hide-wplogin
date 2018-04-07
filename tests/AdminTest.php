<?php

use WildWolf\HideWPLogin\Admin;
use WildWolf\HideWPLogin\Plugin;

class AdminTest extends WP_UnitTestCase
{
	public function testInstance()
	{
		$i1 = Admin::instance();
		$i2 = Admin::instance();
		$this->assertSame($i1, $i2);
		$this->assertEquals(10, has_action('admin_init', [$i1, 'admin_init']));
	}

	public function testRegisterSettings()
	{
		global $wp_settings_sections;
		global $wp_settings_fields;
		$copy_s = $wp_settings_sections;
		$copy_f = $wp_settings_fields;

		$wp_settings_sections = [];

		try {
			Admin::instance()->admin_init();

			$this->assertArrayHasKey('permalink',         $wp_settings_sections);
			$this->assertArrayHasKey('wwhwl-section',     $wp_settings_sections['permalink']);

			$this->assertArrayHasKey('permalink',         $wp_settings_fields);
			$this->assertArrayHasKey('wwhwl-section',     $wp_settings_fields['permalink']);
			$this->assertArrayHasKey(Plugin::OPTION_NAME, $wp_settings_fields['permalink']['wwhwl-section']);
		}
		finally {
			$wp_settings_sections = $copy_s;
			$wp_settings_fields   = $copy_f;
		}
	}

	public function testRegisterSettings2()
	{
		global $wp_settings_sections;
		global $wp_settings_fields;
		global $wp_rewrite;

		$copy_s = $wp_settings_sections;
		$copy_f = $wp_settings_fields;

		$wp_settings_sections = [];

		try {
			$wp_settings_sections = [];

			update_option('permalink_structure', '');
			$wp_rewrite->init();

			Admin::instance()->admin_init();

			$this->assertArrayHasKey('permalink',         $wp_settings_fields);
			$this->assertArrayHasKey('wwhwl-section',     $wp_settings_fields['permalink']);
			$this->assertArrayHasKey(Plugin::OPTION_NAME, $wp_settings_fields['permalink']['wwhwl-section']);
			$this->assertArrayHasKey('args',              $wp_settings_fields['permalink']['wwhwl-section'][Plugin::OPTION_NAME]);
			$this->assertArrayHasKey('after',             $wp_settings_fields['permalink']['wwhwl-section'][Plugin::OPTION_NAME]['args']);
			$this->assertEmpty($wp_settings_fields['permalink']['wwhwl-section'][Plugin::OPTION_NAME]['args']['after']);

			$wp_settings_sections = [];

			update_option('permalink_structure', '/%post_name%/');
			$wp_rewrite->init();

			Admin::instance()->admin_init();

			$this->assertArrayHasKey('permalink',         $wp_settings_fields);
			$this->assertArrayHasKey('wwhwl-section',     $wp_settings_fields['permalink']);
			$this->assertArrayHasKey(Plugin::OPTION_NAME, $wp_settings_fields['permalink']['wwhwl-section']);
			$this->assertArrayHasKey('args',              $wp_settings_fields['permalink']['wwhwl-section'][Plugin::OPTION_NAME]);
			$this->assertArrayHasKey('after',             $wp_settings_fields['permalink']['wwhwl-section'][Plugin::OPTION_NAME]['args']);
			$this->assertNotEmpty($wp_settings_fields['permalink']['wwhwl-section'][Plugin::OPTION_NAME]['args']['after']);
		}
		finally {
			$wp_settings_sections = $copy_s;
			$wp_settings_fields   = $copy_f;
		}
	}

	public function testAdminInit()
	{
		$inst  = Admin::instance();
		$_POST = [];

		$inst->admin_init();
		$this->assertFalse(has_action('load-options-permalink.php', [$inst, 'load_options_permalink']));
		$this->assertEquals(10, has_action('admin_notices', [$inst, 'admin_notices']));
		$this->assertEquals(10, has_filter('plugin_action_links_ww-hide-wplogin/plugin.php', [$inst, 'plugin_action_links']));

		$_POST = ['something' => 1];
		$inst->admin_init();
		$this->assertEquals(10, has_action('load-options-permalink.php', [$inst, 'load_options_permalink']));
	}

	/**
	 * @expectedException \WPDieException
	 */
	public function testLoginURL_authredirect()
	{
		global $current_screen;

		$this->assertFalse(is_admin());
		$current_screen = new class {
			public function in_admin()
			{
				return true;
			}
		};

		add_filter('wp_redirect', function($url) { throw new \Exception($url); });

		update_option(Plugin::OPTION_NAME, 'LOGIN');
		try {
			auth_redirect();
		}
		finally {
			$current_screen = null;
		}
	}

	public function testAdminNotices()
	{
		global $pagenow;
		$copy = $pagenow;

		try {
			remove_all_actions('admin_notices');
			$inst  = Admin::instance();
			$inst->admin_init();

			delete_transient('settings_errors');
			$e = get_settings_errors();
			$this->assertEmpty($e);

			$pagenow = 'index.php';
			do_action('admin_notices');
			$e = get_settings_errors();
			$this->assertEmpty($e);

			$pagenow = 'options-permalink.php';
			$_GET    = [];
			do_action('admin_notices');
			$e = get_settings_errors();
			$this->assertEmpty($e);

			$_GET = ['settings-updated' => 'true'];
			do_action('admin_notices');
			$e = get_settings_errors();
			$this->assertNotEmpty($e);

			$this->assertArrayHasKey(0, $e);
			$this->assertArrayHasKey('code', $e[0]);
			$this->assertEquals('wwhwl_settings_updated', $e[0]['code']);
		}
		finally {
			delete_transient('settings_errors');
			$pagenow = $copy;
			$_GET    = [];
		}
	}

	public function testPluginActionLinks()
	{
		$inst  = Admin::instance();
		$inst->admin_init();

		$links = apply_filters('plugin_action_links_ww-hide-wplogin/plugin.php', []);
		$this->assertNotEmpty($links);
		$this->assertTrue(is_array($links));
		$this->assertArrayHasKey('settings', $links);
	}

	public function testNetworkAdminPluginActionLinks()
	{
		if (is_multisite()) {
			$this->assertTrue(is_plugin_active_for_network('ww-hide-wplogin/plugin.php'));

			$inst  = Admin::instance();
			$inst->admin_init();

			$links = apply_filters('network_admin_plugin_action_links_ww-hide-wplogin/plugin.php', []);
			$this->assertNotEmpty($links);
			$this->assertTrue(is_array($links));
			$this->assertArrayHasKey('settings', $links);
		}
		else {
			$this->markTestSkipped("This test makes sense only with WPMU");
		}
	}

	public function testGetForbiddenSlugs()
	{
		$_POST = [Plugin::OPTION_NAME => 'p'];

		try {
			delete_option(Plugin::OPTION_NAME);
			$inst = Admin::instance();
			$inst->load_options_permalink();
			$actual = get_option(Plugin::OPTION_NAME);
			$this->assertEmpty($actual);
		}
		finally {
			$_POST = [];
		}
	}

	public function testLoadOptionsPermalink()
	{
		$_POST = [Plugin::OPTION_NAME => 'login'];

		try {
			delete_option(Plugin::OPTION_NAME);
			$inst = Admin::instance();
			$inst->load_options_permalink();
			$actual = get_option(Plugin::OPTION_NAME);
			$this->assertEquals($_POST[Plugin::OPTION_NAME], $actual);
		}
		finally {
			$_POST = [];
		}
	}

	public function testInputField()
	{
		delete_option(Plugin::OPTION_NAME);

		$inst = Admin::instance();
		ob_start();
		$inst->input_field([]);

		$actual   = trim(ob_get_clean());
		$expected = '<input type="text" name="wwhwl_slug" value=""/>';
		$this->assertEquals($expected, $actual);
	}

	public function testWPMUOptions()
	{
		if (is_multisite()) {
			$this->assertTrue(is_plugin_active_for_network('ww-hide-wplogin/plugin.php'));

			$inst  = Admin::instance();
			$inst->admin_init();

			update_site_option(Plugin::OPTION_NAME, 'xxx');

			ob_start();
			do_action('wpmu_options');
			$s = ob_get_clean();

			$this->assertContains('value="xxx"', $s);
		}
		else {
			$this->markTestSkipped("This test makes sense only with WPMU");
		}
	}

	public function testUpdateWPMUOptions()
	{
		if (is_multisite()) {
			$this->assertTrue(is_plugin_active_for_network('ww-hide-wplogin/plugin.php'));

			$inst  = Admin::instance();
			$inst->admin_init();

			$_POST[Plugin::OPTION_NAME] = 'yyy';
			do_action('update_wpmu_options');
			$actual = get_site_option(Plugin::OPTION_NAME);

			$this->assertEquals($_POST[Plugin::OPTION_NAME], $actual);
		}
		else {
			$this->markTestSkipped("This test makes sense only with WPMU");
		}
	}
}
