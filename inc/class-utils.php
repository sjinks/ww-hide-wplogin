<?php
namespace WildWolf\WordPress\HideWPLogin;

use WP_Rewrite;

abstract class Utils {
	public static function wp_die_handler( callable $s ): callable {
		if ( is_string( $s ) && '_default_wp_die_handler' === $s ) {
			return [ __CLASS__, 'my_wp_die_handler' ];
		}

		// @codeCoverageIgnoreStart
		return $s;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * @codeCoverageIgnore
	 * @param string $message
	 * @return never
	 */
	public static function my_wp_die_handler( $message ) {
		die( esc_html( $message ) );
	}

	public static function is_called_from( string $function ): bool {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

		foreach ( $bt as $x ) {
			if ( isset( $x['function'] ) && $function === $x['function'] ) {
				return true;
			}
		}

		return false;
	}

	public static function template_loader(): void {
		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', true );
		}

		wp();

		/** @psalm-suppress UnresolvableInclude */
		require ABSPATH . WPINC . '/template-loader.php';
		// @codeCoverageIgnoreStart
	}
	// @codeCoverageIgnoreEnd

	/**
	 * @global WP_Rewrite $wp_rewrite
	 */
	public static function handle_trailing_slash( string $url ): string {
		/** @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		return $wp_rewrite->use_trailing_slashes
			? trailingslashit( $url )
			: untrailingslashit( $url );
	}

	public static function redirect_to_login( string $url ): void {
		/** @var mixed $qs */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- we need the original query string
		$qs  = $_SERVER['QUERY_STRING'] ?? '';
		$qs  = empty( $qs ) || ! is_string( $qs ) ? '' : ( '?' . $qs );
		$url = self::handle_trailing_slash( $url ) . $qs;

		// wp_safe_redirect() will call wp_sanitize_redirect() on $url

		// phpcs:ignore WordPressVIPMinimum.Security.ExitAfterRedirect.NoExit -- the caller should call exit()
		wp_safe_redirect( $url );
	}

	public static function terminate(): void {
		add_filter( 'wp_die_handler', [ __CLASS__, 'wp_die_handler' ], 0 );
		wp_die();
		// @codeCoverageIgnoreStart
	}
	// @codeCoverageIgnoreEnd

	/**
	 * @global WP_Rewrite $wp_rewrite
	 */
	public static function do_permalinks_differ_with_slash( string $s1, string $s2 ): bool {
		/** @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		if ( $wp_rewrite->using_permalinks() ) {
			return $s1 !== $s2 && untrailingslashit( $s1 ) === untrailingslashit( $s2 );
		}

		return false;
	}

	public static function is_same_path( string $s1, string $s2 ): bool {
		$d1 = rawurldecode( $s1 );
		$d2 = rawurldecode( $s2 );
		return $s1 === $s2 || $d1 === $s2 || $s1 === $d2 || $d1 === $d2;
	}

	public static function ends_with( string $s1, string $s2 ): bool {
		$d1 = rawurldecode( $s1 );
		$d2 = rawurldecode( $s2 );

		return substr( $s1, -strlen( $s2 ) ) === $s2 || substr( $s1, -strlen( $d2 ) ) === $d2 || substr( $d1, -strlen( $s2 ) ) === $s2 || substr( $d1, -strlen( $d2 ) ) === $d2;
	}

	/**
	 * @see wp_magic_quotes()
	 */
	public static function is_post_pass_request(): bool {
		// phpcs:disable WordPress.Security.NonceVerification -- nonce is not available here
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- we need the original values; we use them only in comparisons
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		// In WordPress, `$_REQUEST = array_merge( $_GET, $_POST );`
		$rm = (string) ( $_SERVER['REQUEST_METHOD'] ?? '' );
		$ga = (string) ( $_GET['action'] ?? '' );
		$ra = (string) ( $_REQUEST['action'] ?? '' );
		return ( 'POST' === $rm ) && ( 'postpass' === $ga ) && isset( $_POST['post_password'] ) && ( $ga === $ra );
		// phpcs:enable
	}

	/**
	 * @psalm-param array<string,mixed> $params
	 */
	public static function render( string $view, array $params = [] ): void {
		/** @psalm-suppress UnresolvableInclude */
		require __DIR__ . '/../views/' . $view . '.php';
	}
}
