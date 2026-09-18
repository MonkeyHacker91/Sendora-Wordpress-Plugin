=== Sendora ===
Contributors: sendora
Tags: crm, automation, messaging, whatsapp, woocommerce, contact-form-7
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to Sendora with an API key: capture leads, embed the official chat widget, and sync WooCommerce orders.

== Description ==

Sendora connects your WordPress site to your Sendora account using a server-side API key (`sk_…`). Data flows **from WordPress to Sendora only** in this version—no inbound webhooks or OAuth.

**Features (v1)**

* **Connection** — HTTPS API base URL, masked API key storage, and an admin connection test.
* **Lead capture** — Shortcode `[sendora_form]` upserts contacts and can trigger a default flow.
* **Contact Form 7** — Map each form’s name, phone, and email tags to Sendora fields after mail is sent.
* **Chat widget** — Enable the official Sendora public embed with your Widget ID.
* **WooCommerce** — On order created, payment complete, or cancellation (configurable): upsert billing contact, trigger flows, or send messages.
* **Local logs** — Admin log table for form, CF7, and Woo sync attempts (no full API key stored).

**Recommended API key scopes**

Create a key in the Sendora app with at least:

* `contacts:write` — upsert contacts from forms and orders
* `flows:write` — trigger automation flows
* `messages:write` — only if you use paid-order “send message” actions

WooCommerce and Contact Form 7 are optional; their settings appear when those plugins are active.

== Installation ==

1. Upload the `sendora` folder to `/wp-content/plugins/` or install the plugin through the WordPress plugins screen.
2. Activate **Sendora** through the **Plugins** menu.
3. Open **Sendora** in the admin sidebar.
4. Set **API base URL** (default `https://api.sendora.com.br`) and paste your **API key** (`sk_…`).
5. Click **Save Changes**, then **Test connection**. A green result means the key and scopes are valid.
6. Optionally set a **Default flow**, **Default country code** for phone normalization (default `55`), enable the **chat widget**, map **Contact Form 7** forms, and configure **WooCommerce** actions.
7. Add `[sendora_form]` to any page or post for a simple lead form.

== Frequently Asked Questions ==

= Where do I get an API key? =

In your Sendora account, create a Public API key beginning with `sk_`. Use HTTPS only.

= Why does the connection test fail? =

Check that the base URL uses HTTPS, the key is correct, and the key includes `contacts:write` (and `flows:write` / `messages:write` if you use those features). The test surfaces HTTP errors from the API.

= Is my API key exposed to visitors? =

No. The key is stored in WordPress options, never printed in full after save (only the last four characters are shown), and never sent to the browser in JavaScript.

= Does this plugin receive webhooks from Sendora? =

No. v1 is outbound only: WordPress calls Sendora’s REST API.

= Do I need WooCommerce or Contact Form 7? =

No. The shortcode and widget work without them. CF7 mapping and Woo order sync load only when those plugins are active.

= How are phone numbers formatted? =

Digits are normalized with your configured default country code (e.g. Brazil `55`) before upsert.

== Screenshots ==

1. Sendora settings: connection, forms, widget, and WooCommerce options.

== Changelog ==

= 0.1.0 =
* Initial release: API connection, `[sendora_form]`, Contact Form 7 mapping, official widget embed, WooCommerce order sync, local admin logs.

== Upgrade Notice ==

= 0.1.0 =
First public release. Configure your API key before enabling Woo or CF7 sync on production sites.
