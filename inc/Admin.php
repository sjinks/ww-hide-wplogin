<?php
namespace WildWolf\HideWPLogin;

final class Admin
{
	public static function instance()
	{
		static $self = null;

		if (!$self) {
			$self = new self();
		}

		return $self;
	}

	private function __construct()
	{
		$this->init();
	}

	public function init()
	{
		\add_action('admin_init', [$this, 'admin_init']);

		\load_plugin_textdomain('wwhwla', /** @scrutinizer ignore-type */ false, \plugin_basename(\dirname(\dirname(__FILE__))) . '/lang/');
	}

	private function register_settings()
	{
		/**
		 * @var \WP_Rewrite
		 */
		global $wp_rewrite;

		if ($wp_rewrite->using_permalinks()) {
			$before = '<code>' . \trailingslashit(\home_url()) . '</code>';
			$after  = $wp_rewrite->use_trailing_slashes ? '<code>/</code>' : '';
		}
		else {
			$before = '<code>' . \trailingslashit(\home_url()) . '?</code>';
			$after  = '';
		}

		\add_settings_section('wwhwl-section', \__('<span id="hide-wp-login">Hide wp-login.php</span>', 'wwhwla'), '__return_null', 'permalink');
		\add_settings_field(Plugin::OPTION_NAME, \__('Login URL', 'wwhwla'), [$this, 'input_field'], 'permalink', 'wwhwl-section', ['label_for' => Plugin::OPTION_NAME, 'before' => $before, 'after' => $after]);
	}

	public function admin_init()
	{
		$this->register_settings();

		if (!empty($_POST)) {
			\add_action('load-options-permalink.php', [$this, 'load_options_permalink']);
		}

		$plugin = \plugin_basename(\dirname(__DIR__) . '/plugin.php');
		\add_filter('plugin_action_links_' . $plugin, [$this, 'plugin_action_links']);
		\add_action('admin_notices',                  [$this, 'admin_notices']);

		if (\is_multisite() && \is_plugin_active_for_network($plugin)) {
			\add_filter('network_admin_plugin_action_links_' . $plugin, [$this, 'plugin_action_links']);
			\add_action('wpmu_options',        [$this, 'wpmu_options']);
			\add_action('update_wpmu_options', [$this, 'update_wpmu_options']);
		}
	}

	public function input_field(array $args)
	{
		$name   = Plugin::OPTION_NAME;
		$value  = \esc_attr((string)\get_option($name));
		$id     = \esc_attr($args['label_for'] ?? '');
		$type   = \esc_attr($args['type'] ?? 'text');
		$before = $args['before'] ?? '';
		$after  = $args['after']  ?? '';

		$id    = $id ? (' id="' . $id . '"') : '';

		echo <<< EOT
{$before}<input type="{$type}" name="{$name}"{$id} value="{$value}"/>{$after}
EOT;
	}

	private static function get_forbidden_slugs() : array
	{
		global $wp;
		return \array_merge($wp->public_query_vars, $wp->private_query_vars);
	}

	public function load_options_permalink()
	{
		\assert(!empty($_POST));

		if (isset($_POST[Plugin::OPTION_NAME])) {
			$value = \sanitize_title_with_dashes($_POST[Plugin::OPTION_NAME]);

			if (!in_array($value, self::get_forbidden_slugs())) {
				\update_option(Plugin::OPTION_NAME, $value);
			}
		}
	}

	public function admin_notices()
	{
		global $pagenow;

		if ('options-permalink.php' === $pagenow && !empty($_GET['settings-updated'])) {
			\add_settings_error('general', 'wwhwl_settings_updated', \sprintf(\__("Your login URL is now <strong><a href=\"%1\$s\" target=\"_blank\">%1\$s</a></strong>. Please bookmark it.", 'wwhwla'), \wp_login_url()), 'updated');
		}
	}

	public function plugin_action_links(array $links) : array
	{
		$url  = \esc_attr(\admin_url('options-permalink.php#hide-wp-login'));
		$link = '<a href="' . $url . '">' . \__('Settings', 'wwhwla') . '</a>';
		$links['settings'] = $link;
		return $links;
	}

	public function wpmu_options()
	{
		$options = [
			'name'  => Plugin::OPTION_NAME,
			'value' => \get_site_option(Plugin::OPTION_NAME, ''),
		];

		require __DIR__ . '/../views/wpmu-options.php';
	}

	public function update_wpmu_options()
	{
		if (isset($_POST[Plugin::OPTION_NAME])) {
			$name  = Plugin::OPTION_NAME;
			$value = $_POST[$name];

			\update_site_option($name, $value);
		}
	}
}
