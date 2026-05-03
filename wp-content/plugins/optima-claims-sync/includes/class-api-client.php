<?php
/**
 * HTTP client for the external claims API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Api_Client {

	private array $settings;

	public function __construct( ?array $settings = null ) {
		$this->settings = $settings ?? Optima_Claims_Admin_Settings::get_settings();
	}

	/**
	 * Performs the configured request(s), follows Claimable-style pagination headers, and returns
	 * one merged JSON root so sync can use "Claims array path" unchanged.
	 *
	 * Pagination: @link https://developers.claimable.com/reference/pagination
	 * Uses Link (rel="next") when present, else Current-Page / Total-Pages with a incremented page query param.
	 *
	 * @param callable|null $on_page Optional. Called after each successful HTTP page with items_so_far, headers, etc. (for live admin progress).
	 * @return array|WP_Error
	 */
	public function fetch_claims( ?callable $on_page = null ) {
		$base = isset( $this->settings['api_base_url'] ) ? trim( (string) $this->settings['api_base_url'] ) : '';
		if ( $base === '' ) {
			return new WP_Error( 'optima_claims_no_base_url', __( 'API base URL is not configured.', 'optima-claims-sync' ) );
		}

		$path = isset( $this->settings['endpoint_path'] ) ? trim( (string) $this->settings['endpoint_path'] ) : '/claims';
		if ( $path !== '' && $path[0] !== '/' ) {
			$path = '/' . $path;
		}

		$url = $this->join_url( $base, $path );
		$url = $this->apply_claims_import_scope( $url );

		$claims_path = isset( $this->settings['claims_array_path'] ) ? trim( (string) $this->settings['claims_array_path'] ) : '';

		$max_pages = (int) apply_filters( 'optima_claims_api_max_pages', 500 );
		if ( $max_pages < 1 ) {
			$max_pages = 1;
		}

		$combined_items = array();
		$first_root     = null;
		$visited        = array();

		for ( $page_index = 0; $page_index < $max_pages; $page_index++ ) {
			if ( $url === '' ) {
				break;
			}
			if ( isset( $visited[ $url ] ) ) {
				break;
			}
			$visited[ $url ] = true;

			$result = $this->request_claims_url( $url );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$root    = $result['data'];
			$headers = $result['headers'];

			if ( ! is_array( $root ) ) {
				return new WP_Error(
					'optima_claims_invalid_root',
					__( 'API response root was not a JSON object or array.', 'optima-claims-sync' )
				);
			}

			if ( $first_root === null ) {
				$first_root = $root;
			}

			$chunk = Optima_Claims_Sync_Service::array_path( $root, $claims_path );
			if ( null === $chunk ) {
				return new WP_Error(
					'optima_claims_no_list',
					__( 'Could not find a claims list in the API response. Adjust "Claims array path" in settings.', 'optima-claims-sync' )
				);
			}

			$combined_items = array_merge(
				$combined_items,
				Optima_Claims_Sync_Service::normalize_to_list( $chunk )
			);

			if ( $on_page ) {
				$on_page(
					array(
						'http_page'     => $page_index + 1,
						'items_so_far'  => count( $combined_items ),
						'total_pages'   => (int) $this->get_header_line( $headers, 'total-pages' ),
						'current_page'  => (int) $this->get_header_line( $headers, 'current-page' ),
						'per_page'      => (int) $this->get_header_line( $headers, 'per-page' ),
						'total_count'   => (int) $this->get_header_line( $headers, 'total-count' ),
					)
				);
			}

			$next_url = $this->get_next_page_url( $headers, $url );
			if ( $next_url === null || $next_url === '' ) {
				break;
			}
			$url = WP_Http::make_absolute_url( $next_url, $url );
			// Rel="next" URLs often drop list filters; re-merge "open only" (and filtered hook params).
			$url = $this->apply_claims_import_scope( $url );
		}

		if ( $first_root === null ) {
			return apply_filters( 'optima_claims_api_response', array() );
		}

		$merged = $this->inject_list_at_claims_path( $first_root, $claims_path, $combined_items );

		/**
		 * Filter decoded API response (array) before sync extracts the claims list.
		 *
		 * @param array $data Merged JSON root across all pages.
		 */
		return apply_filters( 'optima_claims_api_response', $merged );
	}

	/**
	 * Single HTTP request; returns decoded JSON and response headers for pagination.
	 *
	 * @return array{data: array, headers: mixed}|WP_Error
	 */
	private function request_claims_url( string $url ) {
		$method = strtoupper( (string) ( $this->settings['http_method'] ?? 'GET' ) );
		if ( ! in_array( $method, array( 'GET', 'POST' ), true ) ) {
			$method = 'GET';
		}

		$timeout = isset( $this->settings['timeout_seconds'] ) ? absint( $this->settings['timeout_seconds'] ) : 30;
		if ( $timeout < 5 ) {
			$timeout = 5;
		}
		if ( $timeout > 120 ) {
			$timeout = 120;
		}

		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => $this->build_headers(),
		);

		if ( $method === 'POST' ) {
			$body_mode = $this->settings['post_body_mode'] ?? 'json';
			if ( $body_mode === 'json' ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = wp_json_encode( $this->decode_json_field( $this->settings['post_body_json'] ?? '{}' ) );
			} elseif ( $body_mode === 'form' ) {
				$args['body'] = $this->decode_json_field( $this->settings['post_body_json'] ?? '{}' );
				if ( ! is_array( $args['body'] ) ) {
					$args['body'] = array();
				}
			}
		}

		/**
		 * Filter the request arguments before the claims API call.
		 *
		 * @param array  $args Request arguments for wp_remote_request.
		 * @param string $url  Full request URL.
		 */
		$args = apply_filters( 'optima_claims_api_request_args', $args, $url );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'optima_claims_http_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: response body excerpt */
					__( 'API returned HTTP %1$s: %2$s', 'optima-claims-sync' ),
					(string) $code,
					function_exists( 'mb_substr' ) ? mb_substr( (string) $body, 0, 500 ) : substr( (string) $body, 0, 500 )
				),
				array( 'status' => $code, 'body' => $body )
			);
		}

		$data = json_decode( (string) $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'optima_claims_invalid_json',
				__( 'API response was not valid JSON.', 'optima-claims-sync' ),
				array( 'json_error' => json_last_error_msg() )
			);
		}

		return array(
			'data'    => is_array( $data ) ? $data : array(),
			'headers' => wp_remote_retrieve_headers( $response ),
		);
	}

	/**
	 * @param mixed $headers Response headers from wp_remote_retrieve_headers().
	 */
	private function get_next_page_url( $headers, string $current_url ): ?string {
		$link = $this->get_header_line( $headers, 'link' );
		$next = $this->parse_link_rel_url( $link, 'next' );
		if ( $next !== null && $next !== '' ) {
			return $next;
		}

		$total = (int) $this->get_header_line( $headers, 'total-pages' );
		$cur   = (int) $this->get_header_line( $headers, 'current-page' );
		if ( $total > 0 && $cur > 0 && $cur < $total ) {
			return $this->url_with_page_query( $current_url, $cur + 1 );
		}

		return null;
	}

	/**
	 * @param mixed $headers Response headers from wp_remote_retrieve_headers().
	 */
	private function get_header_line( $headers, string $name ): string {
		if ( is_array( $headers ) ) {
			foreach ( $headers as $hname => $hvalue ) {
				if ( strcasecmp( (string) $hname, $name ) === 0 ) {
					return $this->header_value_to_string( $hvalue );
				}
			}
			return '';
		}
		if ( is_object( $headers ) ) {
			if ( method_exists( $headers, 'getValues' ) ) {
				$vals = $headers->getValues( $name );
				if ( is_array( $vals ) && $vals !== array() ) {
					return $this->header_value_to_string( $vals[0] );
				}
			}
			foreach ( $headers as $hname => $hvalue ) {
				if ( strcasecmp( (string) $hname, $name ) === 0 ) {
					return $this->header_value_to_string( $hvalue );
				}
			}
		}
		return '';
	}

	/**
	 * @param mixed $hvalue
	 */
	private function header_value_to_string( $hvalue ): string {
		if ( is_array( $hvalue ) ) {
			return implode( ', ', array_map( 'strval', $hvalue ) );
		}
		return (string) $hvalue;
	}

	/**
	 * RFC 8288 Link header: extract URL for a given rel (e.g. next).
	 */
	private function parse_link_rel_url( string $link_header, string $rel ): ?string {
		$rel_l = strtolower( $rel );
		if ( $link_header === '' ) {
			return null;
		}
		$parts = preg_split( '/\s*,\s*(?=<)/', $link_header );
		if ( ! is_array( $parts ) ) {
			return null;
		}
		foreach ( $parts as $part ) {
			if ( ! preg_match( '/<([^>]+)>\s*;\s*(.+)/', trim( $part ), $m ) ) {
				continue;
			}
			$params = $m[2];
			if ( ! preg_match( '/rel\s*=\s*"([^"]+)"/i', $params, $rm ) && ! preg_match( "/rel\s*=\s*'([^']+)'/i", $params, $rm ) ) {
				if ( ! preg_match( '/rel\s*=\s*([^;,\s]+)/i', $params, $rm ) ) {
					continue;
				}
			}
			$rels = array_map( 'trim', explode( ' ', strtolower( $rm[1] ) ) );
			if ( in_array( $rel_l, $rels, true ) ) {
				return html_entity_decode( $m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
			}
		}
		return null;
	}

	/**
	 * Fallback when Link is missing: same URL with p=N (Claimable list claims pagination parameter).
	 */
	private function url_with_page_query( string $url, int $page ): string {
		$part = wp_parse_url( $url );
		if ( ! is_array( $part ) ) {
			return $url;
		}
		$query = array();
		if ( ! empty( $part['query'] ) ) {
			parse_str( (string) $part['query'], $query );
		}
		unset( $query['page'] );
		$query['p'] = (string) max( 1, $page );
		$scheme   = isset( $part['scheme'] ) ? $part['scheme'] . '://' : '';
		$host     = $part['host'] ?? '';
		$port     = isset( $part['port'] ) ? ':' . (int) $part['port'] : '';
		$user     = $part['user'] ?? '';
		$pass     = isset( $part['pass'] ) ? ':' . $part['pass'] : '';
		$auth     = ( $user !== '' || $pass !== '' ) ? $user . $pass . '@' : '';
		$path     = $part['path'] ?? '/';
		$qstr     = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		$fragment = isset( $part['fragment'] ) ? '#' . $part['fragment'] : '';
		return $scheme . $auth . $host . $port . $path . '?' . $qstr . $fragment;
	}

	/**
	 * Replace the list at dotted claims path with the merged list from all pages.
	 *
	 * @param array $template First-page decoded JSON (structure preserved except list branch).
	 * @param array $merged_list Normalized claim rows.
	 * @return array|array<int, mixed>
	 */
	private function inject_list_at_claims_path( array $template, string $path, array $merged_list ) {
		if ( $path === '' ) {
			return $merged_list;
		}
		$out   = $template;
		$keys  = explode( '.', $path );
		$ref   = &$out;
		$last  = count( $keys ) - 1;
		foreach ( $keys as $i => $key ) {
			if ( $i === $last ) {
				$ref[ $key ] = $merged_list;
				break;
			}
			if ( ! isset( $ref[ $key ] ) || ! is_array( $ref[ $key ] ) ) {
				$ref[ $key ] = array();
			}
			$ref = &$ref[ $key ];
		}
		return $out;
	}

	private function join_url( string $base, string $path ): string {
		$base = rtrim( $base, '/' );
		return $base . $path;
	}

	/**
	 * When "Open claims only" is selected, merge query parameters into the list URL (GET query or POST target URL).
	 * Default matches Claimable list claims: status=open (see https://developers.claimable.com/reference/list_claims ).
	 * Other APIs can override via optima_claims_api_open_claims_query_params (e.g. filter[status]=open).
	 */
	private function apply_claims_import_scope( string $url ): string {
		$scope = isset( $this->settings['claims_import_scope'] ) ? sanitize_key( (string) $this->settings['claims_import_scope'] ) : 'all';
		if ( $scope !== 'open' ) {
			return $url;
		}
		$params = apply_filters(
			'optima_claims_api_open_claims_query_params',
			array( 'status' => 'open' ),
			$url,
			$this->settings
		);
		if ( ! is_array( $params ) || $params === array() ) {
			return $url;
		}
		return $this->url_with_merged_query( $url, $params );
	}

	/**
	 * @param array<string, mixed> $extra_query Merged into the URL query string (nested arrays become bracket notation).
	 */
	private function url_with_merged_query( string $url, array $extra_query ): string {
		$part = wp_parse_url( $url );
		if ( ! is_array( $part ) ) {
			return $url;
		}
		$query = array();
		if ( ! empty( $part['query'] ) ) {
			parse_str( (string) $part['query'], $query );
		}
		foreach ( $extra_query as $key => $val ) {
			if ( is_array( $val ) && isset( $query[ $key ] ) && is_array( $query[ $key ] ) ) {
				$query[ $key ] = array_replace( $query[ $key ], $val );
			} else {
				$query[ $key ] = $val;
			}
		}
		$scheme   = isset( $part['scheme'] ) ? $part['scheme'] . '://' : '';
		$host     = $part['host'] ?? '';
		$port     = isset( $part['port'] ) ? ':' . (int) $part['port'] : '';
		$user     = $part['user'] ?? '';
		$pass     = isset( $part['pass'] ) ? ':' . $part['pass'] : '';
		$auth     = ( $user !== '' || $pass !== '' ) ? $user . $pass . '@' : '';
		$path     = $part['path'] ?? '/';
		$qstr     = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		$fragment = isset( $part['fragment'] ) ? '#' . $part['fragment'] : '';
		$glue     = $qstr === '' ? '' : '?';
		return $scheme . $auth . $host . $port . $path . $glue . $qstr . $fragment;
	}

	private function build_headers(): array {
		$headers = array(
			'Accept' => 'application/json',
		);

		$auth = $this->settings['auth_type'] ?? 'none';
		$key  = isset( $this->settings['api_key'] ) ? trim( (string) $this->settings['api_key'] ) : '';

		if ( $auth === 'bearer' && $key !== '' ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		} elseif ( $auth === 'api_key_header' && $key !== '' ) {
			$name = isset( $this->settings['api_key_header_name'] ) ? trim( (string) $this->settings['api_key_header_name'] ) : 'X-API-Key';
			if ( $name === '' ) {
				$name = 'X-API-Key';
			}
			$headers[ $name ] = $key;
		} elseif ( $auth === 'basic' && $key !== '' ) {
			$user = isset( $this->settings['basic_auth_user'] ) ? (string) $this->settings['basic_auth_user'] : '';
			$pass = isset( $this->settings['basic_auth_password'] ) ? (string) $this->settings['basic_auth_password'] : '';
			if ( $user !== '' || $pass !== '' ) {
				$headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . $pass );
			}
		}

		$extra = isset( $this->settings['extra_headers_json'] ) ? trim( (string) $this->settings['extra_headers_json'] ) : '';
		if ( $extra !== '' ) {
			$decoded = json_decode( $extra, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $hname => $hval ) {
					if ( is_string( $hname ) && ( is_string( $hval ) || is_numeric( $hval ) ) ) {
						$headers[ $hname ] = (string) $hval;
					}
				}
			}
		}

		return $headers;
	}

	/**
	 * @param string $json
	 * @return array|mixed
	 */
	private function decode_json_field( $json ) {
		$decoded = json_decode( (string) $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return array();
		}
		return $decoded;
	}
}
