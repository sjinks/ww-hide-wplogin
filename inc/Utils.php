<?php
namespace WildWolf\HideWPLogin;

abstract class Utils
{
	public static function wp_die_handler(callable $s) : callable
	{
		if (\is_string($s) && '_default_wp_die_handler' === $s) {
			return [__CLASS__, 'my_wp_die_handler'];
		}

		// @codeCoverageIgnoreStart
		return $s;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * @codeCoverageIgnore
	 * @param string $message
	 */
	public static function my_wp_die_handler($message)
	{
		die((string)$message);
	}

	public static function isCalledFrom(string $function) : bool
	{
		$bt = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);

		foreach ($bt as $x) {
			if (isset($x['function']) && $function === $x['function']) {
				return true;
			}
		}

		return false;
	}

	public static function templateLoader()
	{
		if (!\defined('WP_USE_THEMES')) {
			\define('WP_USE_THEMES', true);
		}

		\wp();

		require(\ABSPATH . \WPINC . '/template-loader.php');
	// @codeCoverageIgnoreStart
	}
	// @codeCoverageIgnoreEnd

	public static function handleTrailingSlash(string $url) : string
	{
		/**
		 * @var \WP_Rewrite
		 */
		global $wp_rewrite;

		return $wp_rewrite->use_trailing_slashes
			? \trailingslashit($url)
			: \untrailingslashit($url)
		;
	}

	public static function redirectToLogin(string $url)
	{
		$qs  = empty($_SERVER['QUERY_STRING']) ? '' : ('?' . $_SERVER['QUERY_STRING']);
		$url = self::handleTrailingSlash($url) . $qs;

		\wp_safe_redirect($url);
	}

	public static function terminate()
	{
		\add_filter('wp_die_handler', [__CLASS__, 'wp_die_handler'], 0);
		\wp_die();
	// @codeCoverageIgnoreStart
	}
	// @codeCoverageIgnoreEnd

	public static function doPermalinksDifferWithSlash(string $s1, string $s2) : bool
	{
		/**
		 * @var \WP_Rewrite
		 */
		global $wp_rewrite;

		if ($wp_rewrite->using_permalinks()) {
			return $s1 !== $s2 && \untrailingslashit($s1) === \untrailingslashit($s2);
		}

		return false;
	}

	public static function isSamePath(string $s1, string $s2) : bool
	{
		$d1 = \rawurldecode($s1);
		$d2 = \rawurldecode($s2);
		return $s1 === $s2 || $d1 === $s2 || $s1 === $d2 || $d1 === $d2;
	}
}
