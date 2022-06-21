<?php

namespace WildWolf\WordPress\HideWPLogin;

use ArrayAccess;
use LogicException;
use WildWolf\Utils\Singleton;

/**
 * @psalm-type SettingsArray = array{
 *  slug: string
 * }
 *
 * @template-implements ArrayAccess<string, scalar>
 */
final class Settings implements ArrayAccess {
	use Singleton;

	/** @var string  */
	const OPTION_KEY = 'wwhwl_slug';

	/**
	 * @psalm-readonly
	 * @psalm-var SettingsArray
	 */
	private static $defaults = [
		'slug' => '',
	];

	private static bool $sitewide = false;

	/**
	 * @var array
	 * @psalm-var SettingsArray
	 */
	private $options;

	private string $basename;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		$this->basename = plugin_basename( dirname( __DIR__ ) . '/plugin.php' );
		if ( is_multisite() ) {
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				// @codeCoverageIgnoreStart
				// bootstrap.php includes this file
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
				// @codeCoverageIgnoreEnd
			}

			self::$sitewide = is_plugin_active_for_network( $this->basename );
		}

		add_action( 'activate_' . $this->basename,  [ $this, 'reinit' ] );
		$this->refresh();
	}

	public function reinit( $netwide ): void {
		self::$sitewide = ! empty( $netwide );
	}

	public function refresh(): void {
		$this->options = [
			'slug' => (string) self::get_string_option( self::OPTION_KEY ),
		];
	}

	private function get_string_option( string $key ): string {
		$value = get_option( $key, '' );

		if ( empty( $value ) && self::$sitewide ) {
			$value = get_site_option( $key );
		}

		return (string) $value;
	}

	/**
	 * @psalm-return SettingsArray
	 */
	public static function defaults(): array {
		return self::$defaults;
	}

	/**
	 * @param mixed $offset
	 */
	public function offsetExists( $offset ): bool {
		return isset( $this->options[ (string) $offset ] );
	}

	/**
	 * @param mixed $offset
	 * @return int|string|bool|null
	 */
	public function offsetGet( $offset ) {
		return $this->options[ (string) $offset ] ?? null;
	}

	/**
	 * @param mixed $_offset
	 * @param mixed $_value
	 * @psalm-return never
	 * @throws LogicException
	 */
	public function offsetSet( $_offset, $_value ): void {
		throw new LogicException();
	}

	/**
	 * @param mixed $_offset
	 * @psalm-return never
	 * @throws LogicException
	 */
	public function offsetUnset( $_offset ): void {
		throw new LogicException();
	}

	/**
	 * @psalm-return SettingsArray
	 */
	public function as_array(): array {
		return $this->options;
	}

	public function get_slug(): string {
		return $this->options['slug'];
	}
}
