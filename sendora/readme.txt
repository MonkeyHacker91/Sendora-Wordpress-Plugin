=== Sendora ===
Contributors: sendora
Tags: crm, messaging, woocommerce, contact-form-7, marketing-automation
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.8.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to Sendora: capture leads, sync WooCommerce, and send messages via QR or Meta Cloud API.

== Description ==

**Sendora** is the official WordPress connector for the [Sendora](https://sendora.com.br) platform (CRM, messaging, and automations).

After you paste a server-side API key (`sk_…`), this plugin sends contact and order data **from WordPress to Sendora over HTTPS**. It does not receive inbound webhooks from Sendora in this version.

= What you can do =

* **Connect** — Save an API key, test the connection, disconnect, and see workspace status.
* **Onboarding** — First-run wizard and dashboard checklist that explain QR message models vs Meta official templates.
* **Lead forms** — Shortcode `[sendora_form]` creates/updates contacts and can trigger a Sendora flow.
* **Contact Form 7** — Map name, phone, and email fields when CF7 is active.
* **Chat widget** — Opt-in embed of the official Sendora widget (page targeting supported).
* **WooCommerce** — Per-event cards (order created, paid, abandoned cart, shipped, and more) with message and/or CRM actions.
* **Two message types**
  * **WhatsApp (QR Code)** — Message models with dynamic variables (`{{nome}}`, `{{pedido}}`, …) managed at [app.sendora.com.br/templates](https://app.sendora.com.br/templates).
  * **WhatsApp Official (Meta)** — Approved WABA templates from the Meta Cloud API.
* **Automations** — Rules in WordPress (trigger, conditions, delay) that run Sendora actions (message, CRM, flow).
* **Local logs** — Admin log table with API keys redacted.

= External service =

This plugin depends on the **Sendora** SaaS API:

* API base: `https://api.sendora.com.br`
* App: `https://app.sendora.com.br`

You need a Sendora account and an API key. Message delivery and CRM features are provided by that service (Guideline 6 — genuine SaaS integration).

**Recommended API key scopes**

* `contacts:write`
* `messages:write` (or `messages:send`)
* `flows:write` (optional, for flow triggers)

WooCommerce and Contact Form 7 are optional. Their settings appear only when those plugins are active.

== Installation ==

1. Upload the `sendora` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate **Sendora**.
3. Open **Sendora** in the admin menu. Follow the onboarding wizard if shown.
4. Go to **Conexão** (Connection), paste your API key (`sk_…`), save, then **Test connection**.
5. Optionally configure:
   * **Configurações** — default country code and test phone
   * **Formulários** — `[sendora_form]` and/or Contact Form 7
   * **Widget** — enable the chat widget and set the widget ID
   * **WooCommerce** — enable order events and choose QR models or Meta templates
   * **Automações** — optional hybrid rules
6. Add `[sendora_form]` to any page or post to capture leads.

== Frequently Asked Questions ==

= Where do I get an API key? =

In your Sendora account (app.sendora.com.br), create a Public API key that starts with `sk_`. Calls use HTTPS only.

= What is the difference between QR models and Meta templates? =

* **QR / message models** — Your own texts with `{{variables}}`, created under **Templates** in the Sendora app (`/templates`). Used when the channel is **WhatsApp (QR Code)**.
* **Meta official templates** — Approved Cloud API (WABA) templates. Used when the channel is **WhatsApp Oficial (Meta API)**.

They are listed separately in the plugin depending on the channel you select.

= Why is the message dropdown empty? =

For **QR Code**, create a model at app.sendora.com.br/templates, then reload the admin page (the plugin caches the list for a few minutes and does not keep an empty cache).

For **Meta**, you need at least one **APPROVED** template on your connected WABA.

= Is my API key visible to site visitors? =

No. The key is stored in the WordPress options table with autoload disabled. The admin UI shows only the last four characters after save. It is never printed in front-end JavaScript.

= Does the plugin phone home without consent? =

No analytics or telemetry run on install. Data is sent to Sendora only after an administrator saves an API key and enables features (forms, WooCommerce events, widget, automations, or manual test send).

= Does enabling the chat widget load a remote script? =

Yes. When the widget is enabled and a widget ID is set, the official embed script is loaded from your API base URL (`/public/widget/embed` on `https://api.sendora.com.br`). This is the Sendora product embed, not a third-party ad CDN.

= Does this plugin receive webhooks from Sendora? =

No. This version is outbound only: WordPress calls the Sendora REST API.

= Do I need WooCommerce or Contact Form 7? =

No. The shortcode and widget work without them.

= How are phone numbers formatted? =

Digits are normalized with your configured default country calling code (Brazil `55` by default) before contact upsert.

== Screenshots ==

1. Dashboard — connection status, onboarding checklist, and recent activity.
2. Connection — API key, test, and disconnect.
3. WooCommerce — event cards with channel (QR or Meta) and message selection.
4. Forms — native form and Contact Form 7 mappings.
5. Automations — trigger, conditions, delay, and Sendora actions.

== Changelog ==

= 0.8.6 =
* Plugin Check: nonce no deep-link do wizard, wp_unslash em AJAX admin, version no enqueue do widget.

= 0.8.5 =
* Plugin Check: Plugin URI e Author URI distintos (app vs site).

= 0.8.4 =
* Fix: Finalizar/Concluir gravam flag permanente (onboarding não volta após reload).

= 0.8.3 =
* Onboarding: botões (ex. Ir para Conexão) sem underline/azul padrão do wp-admin.

= 0.8.2 =
* Onboarding: “Finalizar” / “Concluir” fecham o guia de forma permanente (não reaparece).

= 0.8.1 =
* Release prep for WordPress.org: professional English readme, GPL-2.0-or-later header, fuller uninstall.
* Clarified QR message models vs Meta official templates; Contributors: sendora.

= 0.8.0 =
* Admin onboarding wizard and dashboard checklist (plugin only).
* Inline help distinguishing QR models (/templates) from Meta templates.

= 0.7.1 =
* Do not cache an empty QR template list (new models appear after refresh).

= 0.7.0 =
* Hybrid automations: WordPress trigger/conditions/delay; Sendora message, CRM, and flows.
* Triggers: Sendora form, Contact Form 7, WooCommerce events (including abandoned cart).

= 0.6.2 =
* Channel label: “WhatsApp (QR Code)” (no Evolution branding in the UI).

= 0.6.1 =
* Test send: phone field next to the button; settings store the default test number.

= 0.6.0 =
* Channel picker: WhatsApp QR Code or Meta Official WABA per event/form.
* Instance lists from GET /api/connections and GET /api/meta/waba.
* Meta send path: approved templates and POST /api/messages/send-template.

= 0.5.0 =
* WooCommerce CRM: per-event funnel, stage, and tags.
* Cards support message-only, CRM-only, or both.

= 0.4.1 =
* Phone country select and input mask on `[sendora_form]`.

= 0.4.0 =
* Forms: card UX aligned with WooCommerce (message per form + CF7).
* Abandoned checkout event with configurable delay.
* “Enviar teste” on event/form cards.

= 0.3.0 =
* WooCommerce event → Sendora message models (GET /api/templates, POST /api/messages/send).
* Async processing via Action Scheduler when available, otherwise WP-Cron.
* Template variables aligned with Sendora (`{{nome}}`, `{{pedido}}`, etc.).

= 0.2.0 =
* Fluent-style admin shell, connection workspace, widget page targeting, event bus foundation.

= 0.1.0 =
* Initial release: API connection, `[sendora_form]`, Contact Form 7 mapping, official widget embed, WooCommerce sync, local logs.
* Hardened for review: prepared SQL, escaping, nonces/capabilities, HTTPS-only API, uninstall cleanup, HPOS declaration.

== Upgrade Notice ==

= 0.8.1 =
Recommended for WordPress.org submission: clearer docs and safer uninstall cleanup.

= 0.8.0 =
Adds plugin onboarding. Existing sites may see the wizard once; you can dismiss it from the dashboard.

= 0.3.0 =
Reconfigure WooCommerce events: choose a Sendora message (or CRM action) per order event.

== Privacy ==

This plugin sends data to the Sendora API (`https://api.sendora.com.br`) only after an administrator configures an API key and enables features.

* **API key** — Stored in `sendora_settings` (autoload off). Masked in the admin UI. Never exposed to visitors.
* **Forms / Contact Form 7 / WooCommerce / Automations** — Name, phone, email, tags, funnel/stage, and related order metadata may be posted over HTTPS.
* **Chat widget** — Off by default. When enabled, loads the official embed from the Sendora API host.
* **Logs** — Local table `{prefix}sendora_logs`. Secrets are redacted. Removed on uninstall together with settings.

No advertising trackers are bundled. See also [Sendora](https://sendora.com.br) terms and privacy policy for the SaaS account.
