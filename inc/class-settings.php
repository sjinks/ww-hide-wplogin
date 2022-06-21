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
 * @template-implements ArrayAccess<string, string>
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

	/**
	 * @var array
	 * @psalm-var SettingsArray
	 */
	private $options;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		$this->refresh();

		if ( is_multisite() ) {
			add_action( 'add_site_option_' . self::OPTION_KEY, [ $this, 'refresh' ], 9 );
			add_action( 'update_site_option_' . self::OPTION_KEY, [ $this, 'refresh' ], 9 );
			add_action( 'delete_site_option_' . self::OPTION_KEY, [ $this, 'refresh' ], 9 );
		}

		add_action( 'add_option_' . self::OPTION_KEY, [ $this, 'refresh' ], 9 );
		add_action( 'update_option_' . self::OPTION_KEY, [ $this, 'refresh' ], 9 );
		add_action( 'delete_option_' . self::OPTION_KEY, [ $this, 'refresh' ], 9 );
	}

	public function refresh(): void {
		$this->options = [
			'slug' => self::get_string_option( self::OPTION_KEY ),
		];
	}

	private function get_string_option( string $key ): string {
		/** @var mixed */
		$value = get_option( $key, '' );

		if ( empty( $value ) && is_multisite() ) {
			/** @var mixed */
			$value = get_site_option( $key, '' );
		}

		return is_scalar( $value ) ? (string) $value : '';
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
	 * @return string|null
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
