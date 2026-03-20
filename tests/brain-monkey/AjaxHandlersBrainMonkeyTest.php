<?php
/**
 * Brain Monkey tests for AlvoBotPro_Ajax class.
 *
 * Tests the ACTUAL Ajax handler with expectation-based WP function mocking.
 * Distinct from the legacy AjaxHandlersTest which only tests data structures.
 *
 * Note: In real WP, wp_send_json_error()/wp_send_json_success() calls exit().
 * We simulate this with AjaxTerminatedException so execution halts exactly as in production.
 */

use Brain\Monkey\Functions;

require_once __DIR__ . '/../BrainMonkeyTestCase.php';

/**
 * Thrown by our wp_send_json_error/success stubs to simulate the real exit() call.
 */
class AjaxTerminatedException extends \RuntimeException
{
    private array $payload;
    private bool $success;

    public function __construct(array $payload, bool $success)
    {
        parent::__construct('AJAX terminated');
        $this->payload = $payload;
        $this->success = $success;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}

// Minimal AlvoBotPro stub — the real class requires full WP and DB.
if (!class_exists('AlvoBotPro')) {
    class AlvoBotPro
    {
        public static function get_default_modules(): array
        {
            return [];
        }

        public static function get_module_registry(): array
        {
            return [];
        }

        public static function clear_options_cache(): void {}

        public static function debug_log(string $module, string $message): void {}
    }
}

// Minimal AlvoBotPro_PluginManager stub.
if (!class_exists('AlvoBotPro_PluginManager')) {
    class AlvoBotPro_PluginManager
    {
        public function generate_alvobot_app_password($user): ?string
        {
            return null;
        }

        public function register_site(?string $password): bool
        {
            return false;
        }
    }
}

class AjaxHandlersBrainMonkeyTest extends BrainMonkeyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('AlvoBotPro_Ajax')) {
            Functions\stubs(['add_action' => true]);
            require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-pro-ajax.php';
        }
    }

    /**
     * Registers the wp_send_json_error/success throw-aliases.
     */
    private function registerJsonAliases(): void
    {
        Functions\when('wp_send_json_error')->alias(
            static function ($data) {
                throw new AjaxTerminatedException(
                    is_array($data) ? $data : ['message' => (string) $data],
                    false
                );
            }
        );

        Functions\when('wp_send_json_success')->alias(
            static function ($data = []) {
                throw new AjaxTerminatedException(
                    is_array($data) ? $data : ['message' => (string) $data],
                    true
                );
            }
        );
    }

    /**
     * Wraps the ajax method call and returns the caught AjaxTerminatedException.
     */
    private function callAndCatch(callable $fn): AjaxTerminatedException
    {
        try {
            $fn();
            $this->fail('Expected AjaxTerminatedException was not thrown (wp_send_json_* was never called).');
        } catch (AjaxTerminatedException $e) {
            return $e;
        }
    }

    /**
     * toggle_module() terminates with an error when the user lacks manage_options.
     */
    public function testToggleModuleDeniedWithoutCapability(): void
    {
        $this->registerJsonAliases();

        Functions\when('add_action')->justReturn(true);
        Functions\when('check_ajax_referer')->justReturn(false);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('get_option')->justReturn([]);
        Functions\when('wp_parse_args')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('get_user_by')->justReturn(false);

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        $ajax = new AlvoBotPro_Ajax();

        $e = $this->callAndCatch([$ajax, 'toggle_module']);

        $this->assertFalse($e->isSuccess());
        $this->assertSame('Permissão negada', $e->getPayload()['message']);
    }

    /**
     * toggle_module() terminates with an error when nonce verification fails.
     */
    public function testToggleModuleFailsOnInvalidNonce(): void
    {
        $this->registerJsonAliases();

        Functions\when('add_action')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('get_option')->justReturn([]);
        Functions\when('wp_parse_args')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);

        Functions\expect('current_user_can')
            ->with('manage_options')
            ->andReturn(true);

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('alvobot_pro_nonce', 'nonce', false)
            ->andReturn(false);

        $_POST = [];

        $ajax = new AlvoBotPro_Ajax();

        $e = $this->callAndCatch([$ajax, 'toggle_module']);

        $this->assertSame('Nonce inválido', $e->getPayload()['message']);
    }

    /**
     * toggle_module() terminates with an error when the module name is empty after nonce passes.
     */
    public function testToggleModuleFailsOnEmptyModuleName(): void
    {
        $this->registerJsonAliases();

        Functions\when('add_action')->justReturn(true);
        Functions\when('wp_unslash')->returnArg();
        Functions\when('get_option')->justReturn([]);
        Functions\when('wp_parse_args')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);

        Functions\expect('current_user_can')
            ->with('manage_options')
            ->andReturn(true);

        Functions\expect('check_ajax_referer')
            ->with('alvobot_pro_nonce', 'nonce', false)
            ->andReturn(1);

        // sanitize_text_field returns empty string — simulates empty module name.
        Functions\expect('sanitize_text_field')
            ->zeroOrMoreTimes()
            ->andReturn('');

        $_POST = ['module' => '', 'enabled' => 'true'];

        $ajax = new AlvoBotPro_Ajax();

        $e = $this->callAndCatch([$ajax, 'toggle_module']);

        $this->assertSame('Módulo não especificado', $e->getPayload()['message']);
    }

    /**
     * retry_registration() terminates with an error without manage_options capability.
     */
    public function testRetryRegistrationDeniedWithoutCapability(): void
    {
        $this->registerJsonAliases();

        Functions\when('add_action')->justReturn(true);
        Functions\when('check_ajax_referer')->justReturn(false);
        Functions\when('get_user_by')->justReturn(false);
        Functions\when('get_option')->justReturn(null);
        Functions\when('update_option')->justReturn(true);

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        $ajax = new AlvoBotPro_Ajax();

        $e = $this->callAndCatch([$ajax, 'retry_registration']);

        $this->assertFalse($e->isSuccess());
        $this->assertSame('Permissão negada', $e->getPayload()['message']);
    }

    /**
     * retry_registration() terminates with an error when nonce is invalid.
     */
    public function testRetryRegistrationFailsOnInvalidNonce(): void
    {
        $this->registerJsonAliases();

        Functions\when('add_action')->justReturn(true);
        Functions\when('get_user_by')->justReturn(false);
        Functions\when('get_option')->justReturn(null);
        Functions\when('update_option')->justReturn(true);

        Functions\expect('current_user_can')
            ->with('manage_options')
            ->andReturn(true);

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('alvobot_retry_registration', 'nonce', false)
            ->andReturn(false);

        $ajax = new AlvoBotPro_Ajax();

        $e = $this->callAndCatch([$ajax, 'retry_registration']);

        $this->assertSame('Nonce inválido', $e->getPayload()['message']);
    }

    /**
     * retry_registration() terminates with an error when the alvobot user cannot be found.
     */
    public function testRetryRegistrationFailsWhenUserNotFound(): void
    {
        $this->registerJsonAliases();

        Functions\when('add_action')->justReturn(true);
        Functions\when('get_option')->justReturn(null);
        Functions\when('update_option')->justReturn(true);

        Functions\expect('current_user_can')
            ->with('manage_options')
            ->andReturn(true);

        Functions\expect('check_ajax_referer')
            ->with('alvobot_retry_registration', 'nonce', false)
            ->andReturn(1);

        Functions\expect('get_user_by')
            ->once()
            ->with('login', 'alvobot')
            ->andReturn(false);

        $ajax = new AlvoBotPro_Ajax();

        $e = $this->callAndCatch([$ajax, 'retry_registration']);

        $this->assertFalse($e->isSuccess());
        // The real message is 'Usuário alvobot não encontrado. Execute a inicialização primeiro.'
        $this->assertStringContainsStringIgnoringCase('usuário alvobot não encontrado', $e->getPayload()['message']);
    }
}
