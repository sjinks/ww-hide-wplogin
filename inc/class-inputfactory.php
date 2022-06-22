<?php

namespace WildWolf\WordPress\HideWPLogin;

use ArrayAccess;

/**
 * @psalm-type InputArgs = array{label_for: string, name: string, type?: string, before?: string, after?: string}
 */
final class InputFactory {
	/** @var ArrayAccess<string,string>|array<string,string> */
	private $settings;

	/**
	 * @param ArrayAccess<string,string>|array<string,string> $settings
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @psalm-param InputArgs $args
	 */
	public function input( array $args ): void {
		$id     = $args['label_for'];
		$name   = $args['name'];
		$type   = $args['type'] ?? 'text';
		$value  = $this->settings[ $name ] ?? '';
		$before = $args['before'] ?? '';
		$after  = $args['after'] ?? '';

		printf(
			'%s<input type="%s" name="%s" id="%s" value="%s"/>%s',
			self::kses( $before ),  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FP, we use wp_kses()
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $value ),
			self::kses( $after )    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FP, we use wp_kses()
		);
	}

	private static function kses( string $s ): string {
		return wp_kses(
			$s,
			[
				'br'     => [],
				'code'   => [],
				'em'     => [],
				'strong' => [],
			]
		);
	}
}
