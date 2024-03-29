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
		$basename = \plugin_basename(\dirname(__DIR__) . '/plugin.php');
		if (\is_multisite()) {
			if (!\function_exists('\\is_plugin_active_for_network')) {
				// @codeCoverageIgnoreStart
				// bootstrap.php includes this file
				require_once(\ABSPATH . '/wp-admin/includes/plugin.php');
				// @codeCoverageIgnoreEnd
			}

			self::$sitewide = \is_plugin_active_for_network($basename);
		}

		\add_action('init', [$this, 'init'], 10, 1);
		\add_action('activate_' . $basename,   [$this, 'activate']);
		\add_action('deactivate_' . $basename, [$this, 'deactivate']);
		$this->init_filters();
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function activate($network_wide)
	{
		self::$sitewide = $network_wide;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function deactivate(/** @scrutinizer ignore-unused */ $network_wide)
	{
	}

	public function init()
	{
		\register_setting('permalink', self::OPTION_NAME, ['type' => 'string', 'default' => '']);

		if (\is_multisite()) {
			\add_action('add_site_option_' . Plugin::OPTION_NAME,    [$this, 'init_filters']);
			\add_action('update_site_option_' . Plugin::OPTION_NAME, [$this, 'init_filters']);
			\add_action('delete_site_option_' . Plugin::OPTION_NAME, [$this, 'init_filters']);
		}

		\add_action('add_option_' . Plugin::OPTION_NAME,    [$this, 'init_filters']);
		\add_action('update_option_' . Plugin::OPTION_NAME, [$this, 'init_filters']);
		\add_action('delete_option_' . Plugin::OPTION_NAME, [$this, 'init_filters']);

		if (\is_admin()) {
			// @codeCoverageIgnoreStart
			Admin::instance();
			// @codeCoverageIgnoreEnd
		}
	}

	public function init_filters()
	{
		static $filter_lut = [
			false => '\\add_filter',
			true  => '\\remove_filter',
		];

		static $action_lut = [
			false => '\\add_action',
			true  => '\\remove_action',
		];

		$slug    = $this->get_login_slug();
		$key     = empty($slug);
		$action  = $action_lut[$key];
		$filter  = $filter_lut[$key];
		$naction = $action_lut[!$key];

		$filter('login_url',            [$this, 'site_url'], 100, 1);
		$filter('site_url',             [$this, 'site_url'], 100, 3);
		$filter('network_site_url',     [$this, 'site_url'], 100, 3);
		$filter('wp_redirect',          [$this, 'site_url'], 100, 1);

		$action('wp_loaded',            [$this, 'wp_loaded']);
		$filter('update_welcome_email', [$this, 'update_welcome_email']);

		$naction('template_redirect',   'wp_redirect_admin_locations', 1000);

		\is_admin() && $filter('login_url', [Admin::instance(), 'login_url'], 100, 1);
	}

	/**
	 * @param string $url
	 * @param string $path
	 * @param string $scheme
	 * @return string
	 * @see https://core.trac.wordpress.org/ticket/45506 - due to a WP 5.0 bug, $scheme could be an array
	 */
	public function site_url($url, $path = null, $scheme = null)
	{
		// @codeCoverageIgnoreStart
		if (\is_array($scheme)) {
			$scheme = null;
		}
		// @codeCoverageIgnoreEnd

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

		if (Utils::isPostPassRequest()) {
			return;
		}

		$rel_wpl = \site_url('/', 'relative') . 'wp-login.php';

		// Handle WPMU subdirectory installation (https://www.nginx.com/resources/wiki/start/topics/recipes/wordpress/):
		// rewrite ^(/[^/]+)?(/wp-.*) $2 last;
		if (Utils::isSamePath($path, $rel_wpl) || is_multisite() && !is_subdomain_install() && Utils::endsWith($path, 'wp-login.php')) {
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

		$rpath = str_ireplace('%2f', '/', $_SERVER['REQUEST_URI']);
		$rpath = \preg_replace('!/{2,}!', '/', $rpath);
		$rpath = (string)\wp_parse_url($rpath, \PHP_URL_PATH);
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
