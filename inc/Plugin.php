<?php
namespace WildWolf\HideWPLogin;


final class Plugin
{
	const OPTION_NAME = 'wwhwl_slug';

	public static function instance()
	{
		static $self = null;

		if (!$self) {
			// @codeCoverageIgnoreStart
			// The plugin is loaded before code coverage processing
			// is initialized, therefore the system thinks
			// that this code never executes
			$self = new self();
			// @codeCoverageIgnoreEnd
		}

		return $self;
	}

	/**
	 * @codeCoverageIgnore the plugin is initialized before the coverage processing starts
	 */
	public function __construct()
	{
		\add_action('init', [$this, 'init'], 10, 1);
	}

	public function init()
	{
		\load_plugin_textdomain('wwhwl', /** @scrutinizer ignore-type */ false, \plugin_basename(\dirname(__DIR__)) . '/lang/');

		\register_setting('permalink', self::OPTION_NAME, ['type' => 'string', 'default' => '']);

		\add_filter('login_url',            [$this, 'login_url'],   100, 1);
		\add_filter('site_url',             [$this, 'site_url'],    100, 3);
		\add_filter('network_site_url',     [$this, 'site_url'],    100, 3);
		\add_filter('wp_redirect',          [$this, 'wp_redirect'], 100, 1);

		\add_action('wp_loaded',            [$this, 'wp_loaded']);
		\add_filter('update_welcome_email', [$this, 'update_welcome_email']);

		\remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);

		if (\is_admin()) {
			// @codeCoverageIgnoreStart
			Admin::instance();
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public function login_url($url)
	{
		if (\is_admin()) {
			$f = Utils::isCalledFrom('auth_redirect');

			if ($f) {
				\wp_die(\__('You must log in to access the administrative area.', 'wwhwl'));
			}
		}

		return $this->rewrite_login_url($url);
	}

	public function site_url($url, $path, $scheme)
	{
		return $this->rewrite_login_url($url, $scheme);
	}

	public function wp_redirect($url)
	{
		return $this->rewrite_login_url($url, null);
	}

	/**
	 * @param string $path Should be WITHOUT trailing slash
	 * @return bool
	 */
	public function is_new_login(string $path) : bool
	{
		/**
		 * @var \WP_Rewrite
		 */
		global $wp_rewrite;

		$slug = $this->get_login_slug();
		if ($wp_rewrite->using_permalinks()) {
			return $path === \home_url($slug, 'relative');
		}

		return $path === \home_url('', 'relative') && isset($_GET[$slug]);
	}

	public function wp_loaded()
	{
		/**
		 * @var string
		 */
		global $pagenow;

		$slug = $this->get_login_slug();
		if (empty($slug)) {
			return;
		}

		$rpath    = (string)\parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
		$path     = \untrailingslashit($rpath);
		$rel_wpl  = \site_url('/', 'relative') . 'wp-login.php';

		if (Utils::isSamePath($path, $rel_wpl)) {
			\do_action('wwhwl_wplogin_accessed');

			// @codeCoverageIgnoreStart
			$pagenow                = 'index.php';
			$_SERVER['REQUEST_URI'] = Utils::handleTrailingSlash('/wp-login-php/');
			Utils::templateLoader();
			Utils::terminate();
			// @codeCoverageIgnoreEnd
		}

		if ($this->is_new_login($path)) {
			if (Utils::doPermalinksDifferWithSlash($rpath, Utils::handleTrailingSlash($rpath))) {
				\do_action('wwhwl_canonical_login_redirect');
				// @codeCoverageIgnoreStart
				Utils::redirectToLogin($this->new_login_url());
				// @codeCoverageIgnoreEnd
			}
			else {
				\do_action('wwhwl_new_login');

				// @codeCoverageIgnoreStart
				$pagenow = 'wp-login.php';
				require_once \ABSPATH . 'wp-login.php';
				// @codeCoverageIgnoreEnd
			}

			// @codeCoverageIgnoreStart
			Utils::terminate();
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * @param string $s
	 * @return string
	 */
	public function update_welcome_email($s)
	{
		$slug = $this->get_login_slug();
		if (empty($slug)) {
			return $s;
		}

		return \str_replace('wp-login.php', Utils::handleTrailingSlash($slug), $s);
	}

	private static function get_login_slug() : string
	{
		return (string)\get_option(self::OPTION_NAME, '');
	}

	public function new_login_url(string $scheme = null)
	{
		/**
		 * @var \WP_Rewrite
		 */
		global $wp_rewrite;

		$root = \home_url('/', $scheme);
		$slug = self::get_login_slug();
		if ($wp_rewrite->using_permalinks()) {
			return Utils::handleTrailingSlash($root . $slug);
		}

		return $root . '?' . $slug;
	}

	private function rewrite_login_url(string $url, string $scheme = null) : string
	{
		$slug = $this->get_login_slug();
		if (empty($slug)) {
			return $url;
		}

		if (false !== \strpos($url, 'wp-login.php')) {
			if (\is_ssl()) {
				$scheme = 'https';
			}

			$args = \explode('?', $url);

			if (isset($args[1])) {
				\parse_str($args[1], $args);
				$url = \add_query_arg($args, $this->new_login_url($scheme));
			}
			else {
				$url = $this->new_login_url($scheme);
			}
		}

		return $url;
	}
}
