<?php

use WildWolf\WordPress\HideWPLogin\InputFactory;

/**
 * @psalm-suppress MissingConstructor
 * @psalm-import-type InputArgs from InputFactory
 * @covers \WildWolf\WordPress\HideWPLogin\InputFactory
 */
class Test_InputFactory extends WP_UnitTestCase /* NOSONAR */ {
	/** @var InputFactory */
	private $input_factory;

	public function setUp(): void {
		$this->input_factory = new InputFactory( [ 'somekey' => 'somevalue' ] );
	}

	public function test_input(): void {
		$output = $this->render( 'input', [
			'label_for' => 'somekey',
			'before'    => 'be<span></span>fore',
			'after'     => '<code>after</code>',
		], $this->input_factory );

		self::assertStringContainsString( 'id="somekey"', $output );
		self::assertStringContainsString( 'name="somekey"', $output );
		self::assertStringContainsString( 'before', $output );
		self::assertStringContainsString( '<code>after</code>', $output );
	}

	private function render( string $method, array $args, InputFactory $factory ): string {
		ob_start();
		$factory->$method( $args );
		$result = ob_get_clean();

		self::assertIsString( $result );
		return $result;
	}
}
