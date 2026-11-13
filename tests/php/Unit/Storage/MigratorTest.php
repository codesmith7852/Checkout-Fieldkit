<?php
/**
 * Tests for schema migrations.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Unit\Storage;

use CheckoutFieldKit\Storage\Migrator;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §3 and ADR 0005: older stored values are migrated forward on read.
 */
class MigratorTest extends TestCase {

	public function test_a_current_value_is_left_alone() {
		$migrator = new Migrator(
			2,
			array(
				1 => static function () {
					throw new \LogicException( 'Must not run.' );
				},
			)
		);

		$this->assertSame(
			array(
				'version' => 2,
				'a'       => 1,
			),
			$migrator->migrate(
				array(
					'version' => 2,
					'a'       => 1,
				)
			)
		);
	}

	public function test_steps_run_in_order_from_the_stored_version() {
		$migrator = new Migrator(
			3,
			array(
				2 => static function ( $value ) {
					$value['log'][] = '2→3';
					return $value;
				},
				1 => static function ( $value ) {
					$value['log'][] = '1→2';
					return $value;
				},
			)
		);

		$this->assertSame(
			array(
				'version' => 3,
				'log'     => array( '1→2', '2→3' ),
			),
			$migrator->migrate( array( 'version' => 1 ) )
		);
		$this->assertSame(
			array(
				'version' => 3,
				'log'     => array( '2→3' ),
			),
			$migrator->migrate( array( 'version' => 2 ) )
		);
	}

	public function test_a_value_without_a_version_counts_as_version_one() {
		$migrator = new Migrator(
			2,
			array(
				1 => static function ( $value ) {
					$value['migrated'] = true;
					return $value;
				},
			)
		);

		$this->assertSame(
			array(
				'migrated' => true,
				'version'  => 2,
			),
			$migrator->migrate( array() )
		);
		$this->assertSame( 1, $migrator->version_of( array( 'version' => 'x' ) ) );
	}

	public function test_versions_without_a_step_are_simply_raised() {
		$this->assertSame( array( 'version' => 4 ), ( new Migrator( 4 ) )->migrate( array( 'version' => 2 ) ) );
	}

	public function test_steps_can_be_added_later() {
		$migrator = ( new Migrator( 2 ) )->add_step(
			1,
			static function ( $value ) {
				$value['added'] = true;
				return $value;
			}
		);

		$this->assertTrue( $migrator->migrate( array( 'version' => 1 ) )['added'] );
	}

	public function test_newer_values_are_recognised_and_left_alone() {
		$migrator = new Migrator( 1 );

		$this->assertTrue( $migrator->is_newer( array( 'version' => 2 ) ) );
		$this->assertFalse( $migrator->is_newer( array( 'version' => 1 ) ) );
		$this->assertSame( array( 'version' => 2 ), $migrator->migrate( array( 'version' => 2 ) ) );
		$this->assertSame( 1, $migrator->current_version() );
	}
}
