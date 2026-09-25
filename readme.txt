=== CASABLANCA Booking Engine ===
Contributors: mhairer
Tags: CASABLANCA, booking engine, hotel, availability
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: AGPL-3.0-only
License URI: https://www.gnu.org/licenses/agpl-3.0.html

Integrates the CASABLANCA Booking Engine v2 into WordPress with background sync, SSR widgets, and PCI-safe redirect handover.

== Description ==

Author: Martin Hairer (martin.hairer@casablanca.at)

This plugin synchronises room types, rates, and availability from the CASABLANCA IBE v2 API and renders SEO-friendly booking widgets. Guests complete booking and payment on the CASABLANCA booking engine (PCI-safe redirect).

**Requirements**

The plugin is free to install, but to sync data and show working booking widgets you need access to the **CASABLANCA Booking Engine** with a valid **API key**. Enter your Tenant ID and API key under **CASABLANCA Booking** in the admin. Without that backend, the plugin cannot reach live availability or complete the booking handover.

**Features**

* Background ARI sync with audit log
* Daily WP-Cron task (staggered on first save)
* Gutenberg blocks and matching shortcodes (search, calendar, rooms, packages, detail pages, price teaser)
* Overview → detail links via pretty URLs `/{detail-page}/{slug}/`
* Live calendar via same-origin REST proxy
* Backend configuration with encrypted API key
* Theme and per-site CSS overrides
* Environment mode via `CASABLANCA_BOOKING_ENV` in wp-config.php

**License note:** The plugin source is free software under **AGPL-3.0-only**; you may use, modify, and share it under that license. That is separate from the CASABLANCA Booking Engine service and API key needed for live operation.

== Installation ==

1. Upload the plugin zip via **Plugins → Add New → Upload Plugin**, or copy the `casablanca-booking` folder to `wp-content/plugins/`.
2. Activate the plugin.
3. Open **Settings → Permalinks** and click **Save** once (registers detail-page rewrite rules).
4. Open **CASABLANCA Booking** in the admin menu.
5. Enter Tenant ID and API key, then save.
6. Insert blocks or shortcodes on your pages.

== Frequently Asked Questions ==

= Do I need CASABLANCA Booking Engine to use this plugin? =

The plugin itself is free under AGPL-3.0-only. To work properly on a live site, you need a **CASABLANCA Booking Engine** account with an **API key** (Tenant ID + API key in the plugin settings). The plugin connects to the CASABLANCA IBE v2 API; without that backend, sync and widgets will not show real availability or booking links.

= How do I run sync manually? =

Use **Sync now** in the admin module, or `wp casablanca-booking sync` when WP-CLI is available.

= How do I use staging API hosts? =

Add to `wp-config.php`:

`define('CASABLANCA_BOOKING_ENV', 'staging');`

= How do I link room/package cards to a detail page? =

1. Create a detail page and add the **Room details** or **Package details** block.
2. On the overview block, set **Card link** to **Details** and select that page.
3. Cards link to `/{detail-page}/{slug}/`. No fallback id is required on the live URL.
4. If detail pages show “not found”, save **Settings → Permalinks** once.

== Shortcodes ==

* `[casablanca_search_bar]`
* `[casablanca_calendar]`
* `[casablanca_room_types]`
* `[casablanca_packages]`
* `[casablanca_room_detail]`
* `[casablanca_package_detail]`
* `[casablanca_price_teaser]`

== Changelog ==

= 1.0.0 =
* Initial WordPress plugin.
* Room/package detail URLs resolve the synced slug from the last path segment.
