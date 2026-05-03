<?php
/**
 * Discover nested field paths from a claim object and read values by path.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optima_Claims_Field_Discovery {

	/**
	 * Build a flat list of importable paths from one claim array (first API row).
	 *
	 * @return array<int, array{path:string,type:string,sample:string}>
	 */
	public static function discover_paths( array $item ): array {
		$out = array();
		self::walk( $item, '', $out );
		/**
		 * Filter discovered field definitions before they are shown in admin.
		 *
		 * @param array $out  List of [ 'path', 'type', 'sample' ].
		 * @param array $item Source claim row.
		 */
		$filtered = apply_filters( 'optima_claims_discovered_fields', $out, $item );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * Read a value from a nested array using dot segments; numeric segments address list indices.
	 *
	 * @param array  $root
	 * @param string $path e.g. customer.address.line1 or items.0.code
	 * @return mixed|null
	 */
	public static function get_value_at_path( array $root, string $path ) {
		$path = trim( $path );
		if ( $path === '' ) {
			return null;
		}
		$parts = explode( '.', $path );
		$cur   = $root;
		foreach ( $parts as $part ) {
			if ( ! is_array( $cur ) ) {
				return null;
			}
			if ( array_key_exists( $part, $cur ) ) {
				$cur = $cur[ $part ];
				continue;
			}
			if ( ctype_digit( $part ) ) {
				$idx = (int) $part;
				if ( array_key_exists( $idx, $cur ) ) {
					$cur = $cur[ $idx ];
					continue;
				}
			}
			return null;
		}
		return $cur;
	}

	/**
	 * Normalize a value for post meta (scalar string or JSON for arrays/objects).
	 *
	 * @param mixed $value
	 * @return string|float|int|bool|null
	 */
	public static function normalize_for_meta( $value ) {
		if ( $value === null ) {
			return null;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return $value;
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			return wp_json_encode( $value );
		}
		return null;
	}

	/**
	 * Stable meta key derived from API path (max length safe for wp_postmeta).
	 */
	public static function path_to_meta_key( string $path ): string {
		$slug = preg_replace( '/[^a-z0-9]+/i', '_', str_replace( array( '.', '-' ), '_', $path ) );
		$slug = trim( (string) $slug, '_' );
		$slug = preg_replace( '/_+/', '_', $slug );
		$base = 'optima_claim_' . strtolower( $slug );
		if ( strlen( $base ) > 180 ) {
			$base = 'optima_claim_' . substr( md5( $path ), 0, 32 );
		}
		return $base;
	}

	/**
	 * @param array $data
	 * @param string $prefix
	 * @param array<int, array{path:string,type:string,sample:string}> $out
	 */
	private static function walk( $data, string $prefix, array &$out ): void {
		if ( ! is_array( $data ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				continue;
			}
			$segment = is_int( $key ) ? (string) $key : $key;
			$path    = $prefix === '' ? $segment : $prefix . '.' . $segment;

			if ( is_scalar( $value ) || $value === null ) {
				$out[] = array(
					'path'   => $path,
					'type'   => self::scalar_type( $value ),
					'sample' => self::sample_string( $value ),
				);
				continue;
			}

			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( self::is_list( $value ) ) {
				$n = count( $value );
				$out[] = array(
					'path'   => $path,
					'type'   => 'array',
					'sample' => sprintf(
						_n( 'JSON array (%d item)', 'JSON array (%d items)', $n, 'optima-claims-sync' ),
						$n
					),
				);
				if ( isset( $value[0] ) && is_array( $value[0] ) ) {
					self::walk( $value[0], $path . '.0', $out );
				}
				continue;
			}

			self::walk( $value, $path, $out );
		}
	}

	/**
	 * @param array $arr
	 */
	private static function is_list( array $arr ): bool {
		if ( array() === $arr ) {
			return true;
		}
		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	/**
	 * @param mixed $value
	 */
	private static function scalar_type( $value ): string {
		if ( $value === null ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return 'boolean';
		}
		if ( is_int( $value ) ) {
			return 'integer';
		}
		if ( is_float( $value ) ) {
			return 'number';
		}
		return 'string';
	}

	/**
	 * @param mixed $value
	 */
	private static function sample_string( $value ): string {
		if ( $value === null ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		$s = is_scalar( $value ) ? (string) $value : '';
		if ( strlen( $s ) > 120 ) {
			return substr( $s, 0, 117 ) . '…';
		}
		return $s;
	}

	/**
	 * Group flat discovered rows into a tree by dot path segments (for nested admin UI).
	 *
	 * @param array<int, array{path:string,type:string,sample:string}> $rows
	 * @return array{segment:string, children: array<string, array>, leaf: ?array}
	 */
	public static function build_path_tree( array $rows ): array {
		$root = array(
			'segment'  => '',
			'children' => array(),
			'leaf'     => null,
		);
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['path'] ) ) {
				continue;
			}
			$parts = explode( '.', (string) $row['path'] );
			$parts = array_values(
				array_filter(
					$parts,
					static function ( $p ) {
						return is_string( $p ) && $p !== '';
					}
				)
			);
			if ( $parts === array() ) {
				continue;
			}
			self::path_tree_insert( $root, $parts, $row, 0 );
		}
		self::path_tree_sort( $root );
		return $root;
	}

	/**
	 * @param array{segment:string, children: array<string, array>, leaf: ?array} $node
	 * @param array<int, string>                                                    $parts
	 * @param array{path:string,type:string,sample:string}                        $row
	 */
	private static function path_tree_insert( array &$node, array $parts, array $row, int $index ): void {
		if ( $index >= count( $parts ) ) {
			$node['leaf'] = $row;
			return;
		}
		$seg = $parts[ $index ];
		if ( ! isset( $node['children'][ $seg ] ) ) {
			$node['children'][ $seg ] = array(
				'segment'  => $seg,
				'children' => array(),
				'leaf'     => null,
			);
		}
		self::path_tree_insert( $node['children'][ $seg ], $parts, $row, $index + 1 );
	}

	/**
	 * @param array{children: array<string, array>} $node
	 */
	private static function path_tree_sort( array &$node ): void {
		if ( empty( $node['children'] ) ) {
			return;
		}
		uksort( $node['children'], 'strnatcasecmp' );
		foreach ( $node['children'] as &$child ) {
			self::path_tree_sort( $child );
		}
		unset( $child );
	}

	/**
	 * @param array{children: array<string, array>, leaf: ?array} $node
	 */
	private static function path_tree_leaf_count( array $node ): int {
		$n = ! empty( $node['leaf'] ) ? 1 : 0;
		foreach ( $node['children'] as $child ) {
			$n += self::path_tree_leaf_count( $child );
		}
		return $n;
	}

	/**
	 * Output nested, collapsible field list for the settings screen.
	 *
	 * @param array{segment:string, children: array<string, array>, leaf: ?array} $tree_root
	 * @param array<int, string>                                                   $import_paths
	 */
	public static function render_field_tree_for_admin( array $tree_root, array $import_paths ): void {
		if ( empty( $tree_root['children'] ) && empty( $tree_root['leaf'] ) ) {
			return;
		}

		/**
		 * Which root-level path segment’s accordion should open first (matches `data-optima-segment`, case-insensitive). Falls back to `api`, then the first group.
		 *
		 * @param string $segment   Default segment label, default `path` (API path column).
		 * @param array  $tree_root Built tree root.
		 */
		$default_accordion = apply_filters( 'optima_claims_sync_default_field_group_segment', 'path', $tree_root );
		$default_accordion = strtolower( (string) $default_accordion );

		echo '<div class="optima-claims-field-tree" data-optima-default-accordion="' . esc_attr( $default_accordion ) . '">';
		echo '<div class="optima-claims-field-grid-header" role="row">';
		echo '<span class="screen-reader-text">' . esc_html__( 'Import', 'optima-claims-sync' ) . '</span>';
		echo '<span>' . esc_html__( 'API path', 'optima-claims-sync' ) . '</span>';
		echo '<span>' . esc_html__( 'Type', 'optima-claims-sync' ) . '</span>';
		echo '<span>' . esc_html__( 'Sample (first row)', 'optima-claims-sync' ) . '</span>';
		echo '<span>' . esc_html__( 'Post meta key', 'optima-claims-sync' ) . '</span>';
		echo '</div>';

		if ( ! empty( $tree_root['leaf'] ) ) {
			self::render_field_tree_leaf_row( $tree_root['leaf'], $import_paths );
		}
		self::render_field_tree_branches( $tree_root['children'], $import_paths, 0 );
		echo '</div>';
	}

	/**
	 * @param array<string, array{segment:string, children: array<string, array>, leaf: ?array}> $children
	 * @param array<int, string>                                                               $import_paths
	 */
	private static function render_field_tree_branches( array $children, array $import_paths, int $depth ): void {
		foreach ( $children as $node ) {
			$has_kids = ! empty( $node['children'] );
			$has_leaf = ! empty( $node['leaf'] );
			if ( $has_kids ) {
				$leaf_count = self::path_tree_leaf_count( $node );
				echo '<details class="optima-claims-field-group" data-optima-segment="' . esc_attr( $node['segment'] ) . '">';
				echo '<summary class="optima-claims-field-summary">';
				echo esc_html( $node['segment'] );
				echo ' <span class="description">(' . esc_html( (string) $leaf_count ) . ')</span>';
				echo '</summary>';
				echo '<div class="optima-claims-field-nested">';
				if ( $has_leaf ) {
					self::render_field_tree_leaf_row( $node['leaf'], $import_paths );
				}
				self::render_field_tree_branches( $node['children'], $import_paths, $depth + 1 );
				echo '</div>';
				echo '</details>';
			} elseif ( $has_leaf ) {
				self::render_field_tree_leaf_row( $node['leaf'], $import_paths );
			}
		}
	}

	/**
	 * @param array{path:string,type:string,sample:string} $row
	 * @param array<int, string>                            $import_paths
	 */
	private static function render_field_tree_leaf_row( array $row, array $import_paths ): void {
		$path     = (string) $row['path'];
		$type     = isset( $row['type'] ) ? (string) $row['type'] : '';
		$sample   = isset( $row['sample'] ) ? (string) $row['sample'] : '';
		$meta_key = self::path_to_meta_key( $path );
		$checked  = in_array( $path, $import_paths, true );

		echo '<div class="optima-claims-field-leaf">';
		echo '<span class="optima-claims-field-leaf-cb">';
		printf(
			'<input class="optima-claims-import-path" type="checkbox" name="import_paths[]" value="%1$s"%2$s />',
			esc_attr( $path ),
			$checked ? ' checked="checked"' : ''
		);
		echo '</span>';
		echo '<span class="optima-claims-field-leaf-path"><code>' . esc_html( $path ) . '</code></span>';
		echo '<span class="optima-claims-field-leaf-type">' . esc_html( $type ) . '</span>';
		echo '<span class="optima-claims-field-leaf-sample">' . esc_html( $sample ) . '</span>';
		echo '<span class="optima-claims-field-leaf-meta"><code>' . esc_html( $meta_key ) . '</code></span>';
		echo '</div>';
	}
}
