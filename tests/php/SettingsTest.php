<?php
/**
 * Hoobert_Settings: the typed getters and the save-time sanitizer.
 *
 * The sanitizer carries the rules that are easy to break by accident: the key
 * field renders empty, so an empty submission must preserve the stored secret
 * rather than wipe it, and the form never posts the install token, so saving it
 * must not drop the token either.
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
		$this->assertSame( '', Hoobert_Settings::install_token() );
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

		$this->assertSame(
			array(
				'connection'    => Hoobert_Settings::CONNECTION_SHARED,
				'endpoint'      => '',
				'api_key'       => '',
				'install_token' => '',
			),
			$saved
		);
	}

	public function test_a_fresh_site_uses_the_shared_model(): void {
		$this->assertTrue( Hoobert_Settings::uses_shared() );
	}

	public function test_a_site_with_its_own_endpoint_and_key_keeps_using_them(): void {
		$this->stored( array( 'endpoint' => 'https://fernfly.example/infer', 'api_key' => 'stored-key' ) );

		$this->assertFalse( Hoobert_Settings::uses_shared() );
	}

	public function test_an_endpoint_without_a_key_falls_back_to_the_shared_model(): void {
		$this->stored( array( 'endpoint' => 'https://fernfly.example/infer', 'api_key' => '' ) );

		$this->assertTrue( Hoobert_Settings::uses_shared() );
	}

	public function test_a_saved_choice_wins_over_the_saved_fields(): void {
		$this->stored( array( 'connection' => 'shared', 'endpoint' => 'https://fernfly.example/infer', 'api_key' => 'stored-key' ) );
		$this->assertTrue( Hoobert_Settings::uses_shared() );

		$this->stored( array( 'connection' => 'own' ) );
		$this->assertFalse( Hoobert_Settings::uses_shared() );
	}

	public function test_an_unknown_saved_choice_is_ignored(): void {
		$this->stored( array( 'connection' => 'bogus', 'endpoint' => 'https://fernfly.example/infer', 'api_key' => 'stored-key' ) );

		$this->assertFalse( Hoobert_Settings::uses_shared() );
	}

	public function test_sanitize_keeps_a_valid_choice_and_replaces_an_unknown_one(): void {
		$this->assertSame( 'own', Hoobert_Settings::sanitize( array( 'connection' => 'own' ) )['connection'] );
		$this->assertSame( 'shared', Hoobert_Settings::sanitize( array( 'connection' => 'bogus' ) )['connection'] );
	}

	public function test_saving_the_form_keeps_the_install_token(): void {
		$this->stored( array( 'install_token' => 'issued-token' ) );

		$saved = Hoobert_Settings::sanitize( array( 'connection' => 'own', 'endpoint' => 'https://fernfly.example/infer', 'api_key' => 'new-key' ) );

		$this->assertSame( 'issued-token', $saved['install_token'] );
	}

	public function test_sanitize_accepts_an_install_token_it_is_handed(): void {
		$this->stored( array( 'install_token' => 'old-token' ) );

		$saved = Hoobert_Settings::sanitize( array( 'install_token' => 'new-token' ) );

		$this->assertSame( 'new-token', $saved['install_token'] );
	}

	public function test_sanitize_leaves_its_own_output_unchanged(): void {
		$this->stored( array() );
		$once = Hoobert_Settings::sanitize(
			array( 'connection' => 'own', 'endpoint' => 'https://fernfly.example/infer', 'api_key' => 'key', 'install_token' => 'token' )
		);

		// update_option() runs the registered sanitizer over values it is handed, twice on a first save.
		$this->stored( $once );
		$this->assertSame( $once, Hoobert_Settings::sanitize( $once ) );
	}

	public function test_saving_an_install_token_keeps_the_other_settings(): void {
		$this->stored( array( 'connection' => 'shared', 'endpoint' => 'https://fernfly.example/infer', 'api_key' => 'stored-key' ) );

		Hoobert_Settings::save_install_token( 'issued-token' );

		$this->assertSame( 'issued-token', Hoobert_Settings::install_token() );
		$this->assertSame( 'https://fernfly.example/infer', Hoobert_Settings::endpoint() );
		$this->assertSame( 'stored-key', Hoobert_Settings::api_key() );
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
