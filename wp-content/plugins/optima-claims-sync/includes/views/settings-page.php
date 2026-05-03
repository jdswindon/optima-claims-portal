<?php
/**
 * Admin settings markup.
 *
 * @var array        $s
 * @var int|false    $next
 * @var string       $last_run
 * @var mixed        $last_stats
 * @var array|null   $discovery Option snapshot from Optima_Claims_Admin_Settings::FIELD_DISCOVERY_OPTION.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$discovery_paths = ( is_array( $discovery ) && ! empty( $discovery['paths'] ) && is_array( $discovery['paths'] ) ) ? $discovery['paths'] : array();
$discovery_at      = ( is_array( $discovery ) && ! empty( $discovery['captured_at'] ) ) ? (string) $discovery['captured_at'] : '';
$import_paths      = ! empty( $s['import_paths'] ) && is_array( $s['import_paths'] ) ? $s['import_paths'] : array();
$api_key_stored    = isset( $s['api_key'] ) && trim( (string) $s['api_key'] ) !== '';

?>
<div class="wrap optima-claims-sync-settings-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="optima-claims-sync-settings-layout">
		<div class="optima-claims-sync-settings-main">
			<p><?php esc_html_e( 'Configure the external API and run a manual sync. Claims are stored as the “Claims” post type.', 'optima-claims-sync' ); ?></p>

			<?php if ( $next ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: localized date/time */
							__( 'Next scheduled sync: %s', 'optima-claims-sync' ),
							wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next )
						)
					);
					?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'No cron event scheduled. Enable scheduled sync on the Scheduled Sync tab and save settings.', 'optima-claims-sync' ); ?></p>
			<?php endif; ?>

			<p id="optima-claims-sync-summary" class="optima-claims-sync-summary" aria-live="polite">
				<span id="optima-claims-sync-spinner" class="spinner" style="float: none; margin: 0 8px 0 0; vertical-align: middle;" aria-hidden="true"></span>
				<span id="optima-claims-sync-summary-text">
					<?php if ( is_string( $last_run ) && $last_run !== '' ) : ?>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: UTC datetime */
								__( 'Last sync finished at (UTC): %s', 'optima-claims-sync' ),
								$last_run
							)
						);
						?>
						<?php if ( is_array( $last_stats ) ) : ?>
							—
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: created count, 2: updated count, 3: skipped count */
									__( '%1$d created, %2$d updated, %3$d skipped.', 'optima-claims-sync' ),
									(int) ( $last_stats['created'] ?? 0 ),
									(int) ( $last_stats['updated'] ?? 0 ),
									(int) ( $last_stats['skipped'] ?? 0 )
								)
							);
							?>
						<?php endif; ?>
					<?php else : ?>
						<?php esc_html_e( 'No completed sync recorded yet.', 'optima-claims-sync' ); ?>
					<?php endif; ?>
				</span>
			</p>

			<hr />

			<form method="post" action="" id="optima-claims-sync-settings-form" class="optima-claims-sync-settings-form">
		<?php wp_nonce_field( 'optima_claims_sync_save_settings' ); ?>
		<input type="hidden" name="optima_claims_sync_save" value="1" />

		<h2 class="nav-tab-wrapper optima-claims-sync-tabs" role="tablist">
			<a href="#optima-claims-tab-connection-panel"
				id="optima-claims-tab-link-connection"
				class="nav-tab nav-tab-active"
				role="tab"
				aria-selected="true"
				aria-controls="optima-claims-tab-connection-panel">
				<?php esc_html_e( 'Connection Details', 'optima-claims-sync' ); ?>
			</a>
			<a href="#optima-claims-tab-field-import-panel"
				id="optima-claims-tab-link-field-import"
				class="nav-tab"
				role="tab"
				aria-selected="false"
				aria-controls="optima-claims-tab-field-import-panel">
				<?php esc_html_e( 'Field Import', 'optima-claims-sync' ); ?>
			</a>
			<a href="#optima-claims-tab-scheduled-panel"
				id="optima-claims-tab-link-scheduled"
				class="nav-tab"
				role="tab"
				aria-selected="false"
				aria-controls="optima-claims-tab-scheduled-panel">
				<?php esc_html_e( 'Scheduled Sync', 'optima-claims-sync' ); ?>
			</a>
		</h2>

		<div id="optima-claims-tab-connection-panel"
			class="optima-claims-sync-tab-panel is-active"
			role="tabpanel"
			aria-labelledby="optima-claims-tab-link-connection"
			tabindex="0">
			<h3 class="screen-reader-text"><?php esc_html_e( 'Connection Details', 'optima-claims-sync' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="api_base_url"><?php esc_html_e( 'API base URL', 'optima-claims-sync' ); ?></label></th>
					<td>
						<input name="api_base_url" id="api_base_url" type="url" class="regular-text code" value="<?php echo esc_attr( $s['api_base_url'] ); ?>" placeholder="https://api.example.com" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="endpoint_path"><?php esc_html_e( 'Endpoint path', 'optima-claims-sync' ); ?></label></th>
					<td>
						<input name="endpoint_path" id="endpoint_path" type="text" class="regular-text code" value="<?php echo esc_attr( $s['endpoint_path'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Appended to the base URL (e.g. /api/v1/claims). When the API paginates (Link or page headers), all pages are fetched and merged before sync.', 'optima-claims-sync' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="http_method"><?php esc_html_e( 'HTTP method', 'optima-claims-sync' ); ?></label></th>
					<td>
						<select name="http_method" id="http_method">
							<option value="GET" <?php selected( $s['http_method'], 'GET' ); ?>>GET</option>
							<option value="POST" <?php selected( $s['http_method'], 'POST' ); ?>>POST</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="auth_type"><?php esc_html_e( 'Authentication', 'optima-claims-sync' ); ?></label></th>
					<td>
						<select name="auth_type" id="auth_type">
							<option value="none" <?php selected( $s['auth_type'], 'none' ); ?>><?php esc_html_e( 'None', 'optima-claims-sync' ); ?></option>
							<option value="bearer" <?php selected( $s['auth_type'], 'bearer' ); ?>><?php esc_html_e( 'Bearer token', 'optima-claims-sync' ); ?></option>
							<option value="api_key_header" <?php selected( $s['auth_type'], 'api_key_header' ); ?>><?php esc_html_e( 'API key header', 'optima-claims-sync' ); ?></option>
							<option value="basic" <?php selected( $s['auth_type'], 'basic' ); ?>><?php esc_html_e( 'HTTP Basic', 'optima-claims-sync' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="api_key"><?php esc_html_e( 'API key / Bearer token', 'optima-claims-sync' ); ?></label></th>
					<td>
						<input name="api_key" id="api_key" type="password" class="regular-text code" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $api_key_stored ? '****************' : '' ); ?>" />
					</td>
				</tr>
			</table>

			<details class="optima-claims-field-group optima-claims-connection-advanced">
				<summary class="optima-claims-field-summary">
					<?php esc_html_e( 'Advanced configuration', 'optima-claims-sync' ); ?>
				</summary>
				<div class="optima-claims-field-nested">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="claims_import_scope"><?php esc_html_e( 'Claims to import', 'optima-claims-sync' ); ?></label></th>
							<td>
								<select name="claims_import_scope" id="claims_import_scope">
									<option value="all" <?php selected( $s['claims_import_scope'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All claims', 'optima-claims-sync' ); ?></option>
									<option value="open" <?php selected( $s['claims_import_scope'] ?? 'all', 'open' ); ?>><?php esc_html_e( 'Open claims only', 'optima-claims-sync' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="claims_sync_type_allowlist"><?php esc_html_e( 'Only import claim types', 'optima-claims-sync' ); ?></label></th>
							<td>
								<input name="claims_sync_type_allowlist" id="claims_sync_type_allowlist" type="text" class="regular-text" value="<?php echo esc_attr( $s['claims_sync_type_allowlist'] ?? '' ); ?>" placeholder="<?php echo esc_attr( 'Motor' ); ?>" />
								<p class="description"><?php esc_html_e( 'Comma-separated claim type labels (case-insensitive), matched after sync data is loaded. Leave blank to import every claim returned by the API. Rows with no matching type are skipped (not created or updated).', 'optima-claims-sync' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="claims_sync_type_json_path"><?php esc_html_e( 'Claim type JSON path (optional)', 'optima-claims-sync' ); ?></label></th>
							<td>
								<input name="claims_sync_type_json_path" id="claims_sync_type_json_path" type="text" class="regular-text code" value="<?php echo esc_attr( $s['claims_sync_type_json_path'] ?? '' ); ?>" placeholder="claim_type.name" />
								<p class="description"><?php esc_html_e( 'Dot path to the claim type on each claim object. Leave empty to auto-detect common fields (e.g. claimType, claim_type, type.name). Use Field Import discovery if you need the exact path.', 'optima-claims-sync' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="api_key_header_name"><?php esc_html_e( 'API key header name', 'optima-claims-sync' ); ?></label></th>
							<td>
								<input name="api_key_header_name" id="api_key_header_name" type="text" class="regular-text code" value="<?php echo esc_attr( $s['api_key_header_name'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="basic_auth_user"><?php esc_html_e( 'Basic auth username', 'optima-claims-sync' ); ?></label></th>
							<td>
								<input name="basic_auth_user" id="basic_auth_user" type="text" class="regular-text" value="<?php echo esc_attr( $s['basic_auth_user'] ); ?>" autocomplete="username" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="basic_auth_password"><?php esc_html_e( 'Basic auth password', 'optima-claims-sync' ); ?></label></th>
							<td>
								<input name="basic_auth_password" id="basic_auth_password" type="password" class="regular-text" value="" autocomplete="new-password" />
								<p class="description"><?php esc_html_e( 'Leave blank to keep the current password.', 'optima-claims-sync' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="post_body_json"><?php esc_html_e( 'POST body (JSON object)', 'optima-claims-sync' ); ?></label></th>
							<td>
								<textarea name="post_body_json" id="post_body_json" rows="4" class="large-text code"><?php echo esc_textarea( $s['post_body_json'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Used when HTTP method is POST. Sent as JSON or form fields depending on the option below.', 'optima-claims-sync' ); ?></p>
								<select name="post_body_mode" id="post_body_mode">
									<option value="json" <?php selected( $s['post_body_mode'], 'json' ); ?>><?php esc_html_e( 'application/json', 'optima-claims-sync' ); ?></option>
									<option value="form" <?php selected( $s['post_body_mode'], 'form' ); ?>><?php esc_html_e( 'application/x-www-form-urlencoded', 'optima-claims-sync' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="extra_headers_json"><?php esc_html_e( 'Extra headers (JSON object)', 'optima-claims-sync' ); ?></label></th>
							<td>
								<textarea name="extra_headers_json" id="extra_headers_json" rows="3" class="large-text code"><?php echo esc_textarea( $s['extra_headers_json'] ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="claims_array_path"><?php esc_html_e( 'Claims array path', 'optima-claims-sync' ); ?></label></th>
							<td>
								<input name="claims_array_path" id="claims_array_path" type="text" class="regular-text code" value="<?php echo esc_attr( $s['claims_array_path'] ); ?>" placeholder="data.items" />
								<p class="description"><?php esc_html_e( 'Dot path to the array of claims in the JSON response. Leave empty if the root is already an array.', 'optima-claims-sync' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="timeout_seconds"><?php esc_html_e( 'Timeout (seconds)', 'optima-claims-sync' ); ?></label></th>
							<td>
								<input name="timeout_seconds" id="timeout_seconds" type="number" min="5" max="120" value="<?php echo esc_attr( (string) $s['timeout_seconds'] ); ?>" />
							</td>
						</tr>
					</table>
				</div>
			</details>
		</div>

		<div id="optima-claims-tab-field-import-panel"
			class="optima-claims-sync-tab-panel"
			role="tabpanel"
			aria-labelledby="optima-claims-tab-link-field-import"
			tabindex="0"
			hidden>
			<h3 class="screen-reader-text"><?php esc_html_e( 'Field Import', 'optima-claims-sync' ); ?></h3>
			<p>
				<?php esc_html_e( 'Discover fields from the first claim returned by your API (using the saved connection settings). Then choose which dot-notation paths to store as post meta on each sync.', 'optima-claims-sync' ); ?>
			</p>
			<p style="margin-bottom: 1.5em;">
				<?php wp_nonce_field( 'optima_claims_discover_fields', '_wpdiscover_nonce' ); ?>
				<button type="submit" name="optima_claims_discover_fields" value="1" class="button button-secondary" id="optima-claims-discover-submit">
					<?php esc_html_e( 'Discover fields from API', 'optima-claims-sync' ); ?>
				</button>
			</p>

			<?php if ( $discovery_at !== '' ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: UTC datetime */
							__( 'Last field discovery (UTC): %s', 'optima-claims-sync' ),
							$discovery_at
						)
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( empty( $import_paths ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Import mode:', 'optima-claims-sync' ); ?></strong>
					<?php esc_html_e( 'Automatic — all default claim columns mapped until you select one or more fields below and save.', 'optima-claims-sync' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $discovery_paths !== array() ) : ?>
				<input type="hidden" name="optima_claims_field_import_present" value="1" />
				<p>
					<label>
						<input type="checkbox" id="optima-claims-toggle-all-import" />
						<?php esc_html_e( 'Select / deselect all', 'optima-claims-sync' ); ?>
					</label>
				</p>
				<p class="optima-claims-field-search-wrap">
					<label for="optima-claims-field-search" class="screen-reader-text">
						<?php esc_html_e( 'Filter discovered fields', 'optima-claims-sync' ); ?>
					</label>
					<input
						type="search"
						id="optima-claims-field-search"
						class="regular-text"
						autocomplete="off"
						placeholder="<?php esc_attr_e( 'Search path, type, sample, meta key…', 'optima-claims-sync' ); ?>"
					/>
					<span id="optima-claims-field-search-count" class="description optima-claims-field-search-count" aria-live="polite"></span>
				</p>
				<?php
				$path_tree = Optima_Claims_Field_Discovery::build_path_tree( $discovery_paths );
				Optima_Claims_Field_Discovery::render_field_tree_for_admin( $path_tree, $import_paths );
				?>
			<?php else : ?>
				<p><em><?php esc_html_e( 'No field list yet. Save your connection settings on the Connection Details tab, then use “Discover fields from API”.', 'optima-claims-sync' ); ?></em></p>
			<?php endif; ?>
		</div>

		<div id="optima-claims-tab-scheduled-panel"
			class="optima-claims-sync-tab-panel"
			role="tabpanel"
			aria-labelledby="optima-claims-tab-link-scheduled"
			tabindex="0"
			hidden>
			<h3 class="screen-reader-text"><?php esc_html_e( 'Scheduled Sync', 'optima-claims-sync' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable', 'optima-claims-sync' ); ?></th>
					<td>
						<label>
							<input name="sync_enabled" type="checkbox" value="1" <?php checked( ! empty( $s['sync_enabled'] ) ); ?> />
							<?php esc_html_e( 'Run sync on a schedule via WP-Cron', 'optima-claims-sync' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cron_interval"><?php esc_html_e( 'Interval', 'optima-claims-sync' ); ?></label></th>
					<td>
						<select name="cron_interval" id="cron_interval">
							<option value="hourly" <?php selected( $s['cron_interval'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'optima-claims-sync' ); ?></option>
							<option value="twicedaily" <?php selected( $s['cron_interval'], 'twicedaily' ); ?>><?php esc_html_e( 'Twice daily', 'optima-claims-sync' ); ?></option>
							<option value="daily" <?php selected( $s['cron_interval'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'optima-claims-sync' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

			<p class="submit">
				<?php submit_button( __( 'Save settings', 'optima-claims-sync' ), 'primary', 'submit', false ); ?>
			</p>
			</form>
		</div>

		<aside class="optima-claims-sync-settings-sidebar" aria-label="<?php esc_attr_e( 'Manual sync', 'optima-claims-sync' ); ?>">
			<div class="optima-claims-sync-sidebar-card postbox">
				<h2 class="hndle" style="cursor: default;"><span><?php esc_html_e( 'Manual sync', 'optima-claims-sync' ); ?></span></h2>
				<div class="inside">
					<p class="description" style="margin-top: 0;">
						<?php esc_html_e( 'Pull the latest claims from the API immediately using the saved connection settings.', 'optima-claims-sync' ); ?>
					</p>
					<form method="post" action="" id="optima-claims-run-sync-form">
						<?php wp_nonce_field( 'optima_claims_sync_run_now' ); ?>
						<input type="hidden" name="optima_claims_sync_action" value="run_now" />
						<?php submit_button( __( 'Run sync now', 'optima-claims-sync' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="" class="optima-claims-delete-closed-form" style="margin-top: 12px;" onsubmit="return window.confirm( <?php echo wp_json_encode( __( 'Permanently delete all claims whose synced status is closed? Child uploads on those claims will be removed. This cannot be undone.', 'optima-claims-sync' ) ); ?> );">
						<?php wp_nonce_field( 'optima_claims_sync_delete_closed' ); ?>
						<input type="hidden" name="optima_claims_sync_action" value="delete_closed_claims" />
						<?php submit_button( __( 'Delete closed claims', 'optima-claims-sync' ), 'delete', 'submit', false ); ?>
					</form>
				</div>
			</div>
		</aside>
	</div>

	<style>
		.optima-claims-sync-settings-layout {
			display: flex;
			flex-wrap: wrap;
			align-items: stretch;
			gap: 1.5rem 2rem;
			margin-top: 0.5rem;
		}
		.optima-claims-sync-settings-main {
			flex: 1 1 28rem;
			min-width: 0;
		}
		.optima-claims-sync-settings-sidebar {
			flex: 0 1 20rem;
			width: 100%;
			max-width: 22rem;
			display: flex;
			flex-direction: column;
		}
		.optima-claims-sync-sidebar-card {
			position: sticky;
			top: calc(var(--wp-admin--admin-bar--height, 32px) + 12px);
			align-self: flex-start;
			width: 100%;
			margin: 0;
			box-sizing: border-box;
			z-index: 2;
		}
		.optima-claims-sync-sidebar-card .hndle {
			margin: 0;
			padding: 10px 14px;
			line-height: 1.4;
			box-sizing: border-box;
			border-bottom: 1px solid #c3c4c7;
		}
		.optima-claims-sync-sidebar-card .inside {
			margin: 0;
			padding: 14px;
			box-sizing: border-box;
		}
		.optima-claims-sync-sidebar-card .inside .button {
			width: 100%;
			text-align: center;
			justify-content: center;
		}
		@media screen and (max-width: 782px) {
			.optima-claims-sync-settings-sidebar {
				flex: 1 1 100%;
				max-width: none;
			}
			.optima-claims-sync-sidebar-card {
				position: static;
			}
			.optima-claims-sync-sidebar-card .inside .button {
				width: auto;
			}
		}
		.optima-claims-sync-tab-panel { margin-top: 1em; }
		.optima-claims-sync-tab-panel[hidden] { display: none !important; }
		.optima-claims-field-tree {
			width: 100%;
			max-width: 100%;
			margin-top: 0.5rem;
			box-sizing: border-box;
		}
		.optima-claims-field-grid-header {
			display: grid;
			grid-template-columns: 2.25rem minmax(0, 2.4fr) 7rem minmax(0, 1.5fr) minmax(0, 1.5fr);
			gap: 0.5rem 0.75rem;
			align-items: center;
			padding: 0.4rem 0.5rem;
			font-weight: 600;
			border: 1px solid #c3c4c7;
			background: #f6f7f7;
			border-radius: 2px;
			margin-bottom: 0.5rem;
			box-sizing: border-box;
		}
		.optima-claims-field-leaf {
			display: grid;
			grid-template-columns: 2.25rem minmax(0, 2.4fr) 7rem minmax(0, 1.5fr) minmax(0, 1.5fr);
			gap: 0.5rem 0.75rem;
			align-items: start;
			padding: 0.4rem 0.5rem;
			border: 1px solid #dcdcde;
			background: #fff;
			border-radius: 2px;
			margin-bottom: 0.35rem;
			box-sizing: border-box;
		}
		.optima-claims-field-leaf-path,
		.optima-claims-field-leaf-meta,
		.optima-claims-field-leaf-type,
		.optima-claims-field-leaf-sample {
			min-width: 0;
		}
		.optima-claims-field-leaf-path code,
		.optima-claims-field-leaf-meta code {
			overflow-wrap: anywhere;
			word-break: break-word;
		}
		.optima-claims-field-tree > .optima-claims-field-group {
			margin-bottom: 0.35rem;
		}
		.optima-claims-field-group {
			width: 100%;
			max-width: 100%;
			box-sizing: border-box;
			border: 1px solid #c3c4c7;
			border-radius: 2px;
			background: #f6f7f7;
		}
		.optima-claims-field-group[open] {
			background: #e8f4fc;
			border-color: #72aee6;
			box-shadow: inset 3px 0 0 0 #2271b1;
		}
		.optima-claims-field-summary {
			cursor: pointer;
			padding: 0.45rem 0.6rem;
			font-weight: 600;
			list-style: none;
		}
		.optima-claims-field-group[open] > .optima-claims-field-summary {
			background: #d9ecf9;
			border-radius: 1px 1px 0 0;
		}
		.optima-claims-field-summary::-webkit-details-marker { display: none; }
		.optima-claims-field-group .optima-claims-field-nested {
			margin: 0;
			padding: 0.35rem 0.5rem 0.5rem 0.65rem;
			border-top: 1px solid #dcdcde;
			background: #fff;
		}
		.optima-claims-connection-advanced {
			margin-top: 1rem;
		}
		.optima-claims-connection-advanced .optima-claims-field-nested .form-table {
			margin-top: 0;
		}
		.optima-claims-field-nested .optima-claims-field-group {
			margin: 0.35rem 0 0.35rem 0.5rem;
		}
		.optima-claims-field-nested > .optima-claims-field-leaf:last-child {
			margin-bottom: 0;
		}
		.optima-claims-field-leaf-sample {
			word-break: break-word;
			overflow-wrap: anywhere;
		}
		.optima-claims-field-search-wrap {
			margin: 0.75rem 0 0.5rem;
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 0.35rem 0.75rem;
		}
		.optima-claims-field-search-wrap input[type="search"] {
			min-width: min(100%, 22rem);
			max-width: 100%;
		}
		.optima-claims-field-search-count:empty {
			display: none;
		}
		.optima-claims-field-filter-hidden {
			display: none !important;
		}
	</style>
	<script>
	(function () {
		var tabLinks = document.querySelectorAll('.optima-claims-sync-tabs .nav-tab');
		var panels = document.querySelectorAll('.optima-claims-sync-tab-panel');
		if (!tabLinks.length || !panels.length) return;

		function showTab(panelId, focusLink) {
			tabLinks.forEach(function (link) {
				var on = link.getAttribute('href') === '#' + panelId;
				link.classList.toggle('nav-tab-active', on);
				link.setAttribute('aria-selected', on ? 'true' : 'false');
			});
			panels.forEach(function (panel) {
				var on = panel.id === panelId;
				panel.classList.toggle('is-active', on);
				if (on) {
					panel.removeAttribute('hidden');
				} else {
					panel.setAttribute('hidden', 'hidden');
				}
			});
			if (focusLink && typeof focusLink.focus === 'function') {
				focusLink.focus();
			}
		}

		tabLinks.forEach(function (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				var id = link.getAttribute('href');
				if (id && id.charAt(0) === '#') {
					id = id.slice(1);
					showTab(id, link);
					if (window.history && window.history.replaceState) {
						window.history.replaceState(null, '', '#' + id);
					}
				}
			});
			link.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
					e.preventDefault();
					var i = Array.prototype.indexOf.call(tabLinks, link);
					var next = e.key === 'ArrowRight' ? tabLinks[i + 1] : tabLinks[i - 1];
					if (next) {
						next.click();
					}
				}
			});
		});

		var hash = window.location.hash.replace(/^#/, '');
		if (hash && document.getElementById(hash)) {
			showTab(hash, document.querySelector('.optima-claims-sync-tabs a[href="#' + hash + '"]'));
		}

		var master = document.getElementById('optima-claims-toggle-all-import');
		if (master) {
			master.addEventListener('change', function () {
				var on = master.checked;
				document.querySelectorAll('.optima-claims-import-path').forEach(function (el) {
					el.checked = on;
				});
			});
		}

		(function () {
			var tree = document.querySelector('.optima-claims-field-tree');
			if (!tree) return;

			function allGroups() {
				return tree.querySelectorAll('details.optima-claims-field-group');
			}

			function rootGroups() {
				var out = [];
				var n = tree.firstElementChild;
				while (n) {
					if (n.tagName === 'DETAILS' && n.classList.contains('optima-claims-field-group')) {
						out.push(n);
					}
					n = n.nextElementSibling;
				}
				return out;
			}

			function openExclusive(detail) {
				allGroups().forEach(function (d) {
					if (d !== detail) {
						d.removeAttribute('open');
					}
				});
				detail.setAttribute('open', '');
			}

			var roots = rootGroups();
			var preferred = null;
			if (roots.length) {
				var want = (tree.getAttribute('data-optima-default-accordion') || 'path').toLowerCase();
				roots.forEach(function (d) {
					var seg = (d.getAttribute('data-optima-segment') || '').toLowerCase();
					if (seg === want) {
						preferred = d;
					}
				});
				if (!preferred) {
					roots.forEach(function (d) {
						var seg = (d.getAttribute('data-optima-segment') || '').toLowerCase();
						if (seg === 'api' && !preferred) {
							preferred = d;
						}
					});
				}
				if (!preferred) {
					preferred = roots[0];
				}
			}

			function restoreDefaultAccordion() {
				if (!preferred || !roots.length) {
					return;
				}
				tree.classList.remove('optima-claims-field-tree--filtering');
				openExclusive(preferred);
			}

			tree.addEventListener('toggle', function (e) {
				if (tree.classList.contains('optima-claims-field-tree--filtering')) {
					return;
				}
				var el = e.target;
				if (el.tagName !== 'DETAILS' || !el.classList.contains('optima-claims-field-group')) {
					return;
				}
				if (!el.open) {
					return;
				}
				allGroups().forEach(function (d) {
					if (d !== el) {
						d.removeAttribute('open');
					}
				});
			});

			if (preferred) {
				openExclusive(preferred);
			}

			function depth(el) {
				var d = 0;
				var t = el;
				while (t && t !== tree) {
					d++;
					t = t.parentElement;
				}
				return d;
			}

			var searchInput = document.getElementById('optima-claims-field-search');
			var searchCount = document.getElementById('optima-claims-field-search-count');

			function updateSearchCount(visible, total) {
				if (!searchCount) {
					return;
				}
				if (!searchInput || !searchInput.value.trim()) {
					searchCount.textContent = '';
					return;
				}
				var s = window.optimaClaimsFieldSearchL10nShowing || '';
				searchCount.textContent = s
					.replace('%1$d', String(visible))
					.replace('%2$d', String(total));
			}

			function applyFieldFilter() {
				var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
				var leaves = tree.querySelectorAll('.optima-claims-field-leaf');
				var total = leaves.length;

				if (!q) {
					leaves.forEach(function (l) {
						l.classList.remove('optima-claims-field-filter-hidden');
					});
					allGroups().forEach(function (d) {
						d.classList.remove('optima-claims-field-filter-hidden');
					});
					if (searchCount) {
						searchCount.textContent = '';
					}
					restoreDefaultAccordion();
					return;
				}

				tree.classList.add('optima-claims-field-tree--filtering');
				var visible = 0;
				leaves.forEach(function (leaf) {
					var text = leaf.textContent.toLowerCase();
					if (text.indexOf(q) !== -1) {
						leaf.classList.remove('optima-claims-field-filter-hidden');
						visible++;
					} else {
						leaf.classList.add('optima-claims-field-filter-hidden');
					}
				});

				var groups = Array.prototype.slice.call(tree.querySelectorAll('details.optima-claims-field-group'));
				groups.sort(function (a, b) {
					return depth(b) - depth(a);
				});
				groups.forEach(function (g) {
					var nested = g.querySelector(':scope > .optima-claims-field-nested');
					if (!nested) {
						g.classList.add('optima-claims-field-filter-hidden');
						return;
					}
					var any = false;
					Array.prototype.forEach.call(nested.children, function (ch) {
						if (ch.classList && ch.classList.contains('optima-claims-field-leaf')) {
							if (!ch.classList.contains('optima-claims-field-filter-hidden')) {
								any = true;
							}
						} else if (ch.tagName === 'DETAILS' && ch.classList.contains('optima-claims-field-group')) {
							if (!ch.classList.contains('optima-claims-field-filter-hidden')) {
								any = true;
							}
						}
					});
					if (any) {
						g.classList.remove('optima-claims-field-filter-hidden');
					} else {
						g.classList.add('optima-claims-field-filter-hidden');
					}
				});

				tree.querySelectorAll('.optima-claims-field-leaf:not(.optima-claims-field-filter-hidden)').forEach(function (leaf) {
					var p = leaf.parentElement;
					while (p && p !== tree) {
						if (p.tagName === 'DETAILS' && p.classList.contains('optima-claims-field-group')) {
							p.setAttribute('open', '');
						}
						p = p.parentElement;
					}
				});

				updateSearchCount(visible, total);
			}

			if (searchInput) {
				window.optimaClaimsFieldSearchL10nShowing =
					<?php echo wp_json_encode( __( 'Showing %1$d of %2$d fields.', 'optima-claims-sync' ) ); ?>;

				var debounce = null;
				searchInput.addEventListener('input', function () {
					clearTimeout(debounce);
					debounce = setTimeout(applyFieldFilter, 100);
				});
				searchInput.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') {
						searchInput.value = '';
						applyFieldFilter();
					}
				});
			}
		})();
	})();
	</script>
</div>
