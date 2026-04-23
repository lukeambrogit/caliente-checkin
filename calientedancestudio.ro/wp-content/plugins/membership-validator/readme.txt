=== Membership Validator Core ===
Contributors: remuslazar
Tags: membership, woocommerce, qr-code, validation, dance-studio
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce

Modular WordPress plugin for membership validation and access management for dance studios.

== Description ==

Membership Validator Core provides a complete membership management system built on top of WooCommerce. It includes:

* QR code generation and scanning for membership validation
* WooCommerce My Account integration with a dedicated membership dashboard
* Admin table for managing all members, sessions, and renewals
* REST API for mobile app integration (Android/iOS)
* Schedule manager for dance courses
* Pool product manager for bundled course packages
* API key authentication for mobile app access
* CSV export of validation logs

Operational notes:
* QR validation uses the shared membership check-in flow across app and admin paths.
* Schedule validation remains enforced during membership validation.
* Local member photo upload/storage is not part of this plugin release.

Date handling note:
* Frontend/admin UI date display follows WordPress settings (`Settings → General` for date/time format).
* Database persistence remains unchanged and uses internal normalized formats for storage (`Y-m-d` / `Y-m-d H:i:s`).

== Installation ==

1. Upload the `membership-validator` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Ensure WooCommerce is installed and active.
4. Go to **Membership Validator → Dashboard** and activate the desired add-ons.
5. Configure API keys under **Membership Validator → Settings** for mobile app access.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. Memberships are created automatically when a WooCommerce order is completed.

= How does QR code validation work? =

Each active membership has a unique QR code. Staff scans the QR code via the mobile app, which calls the REST API to validate and consume a session.

= Are member photos stored by this plugin? =

No. This plugin release does not store local member face photos.


= Can clients see their own membership? =

Yes. After logging in, clients can visit **My Account → Abonamente** to view their active membership, remaining sessions, and QR code.

== Changelog ==

= 2.0.0 =
* Complete rewrite with modular add-on architecture
* REST API for mobile app integration
* QR code system with session tracking
* Pool product manager for bundled course packages
* Schedule manager with course hour configuration
* WooCommerce HPOS compatibility declared
* Removed legacy local member photo upload flow
* Unified QR validation through the shared check-in engine
* Removed plugin-level request rate limiting

= 1.0.0 =
* Initial release
