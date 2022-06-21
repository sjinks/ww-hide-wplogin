<?php

namespace WildWolf\WordPress\HideWPLogin;

use WildWolf\Utils\Singleton;
use WP;

final class Admin {
	use Singleton;

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		add_action( 'admin_init', [ AdminSettings::class, 'instance' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		load_plugin_textdomain( 'wwhwla', false, plugin_basename( dirname( __DIR__ ) ) . '/lang/' );
	}

	public function admin_init(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WP Core validates the nonce
		if ( ! empty( $_POST ) ) {
			add_action( 'load-options-permalink.php', [ $this, 'load_options_permalink' ] );
		}

		$plugin = plugin_basename( dirname( __DIR__ ) . '/plugin.php' );
		add_filter( 'plugin_action_links_' . $plugin, [ $this, 'plugin_action_links' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		if ( is_plugin_active_for_network( $plugin ) ) {
			add_filter( 'network_admin_plugin_action_links_' . $plugin, [ $this, 'network_admin_plugin_action_links' ] );
			add_action( 'wpmu_options', [ $this, 'wpmu_options' ] );
			add_action( 'update_wpmu_options', [ $this, 'update_wpmu_options' ] );
		}
	}

	/**
	 * @param string $url
	 * @psalm-suppress RedundantCastGivenDocblockType
	 */
	public function login_url( $url ): string {
		$f = Utils::is_called_from( 'auth_redirect' );

		if ( $f ) {
			wp_die( esc_html__( 'You must log in to access the administrative area.', 'wwhwla' ) );
		}

		return (string) $url;
	}

	/**
	 * @global WP $wp
	 * @return string[]
	 */
	private static function get_forbidden_slugs(): array {
		/** @var WP $wp */
		global $wp;
		return array_merge( $wp->public_query_vars, $wp->private_query_vars );
	}

	public function load_options_permalink(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WP Core validates the nonce
		if ( isset( $_POST[ Settings::OPTION_KEY ] ) && is_string( $_POST[ Settings::OPTION_KEY ] ) ) {
			$value = sanitize_title_with_dashes( $_POST[ Settings::OPTION_KEY ] );

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
			// translators: 1 = login URL
			add_settings_error( 'general', 'wwhwl_settings_updated', sprintf( esc_html__( 'Your login URL is now <strong><a href="%1$s" target="_blank">%1$s</a></strong>. Please bookmark it.', 'wwhwla' ), wp_login_url() ), 'updated' );
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

	/**
	 * @param string[] $links
	 * @return string[]
	 */
	public function network_admin_plugin_action_links( array $links ): array {
		$url               = esc_url( network_admin_url( 'settings.php#hide-wp-login' ) );
		$link              = '<a href="' . $url . '">' . esc_html__( 'Settings', 'wwhwla' ) . '</a>';
		$links['settings'] = $link;
		return $links;
	}

	public function wpmu_options(): void {
		$options = [
			'name'  => Settings::OPTION_KEY,
			'value' => Settings::instance()->get_slug(),
		];

		Utils::render( 'wpmu-options', $options );
	}

	public function update_wpmu_options(): void {
		$name = Settings::OPTION_KEY;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WP Core validates the nonce
		if ( isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ) {
			$value = sanitize_title_with_dashes( wp_unslash( $_POST[ $name ] ), '', 'save' );

			update_site_option( $name, $value );
		}
		// phpcs:enable
	}
}
