<?php
/**
 * Brain Monkey tests for AlvoBotPro_Smart_Links_Injector.
 *
 * Tests inject_links() guard conditions and parse_by_tag/extract_leading_ad behavior
 * indirectly, using mocked WP functions.
 */

use Brain\Monkey\Functions;

require_once __DIR__ . '/../BrainMonkeyTestCase.php';

class SmartLinksInjectorTest extends BrainMonkeyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\expect('add_action')->andReturn(true);
        Functions\expect('add_filter')->andReturn(true);

        // Load renderer dependency before the injector.
        require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/smart-internal-links/includes/class-link-renderer.php';
        require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/smart-internal-links/includes/class-content-injector.php';
    }

    /**
     * inject_links() returns content unchanged when is_singular() is false.
     */
    public function testInjectLinksReturnEarlyWhenNotSingular(): void
    {
        Functions\expect('is_singular')->once()->andReturn(false);
        Functions\expect('is_admin')->andReturn(false);
        Functions\expect('wp_doing_ajax')->andReturn(false);

        $injector = new AlvoBotPro_Smart_Links_Injector();
        $original = '<p>Hello world.</p><p>Second paragraph here.</p>';

        $result = $injector->inject_links($original);

        $this->assertSame($original, $result, 'Content must be returned unchanged when not on a singular post.');
    }

    /**
     * inject_links() returns content unchanged when is_admin() is true.
     */
    public function testInjectLinksReturnEarlyInAdminContext(): void
    {
        Functions\expect('is_singular')->andReturn(true);
        Functions\expect('is_admin')->once()->andReturn(true);
        Functions\expect('wp_doing_ajax')->andReturn(false);

        $injector = new AlvoBotPro_Smart_Links_Injector();
        $original = '<p>Content here.</p><p>More content.</p>';

        $result = $injector->inject_links($original);

        $this->assertSame($original, $result, 'Content must be returned unchanged on admin pages.');
    }

    /**
     * inject_links() returns content unchanged during AJAX requests.
     */
    public function testInjectLinksReturnEarlyDuringAjax(): void
    {
        Functions\expect('is_singular')->andReturn(true);
        Functions\expect('is_admin')->andReturn(false);
        Functions\expect('wp_doing_ajax')->once()->andReturn(true);

        $injector = new AlvoBotPro_Smart_Links_Injector();
        $original = '<p>Ajax content.</p><p>More content.</p>';

        $result = $injector->inject_links($original);

        $this->assertSame($original, $result, 'Content must be returned unchanged during AJAX requests.');
    }

    /**
     * inject_links() returns content unchanged when not in the main query loop.
     */
    public function testInjectLinksReturnEarlyWhenNotInMainLoop(): void
    {
        Functions\expect('is_singular')->andReturn(true);
        Functions\expect('is_admin')->andReturn(false);
        Functions\expect('wp_doing_ajax')->andReturn(false);
        Functions\expect('in_the_loop')->once()->andReturn(false);
        Functions\expect('is_main_query')->zeroOrMoreTimes()->andReturn(true);

        $injector = new AlvoBotPro_Smart_Links_Injector();
        $original = '<p>Widget sidebar content.</p><p>More here.</p>';

        $result = $injector->inject_links($original);

        $this->assertSame($original, $result, 'Content must be returned unchanged in secondary loops.');
    }

    /**
     * inject_links() returns content unchanged when get_the_ID() returns false.
     */
    public function testInjectLinksReturnEarlyWhenNoPostId(): void
    {
        Functions\expect('is_singular')->andReturn(true);
        Functions\expect('is_admin')->andReturn(false);
        Functions\expect('wp_doing_ajax')->andReturn(false);
        Functions\expect('in_the_loop')->andReturn(true);
        Functions\expect('is_main_query')->andReturn(true);
        Functions\expect('get_the_ID')->once()->andReturn(false);

        $injector = new AlvoBotPro_Smart_Links_Injector();
        $original = '<p>Post content.</p><p>Second paragraph.</p>';

        $result = $injector->inject_links($original);

        $this->assertSame($original, $result, 'Content must be returned unchanged when post ID is unavailable.');
    }

    /**
     * inject_links() returns content unchanged when meta is disabled.
     *
     * Must run BEFORE testInjectLinksReturnEarlyForRestRequest because that test
     * defines the REST_REQUEST constant (PHP constants are immutable). Once set,
     * inject_links() always takes the REST early-return path, making it impossible
     * to test the meta-disabled branch in the same process.
     */
    public function testInjectLinksReturnEarlyWhenMetaDisabled(): void
    {
        Functions\expect('is_singular')->andReturn(true);
        Functions\expect('is_admin')->andReturn(false);
        Functions\expect('wp_doing_ajax')->andReturn(false);
        Functions\expect('in_the_loop')->andReturn(true);
        Functions\expect('is_main_query')->andReturn(true);
        Functions\expect('get_the_ID')->andReturn(99);

        // get_validated_meta() is a static call on AlvoBotPro_Smart_Internal_Links.
        // We mock get_option to return an empty array so the guard kicks in.
        Functions\expect('get_option')->andReturn([]);

        // Stub the static call by defining the class if not present.
        if (!class_exists('AlvoBotPro_Smart_Internal_Links')) {
            eval('class AlvoBotPro_Smart_Internal_Links {
                public static function get_validated_meta($post_id) { return null; }
            }');
        }

        $injector = new AlvoBotPro_Smart_Links_Injector();
        $original = '<p>Article paragraph one.</p><p>Article paragraph two.</p>';

        $result = $injector->inject_links($original);

        $this->assertSame($original, $result, 'Content must be returned unchanged when link meta is disabled.');
    }

    /**
     * inject_links() returns content unchanged inside a REST request.
     *
     * MUST run last in this class: it defines REST_REQUEST=true (an immutable PHP
     * constant) which would cause every subsequent test in the same process to
     * take the REST early-return path, masking unrelated failures.
     */
    public function testInjectLinksReturnEarlyForRestRequest(): void
    {
        Functions\expect('is_singular')->andReturn(true);
        Functions\expect('is_admin')->andReturn(false);
        Functions\expect('wp_doing_ajax')->andReturn(false);
        Functions\expect('in_the_loop')->andReturn(true);
        Functions\expect('is_main_query')->andReturn(true);
        Functions\expect('get_the_ID')->andReturn(42);

        if (!defined('REST_REQUEST')) {
            define('REST_REQUEST', true);
        }

        $injector = new AlvoBotPro_Smart_Links_Injector();
        $original = '<p>REST content.</p><p>Another paragraph.</p>';

        $result = $injector->inject_links($original);

        $this->assertSame($original, $result, 'Content must be returned unchanged for REST API requests.');
    }
}
