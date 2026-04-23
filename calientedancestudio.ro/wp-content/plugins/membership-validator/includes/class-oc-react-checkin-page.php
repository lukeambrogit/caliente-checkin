<?php
/**
 * React Check-in App — WordPress mount point
 *
 * Registers the [oc_checkin_app] shortcode, enqueues the CRA build assets from
 * assets/react-checkin/ and injects the API configuration so the React app can
 * talk to the caliente/v1 REST namespace without hard-coding credentials.
 *
 * Usage: place [oc_checkin_app] on any WordPress page (e.g. /check-in).
 *
 * @package MembershipValidatorCore
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OC_React_Checkin_Page {

	/** Filesystem path to the React CRA build directory. */
	private string $build_dir;

	/** Public URL for the React CRA build directory. */
	private string $build_url;

	/** Shortcode tag. */
	private string $shortcode = 'oc_checkin_app';

	public function __construct() {
		$this->build_dir = OC_PLUGIN_DIR . 'assets/react-checkin';
		$this->build_url = OC_PLUGIN_URL . 'assets/react-checkin';

		add_shortcode( $this->shortcode, [ $this, 'render_shortcode' ] );
		add_shortcode( 'oc_checkin_dashboard', [ $this, 'render_dashboard_shortcode' ] );
	}

	public function render_dashboard_shortcode(): string {
		return $this->render_shortcode( [], 'dashboard' );
	}

	// -------------------------------------------------------------------------
	// Shortcode callback
	// -------------------------------------------------------------------------

	public function render_shortcode( $atts = [], string $mode = 'checkin' ): string {
		if ( ! $this->build_exists() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div style="padding:16px;border:2px dashed #c00;color:#c00;font-family:monospace;">'
					. '<strong>[oc_checkin_app]</strong> — React build not found.<br>'
					. 'Run <code>npm run build</code> inside <code>caliente_web_ui/</code>, then copy the <code>build/</code> '
					. 'contents into <code>wp-content/plugins/membership-validator/assets/react-checkin/</code>.'
					. '</div>';
			}
			return '';
		}

		$this->enqueue_assets();

		// Inject runtime config so the React app can reach the WP REST API.
		$upload_dir = wp_upload_dir();
		$ws_url     = (string) get_option( 'oc_ws_server_url', '' );
		$config = [
			'apiBaseUrl'     => rest_url( 'caliente/v1' ),
			'deviceId'       => (string) get_option( 'oc_checkin_device_id', 'studio-checkin-1' ),
			'apiToken'       => (string) get_option( 'oc_checkin_api_token', '' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'uploadsBaseUrl' => $upload_dir['baseurl'],
			'mode'           => in_array( $mode, [ 'checkin', 'dashboard' ], true ) ? $mode : 'checkin',
			'wsUrl'          => $ws_url !== '' ? rtrim( $ws_url, '/' ) : '',
		];

		wp_add_inline_script(
			'oc-react-checkin-main',
			'window.OC_CHECKIN_CONFIG = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		// CRA expects <div id="root"> in the page.
		return '<div id="root"></div>';
	}

	// -------------------------------------------------------------------------
	// Asset enqueueing
	// -------------------------------------------------------------------------

	private function build_exists(): bool {
		return file_exists( $this->build_dir . '/asset-manifest.json' );
	}

	private function enqueue_assets(): void {
		$manifest_path = $this->build_dir . '/asset-manifest.json';
		$manifest      = json_decode( (string) file_get_contents( $manifest_path ), true );

		if ( ! is_array( $manifest ) ) {
			return;
		}

		// CRA v5 puts all chunk paths under manifest["files"].
		$files = $manifest['files'] ?? $manifest;

		// --- CSS ---
		$css_key = $this->find_manifest_key( $files, 'main', '.css' );
		if ( $css_key ) {
			wp_enqueue_style(
				'oc-react-checkin-main',
				$this->asset_url( $files[ $css_key ] ),
				[],
				$this->asset_ver( $files[ $css_key ] )
			);
		}

		// --- Runtime chunk (must load before main JS) ---
		$runtime_key = $this->find_manifest_key( $files, 'runtime', '.js' );
		if ( $runtime_key ) {
			wp_enqueue_script(
				'oc-react-checkin-runtime',
				$this->asset_url( $files[ $runtime_key ] ),
				[],
				$this->asset_ver( $files[ $runtime_key ] ),
				true
			);
		}

		// --- Main JS ---
		$js_key = $this->find_manifest_key( $files, 'main', '.js' );
		if ( $js_key ) {
			$deps = $runtime_key ? [ 'oc-react-checkin-runtime' ] : [];
			wp_enqueue_script(
				'oc-react-checkin-main',
				$this->asset_url( $files[ $js_key ] ),
				$deps,
				$this->asset_ver( $files[ $js_key ] ),
				true
			);
		}
	}

	/**
	 * Find a key in the manifest files array that contains both $needle and $ext.
	 *
	 * @param array  $files   Associative array from asset-manifest.json.
	 * @param string $needle  Partial key name to look for (e.g. "main", "runtime").
	 * @param string $ext     File extension including dot (e.g. ".js", ".css").
	 * @return string|null
	 */
	private function find_manifest_key( array $files, string $needle, string $ext ): ?string {
		foreach ( array_keys( $files ) as $key ) {
			if ( strpos( $key, $needle ) !== false && substr( $key, - strlen( $ext ) ) === $ext ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Convert a manifest path to a full URL.
	 *
	 * When PUBLIC_URL is set during the CRA build, manifest paths already contain
	 * the full plugin-relative prefix (e.g. /wp-content/plugins/.../static/js/main.js).
	 * Strip that prefix before appending to build_url to avoid doubling the path.
	 */
	private function asset_url( string $manifest_path ): string {
		$plugin_path = (string) parse_url( $this->build_url, PHP_URL_PATH );
		if ( $plugin_path !== '' && strpos( $manifest_path, $plugin_path ) === 0 ) {
			$manifest_path = substr( $manifest_path, strlen( $plugin_path ) );
		}
		return $this->build_url . '/' . ltrim( $manifest_path, '/' );
	}

	/**
	 * Return a version string for cache busting — use filemtime if the file exists on disk.
	 */
	private function asset_ver( string $manifest_path ): string {
		$abs = $this->build_dir . '/' . ltrim( $manifest_path, '/' );
		return file_exists( $abs ) ? (string) filemtime( $abs ) : OC_PLUGIN_VERSION;
	}
}
