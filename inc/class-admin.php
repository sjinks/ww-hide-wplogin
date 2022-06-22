<?php

namespace WildWolf\WordPress\HideWPLogin;

use WildWolf\Utils\Singleton;
use WP;

final class Admin {
	use Singleton;

	private string $plugin;

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		$this->plugin = plugin_basename( dirname( __DIR__ ) . '/plugin.php' );
		
		load_plugin_textdomain( 'wwhwla', false, plugin_basename( dirname( __DIR__ ) ) . '/lang/' );

		add_action( 'admin_init', [ AdminSettings::class, 'instance' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	public function admin_init(): void {
		if ( 'POST' === strtoupper( Utils::get_server_var( 'REQUEST_METHOD' ) ) ) {
			add_action( 'load-options-permalink.php', [ $this, 'load_options_permalink' ] );
		}

		add_filter( 'plugin_action_links_' . $this->plugin, [ $this, 'plugin_action_links' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		if ( is_plugin_active_for_network( $this->plugin ) ) {
			Network_Admin::instance();
		}
	}

	/**
	 * @param string $url
	 * @psalm-suppress RedundantCastGivenDocblockType
	 */
	public function login_url( $url ): string {
		if ( Utils::is_called_from( 'auth_redirect' ) ) {
			wp_die( esc_html__( 'You must log in to access the administrative area.', 'wwhwla' ) );
		}

		return (string) $url;
	}

	/**
	 * @global WP $wp
	 * @return string[]
	 */
	public static function get_forbidden_slugs(): array {
		/** @var WP $wp */
		global $wp;
		return array_merge( $wp->public_query_vars, $wp->private_query_vars );
	}

	public function load_options_permalink(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WP Core validates the nonce
		if ( isset( $_POST[ Settings::OPTION_KEY ] ) && is_string( $_POST[ Settings::OPTION_KEY ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- we have a sanitize_callback for the option
			$value = wp_unslash( $_POST[ Settings::OPTION_KEY ] );

			if ( ! in_array( $value, self::get_forbidden_slugs() ) ) {
				update_option( Settings::OPTION_KEY, $value );
			}
		}
		// phpcs::enable
	}

	public function admin_notices(): void {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification -- nonce is unavailable here
		if ( 'options-permalink.php' === $pagenow && ! empty( $_GET['settings-updated'] ) ) {
			$login_url = wp_login_url();
			$link      = sprintf( '<strong><a href="%1$s" target="_blank">%1$s</a></strong>', $login_url );

			add_settings_error(
				'general',
				'wwhwl_settings_updated',
				// translators: 1 = login URL as a HTML link
				sprintf( esc_html__( 'Your login URL is now %1$s. Please bookmark it.', 'wwhwla' ), $link ),
				'updated'
			);
		}
	}

	/**
	 * @param string[] $links
	 * @return string[]
	 */
	public function plugin_action_links( array $links ): array {
		$url               = esc_url( admin_url( 'options-permalink.php#hide-wp-login' ) );
		$link              = '<a href="' . $url . '">' . esc_html__( 'Settings', 'wwhwla' ) . '</a>';
		$links['settings'] = $link;
		return $links;
	}
}
