<?php
/**
 * Brain Monkey tests for shared country/language helpers.
 */

require_once __DIR__ . '/../BrainMonkeyTestCase.php';
require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/shared/countries-languages.php';

class CountriesLanguagesTest extends BrainMonkeyTestCase
{
	public function testLanguageAliasesRemainSupported(): void
	{
		$this->assertSame( 'bn', alvobot_get_language_iso_code( 'bengali' ) );
		$this->assertSame( 'gn', alvobot_get_language_iso_code( 'guarani' ) );
		$this->assertSame( 'aa', alvobot_get_language_iso_code( 'afar' ) );
		$this->assertSame( 'oc', alvobot_get_language_iso_code( 'occitan' ) );
	}
}
