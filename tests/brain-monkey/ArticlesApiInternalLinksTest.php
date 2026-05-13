<?php
/**
 * Brain Monkey tests for AlvoBotPro_ArticlesApi (internal-links endpoint).
 *
 * Covers the spec's required test cases:
 *   1. Empty content → internal_links: [], status 200.
 *   2. Relative internal link → absolutized + normalized.
 *   3. wp-block-button class → is_button: true.
 *   4. Tracking params (utm_*, fbclid, gclid, ref) stripped.
 *   5. Image-only link → anchor_text "[image: alt]".
 *   6. Many external links same host → one summary row with correct count.
 *   7. post_id inexistente → 404 post_not_found.
 *   8. Post rascunho → 422 post_not_published.
 *   9. refresh=1 ignores transient.
 *  10. Subdomínio diferente → externo.
 *  + Auth via token header, X-Alvobot-Token header, and ?token query param.
 */

use Brain\Monkey\Functions;

require_once __DIR__ . '/../BrainMonkeyTestCase.php';

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

// ---------------------------------------------------------------------------
// Stubs for WP REST primitives + plugin static logger.
// Defined inline since the real classes are unavailable without a full WP load.
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE = 'GET';
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $status;
		public $headers = array();

		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = (int) $status;
		}

		public function header( $key, $value ) {
			$this->headers[ $key ] = $value;
		}

		public function get_data() {
			return $this->data;
		}

		public function get_status() {
			return $this->status;
		}
	}
}

if ( ! class_exists( 'ArticlesApiTestRequest' ) ) {
	class ArticlesApiTestRequest {
		private $params  = array();
		private $headers = array();

		public function __construct( array $params = array(), array $headers = array() ) {
			$this->params = $params;
			foreach ( $headers as $k => $v ) {
				$this->headers[ strtolower( str_replace( '-', '_', $k ) ) ] = $v;
			}
		}

		public function get_param( $name ) {
			return isset( $this->params[ $name ] ) ? $this->params[ $name ] : null;
		}

		public function get_header( $name ) {
			$key = strtolower( str_replace( '-', '_', $name ) );
			return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : null;
		}
	}
}

if ( ! class_exists( 'AlvoBotPro' ) ) {
	class AlvoBotPro {
		public static function debug_log( $module, $message ) {
			// no-op in tests
		}
	}
}

class ArticlesApiInternalLinksTest extends BrainMonkeyTestCase
{
	/** @var array<string, array{value:mixed, expiration:int}> */
	private $transients = array();

	protected function setUp(): void
	{
		parent::setUp();

		$this->transients = array();
		$transients_ref   = &$this->transients;

		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( '__' )->returnArg();

		Functions\when( 'home_url' )->alias(
			static function ( $path = '' ) {
				return 'https://example.com' . $path;
			}
		);

		Functions\when( 'get_permalink' )->alias(
			static function ( $post = null ) {
				$id = is_object( $post ) ? (int) $post->ID : (int) $post;
				return 'https://example.com/?p=' . $id;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		Functions\when( 'url_to_postid' )->alias(
			static function ( $url ) {
				// Map a couple of known internal URLs to fake post IDs; everything else → 0.
				if ( false !== strpos( (string) $url, '/melhores-bancos-digitais' ) ) {
					return 456;
				}
				if ( false !== strpos( (string) $url, '/cartao-de-credito-sem-anuidade' ) ) {
					return 789;
				}
				return 0;
			}
		);

		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) {
				if ( 'alvobot_site_token' === $name ) {
					return 'secret-token-123';
				}
				return $default;
			}
		);

		Functions\when( 'get_transient' )->alias(
			static function ( $key ) use ( &$transients_ref ) {
				return isset( $transients_ref[ $key ] ) ? $transients_ref[ $key ]['value'] : false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			static function ( $key, $value, $expiration = 0 ) use ( &$transients_ref ) {
				$transients_ref[ $key ] = array(
					'value'      => $value,
					'expiration' => (int) $expiration,
				);
				return true;
			}
		);

		Functions\when( 'delete_transient' )->alias(
			static function ( $key ) use ( &$transients_ref ) {
				unset( $transients_ref[ $key ] );
				return true;
			}
		);

		Functions\when( 'wp_parse_url' )->alias(
			static function ( $url, $component = -1 ) {
				return is_string( $url ) && '' !== $url ? parse_url( $url, $component ) : false;
			}
		);

		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'absint' )->alias(
			static function ( $value ) {
				return max( 0, (int) $value );
			}
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return is_scalar( $value ) ? trim( (string) $value ) : '';
			}
		);

		Functions\when( 'rest_ensure_response' )->alias(
			static function ( $data ) {
				if ( $data instanceof WP_REST_Response ) {
					return $data;
				}
				return new WP_REST_Response( $data, 200 );
			}
		);

		Functions\when( 'is_wp_error' )->alias(
			static function ( $value ) {
				return $value instanceof WP_Error;
			}
		);

		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/articles-api/class-articles-api.php';
	}

	// ------------------------------------------------------------------
	// Private method access
	// ------------------------------------------------------------------

	private function invoke_private( $instance, $method, array $args = array() )
	{
		$ref = new ReflectionMethod( $instance, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $instance, $args );
	}

	private function makePublishedPostStub( $id, $content, $title = 'Test Post' )
	{
		$post              = new stdClass();
		$post->ID          = (int) $id;
		$post->post_status = 'publish';
		$post->post_title  = $title;
		$post->post_content = $content;
		return $post;
	}

	// ------------------------------------------------------------------
	// 1. Empty content → no internal links.
	// ------------------------------------------------------------------

	public function testEmptyContentReturnsZeroInternalLinks(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$post = $this->makePublishedPostStub( 10, '<p>Conteúdo sem nenhum link.</p>' );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 10 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( array(), $data['internal_links'] );
		$this->assertSame( 0, $data['stats']['internal_count'] );
		$this->assertSame( 0, $data['stats']['external_count'] );
		$this->assertFalse( $data['from_cache'] );
		$this->assertSame( 1, $data['schema_version'] );
	}

	// ------------------------------------------------------------------
	// 2. Relative internal link → absolutized + normalized.
	// ------------------------------------------------------------------

	public function testRelativeInternalLinkIsAbsolutizedAndNormalized(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$post = $this->makePublishedPostStub(
			11,
			'<p>Veja <a href="/foo/">aqui</a> e <a href="/melhores-bancos-digitais">esta lista</a>.</p>'
		);

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 11 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertCount( 2, $data['internal_links'] );
		// Trailing slash stripped (path was "/foo/" → "/foo").
		$this->assertSame( 'https://example.com/foo', $data['internal_links'][0]['url'] );
		$this->assertNull( $data['internal_links'][0]['target_post_id'] );
		$this->assertSame( 'https://example.com/melhores-bancos-digitais', $data['internal_links'][1]['url'] );
		$this->assertSame( 456, $data['internal_links'][1]['target_post_id'] );
		$this->assertSame( 1, $data['internal_links'][0]['position'] );
		$this->assertSame( 2, $data['internal_links'][1]['position'] );
	}

	// ------------------------------------------------------------------
	// 3. wp-block-button class → is_button: true.
	// ------------------------------------------------------------------

	public function testWpBlockButtonClassMakesItButton(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$html = '<div class="wp-block-button"><a class="wp-block-button__link" href="/cartao-de-credito-sem-anuidade">QUERO MEU CARTÃO</a></div>';
		$post = $this->makePublishedPostStub( 12, $html );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 12 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertCount( 1, $data['internal_links'] );
		$this->assertTrue( $data['internal_links'][0]['is_button'] );
		$this->assertSame( 'QUERO MEU CARTÃO', $data['internal_links'][0]['anchor_text'] );
	}

	// ------------------------------------------------------------------
	// 4. Tracking params stripped (utm_*, fbclid, gclid, ref).
	// ------------------------------------------------------------------

	public function testTrackingParamsAreStrippedAndOtherParamsKept(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$html = '<p><a href="https://example.com/foo/?utm_source=fb&utm_medium=cpc&keep=this&fbclid=abc&gclid=xyz&ref=hp">x</a></p>';
		$post = $this->makePublishedPostStub( 13, $html );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 13 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertCount( 1, $data['internal_links'] );
		$this->assertSame( 'https://example.com/foo?keep=this', $data['internal_links'][0]['url'] );
	}

	// ------------------------------------------------------------------
	// 5. Image-only link → "[image: alt]".
	// ------------------------------------------------------------------

	public function testImageOnlyLinkUsesAltText(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$html = '<p><a href="/melhores-bancos-digitais"><img src="banner.jpg" alt="banner"></a></p>';
		$post = $this->makePublishedPostStub( 14, $html );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 14 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertCount( 1, $data['internal_links'] );
		$this->assertSame( '[image: banner]', $data['internal_links'][0]['anchor_text'] );
	}

	public function testImageWithoutAltUsesBareImagePlaceholder(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$html = '<p><a href="/melhores-bancos-digitais"><img src="banner.jpg"></a></p>';
		$post = $this->makePublishedPostStub( 15, $html );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 15 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertSame( '[image]', $data['internal_links'][0]['anchor_text'] );
	}

	// ------------------------------------------------------------------
	// 6. Many external links same host → one summary row with count: 50.
	// ------------------------------------------------------------------

	public function testManyExternalLinksToSameDomainGroupOnce(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$html = '<p>';
		for ( $i = 0; $i < 50; $i++ ) {
			$html .= '<a href="https://amazon.com.br/produto-' . $i . '">link ' . $i . '</a> ';
		}
		$html .= '</p>';
		$post = $this->makePublishedPostStub( 16, $html );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 16 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertCount( 1, $data['external_links_summary'] );
		$this->assertSame( 'amazon.com.br', $data['external_links_summary'][0]['domain'] );
		$this->assertSame( 50, $data['external_links_summary'][0]['count'] );
		$this->assertSame( 50, $data['stats']['external_count'] );
		$this->assertSame( 0, $data['stats']['internal_count'] );
	}

	public function testExternalDomainStripsLeadingWww(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$html = '<p><a href="https://www.amazon.com.br/foo">a</a><a href="https://amazon.com.br/bar">b</a></p>';
		$post = $this->makePublishedPostStub( 17, $html );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 17 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertCount( 1, $data['external_links_summary'] );
		$this->assertSame( 'amazon.com.br', $data['external_links_summary'][0]['domain'] );
		$this->assertSame( 2, $data['external_links_summary'][0]['count'] );
	}

	// ------------------------------------------------------------------
	// 7. post_id inexistente → 404 post_not_found.
	// ------------------------------------------------------------------

	public function testPostNotFoundReturns404(): void
	{
		$api = new AlvoBotPro_ArticlesApi();

		Functions\when( 'get_post' )->justReturn( null );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 9999 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'post_not_found', $data['code'] );
		$this->assertSame( 404, $data['data']['status'] );
	}

	public function testMissingParamReturns400(): void
	{
		$api = new AlvoBotPro_ArticlesApi();

		$req      = new ArticlesApiTestRequest( array(), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'missing_param', $response->get_data()['code'] );
	}

	public function testInvalidUrlReturns400(): void
	{
		$api = new AlvoBotPro_ArticlesApi();

		$req      = new ArticlesApiTestRequest( array( 'url' => 'not-a-valid-url' ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'invalid_url', $response->get_data()['code'] );
	}

	public function testUrlNotResolvedReturns404(): void
	{
		$api = new AlvoBotPro_ArticlesApi();

		$req      = new ArticlesApiTestRequest(
			array( 'url' => 'https://example.com/unknown-page' ),
			array( 'token' => 'secret-token-123' )
		);
		$response = $api->handle_internal_links( $req );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'url_not_resolved', $response->get_data()['code'] );
	}

	// ------------------------------------------------------------------
	// 8. Post rascunho → 422 post_not_published.
	// ------------------------------------------------------------------

	public function testDraftPostReturns422(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$post = $this->makePublishedPostStub( 20, '<p>x</p>' );
		$post->post_status = 'draft';

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 20 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'post_not_published', $response->get_data()['code'] );
	}

	// ------------------------------------------------------------------
	// 9. refresh=1 ignores transient and recomputes.
	// ------------------------------------------------------------------

	public function testRefreshFlagBypassesCache(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$post = $this->makePublishedPostStub( 21, '<p><a href="/melhores-bancos-digitais">x</a></p>' );

		Functions\when( 'get_post' )->justReturn( $post );

		$cache_key = 'alvobot_internal_links_v1_21';
		$this->transients[ $cache_key ] = array(
			'value'      => array(
				'schema_version' => 1,
				'post_id'        => 21,
				'post_title'     => 'cached',
				'url'            => 'https://example.com/?p=21',
				'scanned_at'     => '2026-01-01T00:00:00Z',
				'from_cache'     => false,
				'internal_links' => array(),
				'external_links_summary' => array(),
				'stats'          => array(
					'total_links'       => 0,
					'internal_count'    => 0,
					'external_count'    => 0,
					'parse_duration_ms' => 0,
				),
			),
			'expiration' => 21600,
		);

		// Without refresh → cache hit.
		$req_cache   = new ArticlesApiTestRequest( array( 'post_id' => 21 ), array( 'token' => 'secret-token-123' ) );
		$cache_resp  = $api->handle_internal_links( $req_cache );
		$cache_data  = $cache_resp->get_data();
		$this->assertTrue( $cache_data['from_cache'] );
		$this->assertSame( 'cached', $cache_data['post_title'] );
		$this->assertSame( 'hit', $cache_resp->headers['X-Alvobot-Cache'] );

		// With refresh → recompute.
		$req_refresh  = new ArticlesApiTestRequest(
			array( 'post_id' => 21, 'refresh' => 1 ),
			array( 'token' => 'secret-token-123' )
		);
		$fresh_resp   = $api->handle_internal_links( $req_refresh );
		$fresh_data   = $fresh_resp->get_data();
		$this->assertFalse( $fresh_data['from_cache'] );
		$this->assertSame( 'Test Post', $fresh_data['post_title'] );
		$this->assertCount( 1, $fresh_data['internal_links'] );
		$this->assertSame( 'miss', $fresh_resp->headers['X-Alvobot-Cache'] );
	}

	// ------------------------------------------------------------------
	// 10. Subdomain different from home_url → external.
	// ------------------------------------------------------------------

	public function testDifferentSubdomainIsExternal(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$html = '<p><a href="https://blog.example.com/post-no-blog">post</a></p>';
		$post = $this->makePublishedPostStub( 22, $html );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 22 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertSame( 0, $data['stats']['internal_count'] );
		$this->assertSame( 1, $data['stats']['external_count'] );
		$this->assertSame( 'blog.example.com', $data['external_links_summary'][0]['domain'] );
	}

	// ------------------------------------------------------------------
	// Auth — verify_token() accepts the spec-allowed carriers.
	// ------------------------------------------------------------------

	public function testTokenViaLegacyHeaderPasses(): void
	{
		$api = new AlvoBotPro_ArticlesApi();
		$req = new ArticlesApiTestRequest( array(), array( 'token' => 'secret-token-123' ) );
		$this->assertTrue( $api->verify_token( $req ) );
	}

	public function testTokenViaXAlvobotHeaderPasses(): void
	{
		$api = new AlvoBotPro_ArticlesApi();
		$req = new ArticlesApiTestRequest( array(), array( 'x-alvobot-token' => 'secret-token-123' ) );
		$this->assertTrue( $api->verify_token( $req ) );
	}

	public function testTokenViaQueryParamPasses(): void
	{
		$api = new AlvoBotPro_ArticlesApi();
		$req = new ArticlesApiTestRequest( array( 'token' => 'secret-token-123' ), array() );
		$this->assertTrue( $api->verify_token( $req ) );
	}

	public function testMissingTokenReturnsUnauthorized(): void
	{
		$api    = new AlvoBotPro_ArticlesApi();
		$req    = new ArticlesApiTestRequest( array(), array() );
		$result = $api->verify_token( $req );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'unauthorized', $result->get_error_code() );
	}

	public function testWrongTokenReturnsUnauthorized(): void
	{
		$api    = new AlvoBotPro_ArticlesApi();
		$req    = new ArticlesApiTestRequest( array(), array( 'token' => 'wrong-token' ) );
		$result = $api->verify_token( $req );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'unauthorized', $result->get_error_code() );
	}

	// ------------------------------------------------------------------
	// Cache invalidation hook
	// ------------------------------------------------------------------

	public function testSavePostHookDeletesTransient(): void
	{
		$api      = new AlvoBotPro_ArticlesApi();
		$cache_key = 'alvobot_internal_links_v1_42';
		$this->transients[ $cache_key ] = array(
			'value'      => array( 'foo' => 'bar' ),
			'expiration' => 21600,
		);

		$api->invalidate_post_cache( 42 );

		$this->assertArrayNotHasKey( $cache_key, $this->transients );
	}

	// ------------------------------------------------------------------
	// Private helpers — focused tests
	// ------------------------------------------------------------------

	public function testNormalizeUrlLowercasesHostAndStripsFragment(): void
	{
		$api = new AlvoBotPro_ArticlesApi();
		$out = $this->invoke_private( $api, 'normalize_url', array( 'https://Example.COM/Path/?a=1#frag' ) );
		$this->assertSame( 'https://example.com/Path?a=1', $out );
	}

	public function testNormalizeUrlKeepsRootSlash(): void
	{
		$api = new AlvoBotPro_ArticlesApi();
		$out = $this->invoke_private( $api, 'normalize_url', array( 'https://example.com/' ) );
		$this->assertSame( 'https://example.com/', $out );
	}

	public function testIsInternalHostHandlesWwwVariants(): void
	{
		$api = new AlvoBotPro_ArticlesApi();
		$this->assertTrue( $this->invoke_private( $api, 'is_internal_host', array( 'example.com', 'example.com' ) ) );
		$this->assertTrue( $this->invoke_private( $api, 'is_internal_host', array( 'www.example.com', 'example.com' ) ) );
		$this->assertTrue( $this->invoke_private( $api, 'is_internal_host', array( 'example.com', 'www.example.com' ) ) );
		$this->assertFalse( $this->invoke_private( $api, 'is_internal_host', array( 'blog.example.com', 'example.com' ) ) );
		$this->assertFalse( $this->invoke_private( $api, 'is_internal_host', array( 'other.com', 'example.com' ) ) );
	}

	public function testAnchorTextTruncatedAt200Chars(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$long_word = str_repeat( 'a', 300 );
		$post = $this->makePublishedPostStub( 30, '<p><a href="/melhores-bancos-digitais">' . $long_word . '</a></p>' );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 30 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertSame( 200, strlen( $data['internal_links'][0]['anchor_text'] ) );
	}

	public function testRelatedPostsContextDetected(): void
	{
		$api  = new AlvoBotPro_ArticlesApi();
		$html = '<div class="yarpp-related"><a href="/melhores-bancos-digitais">Related</a></div>';
		$post = $this->makePublishedPostStub( 31, $html );

		Functions\when( 'get_post' )->justReturn( $post );

		$req      = new ArticlesApiTestRequest( array( 'post_id' => 31 ), array( 'token' => 'secret-token-123' ) );
		$response = $api->handle_internal_links( $req );

		$data = $response->get_data();
		$this->assertSame( 'related_posts', $data['internal_links'][0]['context'] );
	}
}
