<?php
/**
 * Maps API payloads to claim posts and runs sync.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Sync_Service {

	public const META_API_ID          = '_optima_claim_api_id';
	public const META_REFERENCE       = 'optima_claim_reference';
	public const META_STATUS          = 'optima_claim_status';
	public const META_CLAIMANT        = 'optima_claim_claimant_name';
	public const META_POLICY          = 'optima_claim_policy_number';
	public const META_DATE_OF_LOSS    = 'optima_claim_date_of_loss';
	public const META_EMAIL           = 'optima_claim_contact_email';
	public const META_PHONE           = 'optima_claim_contact_phone';
	public const META_SYNCED_AT       = 'optima_claim_synced_at';
	public const META_EXTERNAL_STATUS = 'optima_claim_external_status';
	/** Always stored for admin display (API created_at). */
	public const META_CORE_CREATED_AT = 'optima_claim_core_created_at';

	/**
	 * @param callable|null $on_progress Optional. Receives arrays: phase fetch_api|api_done|process with keys current, total, stats, etc.
	 * @return array{created:int,updated:int,skipped:int,errors:string[]}|WP_Error
	 */
	public function sync_all( ?callable $on_progress = null ) {
		$tick = static function ( array $payload ) use ( $on_progress ): void {
			if ( $on_progress ) {
				$on_progress( $payload );
			}
		};

		$tick( array( 'phase' => 'fetch_api' ) );

		$client = new Optima_Claims_Api_Client();
		$root   = $client->fetch_claims(
			$on_progress
				? function ( array $page ) use ( $tick ): void {
					$tick( array_merge( array( 'phase' => 'fetch_page' ), $page ) );
				}
				: null
		);

		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$settings = Optima_Claims_Admin_Settings::get_settings();
		$path     = isset( $settings['claims_array_path'] ) ? trim( (string) $settings['claims_array_path'] ) : '';

		$items = self::array_path( $root, $path );
		if ( null === $items ) {
			return new WP_Error(
				'optima_claims_no_list',
				__( 'Could not find a claims list in the API response. Adjust "Claims array path" in settings.', 'optima-claims-sync' )
			);
		}

		$items = self::normalize_to_list( $items );
		$items = $this->filter_items_by_claim_type_allowlist( $items, $settings );
		if ( empty( $items ) ) {
			$tick(
				array(
					'phase' => 'api_done',
					'total' => 0,
				)
			);
			$empty = array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'errors'  => array(),
			);
			update_option( 'optima_claims_sync_last_run_at', current_time( 'mysql', true ), false );
			update_option( 'optima_claims_sync_last_stats', $empty, false );
			return $empty;
		}

		$total = count( $items );
		$tick(
			array(
				'phase' => 'api_done',
				'total' => $total,
			)
		);

		$stats = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		$interval = (int) apply_filters( 'optima_claims_sync_progress_interval', 25, $total );
		if ( $interval < 1 ) {
			$interval = 1;
		}

		$done = 0;
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				$stats['skipped']++;
			} else {
				$result = $this->upsert_claim_from_item( $item );
				if ( is_wp_error( $result ) ) {
					$stats['errors'][] = sprintf( '[%s] %s', (string) $index, $result->get_error_message() );
				} elseif ( $result['action'] === 'created' ) {
					$stats['created']++;
				} elseif ( $result['action'] === 'updated' ) {
					$stats['updated']++;
				} else {
					$stats['skipped']++;
				}
			}
			++$done;
			if ( $on_progress && ( $done % $interval === 0 || $done === $total ) ) {
				$tick(
					array(
						'phase'   => 'process',
						'current' => $done,
						'total'   => $total,
						'stats'   => $stats,
					)
				);
			}
		}

		update_option( 'optima_claims_sync_last_run_at', current_time( 'mysql', true ), false );
		update_option( 'optima_claims_sync_last_stats', $stats, false );

		return $stats;
	}

	/**
	 * Permanently delete claim posts whose synced status meta matches “closed” (or values from optima_claims_closed_status_values).
	 * Removes child media attachments first. Skips posts with no status meta or a non-closed status.
	 *
	 * @return array{deleted:int,skipped:int,errors:string[]}
	 */
	public function delete_closed_claim_posts(): array {
		$deleted = 0;
		$skipped = 0;
		$errors  = array();

		$ids = get_posts(
			array(
				'post_type'              => Optima_Claims_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'no_found_rows'          => true,
			)
		);

		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( ! $this->post_marked_closed_by_synced_status( $post_id ) ) {
				++$skipped;
				continue;
			}
			$result = $this->delete_claim_post_and_attachments( $post_id );
			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( '[%d] %s', $post_id, $result->get_error_message() );
				continue;
			}
			++$deleted;
		}

		return array(
			'deleted' => $deleted,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	/**
	 * Uses optima_claim_status, optima_claim_external_status, then any status-like import meta (e.g. long path keys).
	 */
	private function post_marked_closed_by_synced_status( int $post_id ): bool {
		$closed_list = apply_filters( 'optima_claims_closed_status_values', array( 'closed' ), $post_id );
		if ( ! is_array( $closed_list ) || $closed_list === array() ) {
			$closed_list = array( 'closed' );
		}
		$closed_list = array_map(
			static function ( $v ): string {
				return strtolower( trim( (string) $v ) );
			},
			$closed_list
		);

		$primary = get_post_meta( $post_id, self::META_STATUS, true );
		if ( is_scalar( $primary ) && trim( (string) $primary ) !== '' ) {
			$norm = strtolower( trim( (string) $primary ) );
			return in_array( $norm, $closed_list, true );
		}

		$secondary = get_post_meta( $post_id, self::META_EXTERNAL_STATUS, true );
		if ( is_scalar( $secondary ) && trim( (string) $secondary ) !== '' ) {
			$norm = strtolower( trim( (string) $secondary ) );
			return in_array( $norm, $closed_list, true );
		}

		$all = get_post_meta( $post_id );
		if ( ! is_array( $all ) ) {
			return false;
		}
		$prefix = 'optima_claim_';
		$plen   = strlen( $prefix );
		foreach ( $all as $meta_key => $rows ) {
			if ( ! is_string( $meta_key ) || strpos( $meta_key, $prefix ) !== 0 ) {
				continue;
			}
			if ( $meta_key === self::META_STATUS || $meta_key === self::META_EXTERNAL_STATUS ) {
				continue;
			}
			if ( ! self::meta_key_might_hold_api_status( $meta_key, $plen ) ) {
				continue;
			}
			$val = isset( $rows[0] ) ? $rows[0] : null;
			if ( ! is_scalar( $val ) ) {
				continue;
			}
			$norm = strtolower( trim( (string) $val ) );
			if ( $norm !== '' && in_array( $norm, $closed_list, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * True if meta key plausibly stores the API claim status (Field Import uses hashed keys for long paths).
	 */
	private static function meta_key_might_hold_api_status( string $meta_key, int $prefix_len ): bool {
		$tail = substr( $meta_key, $prefix_len );
		if ( stripos( $tail, 'status' ) !== false ) {
			return true;
		}
		return (bool) preg_match( '/(?:^|_)(?:status|state)$/i', $tail );
	}

	/**
	 * @return true|WP_Error
	 */
	private function delete_claim_post_and_attachments( int $post_id ) {
		if ( get_post_type( $post_id ) !== Optima_Claims_Post_Type::POST_TYPE ) {
			return new WP_Error( 'optima_claims_not_claim', __( 'Not a claim post.', 'optima-claims-sync' ) );
		}
		$attachment_ids = get_posts(
			array(
				'post_parent'      => $post_id,
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $attachment_ids as $aid ) {
			wp_delete_attachment( (int) $aid, true );
		}
		$deleted = wp_delete_post( $post_id, true );
		if ( ! $deleted ) {
			return new WP_Error( 'optima_claims_delete_failed', __( 'Could not delete the claim.', 'optima-claims-sync' ) );
		}
		return true;
	}

	/**
	 * @param array $item Raw claim object from API (associative array).
	 * @return array{action:string,post_id?:int}|WP_Error
	 */
	public function upsert_claim_from_item( array $item ) {
		$api_id = $this->extract_api_id( $item );
		if ( $api_id === null || $api_id === '' ) {
			return new WP_Error( 'optima_claims_no_id', __( 'Claim item has no API id.', 'optima-claims-sync' ) );
		}

		$reference = $this->extract_reference( $item );
		if ( $reference === null || $reference === '' ) {
			$reference = (string) $api_id;
		}

		$claimable_id = $this->extract_claimable_id( $item );
		$api_title    = $this->extract_api_title( $item );

		$meta_map = $this->build_meta_map( $item, $api_id, $reference );

		/**
		 * Filter all post meta before insert/update.
		 *
		 * @param array $meta_map Associative meta_key => scalar value.
		 * @param array $item     Original API item.
		 */
		$meta_map = apply_filters( 'optima_claims_sync_meta_map', $meta_map, $item );

		$meta_map = array_merge( $meta_map, $this->core_display_meta( $item ) );

		$meta_map = $this->apply_date_format_to_meta_strings( $meta_map );

		$existing_id = $this->find_post_id_by_api_id( (string) $api_id );

		$post_status = apply_filters( 'optima_claims_sync_post_status', 'publish', $item, $meta_map );
		if ( ! is_string( $post_status ) || $post_status === '' ) {
			$post_status = 'publish';
		}

		$title_left = ( $claimable_id !== null && $claimable_id !== '' ) ? $claimable_id : $reference;
		if ( $api_title !== null && $api_title !== '' ) {
			$title_base = $title_left . ' - ' . $api_title;
		} else {
			$title_base = $title_left;
		}
		/**
		 * Filter the post title for synced claims. Default is `{claimable_id} - {title}` (API fields), with fallbacks if either part is missing.
		 *
		 * @param string $title_base Candidate title.
		 * @param array  $item       Original API item.
		 * @param array  $meta_map   Meta about to be saved.
		 */
		$title = apply_filters( 'optima_claims_sync_post_title', $title_base, $item, $meta_map );
		if ( ! is_string( $title ) || $title === '' ) {
			$title = (string) $title_base;
		}

		$post_name = sanitize_title( $title );
		if ( strlen( $post_name ) > 180 ) {
			$post_name = substr( $post_name, 0, 180 );
		}

		$postarr = array(
			'post_type'    => Optima_Claims_Post_Type::POST_TYPE,
			'post_status'  => $post_status,
			'post_title'   => $title,
			'post_name'    => $post_name,
			'post_content' => '',
		);

		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			$post_id       = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! $post_id ) {
			return new WP_Error( 'optima_claims_save_failed', __( 'Could not save claim post.', 'optima-claims-sync' ) );
		}

		foreach ( $meta_map as $key => $value ) {
			if ( ! is_string( $key ) || $key === '' ) {
				continue;
			}
			if ( $value === null ) {
				delete_post_meta( $post_id, $key );
				continue;
			}
			if ( is_scalar( $value ) ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		return array(
			'action'  => $existing_id ? 'updated' : 'created',
			'post_id' => (int) $post_id,
		);
	}

	private function find_post_id_by_api_id( string $api_id ): int {
		$posts = get_posts(
			array(
				'post_type'      => Optima_Claims_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_API_ID,
				'meta_value'     => $api_id,
				'suppress_filters' => true,
			)
		);
		return $posts ? (int) $posts[0] : 0;
	}

	/**
	 * @param array $item
	 * @param string|int $api_id
	 * @param string $reference
	 * @return array<string, scalar|null>
	 */
	private function build_meta_map( array $item, $api_id, string $reference ): array {
		$base = array(
			self::META_API_ID    => (string) $api_id,
			self::META_REFERENCE => $reference,
			self::META_SYNCED_AT => Optima_Claims_Date_Format::format_timestamp( (int) current_time( 'timestamp' ) ),
		);

		$settings = Optima_Claims_Admin_Settings::get_settings();
		$paths    = array();
		if ( ! empty( $settings['import_paths'] ) && is_array( $settings['import_paths'] ) ) {
			foreach ( $settings['import_paths'] as $p ) {
				$p = is_string( $p ) ? trim( $p ) : '';
				if ( $p !== '' ) {
					$paths[] = $p;
				}
			}
			$paths = array_values( array_unique( $paths ) );
		}

		if ( ! empty( $paths ) ) {
			foreach ( $paths as $path ) {
				$val  = Optima_Claims_Field_Discovery::get_value_at_path( $item, $path );
				$norm = Optima_Claims_Field_Discovery::normalize_for_meta( $val );
				$key  = Optima_Claims_Field_Discovery::path_to_meta_key( $path );
				$base[ $key ] = $norm;
			}
			// Field Import does not include legacy keys; always persist API status for admin tools (e.g. delete closed).
			$status_val = $this->string_or_null( $this->pick_field( $item, array( 'status', 'claimStatus', 'claim_status', 'state' ) ) );
			if ( $status_val !== null && $status_val !== '' ) {
				$base[ self::META_STATUS ] = $status_val;
			}
			return $base;
		}

		return array_merge( $base, $this->legacy_optional_meta( $item ) );
	}

	/**
	 * Previous built-in mapping when no custom import paths are configured.
	 *
	 * @param array $item
	 * @return array<string, string|null>
	 */
	private function legacy_optional_meta( array $item ): array {
		return array(
			self::META_STATUS          => $this->string_or_null( $this->pick_field( $item, array( 'status', 'claimStatus', 'claim_status', 'state' ) ) ),
			self::META_CLAIMANT        => $this->string_or_null( $this->pick_field( $item, array( 'claimantName', 'claimant_name', 'claimant', 'insuredName', 'customerName' ) ) ),
			self::META_POLICY          => $this->string_or_null( $this->pick_field( $item, array( 'policyNumber', 'policy_number', 'policy', 'policyNo' ) ) ),
			self::META_DATE_OF_LOSS    => $this->string_or_null( $this->pick_field( $item, array( 'dateOfLoss', 'date_of_loss', 'lossDate', 'incidentDate' ) ) ),
			self::META_EMAIL           => $this->string_or_null( $this->pick_field( $item, array( 'email', 'contactEmail', 'claimantEmail' ) ) ),
			self::META_PHONE           => $this->string_or_null( $this->pick_field( $item, array( 'phone', 'telephone', 'contactPhone', 'mobile' ) ) ),
			self::META_EXTERNAL_STATUS => $this->string_or_null( $this->pick_field( $item, array( 'externalStatus', 'workflowStatus' ) ) ),
		);
	}

	/**
	 * Meta always saved for admin display (e.g. created_at), independent of Field Import selection.
	 *
	 * @param array $item
	 * @return array<string, scalar|null>
	 */
	private function core_display_meta( array $item ): array {
		$out     = array();
		$created = $this->pick_field(
			$item,
			array(
				'created_at',
				'createdAt',
				'CreatedAt',
				'created',
				'inserted_at',
				'insertedAt',
			)
		);
		if ( $created !== null && is_scalar( $created ) ) {
			$out[ self::META_CORE_CREATED_AT ] = (string) $created;
		}
		if ( ! isset( $out[ self::META_CORE_CREATED_AT ] ) ) {
			$path = apply_filters( 'optima_claims_sync_core_created_at_path', '', $item );
			if ( is_string( $path ) && $path !== '' ) {
				$nested = Optima_Claims_Field_Discovery::get_value_at_path( $item, $path );
				if ( is_scalar( $nested ) ) {
					$out[ self::META_CORE_CREATED_AT ] = (string) $nested;
				}
			}
		}
		/**
		 * Filter always-on display meta merged after the main meta map.
		 *
		 * @param array $out  Keyed by meta_key.
		 * @param array $item API row.
		 */
		$filtered = apply_filters( 'optima_claims_sync_core_display_meta', $out, $item );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * Convert ISO-style and other parseable datetimes in meta values to d-m-y H:i:s (site TZ).
	 *
	 * @param array<string, scalar|null> $meta_map
	 * @return array<string, scalar|null>
	 */
	private function apply_date_format_to_meta_strings( array $meta_map ): array {
		$skip = array(
			self::META_API_ID,
			self::META_REFERENCE,
		);
		foreach ( $meta_map as $key => $value ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			if ( ! is_string( $value ) ) {
				continue;
			}
			$trim = ltrim( $value );
			if ( $trim !== '' && ( $trim[0] === '{' || $trim[0] === '[' ) ) {
				continue;
			}
			$meta_map[ $key ] = Optima_Claims_Date_Format::format_if_datetime( $value );
		}
		return $meta_map;
	}

	/**
	 * @param array $item
	 * @return string|int|null
	 */
	private function extract_api_id( array $item ) {
		$val = $this->pick_field( $item, array( 'id', 'Id', 'ID', 'claimId', 'claim_id', 'uuid', 'GUID' ) );
		if ( $val === null ) {
			return null;
		}
		if ( is_int( $val ) || is_float( $val ) ) {
			return (string) $val;
		}
		if ( is_string( $val ) ) {
			return trim( $val ) === '' ? null : trim( $val );
		}
		return null;
	}

	/**
	 * @param array $item
	 */
	private function extract_reference( array $item ): ?string {
		$val = $this->pick_field(
			$item,
			array(
				'claimReference',
				'claim_reference',
				'reference',
				'claimNumber',
				'claim_number',
				'claimNo',
				'number',
			)
		);
		if ( $val === null ) {
			return null;
		}
		$s = is_scalar( $val ) ? trim( (string) $val ) : '';
		return $s === '' ? null : $s;
	}

	/**
	 * Value used for post title and slug (claimable_id from API).
	 *
	 * @param array $item
	 */
	private function extract_claimable_id( array $item ): ?string {
		$val = $this->pick_field( $item, array( 'claimable_id', 'claimableId' ) );
		if ( $val === null ) {
			$path = apply_filters( 'optima_claims_sync_claimable_id_path', '', $item );
			if ( is_string( $path ) && $path !== '' ) {
				$val = Optima_Claims_Field_Discovery::get_value_at_path( $item, $path );
			}
		}
		if ( $val === null ) {
			return null;
		}
		if ( is_int( $val ) || is_float( $val ) ) {
			return (string) $val;
		}
		if ( is_string( $val ) ) {
			$s = trim( $val );
			return $s === '' ? null : $s;
		}
		return null;
	}

	/**
	 * Human-readable title from API (paired with claimable_id for the post title).
	 *
	 * @param array $item
	 */
	private function extract_api_title( array $item ): ?string {
		$val = $this->pick_field(
			$item,
			array(
				'title',
				'Title',
				'claimTitle',
				'claim_title',
			)
		);
		if ( $val === null ) {
			$path = apply_filters( 'optima_claims_sync_api_title_path', '', $item );
			if ( is_string( $path ) && $path !== '' ) {
				$val = Optima_Claims_Field_Discovery::get_value_at_path( $item, $path );
			}
		}
		if ( $val === null ) {
			return null;
		}
		if ( is_scalar( $val ) ) {
			$s = trim( (string) $val );
			return $s === '' ? null : $s;
		}
		return null;
	}

	/**
	 * @param array $item
	 * @param array<int, string> $keys
	 * @return mixed|null
	 */
	/**
	 * After the API list is normalized, keep only rows whose claim type label matches the allowlist (if configured).
	 *
	 * @param array<int, mixed> $items
	 * @param array             $settings From Optima_Claims_Admin_Settings::get_settings().
	 * @return array<int, mixed>
	 */
	private function filter_items_by_claim_type_allowlist( array $items, array $settings ): array {
		$raw = isset( $settings['claims_sync_type_allowlist'] ) ? trim( (string) $settings['claims_sync_type_allowlist'] ) : '';
		if ( $raw === '' ) {
			return $items;
		}
		$allowed = array();
		foreach ( preg_split( '/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY ) as $part ) {
			$key = strtolower( trim( (string) $part ) );
			if ( $key !== '' ) {
				$allowed[ $key ] = true;
			}
		}
		if ( $allowed === array() ) {
			return $items;
		}
		$path = isset( $settings['claims_sync_type_json_path'] ) ? trim( (string) $settings['claims_sync_type_json_path'] ) : '';

		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				$out[] = $item;
				continue;
			}
			$label = $this->resolve_claim_type_label( $item, $path );
			if ( $label === null || $label === '' ) {
				continue;
			}
			if ( ! isset( $allowed[ strtolower( $label ) ] ) ) {
				continue;
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * @param array  $item Claim row from API.
	 * @param string $path Optional dot path; empty = heuristic keys.
	 */
	private function resolve_claim_type_label( array $item, string $path ): ?string {
		$val = null;
		if ( $path !== '' ) {
			$val = Optima_Claims_Field_Discovery::get_value_at_path( $item, $path );
		} else {
			$val = $this->pick_field(
				$item,
				array(
					'claimType',
					'claim_type',
					'claimTypeName',
					'ClaimType',
					'type',
				)
			);
		}
		$label = $this->normalize_claim_type_value( $val );
		/**
		 * Normalized claim type label used for allowlist matching.
		 *
		 * @param string|null $label Resolved label or null.
		 * @param array       $item  Claim row.
		 * @param string      $path  Configured dot path or empty.
		 */
		$filtered = apply_filters( 'optima_claims_sync_claim_type_label', $label, $item, $path );
		if ( ! is_string( $filtered ) || trim( $filtered ) === '' ) {
			return null;
		}
		return trim( $filtered );
	}

	/**
	 * @param mixed $val Raw field value (string, number, or nested object from API).
	 */
	private function normalize_claim_type_value( $val ): ?string {
		if ( $val === null ) {
			return null;
		}
		if ( is_string( $val ) || is_int( $val ) || is_float( $val ) ) {
			$s = trim( (string) $val );
			return $s === '' ? null : $s;
		}
		if ( ! is_array( $val ) ) {
			return null;
		}
		foreach ( array( 'name', 'label', 'title', 'value', 'code', 'slug' ) as $k ) {
			if ( isset( $val[ $k ] ) && is_scalar( $val[ $k ] ) ) {
				$s = trim( (string) $val[ $k ] );
				if ( $s !== '' ) {
					return $s;
				}
			}
		}
		return null;
	}

	private function pick_field( array $item, array $keys ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $item ) && $item[ $key ] !== '' && $item[ $key ] !== null ) {
				return $item[ $key ];
			}
		}
		$lower = array_change_key_case( $item, CASE_LOWER );
		foreach ( $keys as $key ) {
			$lk = strtolower( $key );
			if ( array_key_exists( $lk, $lower ) && $lower[ $lk ] !== '' && $lower[ $lk ] !== null ) {
				return $lower[ $lk ];
			}
		}
		return null;
	}

	/**
	 * @param mixed $val
	 */
	private function string_or_null( $val ): ?string {
		if ( $val === null ) {
			return null;
		}
		if ( is_scalar( $val ) ) {
			return (string) $val;
		}
		return null;
	}

	/**
	 * @param mixed $data
	 * @param string $path Dot notation, empty string = root.
	 * @return mixed|null
	 */
	public static function array_path( $data, string $path ) {
		if ( $path === '' ) {
			return $data;
		}
		$keys    = explode( '.', $path );
		$current = $data;
		foreach ( $keys as $key ) {
			if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
				return null;
			}
			$current = $current[ $key ];
		}
		return $current;
	}

	/**
	 * @param mixed $items
	 * @return array<int, array>
	 */
	public static function normalize_to_list( $items ): array {
		if ( is_array( $items ) && self::is_assoc_array( $items ) ) {
			return array( $items );
		}
		if ( ! is_array( $items ) ) {
			return array();
		}
		$out = array();
		foreach ( $items as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param array $arr
	 */
	private static function is_assoc_array( array $arr ): bool {
		if ( array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
