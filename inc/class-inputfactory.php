<?php

namespace WildWolf\WordPress\HideWPLogin;

use ArrayAccess;

/**
 * @psalm-type InputArgs = array{label_for: string, type?: string, before?: string, after?: string}
 */
final class InputFactory {
	/** @var ArrayAccess<string,scalar>|array<string,scalar> */
	private $settings;

	/**
	 * @param ArrayAccess<string,scalar>|array<string,scalar> $settings
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @psalm-param InputArgs $args
	 */
	public function input( array $args ): void {
		$id     = $args['label_for'];
		$type   = $args['type'] ?? 'text';
		$value  = $this->settings[ $id ];
		$before = $args['before'] ?? '';
		$after  = $args['after'] ?? '';

		printf(
			'%s<input type="%s" name="%s" id="%s" value="%s"/>%s',
			self::kses( $before ),  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FP, we use wp_kses()
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $id ),
			esc_attr( (string) $value ),
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
