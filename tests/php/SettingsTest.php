<?php
/**
 * Hoobert_Settings: the typed getters and the save-time sanitizer.
 *
 * The sanitizer carries the one rule that is easy to break by accident: the key
 * field renders empty, so an empty submission must preserve the stored secret
 * rather than wipe it.
 *
 * @package Hoobert
 */

class SettingsTest extends HoobertTestCase {

	/**
	 * Seed the saved options.
	 */
	private function stored( array $options ): void {
		Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ] = $options;
	}

	public function test_getters_are_empty_when_nothing_is_saved(): void {
		$this->assertSame( '', Hoobert_Settings::endpoint() );
		$this->assertSame( '', Hoobert_Settings::api_key() );
	}

	public function test_endpoint_drops_a_trailing_slash(): void {
		$this->stored( array( 'endpoint' => 'https://fernfly.example/api/p/27/infer/' ) );

		$this->assertSame( 'https://fernfly.example/api/p/27/infer', Hoobert_Settings::endpoint() );
	}

	public function test_getters_survive_a_corrupt_option(): void {
		Hoobert_Test_State::$options[ Hoobert_Settings::OPTION_NAME ] = 'not an array';

		$this->assertSame( '', Hoobert_Settings::endpoint() );
		$this->assertSame( '', Hoobert_Settings::api_key() );
	}

	public function test_empty_key_submission_preserves_the_stored_key(): void {
		$this->stored( array( 'endpoint' => 'https://fernfly.example/infer', 'api_key' => 'stored-key' ) );

		$saved = Hoobert_Settings::sanitize( array( 'endpoint' => 'https://fernfly.example/infer', 'api_key' => '' ) );

		$this->assertSame( 'stored-key', $saved['api_key'] );
	}

	public function test_a_submitted_key_replaces_the_stored_one(): void {
		$this->stored( array( 'api_key' => 'stored-key' ) );

		$saved = Hoobert_Settings::sanitize( array( 'endpoint' => 'https://fernfly.example/infer', 'api_key' => ' new-key ' ) );

		$this->assertSame( 'new-key', $saved['api_key'] );
	}

	public function test_the_clear_checkbox_wipes_the_key(): void {
		$this->stored( array( 'api_key' => 'stored-key' ) );

		$saved = Hoobert_Settings::sanitize(
			array( 'endpoint' => 'https://fernfly.example/infer', 'api_key' => '', 'api_key_clear' => '1' )
		);

		$this->assertSame( '', $saved['api_key'] );
	}

	public function test_clearing_wins_over_a_key_typed_in_the_same_submission(): void {
		$this->stored( array( 'api_key' => 'stored-key' ) );

		$saved = Hoobert_Settings::sanitize(
			array( 'endpoint' => 'https://fernfly.example/infer', 'api_key' => 'typed-key', 'api_key_clear' => '1' )
		);

		$this->assertSame( '', $saved['api_key'] );
	}

	public function test_a_junk_endpoint_is_rejected(): void {
		$saved = Hoobert_Settings::sanitize( array( 'endpoint' => 'javascript:alert(1)' ) );

		$this->assertSame( '', $saved['endpoint'] );
	}

	public function test_sanitize_tolerates_a_missing_field(): void {
		$saved = Hoobert_Settings::sanitize( array() );

		$this->assertSame( array( 'endpoint' => '', 'api_key' => '' ), $saved );
	}

	public function test_the_stylesheet_is_enqueued_on_the_settings_screen(): void {
		Hoobert_Settings::menu();

		Hoobert_Settings::enqueue_assets( 'woocommerce_page_hoobert' );

		$this->assertSame( array( 'hoobert-admin' ), Hoobert_Test_State::$enqueued_styles );
	}

	public function test_the_stylesheet_is_not_enqueued_on_other_screens(): void {
		Hoobert_Settings::menu();

		Hoobert_Settings::enqueue_assets( 'index.php' );

		$this->assertSame( array(), Hoobert_Test_State::$enqueued_styles );
	}

	public function test_nothing_is_enqueued_when_the_menu_page_was_not_added(): void {
		Hoobert_Test_State::$submenu_hook = false;
		Hoobert_Settings::menu();

		Hoobert_Settings::enqueue_assets( '' );

		$this->assertSame( array(), Hoobert_Test_State::$enqueued_styles );
	}
}
