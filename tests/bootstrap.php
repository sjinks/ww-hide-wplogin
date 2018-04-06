<?php

$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
	$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
	throw new Exception("Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh?");
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin()
{
	if (file_exists(WP_PLUGIN_DIR . '/ww-hide-wplogin') && is_link(WP_PLUGIN_DIR . '/ww-hide-wplogin')) {
		unlink(WP_PLUGIN_DIR . '/ww-hide-wplogin');
	}

	symlink(dirname(dirname(__FILE__)), WP_PLUGIN_DIR . '/ww-hide-wplogin');
	wp_register_plugin_realpath(WP_PLUGIN_DIR . '/ww-hide-wplogin/plugin.php');
	require WP_PLUGIN_DIR . '/ww-hide-wplogin/plugin.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
