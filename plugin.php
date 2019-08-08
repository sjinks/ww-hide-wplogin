<?php
/*
 Plugin Name: WW Hide wp-login.php
 Plugin URI: https://github.com/sjinks/ww-hide-wplogin
 Description: Hides wp-login.php and allows you to use a custom URL for logging in.
 Author: Volodymyr Kolesnykov
 Version: 1.1.4
 License: MIT
 Network:
*/

defined('ABSPATH') || die();

if (defined('VENDOR_PATH')) {
	require VENDOR_PATH . '/vendor/autoload.php';
}
elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require __DIR__ . '/vendor/autoload.php';
}
elseif (file_exists(ABSPATH . 'vendor/autoload.php')) {
	require ABSPATH . 'vendor/autoload.php';
}

WildWolf\HideWPLogin\Plugin::instance();
