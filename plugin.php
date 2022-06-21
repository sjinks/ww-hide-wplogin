<?php
/*
	Plugin Name: WW Hide wp-login.php
	Plugin URI: https://github.com/sjinks/ww-hide-wplogin
	Description: Hides wp-login.php and allows you to use a custom URL for logging in.
	Author: Volodymyr Kolesnykov
	Version: 2.0.0
	License: MIT
*/

use WildWolf\WordPress\HideWPLogin\Plugin;

if ( defined( 'ABSPATH' ) ) {
	if ( defined( 'VENDOR_PATH' ) ) {
		/** @psalm-suppress UnresolvableInclude */
		require VENDOR_PATH . '/vendor/autoload.php';
	} elseif ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require __DIR__ . '/vendor/autoload.php';
	} elseif ( file_exists( ABSPATH . 'vendor/autoload.php' ) ) {
		/** @psalm-suppress MissingFile */
		require ABSPATH . 'vendor/autoload.php';
	}

	Plugin::instance();
}
