<?php
/**
 * Check-in App settings page template
 *
 * Available variables (set by OC_Dashboard::checkin_settings_page()):
 *   $checkin_notice       array{type,message}|null
 *   $new_plain_key        array{device_id,key}|null  — shown ONCE after generation
 *   $oc_checkin_device_id string
 *   $oc_checkin_api_token string
 *   $oc_api_devices       array  — all entries in oc_membership_api_devices
 *
 * @package MembershipValidatorCore
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checkin_notice      = $checkin_notice ?? null;
$new_plain_key       = $new_plain_key ?? null;
$oc_checkin_device_id = $oc_checkin_device_id ?? 'studio-checkin-1';
$oc_checkin_api_token = $oc_checkin_api_token ?? '';
$oc_api_devices      = $oc_api_devices ?? [];
$oc_ws_server_url    = $oc_ws_server_url ?? '';
$oc_ws_server_secret = $oc_ws_server_secret ?? '';
?>
<div class="wrap">

	<h1>
		<span class="dashicons dashicons-smartphone" style="font-size:30px;height:30px;margin-right:8px;vertical-align:middle;"></span>
		<?php esc_html_e( 'Check-in App', OC_TEXT_DOMAIN ); ?>
	</h1>

	<?php if ( is_array( $checkin_notice ) && ! empty( $checkin_notice['message'] ) ) :
		$ntype = in_array( $checkin_notice['type'] ?? '', [ 'success', 'error', 'warning', 'info' ], true )
			? $checkin_notice['type'] : 'info';
	?>
	<div class="notice notice-<?php echo esc_attr( $ntype ); ?> is-dismissible">
		<p><?php echo esc_html( (string) $checkin_notice['message'] ); ?></p>
	</div>
	<?php endif; ?>

	<?php if ( is_array( $new_plain_key ) && ! empty( $new_plain_key['key'] ) ) : ?>
	<div style="background:#fff3cd;border:2px solid #ffc107;border-radius:6px;padding:16px 20px;margin:16px 0;">
		<strong style="display:block;margin-bottom:8px;font-size:14px;">
			⚠️ <?php esc_html_e( 'Save this API key — it will NOT be shown again!', OC_TEXT_DOMAIN ); ?>
		</strong>
		<p style="margin:0 0 8px;">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s = device ID */
					__( 'Device: <code>%s</code>', OC_TEXT_DOMAIN ),
					esc_html( $new_plain_key['device_id'] )
				),
				[ 'code' => [] ]
			);
			?>
		</p>
		<div style="display:flex;align-items:center;gap:8px;">
			<input type="text"
				id="oc-new-api-key"
				value="<?php echo esc_attr( $new_plain_key['key'] ); ?>"
				readonly
				style="font-family:monospace;font-size:13px;width:480px;max-width:100%;"
			>
			<button type="button"
				onclick="
					var el = document.getElementById('oc-new-api-key');
					el.select(); el.setSelectionRange(0, 99999);
					navigator.clipboard ? navigator.clipboard.writeText(el.value) : document.execCommand('copy');
					this.textContent = '✅ Copied!';
					setTimeout(function(){ this.textContent = 'Copy'; }.bind(this), 2500);
				"
				class="button">Copy</button>
		</div>
	</div>
	<?php endif; ?>

	<hr>

	<!-- ================================================================ -->
	<!-- SECTION 1: Check-in App Config                                    -->
	<!-- ================================================================ -->
	<h2><?php esc_html_e( 'Check-in Device Config', OC_TEXT_DOMAIN ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'The Device ID and API token are injected into the React app when the [oc_checkin_app] shortcode renders. They are also used during local dev via the .env file.', OC_TEXT_DOMAIN ); ?>
	</p>

	<form method="post" style="max-width:600px;">
		<?php wp_nonce_field( 'oc_checkin_settings', 'oc_checkin_nonce' ); ?>
		<input type="hidden" name="oc_checkin_action" value="save_config">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="oc_checkin_device_id"><?php esc_html_e( 'Device ID', OC_TEXT_DOMAIN ); ?></label>
				</th>
				<td>
					<input type="text"
						id="oc_checkin_device_id"
						name="oc_checkin_device_id"
						value="<?php echo esc_attr( $oc_checkin_device_id ); ?>"
						class="regular-text"
					>
					<p class="description"><?php esc_html_e( 'Must match a registered device below. Default: studio-checkin-1', OC_TEXT_DOMAIN ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Current API Token', OC_TEXT_DOMAIN ); ?></th>
				<td>
					<?php if ( $oc_checkin_api_token !== '' ) : ?>
						<code><?php echo esc_html( substr( $oc_checkin_api_token, 0, 8 ) . '••••••••••••••••••••••••' ); ?></code>
						<span class="description"> (<?php esc_html_e( 'token is set', OC_TEXT_DOMAIN ); ?>)</span>
					<?php else : ?>
						<em class="description"><?php esc_html_e( 'Not set — generate a key for the check-in device below.', OC_TEXT_DOMAIN ); ?></em>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Device ID', OC_TEXT_DOMAIN ), 'primary', 'submit', false ); ?>
	</form>

	<!-- Regenerate token for the current check-in device -->
	<form method="post" style="display:inline-block;margin-top:8px;">
		<?php wp_nonce_field( 'oc_checkin_settings', 'oc_checkin_nonce' ); ?>
		<input type="hidden" name="oc_checkin_action" value="regenerate_token">
		<?php submit_button(
			__( '🔑 Generate / Regenerate Key for Check-in Device', OC_TEXT_DOMAIN ),
			'secondary',
			'submit',
			false,
			[ 'onclick' => "return confirm('" . esc_js( __( 'This will replace the existing API key. The old key will stop working immediately. Continue?', OC_TEXT_DOMAIN ) ) . "');" ]
		); ?>
	</form>

	<hr>

	<!-- ================================================================ -->
	<!-- SECTION 2: API Devices                                            -->
	<!-- ================================================================ -->
	<h2><?php esc_html_e( 'Registered API Devices', OC_TEXT_DOMAIN ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Each device has its own hashed API key. Only the hash is stored — you must copy the plain key at the moment it is generated.', OC_TEXT_DOMAIN ); ?>
	</p>

	<?php if ( ! empty( $oc_api_devices ) ) : ?>
	<table class="wp-list-table widefat fixed striped" style="max-width:760px;margin-bottom:20px;">
		<thead>
			<tr>
				<th style="width:220px;"><?php esc_html_e( 'Device ID', OC_TEXT_DOMAIN ); ?></th>
				<th><?php esc_html_e( 'Status', OC_TEXT_DOMAIN ); ?></th>
				<th><?php esc_html_e( 'Created', OC_TEXT_DOMAIN ); ?></th>
				<th><?php esc_html_e( 'Last Used', OC_TEXT_DOMAIN ); ?></th>
				<th style="width:100px;"><?php esc_html_e( 'Actions', OC_TEXT_DOMAIN ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $oc_api_devices as $dev_id => $dev_cfg ) :
			$active      = ! isset( $dev_cfg['active'] ) || (bool) $dev_cfg['active'];
			$created_at  = esc_html( $dev_cfg['created_at'] ?? '—' );
			$last_used   = esc_html( $dev_cfg['last_used_at'] ?? '—' );
			$is_checkin  = ( $dev_id === $oc_checkin_device_id );
		?>
			<tr>
				<td>
					<code><?php echo esc_html( (string) $dev_id ); ?></code>
					<?php if ( $is_checkin ) : ?>
						<span style="background:#d63638;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-left:4px;">
							<?php esc_html_e( 'check-in', OC_TEXT_DOMAIN ); ?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<?php echo $active
						? '<span style="color:green;">✔ ' . esc_html__( 'Active', OC_TEXT_DOMAIN ) . '</span>'
						: '<span style="color:#888;">✖ ' . esc_html__( 'Disabled', OC_TEXT_DOMAIN ) . '</span>';
					?>
				</td>
				<td><?php echo $created_at; ?></td>
				<td><?php echo $last_used; ?></td>
				<td>
					<form method="post" style="display:inline;">
						<?php wp_nonce_field( 'oc_checkin_settings', 'oc_checkin_nonce' ); ?>
						<input type="hidden" name="oc_checkin_action" value="delete_device">
						<input type="hidden" name="oc_delete_device_id" value="<?php echo esc_attr( (string) $dev_id ); ?>">
						<button type="submit"
							class="button button-small button-link-delete"
							onclick="return confirm('<?php echo esc_js( sprintf( __( "Delete device '%s'?", OC_TEXT_DOMAIN ), esc_attr( (string) $dev_id ) ) ); ?>');">
							<?php esc_html_e( 'Delete', OC_TEXT_DOMAIN ); ?>
						</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<p><?php esc_html_e( 'No devices registered yet.', OC_TEXT_DOMAIN ); ?></p>
	<?php endif; ?>

	<!-- Add new device -->
	<h3><?php esc_html_e( 'Add New Device', OC_TEXT_DOMAIN ); ?></h3>
	<form method="post" style="max-width:500px;">
		<?php wp_nonce_field( 'oc_checkin_settings', 'oc_checkin_nonce' ); ?>
		<input type="hidden" name="oc_checkin_action" value="add_device">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="oc_new_device_id"><?php esc_html_e( 'Device ID', OC_TEXT_DOMAIN ); ?></label>
				</th>
				<td>
					<input type="text"
						id="oc_new_device_id"
						name="oc_new_device_id"
						placeholder="studio-checkin-1"
						class="regular-text"
						value="<?php echo esc_attr( $oc_checkin_device_id ); ?>"
					>
					<p class="description">
						<?php esc_html_e( 'A random API key will be generated and stored as a SHA-256 hash. The plain key is shown once — copy it immediately.', OC_TEXT_DOMAIN ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( '🔑 Add Device & Generate Key', OC_TEXT_DOMAIN ), 'primary', 'submit', false ); ?>
	</form>

	<hr>

	<!-- ================================================================ -->
	<!-- SECTION: WebSocket Real-time Server                               -->
	<!-- ================================================================ -->
	<h2>⚡ <?php esc_html_e( 'Real-time WebSocket Server', OC_TEXT_DOMAIN ); ?></h2>
	<p style="max-width:660px;color:#555;">
		<?php esc_html_e( 'Configure the Node.js WebSocket server URL so WordPress pushes live check-in events to the dashboard.', OC_TEXT_DOMAIN ); ?>
		<?php echo wp_kses( __( 'Start the server with <code>node server.js</code> inside <code>caliente_ws_server/</code>.', OC_TEXT_DOMAIN ), [ 'code' => [] ] ); ?>
	</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'oc_checkin_settings', 'oc_checkin_nonce' ); ?>
		<input type="hidden" name="oc_checkin_action" value="save_ws_config">
		<table class="form-table" style="max-width:660px;">
			<tr>
				<th scope="row"><label for="oc_ws_server_url"><?php esc_html_e( 'WS Server URL', OC_TEXT_DOMAIN ); ?></label></th>
				<td>
					<input type="url" id="oc_ws_server_url" name="oc_ws_server_url"
						value="<?php echo esc_attr( $oc_ws_server_url ); ?>"
						placeholder="http://localhost:3001"
						class="regular-text">
					<p class="description"><?php esc_html_e( 'HTTP URL of the Node WS server. WordPress will POST to {url}/broadcast after each scan.', OC_TEXT_DOMAIN ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="oc_ws_server_secret"><?php esc_html_e( 'Shared Secret', OC_TEXT_DOMAIN ); ?></label></th>
				<td>
					<input type="text" id="oc_ws_server_secret" name="oc_ws_server_secret"
						value="<?php echo esc_attr( $oc_ws_server_secret ); ?>"
						placeholder="<?php echo $oc_ws_server_secret !== '' ? '(set — leave blank to keep)' : 'e.g. change_this_to_random_secret'; ?>"
						class="regular-text" autocomplete="off">
					<p class="description"><?php esc_html_e( 'Must match WS_SECRET in caliente_ws_server/.env. Leave blank to keep current value.', OC_TEXT_DOMAIN ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( '💾 Save WebSocket Settings', OC_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
		<?php if ( $oc_ws_server_url ) : ?>
			<a href="<?php echo esc_url( rtrim( $oc_ws_server_url, '/' ) . '/health' ); ?>" target="_blank" class="button" style="margin-left:8px;">
				🔍 <?php esc_html_e( 'Test Connection', OC_TEXT_DOMAIN ); ?>
			</a>
		<?php endif; ?>
	</form>

	<hr>

	<!-- ================================================================ -->
	<!-- SECTION 3: How to use                                             -->
	<!-- ================================================================ -->
	<h2><?php esc_html_e( 'How to Complete the Setup', OC_TEXT_DOMAIN ); ?></h2>
	<ol style="max-width:660px;line-height:1.9;">
		<li><?php esc_html_e( 'Click "Generate / Regenerate Key for Check-in Device" (or "Add Device") above.', OC_TEXT_DOMAIN ); ?></li>
		<li><?php esc_html_e( 'Copy the plain API key shown in the yellow box — it is not recoverable later.', OC_TEXT_DOMAIN ); ?></li>
		<li>
			<?php echo wp_kses(
				__( 'Go to <strong>Pages → Add New</strong>, set the title to <strong>Check-in</strong>, paste <code>[oc_checkin_app]</code> into the content, and publish.', OC_TEXT_DOMAIN ),
				[ 'strong' => [], 'code' => [] ]
			); ?>
		</li>
		<li>
			<?php echo wp_kses(
				__( 'Visit <strong>/check-in</strong> — the React app will load, talk to <code>caliente/v1/validate-qr</code>, and authenticate with the stored token automatically.', OC_TEXT_DOMAIN ),
				[ 'strong' => [], 'code' => [] ]
			); ?>
		</li>
		<li>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s = file path */
					__( 'For local dev outside WordPress, paste the plain key into <code>%s</code> as <code>REACT_APP_API_TOKEN=&lt;key&gt;</code>.', OC_TEXT_DOMAIN ),
					'caliente_web_ui/.env'
				),
				[ 'code' => [] ]
			);
			?>
		</li>
	</ol>

</div>
