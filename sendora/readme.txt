=== Sendora ===
Contributors: sendora
Tags: crm, automation, messaging, woocommerce, contact-form-7
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to Sendora with an API key: capture leads, embed the official chat widget, and sync WooCommerce orders.

== Description ==

Sendora connects your WordPress site to your Sendora account using a server-side API key (`sk_…`). Data flows **from WordPress to Sendora only** in this version—no inbound webhooks or OAuth.

**Features (v0.2)**

* **SaaS admin UI** — Dashboard, Conexão, Formulários, Widget, WooCommerce, Automações (preview), Configurações, Logs.
* **Connection** — Masked API key, connection test, disconnect, workspace status from `/api/me`.
* **Lead capture** — Shortcode `[sendora_form]` upserts contacts and can trigger a flow.
* **Contact Form 7** — Map name, phone, and email tags (loaded reliably on `plugins_loaded`).
* **Chat widget** — Official Sendora embed with page targeting.
* **WooCommerce** — Order created / paid / cancelled sync with retry.
* **Event bus** — Internal `Sendora_Events` foundation for automations.
* **Local logs** — Admin log table (API keys redacted).

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

== Privacy ==

This plugin sends contact and order data to the Sendora API only after an administrator saves an API key and enables the relevant features.

* **API key** — Stored in the WordPress database (`sendora_settings`) with autoload disabled. Never printed in full in the admin UI (last four characters only) and never exposed to visitors.
* **Forms / Contact Form 7 / WooCommerce** — Name, phone, email, and related order metadata are posted over HTTPS to the configured API base URL (default `https://api.sendora.com.br`).
* **Chat widget** — Off by default. When enabled, the official Sendora embed script is loaded from your API base URL (`/public/widget/embed`) so visitors can chat. This is the Sendora SaaS widget, not a third-party CDN.
* **Logs** — Local table `{prefix}sendora_logs` stores sync status messages. API keys are redacted. Logs and settings are removed on uninstall.

No analytics or telemetry is sent to Sendora without an administrator configuring the connection.

== Changelog ==

= 0.1.0 =
* Initial release: API connection, `[sendora_form]`, Contact Form 7 mapping, official widget embed, WooCommerce order sync, local admin logs.
* Hardened for WordPress.org review: prepared SQL, output escaping, nonces/capabilities, HTTPS-only API calls, uninstall cleanup, HPOS declaration.

== Upgrade Notice ==

= 0.1.0 =
First public release. Configure your API key before enabling Woo or CF7 sync on production sites.
