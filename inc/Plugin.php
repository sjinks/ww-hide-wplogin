<?php
namespace WildWolf\HideWPLogin;

final class Plugin
{
	const OPTION_NAME = 'wwhwl_slug';

	/**
	 * @var bool
	 */
	private static $sitewide = false;

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
		\register_setting('permalink', self::OPTION_NAME, ['type' => 'string', 'default' => '']);

		if (\is_multisite()) {
			if (!\function_exists('\\is_plugin_active_for_network')) {
				// @codeCoverageIgnoreStart
				// bootstrap.php includes this file
				require_once(\ABSPATH . '/wp-admin/includes/plugin.php');
				// @codeCoverageIgnoreEnd
			}

			$basename = \plugin_basename(\dirname(__DIR__) . '/plugin.php');
			self::$sitewide = \is_plugin_active_for_network($basename);

			\add_action('add_site_option_' . Plugin::OPTION_NAME,    [$this, 'init_filters']);
			\add_action('update_site_option_' . Plugin::OPTION_NAME, [$this, 'init_filters']);
		}

		\add_action('add_option_' . Plugin::OPTION_NAME,    [$this, 'init_filters']);
		\add_action('update_option_' . Plugin::OPTION_NAME, [$this, 'init_filters']);

		$this->init_filters();

		if (\is_admin()) {
			// @codeCoverageIgnoreStart
			Admin::instance();
			// @codeCoverageIgnoreEnd
		}
	}

	public function init_filters()
	{
		$slug = $this->get_login_slug();
		if (!empty($slug)) {
			\add_filter('login_url',            [$this, 'site_url'], 100, 1);
			\add_filter('site_url',             [$this, 'site_url'], 100, 3);
			\add_filter('network_site_url',     [$this, 'site_url'], 100, 3);
			\add_filter('wp_redirect',          [$this, 'site_url'], 100, 1);

			\add_action('wp_loaded',            [$this, 'wp_loaded']);
			\add_filter('update_welcome_email', [$this, 'update_welcome_email']);

			\remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);

			\is_admin() && \add_filter('login_url', [Admin::instance(), 'login_url'], 100, 1);
		}
		else {
			\remove_filter('login_url',            [$this, 'site_url'], 100);
			\remove_filter('site_url',             [$this, 'site_url'],  100);
			\remove_filter('network_site_url',     [$this, 'site_url'],  100);
			\remove_filter('wp_redirect',          [$this, 'site_url'], 100);

			\remove_action('wp_loaded',            [$this, 'wp_loaded']);
			\remove_filter('update_welcome_email', [$this, 'update_welcome_email']);

			\add_action('template_redirect', 'wp_redirect_admin_locations', 1000);

			\is_admin() && \remove_filter('login_url', [Admin::instance(), 'login_url'], 100);
		}
	}

	/**
	 * @param string $url
	 * @param string $path
	 * @param string $scheme
	 * @return string
	 */
	public function site_url($url, $path = null, $scheme = null)
	{
		return $this->rewrite_login_url($url, $scheme);
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

	private function checkOldLogin(string $path)
	{
		/**
		 * @var string
		 */
		global $pagenow;

		$rel_wpl = \site_url('/', 'relative') . 'wp-login.php';

		if (Utils::isSamePath($path, $rel_wpl) && !Utils::isPostPassRequest()) {
			\do_action('wwhwl_wplogin_accessed');

			// @codeCoverageIgnoreStart
			$pagenow                = 'index.php';
			$_SERVER['REQUEST_URI'] = Utils::handleTrailingSlash('/wp-login-php/');
			Utils::templateLoader();
			Utils::terminate();
			// @codeCoverageIgnoreEnd
		}
	}

	private function checkNewLogin(string $path, string $rpath)
	{
		/**
		 * @var string
		 */
		global $pagenow;

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

	public function wp_loaded()
	{
		/**
		 * @var string
		 */
		global $pagenow;

		$rpath = (string)\parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
		$path  = \untrailingslashit($rpath);

		$this->checkOldLogin($path);
		$this->checkNewLogin($path, $rpath);
	}

	/**
	 * @param string $s
	 * @return string
	 */
	public function update_welcome_email($s)
	{
		$slug = $this->get_login_slug();
		return \str_replace('wp-login.php', Utils::handleTrailingSlash($slug), $s);
	}

	public static function get_login_slug() : string
	{
		$slug = \get_option(self::OPTION_NAME, '');

		if (empty($slug) && self::$sitewide) {
			$slug = \get_site_option(self::OPTION_NAME);
		}

		return (string)$slug;
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
		if (false !== \strpos($url, 'wp-login.php?action=postpass')) {
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
