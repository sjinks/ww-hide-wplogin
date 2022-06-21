<?php
namespace WildWolf\WordPress\HideWPLogin;

use WildWolf\Utils\Singleton;
use WP_Rewrite;

final class Plugin {
	use Singleton;

	/**
	 * @codeCoverageIgnore the plugin is initialized before the coverage processing starts
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'init' ], 10, 1 );
		$this->init_filters();
	}

	public function init(): void {
		if ( is_multisite() ) {
			add_action( 'add_site_option_' . Settings::OPTION_KEY, [ $this, 'init_filters' ] );
			add_action( 'update_site_option_' . Settings::OPTION_KEY, [ $this, 'init_filters' ] );
			add_action( 'delete_site_option_' . Settings::OPTION_KEY, [ $this, 'init_filters' ] );
		}

		add_action( 'add_option_' . Settings::OPTION_KEY, [ $this, 'init_filters' ] );
		add_action( 'update_option_' . Settings::OPTION_KEY, [ $this, 'init_filters' ] );
		add_action( 'delete_option_' . Settings::OPTION_KEY, [ $this, 'init_filters' ] );

		if ( is_admin() ) {
			// @codeCoverageIgnoreStart
			Admin::instance();
			// @codeCoverageIgnoreEnd
		}
	}

	public function init_filters(): void {
		/** @psalm-var array<int, callable> */
		static $filter_lut = [
			0 => 'add_filter',
			1 => 'remove_filter',
		];

		/** @psalm-var array<int, callable> */
		static $action_lut = [
			0 => 'add_action',
			1 => 'remove_action',
		];

		Settings::instance()->refresh();

		$slug    = self::get_login_slug();
		$key     = (int) empty( $slug );
		$action  = $action_lut[ $key ];
		$filter  = $filter_lut[ $key ];
		$naction = $action_lut[ ! $key ];

		$filter( 'login_url', [ $this, 'site_url' ], 100, 1 );
		$filter( 'site_url', [ $this, 'site_url' ], 100, 3 );
		$filter( 'network_site_url', [ $this, 'site_url' ], 100, 3 );
		$filter( 'wp_redirect', [ $this, 'site_url' ], 100, 1 );

		$action( 'wp_loaded', [ $this, 'wp_loaded' ] );
		$filter( 'update_welcome_email', [ $this, 'update_welcome_email' ] );

		$naction( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

		is_admin() && $filter( 'login_url', [ Admin::instance(), 'login_url' ], 100, 1 );
	}

	/**
	 * @param string $url
	 * @param string|null $path
	 * @param string|null $scheme
	 * @return string
	 */
	public function site_url( $url, $path = null, $scheme = null ) {
		return $this->rewrite_login_url( $url, $scheme );
	}

	/**
	 * @global WP_Rewrite $wp_rewrite
	 * @param string $path Should be WITHOUT trailing slash
	 */
	public function is_new_login( string $path ) : bool {
		/** @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		$slug = self::get_login_slug();
		if ( $wp_rewrite->using_permalinks() ) {
			return home_url( $slug, 'relative' ) === $path;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is not available here
		return home_url( '', 'relative' ) === $path && isset( $_GET[ $slug ] );
	}

	private function check_old_login( string $path ): void {
		/** @var string $pagenow */
		global $pagenow;

		if ( Utils::is_post_pass_request() ) {
			return;
		}

		$rel_wpl = site_url( '/', 'relative' ) . 'wp-login.php';

		// Handle WPMU subdirectory installation (https://www.nginx.com/resources/wiki/start/topics/recipes/wordpress/):
		// rewrite ^(/[^/]+)?(/wp-.*) $2 last;
		if ( Utils::is_same_path( $path, $rel_wpl ) || is_multisite() && ! is_subdomain_install() && Utils::ends_with( $path, 'wp-login.php' ) ) {
			do_action( 'wwhwl_wplogin_accessed' );

			// @codeCoverageIgnoreStart
			$pagenow                = 'index.php';  // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$_SERVER['REQUEST_URI'] = Utils::handle_trailing_slash( '/wp-login-php/' );
			Utils::template_loader();
			Utils::terminate();
			// @codeCoverageIgnoreEnd
		}
	}

	private function check_new_login( string $path, string $rpath ): void {
		/** @var string $pagenow */
		global $pagenow;

		if ( $this->is_new_login( $path ) ) {
			if ( Utils::do_permalinks_differ_with_slash( $rpath, Utils::handle_trailing_slash( $rpath ) ) ) {
				do_action( 'wwhwl_canonical_login_redirect' );
				// @codeCoverageIgnoreStart
				Utils::redirect_to_login( $this->new_login_url() );
				// @codeCoverageIgnoreEnd
			} else {
				do_action( 'wwhwl_new_login' );

				// @codeCoverageIgnoreStart
				$pagenow = 'wp-login.php';  // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				require_once ABSPATH . 'wp-login.php';
				// @codeCoverageIgnoreEnd
			}

			// @codeCoverageIgnoreStart
			Utils::terminate();
			// @codeCoverageIgnoreEnd
		}
	}

	public function wp_loaded(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- we need to extract the original path
		$request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		$rpath       = str_ireplace( '%2f', '/', $request_uri );
		$rpath       = preg_replace( '!/{2,}!', '/', $rpath );
		$rpath       = (string) wp_parse_url( $rpath, PHP_URL_PATH );
		$path        = untrailingslashit( $rpath );

		$this->check_old_login( $path );
		$this->check_new_login( $path, $rpath );
	}

	/**
	 * @param string $s
	 * @return string
	 */
	public function update_welcome_email( $s ) {
		$slug = self::get_login_slug();
		return str_replace( 'wp-login.php', Utils::handle_trailing_slash( $slug ), $s );
	}

	public static function get_login_slug(): string {
		return Settings::instance()->get_slug();
	}

	/**
	 * @global WP_Rewrite $wp_rewrite
	 */
	public function new_login_url( string $scheme = null ): string {
		/** @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		$root = home_url( '/', $scheme );
		$slug = self::get_login_slug();
		if ( $wp_rewrite->using_permalinks() ) {
			return Utils::handle_trailing_slash( $root . $slug );
		}

		return $root . '?' . $slug;
	}

	private function rewrite_login_url( string $url, string $scheme = null ): string {
		if ( false !== strpos( $url, 'wp-login.php?action=postpass' ) ) {
			return $url;
		}

		if ( false !== strpos( $url, 'wp-login.php' ) ) {
			if ( is_ssl() ) {
				$scheme = 'https';
			}

			$args = explode( '?', $url );

			if ( isset( $args[1] ) ) {
				parse_str( $args[1], $args );
				$url = add_query_arg( $args, $this->new_login_url( $scheme ) );
			} else {
				$url = $this->new_login_url( $scheme );
			}
		}

		return $url;
	}
}
