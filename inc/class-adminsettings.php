<?php

namespace WildWolf\WordPress\HideWPLogin;

use WildWolf\Utils\Singleton;
use WP_Rewrite;

final class AdminSettings {
	use Singleton;

	const OPTION_GROUP = 'permalink';

	private InputFactory $input_factory;

	/**
	 * Constructed during `admin_init`
	 *
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		$this->input_factory = new InputFactory( Settings::instance() );
		$this->register_settings();
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			Settings::OPTION_KEY,
			[
				'default'           => '',
				'sanitize_callback' => fn ( string $s ): string => sanitize_title_with_dashes( $s, '', 'save' ),
			]
		);

		$this->add_settings();
	}

	/**
	 * @global WP_Rewrite $wp_rewrite
	 */
	private function add_settings(): void {
		/** @var WP_Rewrite */
		global $wp_rewrite;

		if ( $wp_rewrite->using_permalinks() ) {
			$before = '<code>' . trailingslashit( home_url() ) . '</code>';
			$after  = $wp_rewrite->use_trailing_slashes ? '<code>/</code>' : '';
		} else {
			$before = '<code>' . trailingslashit( home_url() ) . '?</code>';
			$after  = '';
		}

		$section_name = 'wwhwl-section';

		add_settings_section(
			$section_name,
			__( 'Hide wp-login.php', 'wwhwla' ),
			[ __CLASS__, 'print_hwpl_section' ],
			self::OPTION_GROUP
		);

		add_settings_field(
			Settings::OPTION_KEY,
			__( 'Login URL', 'wwhwla' ),
			[ $this->input_factory, 'input' ],
			self::OPTION_GROUP,
			$section_name,
			[
				'label_for' => Settings::OPTION_KEY,
				'name'      => 'slug',
				'before'    => $before,
				'after'     => $after,
			]
		);
	}

	public static function print_hwpl_section(): void {
		echo '<div id="hide-wp-login"></div>';
	}
}
