<?php

namespace WildWolf\WordPress\HideWPLogin;

use WildWolf\Utils\Singleton;

final class Network_Admin {
	use Singleton;

	private function __construct() {
		$this->admin_init();
	}

	public function admin_init(): void {
		$plugin = plugin_basename( dirname( __DIR__ ) . '/plugin.php' );

		add_filter( 'network_admin_plugin_action_links_' . $plugin, [ $this, 'network_admin_plugin_action_links' ] );
		add_action( 'wpmu_options', [ $this, 'wpmu_options' ] );
		add_action( 'update_wpmu_options', [ $this, 'update_wpmu_options' ] );
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
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- we have a sanitize_callback for the option
			$value = wp_unslash( $_POST[ $name ] );
			if ( ! in_array( $value, Admin::get_forbidden_slugs() ) ) {
				update_site_option( $name, $value );
			}
		}
		// phpcs:enable
	}
}
