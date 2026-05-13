<?php
/**
 * Articles API — REST endpoints for backend article inspection.
 *
 * Exposes read-only endpoints consumed by the NestJS backend (parallel to the
 * existing /plugins/commands service). Auth follows the same shared-token pattern
 * as plugin-manager (alvobot_site_token).
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_ArticlesApi {

	const REST_NAMESPACE      = 'alvobot-pro/v1';
	const ROUTE_INTERNAL      = '/articles/internal-links';
	const SCHEMA_VERSION      = 1;
	const CACHE_KEY_PREFIX    = 'alvobot_internal_links_v1_';
	const CACHE_TTL_SECONDS   = 21600; // 6 hours.
	const MAX_LINKS_RETURNED  = 200;
	const ANCHOR_TEXT_MAX_LEN = 200;
	const SLOW_PARSE_WARN_MS  = 2000;

	private $module_slug = 'articles-api';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'save_post', array( $this, 'invalidate_post_cache' ), 10, 1 );
	}

	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE_INTERNAL,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_internal_links' ),
				'permission_callback' => array( $this, 'verify_token' ),
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'url'     => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'esc_url_raw',
					),
					'refresh' => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'v'       => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Token verification — accepts the shared-token used by /plugins/commands.
	 *
	 * Accepted carriers (first non-empty wins):
	 *   1. HTTP header `token` (legacy, matches /plugins/commands)
	 *   2. HTTP header `X-Alvobot-Token` (preferred by spec)
	 *   3. Query param `token` (GET convenience)
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function verify_token( $request ) {
		$stored = get_option( 'alvobot_site_token' );
		if ( empty( $stored ) ) {
			return new WP_Error(
				'unauthorized',
				'invalid or missing token',
				array( 'status' => 401 )
			);
		}

		$candidates = array(
			$request->get_header( 'token' ),
			$request->get_header( 'x_alvobot_token' ),
			$request->get_header( 'x-alvobot-token' ),
			$request->get_param( 'token' ),
		);

		$token = '';
		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate ) {
				$token = $candidate;
				break;
			}
		}

		if ( '' === $token || ! hash_equals( (string) $stored, (string) $token ) ) {
			return new WP_Error(
				'unauthorized',
				'invalid or missing token',
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * REST callback for /articles/internal-links.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_internal_links( $request ) {
		$post_id_param = $request->get_param( 'post_id' );
		$url_param     = $request->get_param( 'url' );
		$refresh       = (int) $request->get_param( 'refresh' ) === 1;

		// Resolve post.
		$post_id = 0;
		if ( ! empty( $post_id_param ) ) {
			$post_id = (int) $post_id_param;
		} elseif ( ! empty( $url_param ) ) {
			if ( false === filter_var( $url_param, FILTER_VALIDATE_URL ) ) {
				return $this->error_response( 'invalid_url', 'url could not be parsed', 400 );
			}
			$post_id = (int) url_to_postid( $url_param );
			if ( $post_id <= 0 ) {
				return $this->error_response( 'url_not_resolved', 'url did not resolve to a post', 404 );
			}
		} else {
			return $this->error_response( 'missing_param', 'post_id or url is required', 400 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error_response( 'post_not_found', 'post does not exist', 404 );
		}
		if ( 'publish' !== $post->post_status ) {
			return $this->error_response( 'post_not_published', 'post is not published', 422 );
		}

		// Try cache first.
		$cache_key = self::CACHE_KEY_PREFIX . $post_id;
		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				$cached['from_cache'] = true;
				return $this->build_response( $cached, true );
			}
		}

		// Compute.
		$started_at = microtime( true );

		try {
			$payload = $this->build_internal_links_payload( $post );
		} catch ( \Throwable $e ) {
			AlvoBotPro::debug_log(
				$this->module_slug,
				'[parse_failed] post_id=' . $post_id . ' error=' . $e->getMessage()
			);
			return $this->error_response( 'parse_failed', 'DOMDocument failed to parse content', 500 );
		}

		$payload['stats']['parse_duration_ms'] = (int) round( ( microtime( true ) - $started_at ) * 1000 );
		$payload['from_cache']                 = false;

		if ( $payload['stats']['parse_duration_ms'] > self::SLOW_PARSE_WARN_MS ) {
			AlvoBotPro::debug_log(
				$this->module_slug,
				'[slow_parse] post_id=' . $post_id . ' duration_ms=' . $payload['stats']['parse_duration_ms']
			);
		}

		set_transient( $cache_key, $payload, self::CACHE_TTL_SECONDS );

		return $this->build_response( $payload, false );
	}

	/**
	 * Build the payload by extracting links from rendered post content.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function build_internal_links_payload( $post ) {
		$raw_content      = (string) $post->post_content;
		$rendered_content = apply_filters( 'the_content', $raw_content );

		$home_host = $this->host_from_url( home_url( '/' ) );

		$all_anchors = $this->extract_anchors( $rendered_content );

		$internal_links = array();
		$external_count = 0;
		$external_hosts = array();
		$total_links    = 0;
		$position       = 0;

		foreach ( $all_anchors as $anchor ) {
			$href = $anchor['href'];
			if ( '' === $href ) {
				continue;
			}

			// Resolve relative URLs against the site home.
			$absolute = $this->absolutize_url( $href );
			if ( '' === $absolute ) {
				continue;
			}

			++$total_links;

			$normalized = $this->normalize_url( $absolute );
			$link_host  = $this->host_from_url( $normalized );

			if ( $this->is_internal_host( $link_host, $home_host ) ) {
				if ( count( $internal_links ) >= self::MAX_LINKS_RETURNED ) {
					continue;
				}

				++$position;

				$target_post_id = (int) url_to_postid( $normalized );

				$internal_links[] = array(
					'url'            => $normalized,
					'anchor_text'    => $anchor['anchor_text'],
					'context'        => $anchor['context'],
					'is_button'      => $anchor['is_button'],
					'target_post_id' => $target_post_id > 0 ? $target_post_id : null,
					'position'       => $position,
				);
			} else {
				++$external_count;
				$reg_host = $this->registrable_host( $link_host );
				if ( '' !== $reg_host ) {
					if ( ! isset( $external_hosts[ $reg_host ] ) ) {
						$external_hosts[ $reg_host ] = 0;
					}
					++$external_hosts[ $reg_host ];
				}
			}
		}

		// Sort external hosts by count desc, top 20.
		arsort( $external_hosts, SORT_NUMERIC );
		$external_summary = array();
		$index            = 0;
		foreach ( $external_hosts as $host => $count ) {
			if ( $index >= 20 ) {
				break;
			}
			$external_summary[] = array(
				'domain' => $host,
				'count'  => (int) $count,
			);
			++$index;
		}

		$stats = array(
			'total_links'       => $total_links,
			'internal_count'    => count( $internal_links ),
			'external_count'    => $external_count,
			'parse_duration_ms' => 0,
		);

		if ( count( $internal_links ) >= self::MAX_LINKS_RETURNED ) {
			$stats['truncated'] = true;
		}

		return array(
			'schema_version'         => self::SCHEMA_VERSION,
			'post_id'                => (int) $post->ID,
			'post_title'             => (string) $post->post_title,
			'url'                    => (string) get_permalink( $post ),
			'scanned_at'             => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'from_cache'             => false,
			'internal_links'         => $internal_links,
			'external_links_summary' => $external_summary,
			'stats'                  => $stats,
		);
	}

	/**
	 * Parse HTML with DOMDocument and extract anchors with context metadata.
	 *
	 * @param string $html Rendered HTML.
	 * @return array<int, array{href:string, anchor_text:string, context:string, is_button:bool}>
	 */
	private function extract_anchors( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return array();
		}

		$prev_state = libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		// UTF-8 hint avoids mojibake; flags prevent implied <html>/<body>/DTD.
		$loaded = $dom->loadHTML(
			'<?xml encoding="UTF-8"?>' . $html,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $prev_state );

		if ( ! $loaded ) {
			return array();
		}

		$anchors = $dom->getElementsByTagName( 'a' );
		$results = array();

		foreach ( $anchors as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			$href = $node->getAttribute( 'href' );
			$href = is_string( $href ) ? trim( $href ) : '';
			if ( '' === $href ) {
				continue;
			}
			// Skip non-http(s) schemes: mailto:, tel:, javascript:, #fragments only.
			if ( '#' === substr( $href, 0, 1 ) ) {
				continue;
			}
			$scheme = parse_url( $href, PHP_URL_SCHEME );
			if ( is_string( $scheme ) && '' !== $scheme && 'http' !== $scheme && 'https' !== $scheme ) {
				continue;
			}

			$results[] = array(
				'href'        => $href,
				'anchor_text' => $this->build_anchor_text( $node ),
				'context'     => $this->classify_context( $node ),
				'is_button'   => $this->is_button( $node ),
			);
		}

		return $results;
	}

	/**
	 * Anchor text: trimmed/collapsed; for image-only links return [image: alt] or [image].
	 *
	 * @param DOMElement $node Anchor element.
	 * @return string
	 */
	private function build_anchor_text( DOMElement $node ) {
		$text = (string) $node->textContent;
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( (string) $text );

		if ( '' === $text ) {
			$img = null;
			foreach ( $node->getElementsByTagName( 'img' ) as $candidate ) {
				$img = $candidate;
				break;
			}
			if ( $img instanceof DOMElement ) {
				$alt = trim( (string) $img->getAttribute( 'alt' ) );
				$text = '' !== $alt ? '[image: ' . $alt . ']' : '[image]';
			}
		}

		if ( '' === $text ) {
			return '';
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text, 'UTF-8' ) > self::ANCHOR_TEXT_MAX_LEN ) {
				$text = mb_substr( $text, 0, self::ANCHOR_TEXT_MAX_LEN, 'UTF-8' );
			}
		} elseif ( strlen( $text ) > self::ANCHOR_TEXT_MAX_LEN ) {
			$text = substr( $text, 0, self::ANCHOR_TEXT_MAX_LEN );
		}

		return $text;
	}

	/**
	 * Detect button-like anchors via class CSS / inline style markers.
	 *
	 * @param DOMElement $node Anchor element.
	 * @return bool
	 */
	private function is_button( DOMElement $node ) {
		$button_class_patterns = array( 'button', 'btn', 'cta', 'wp-block-button', 'wp-element-button' );

		$cursor = $node;
		$hops   = 0;
		while ( $cursor instanceof DOMElement && $hops < 6 ) {
			$class_attr = strtolower( (string) $cursor->getAttribute( 'class' ) );
			if ( '' !== $class_attr ) {
				foreach ( $button_class_patterns as $needle ) {
					if ( false !== strpos( $class_attr, $needle ) ) {
						return true;
					}
				}
			}

			$style_attr = strtolower( (string) $cursor->getAttribute( 'style' ) );
			if ( '' !== $style_attr
				&& false !== strpos( $style_attr, 'display:inline-block' )
				&& false !== strpos( $style_attr, 'background' )
			) {
				return true;
			}

			$cursor = $cursor->parentNode instanceof DOMElement ? $cursor->parentNode : null;
			++$hops;
		}

		return false;
	}

	/**
	 * Walk ancestors to detect contextual containers (related posts, header/footer/aside, comments, sidebar).
	 * Defaults to in_content.
	 *
	 * @param DOMElement $node Anchor element.
	 * @return string
	 */
	private function classify_context( DOMElement $node ) {
		$related_markers = array(
			'yarpp-related',
			'jp-relatedposts',
			'related-posts',
			'relatedposts',
			'alvobot-related',
		);
		$sidebar_markers = array( 'sidebar', 'widget-area' );
		$comment_markers = array( 'comments', 'comment-list', 'comment-respond' );

		$cursor = $node->parentNode;
		while ( $cursor instanceof DOMElement ) {
			$tag        = strtolower( $cursor->tagName );
			$class_attr = strtolower( (string) $cursor->getAttribute( 'class' ) );
			$id_attr    = strtolower( (string) $cursor->getAttribute( 'id' ) );
			$haystack   = $class_attr . ' ' . $id_attr;

			if ( 'header' === $tag ) {
				return 'header';
			}
			if ( 'footer' === $tag ) {
				return 'footer';
			}
			if ( 'aside' === $tag ) {
				return 'sidebar';
			}

			foreach ( $related_markers as $marker ) {
				if ( false !== strpos( $haystack, $marker ) ) {
					return 'related_posts';
				}
			}
			foreach ( $sidebar_markers as $marker ) {
				if ( false !== strpos( $haystack, $marker ) ) {
					return 'sidebar';
				}
			}
			foreach ( $comment_markers as $marker ) {
				if ( false !== strpos( $haystack, $marker ) ) {
					return 'comments';
				}
			}

			$cursor = $cursor->parentNode instanceof DOMElement ? $cursor->parentNode : null;
		}

		return 'in_content';
	}

	/**
	 * Resolve a possibly-relative URL into an absolute one using home_url() as base.
	 *
	 * @param string $href Raw href value.
	 * @return string Absolute URL or '' if cannot be resolved.
	 */
	private function absolutize_url( $href ) {
		$parts = wp_parse_url( $href );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		if ( isset( $parts['scheme'], $parts['host'] ) ) {
			return $href;
		}

		// Scheme-relative //host/path → keep current scheme.
		if ( ! isset( $parts['scheme'] ) && isset( $parts['host'] ) ) {
			$home_scheme = wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );
			$home_scheme = is_string( $home_scheme ) && '' !== $home_scheme ? $home_scheme : 'https';
			return $home_scheme . ':' . $href;
		}

		// Path-relative.
		if ( isset( $parts['path'] ) && '/' === substr( $parts['path'], 0, 1 ) ) {
			$base  = rtrim( (string) home_url(), '/' );
			$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
			return $base . $parts['path'] . $query;
		}

		return '';
	}

	/**
	 * Normalize URL per spec: lowercase host, strip fragment, strip tracking params,
	 * drop trailing slash (except root).
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private function normalize_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $url;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$host   = strtolower( $parts['host'] );
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '/';

		// Drop trailing slash unless the path is just "/".
		if ( strlen( $path ) > 1 && '/' === substr( $path, -1 ) ) {
			$path = rtrim( $path, '/' );
		}

		$query = '';
		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$query = $this->strip_tracking_params( (string) $parts['query'] );
		}

		$rebuilt = $scheme . '://' . $host . $port . $path;
		if ( '' !== $query ) {
			$rebuilt .= '?' . $query;
		}

		return $rebuilt;
	}

	/**
	 * Drop utm_*, fbclid, gclid, ref from a query string. Preserve others, preserve order.
	 *
	 * @param string $query Raw query string.
	 * @return string
	 */
	private function strip_tracking_params( $query ) {
		if ( '' === $query ) {
			return '';
		}

		$pairs = explode( '&', $query );
		$kept  = array();
		foreach ( $pairs as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$equals_pos = strpos( $pair, '=' );
			$name       = false === $equals_pos ? $pair : substr( $pair, 0, $equals_pos );
			$name_lower = strtolower( urldecode( $name ) );

			if ( 'fbclid' === $name_lower || 'gclid' === $name_lower || 'ref' === $name_lower ) {
				continue;
			}
			if ( 0 === strpos( $name_lower, 'utm_' ) ) {
				continue;
			}
			$kept[] = $pair;
		}

		return implode( '&', $kept );
	}

	/**
	 * Return host extracted from an absolute URL. Lowercased, no www. prefix stripping here.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function host_from_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Compare a link host against home_url() host. The link is internal when:
	 *   - exact host match, OR
	 *   - one host is "www.<other>".
	 *
	 * @param string $link_host Host of the link.
	 * @param string $home_host Host of the site.
	 * @return bool
	 */
	private function is_internal_host( $link_host, $home_host ) {
		if ( '' === $link_host || '' === $home_host ) {
			return false;
		}
		if ( $link_host === $home_host ) {
			return true;
		}
		if ( $link_host === 'www.' . $home_host ) {
			return true;
		}
		if ( $home_host === 'www.' . $link_host ) {
			return true;
		}
		return false;
	}

	/**
	 * Approximate registrable host for grouping external links. Strips a leading "www.".
	 *
	 * @param string $host Lowercased host.
	 * @return string
	 */
	private function registrable_host( $host ) {
		if ( '' === $host ) {
			return '';
		}
		if ( 0 === strpos( $host, 'www.' ) ) {
			return substr( $host, 4 );
		}
		return $host;
	}

	/**
	 * Build the WP_REST_Response with standard headers.
	 *
	 * @param array $payload   Response payload.
	 * @param bool  $cache_hit Whether the payload was served from cache.
	 * @return WP_REST_Response
	 */
	private function build_response( array $payload, $cache_hit ) {
		$response = rest_ensure_response( $payload );
		if ( $response instanceof WP_REST_Response ) {
			$response->header( 'Cache-Control', 'private, max-age=300' );
			$response->header( 'X-Alvobot-Schema-Version', (string) self::SCHEMA_VERSION );
			$response->header( 'X-Alvobot-Cache', $cache_hit ? 'hit' : 'miss' );
		}
		return $response;
	}

	/**
	 * Build a uniform WP_REST_Response error body.
	 *
	 * @param string $code    Error code.
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status.
	 * @return WP_REST_Response
	 */
	private function error_response( $code, $message, $status ) {
		return new WP_REST_Response(
			array(
				'code'    => $code,
				'message' => $message,
				'data'    => array( 'status' => (int) $status ),
			),
			(int) $status
		);
	}

	/**
	 * Invalidate cached payload on post save.
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_post_cache( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		delete_transient( self::CACHE_KEY_PREFIX . $post_id );
	}
}
