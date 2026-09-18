# Sendora WordPress Plugin — Design

**Status:** approved  
**Date:** 2026-09-18  
**Repo:** `Sendora Wordpress Plugin`  
**Related:** Sendora Public API (`api.sendora.com.br/api`), widget público (`/public/widget/embed`)

## Goal

Ship a WordPress plugin that connects a site to a Sendora account via API Key and, in v1, delivers three capabilities:

1. Lead capture → contact (and optional flow trigger) in Sendora  
2. Chat widget embed (official Sendora public widget)  
3. WooCommerce basic order events → contact sync + optional flow/message  

Direction of data in v1: **WordPress → Sendora only** (no inbound webhooks).

## Non-goals (v1)

- OAuth / “Connect with Sendora”  
- Sendora → WordPress webhooks  
- Elementor / Gravity Forms / WPForms (add later on same mapping hooks)  
- Hosting CRM data inside WordPress  
- MCP inside WordPress (MCP stays Cursor/Claude; plugin uses REST)  
- Custom chat UI replacing the official widget  

## Architecture (Approach A — thin proxy)

```
WordPress Admin / Forms / WooCommerce
        │
        ▼
  sendora plugin (PHP)
        │  HTTPS + Authorization: Bearer sk_…
        ▼
  api.sendora.com.br/api/*
        │
        ├── POST /api/contacts          (upsert)
        ├── POST /api/flows/:id/trigger (optional)
        ├── POST /api/messages/send or send-template (optional Woo)
        └── GET  /api/flows, /api/me or billing (connection test / selects)

Widget path (separate):
  Theme footer → official embed script
  → GET /public/widget/embed?id={widget_id}
```

Business automation (sequences, AI agents, keywords) stays in **Sendora flows/agents**. The plugin only fires triggers and maps fields.

## Connection & settings

**Admin menu:** Sendora  

| Setting | Storage | Notes |
|---------|---------|--------|
| API base URL | `options` | Default `https://api.sendora.com.br` |
| API Key `sk_…` | `options` (never echoed in full after save) | Required |
| Connection status | transient / option | Last test result |
| Widget enabled | bool | |
| Widget ID | string | Public embed id |
| Default flow (forms) | UUID or empty | Optional |
| Woo: on order created | action enum | `contact_only` \| `contact_and_flow` \| `off` |
| Woo: on payment complete | action enum + flow/message config | |
| Woo: on cancelled | action enum | Optional |

**Connection test:** authenticated GET that validates the key (prefer lightweight existing endpoint such as billing/status or flows list). Surface HTTP errors and missing scopes clearly.

**Suggested scopes (documented in UI):** `contacts:write`, `flows:write`, and `messages:write` if message send is enabled.

## Feature 1 — Lead capture

1. **Shortcode** `[sendora_form]` — fields: name, phone, email, message (configurable). On submit → upsert contact + optional `trigger_flow`.  
2. **Contact Form 7** — hook on mail/sent; map CF7 fields via admin mapping table.  
3. **Checkout / account phone** — when available without full Woo order path.

**Field mapping model**

```
wp_field_key → sendora_field (name | phone | email | custom_tag)
```

Phone normalized to digits with country code when possible (default +55 configurable).

**Idempotency:** upsert by phone/email as supported by Public API; store last Sendora `contact_id` on CF7 submission meta when possible.

## Feature 2 — Widget

- Toggle enable/disable.  
- Paste Widget ID from Sendora app.  
- Enqueue official embed in `wp_footer` only when enabled and ID present.  
- No custom iframe CSS beyond optional z-index / position class hook for themes.

## Feature 3 — WooCommerce (basic)

| WP event | Default Sendora action |
|----------|------------------------|
| `woocommerce_checkout_order_processed` / order created | Upsert contact from billing name, phone, email |
| `woocommerce_payment_complete` | Optional: trigger flow **or** send message/template (admin choice) |
| Order cancelled (optional) | Optional: trigger flow or log only |

**Order meta**

- `_sendora_contact_id`  
- `_sendora_last_sync_at`  
- `_sendora_last_error`  

**Reliability:** on API failure, enqueue 1–2 retries via Action Scheduler if present, else `wp_cron`. Failures visible in order notes (admin) and a simple Sendora → Logs admin table (local only).

## Plugin structure (proposed)

```
sendora/
  sendora.php                 # bootstrap, constants, activation
  readme.txt                  # WP.org readme
  includes/
    class-sendora-plugin.php
    class-sendora-settings.php
    class-sendora-api-client.php
    class-sendora-forms.php
    class-sendora-cf7.php
    class-sendora-widget.php
    class-sendora-woocommerce.php
    class-sendora-logger.php
  admin/
    views/settings.php
    views/logs.php
    css/admin.css
    js/admin.js
  public/
    css/form.css
    js/form.js
  languages/
docs/
  superpowers/specs/...
```

Requires PHP 8.0+, WordPress 6.2+, WooCommerce optional (features degrade gracefully if Woo inactive).

## Security

- Capability `manage_options` for settings.  
- Nonces on all admin forms and public shortcode submit (REST or admin-ajax with nonce).  
- API key never printed in HTML attributes or client JS.  
- Outbound HTTPS only; reject non-HTTPS base URL in production.  
- Sanitize/escape all mapped fields; rate-limit shortcode submissions per IP (transient).  
- No logging of full API key or full payloads with secrets.

## Error handling & observability

- Local log: timestamp, source (form|cf7|woo), HTTP status, truncated body, order/form id.  
- Admin notice when key missing/invalid.  
- Woo: order note on sync failure after retries exhausted.

## Success criteria (v1)

1. Save API key → connection test green.  
2. Submit `[sendora_form]` → contact appears in Sendora; optional flow runs.  
3. CF7 submit with mapping → same.  
4. Widget appears when enabled with valid ID.  
5. New Woo order → contact upserted; payment complete can trigger configured flow/message.  
6. No Sendora→WP traffic required.

## Future (explicitly deferred)

- OAuth connect  
- Inbound webhooks (update order status, tags)  
- Elementor / Gravity / WPForms bridges  
- Bulk sync / historical import  
- Gutenberg block for form (shortcode first)

## Decisions log

| Decision | Choice |
|----------|--------|
| Product scope | Full kit, phased; v1 = forms + widget + Woo basic |
| Auth | API Key `sk_…` only in v1 |
| Data direction | WP → Sendora only |
| Architecture | Thin proxy to Public API (Approach A) |
| Automation brain | Sendora flows/agents, not WP |
| Widget | Official public embed, not custom chat |
