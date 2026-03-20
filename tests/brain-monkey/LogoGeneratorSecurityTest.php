<?php
/**
 * Brain Monkey tests for AlvoBotPro_LogoGenerator_API security logic.
 *
 * Tests SVG sanitization and token verification via hash_equals in verify_token().
 */

use Brain\Monkey\Functions;

require_once __DIR__ . '/../BrainMonkeyTestCase.php';

class LogoGeneratorSecurityTest extends BrainMonkeyTestCase
{
    /** @var AlvoBotPro_LogoGenerator_API */
    private $api;

    protected function setUp(): void
    {
        parent::setUp();

        Functions\expect('add_action')->andReturn(true);

        require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/logo-generator/includes/class-logo-generator-api.php';

        // Use a minimal stub for the logo generator dependency.
        $logoGeneratorStub = $this->createMock(stdClass::class);

        // We cannot use the real LogoGenerator (requires WP DB), so we pass a plain object.
        // verify_token() only interacts with WP option functions, not the generator object.
        $this->api = new AlvoBotPro_LogoGenerator_API($logoGeneratorStub);
    }

    /**
     * verify_token() returns a WP_Error when no token is stored in options.
     */
    public function testVerifyTokenReturnsFalseWhenNoStoredToken(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('alvobot_site_token', '')
            ->andReturn('');

        Functions\expect('sanitize_text_field')
            ->andReturn('some-token');

        $request = $this->buildRequestWithToken('some-token');

        $result = $this->api->verify_token($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /**
     * verify_token() returns a WP_Error when the provided token does not match.
     */
    public function testVerifyTokenReturnsFalseOnTokenMismatch(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('alvobot_site_token', '')
            ->andReturn('correct-secret-token');

        Functions\expect('sanitize_text_field')
            ->andReturn('wrong-token');

        $request = $this->buildRequestWithToken('wrong-token');

        $result = $this->api->verify_token($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /**
     * verify_token() returns true when the provided token matches the stored token.
     */
    public function testVerifyTokenReturnsTrueOnCorrectToken(): void
    {
        $secret = 'super-secret-token-abc123';

        Functions\expect('get_option')
            ->once()
            ->with('alvobot_site_token', '')
            ->andReturn($secret);

        Functions\expect('sanitize_text_field')
            ->andReturn($secret);

        $request = $this->buildRequestWithToken($secret);

        $result = $this->api->verify_token($request);

        $this->assertTrue($result);
    }

    /**
     * verify_token() returns a WP_Error when the token parameter is completely missing.
     */
    public function testVerifyTokenReturnsFalseWhenTokenMissing(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('alvobot_site_token', '')
            ->andReturn('some-stored-token');

        Functions\expect('sanitize_text_field')
            ->andReturn('');

        $request = $this->buildRequestWithToken('');

        $result = $this->api->verify_token($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /**
     * verify_token() WP_Error has code 'unauthorized' on failure.
     */
    public function testVerifyTokenErrorHasCorrectCode(): void
    {
        Functions\expect('get_option')
            ->with('alvobot_site_token', '')
            ->andReturn('stored-token');

        Functions\expect('sanitize_text_field')
            ->andReturn('bad-token');

        $request = $this->buildRequestWithToken('bad-token');

        $result = $this->api->verify_token($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('unauthorized', $result->get_error_code());
    }

    /**
     * handle_generate_logo() returns a WP_Error when blog_name is missing.
     */
    public function testHandleGenerateLogoRequiresBlogName(): void
    {
        $request = $this->buildRequestWithParams([]);

        $result = $this->api->handle_generate_logo($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('missing_parameter', $result->get_error_code());
    }

    /**
     * handle_generate_logo() returns a WP_Error when icon_svg is missing.
     */
    public function testHandleGenerateLogoRequiresIconSvg(): void
    {
        Functions\expect('sanitize_text_field')->andReturn('My Blog');

        $request = $this->buildRequestWithParams(['blog_name' => 'My Blog']);

        $result = $this->api->handle_generate_logo($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('missing_parameter', $result->get_error_code());
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Build a minimal WP_REST_Request mock that returns the given token in its JSON params.
     */
    private function buildRequestWithToken(string $token): object
    {
        return $this->buildRequestWithParams(['token' => $token]);
    }

    /**
     * Build a minimal WP_REST_Request mock with arbitrary JSON params.
     */
    private function buildRequestWithParams(array $params): object
    {
        $request = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_json_params'])
            ->getMock();

        $request->method('get_json_params')->willReturn($params);

        return $request;
    }
}
