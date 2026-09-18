# Sendora WordPress Plugin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WordPress plugin that connects a site to Sendora via API Key and ships v1 lead capture, official chat widget embed, and basic WooCommerce sync (WordPress → Sendora only).

**Architecture:** Thin PHP proxy to Public API (`Authorization: Bearer sk_…`). Admin stores settings; forms/CF7/Woo hooks call `Sendora_Api_Client`; widget loads official public embed. Automation brain stays in Sendora flows/agents.

**Tech Stack:** PHP 8.0+, WordPress 6.2+, optional WooCommerce, Contact Form 7 hooks, Action Scheduler when available (else `wp_cron`), HTTPS to `api.sendora.com.br`.

**Spec:** `docs/superpowers/specs/2026-09-18-sendora-wordpress-plugin-design.md`

## Global Constraints

- Auth v1: API Key `sk_…` only (no OAuth).
- Data direction v1: WordPress → Sendora only (no inbound webhooks).
- Never expose API key to front-end JS or HTML attributes.
- Default API base: `https://api.sendora.com.br` (HTTPS required).
- Plugin slug/text domain: `sendora`.
- WooCommerce and CF7 are optional; degrade gracefully if inactive.
- Do not commit secrets, `.env`, or real `sk_` keys.
- Work only under `C:\Users\vinny\OneDrive\Documents\Projetos\Sendora Wordpress Plugin` (plugin root = `sendora/` package folder).

## File map

| File | Responsibility |
|------|----------------|
| `sendora/sendora.php` | Plugin bootstrap, constants, activation hooks |
| `sendora/readme.txt` | WP.org-style readme |
| `sendora/includes/class-sendora-plugin.php` | Wires components on `plugins_loaded` |
| `sendora/includes/class-sendora-settings.php` | Options API, admin menu, connection test UI |
| `sendora/includes/class-sendora-api-client.php` | HTTP client: contacts, flows, messages, test |
| `sendora/includes/class-sendora-forms.php` | Shortcode `[sendora_form]` + submit handler |
| `sendora/includes/class-sendora-cf7.php` | CF7 mapping + mail/sent hook |
| `sendora/includes/class-sendora-widget.php` | Footer embed of public widget |
| `sendora/includes/class-sendora-woocommerce.php` | Order created / payment / cancelled hooks |
| `sendora/includes/class-sendora-logger.php` | Local logs table/option + admin list |
| `sendora/includes/class-sendora-phone.php` | Phone normalize (+55 default) |
| `sendora/admin/views/settings.php` | Settings markup |
| `sendora/admin/views/logs.php` | Logs markup |
| `sendora/admin/css/admin.css`, `admin/js/admin.js` | Admin UX |
| `sendora/public/css/form.css`, `public/js/form.js` | Shortcode form |
| `sendora/tests/bootstrap.php` + PHPUnit tests | Unit tests for client/phone/mapping (no live API) |
| `sendora/phpunit.xml.dist` | Test config |

---

### Task 1: Plugin skeleton + API client

**Files:**
- Create: `sendora/sendora.php`
- Create: `sendora/includes/class-sendora-plugin.php`
- Create: `sendora/includes/class-sendora-api-client.php`
- Create: `sendora/includes/class-sendora-phone.php`
- Create: `sendora/tests/bootstrap.php`
- Create: `sendora/tests/test-phone.php`
- Create: `sendora/tests/test-api-client.php`
- Create: `sendora/phpunit.xml.dist`
- Create: `sendora/readme.txt` (stub)

**Interfaces:**
- Produces: `Sendora_Api_Client::from_options(): self`
- Produces: `request(string $method, string $path, ?array $body = null): array{ok:bool,status:int,data:mixed,error:?string}`
- Produces: `upsert_contact(array $fields): array`
- Produces: `list_flows(): array`
- Produces: `trigger_flow(string $flow_id, string $phone, ?string $message = null): array`
- Produces: `send_message(array $payload): array`
- Produces: `test_connection(): array{ok:bool,message:string}`
- Produces: `Sendora_Phone::normalize(string $raw, string $default_cc = '55'): string`

- [ ] **Step 1: Write failing phone + client shape tests**

```php
// tests/test-phone.php
public function test_normalize_strips_and_adds_cc(): void {
    $this->assertSame('5511999999999', Sendora_Phone::normalize('(11) 99999-9999'));
}
```

```php
// tests/test-api-client.php — mock wp_remote_request via filter or injectable HTTP
public function test_upsert_posts_contacts_with_bearer(): void {
    // assert Authorization header starts with Bearer sk_
    // assert path /api/contacts
}
```

- [ ] **Step 2: Run PHPUnit — expect FAIL**

Run: `cd sendora && vendor/bin/phpunit` (or `phpunit` if global)  
Expected: FAIL (classes missing)

- [ ] **Step 3: Implement bootstrap + client + phone**

Constants: `SENDORA_VERSION`, `SENDORA_PLUGIN_FILE`, `SENDORA_PLUGIN_DIR`.  
Client uses `wp_remote_request`, JSON encode/decode, maps non-2xx to `{ok:false,error}`.  
`test_connection()`: `GET /api/flows` or documented lightweight endpoint; treat 401/403 as invalid key.

- [ ] **Step 4: Run tests — PASS**

- [ ] **Step 5: Commit**

```bash
git add sendora/
git commit -m "feat(wp): scaffold plugin and Sendora API client"
```

---

### Task 2: Settings admin + connection test

**Files:**
- Create: `sendora/includes/class-sendora-settings.php`
- Create: `sendora/admin/views/settings.php`
- Create: `sendora/admin/css/admin.css`
- Create: `sendora/admin/js/admin.js`
- Modify: `sendora/includes/class-sendora-plugin.php` — register settings

**Interfaces:**
- Consumes: `Sendora_Api_Client::test_connection()`, `list_flows()`
- Produces: option key `sendora_settings` array shape:
  - `api_base`, `api_key`, `widget_enabled`, `widget_id`, `default_flow_id`, `default_cc`, `woo_on_created`, `woo_on_paid`, `woo_on_cancelled`, `woo_paid_flow_id`, `woo_paid_mode` (`flow`|`message`|`off`)

- [ ] **Step 1: Register `admin_menu` + `admin_init` settings**

Capability: `manage_options`. Mask API key in UI after save (show last 4 chars). Nonces on save and “Test connection” AJAX.

- [ ] **Step 2: AJAX `sendora_test_connection`**

Returns JSON `{ ok, message }`. Never return full key.

- [ ] **Step 3: Manual check list in commit message / README**

- Load WP admin → Sendora → save key → Test → green/red.

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(wp): admin settings and API connection test"
```

---

### Task 3: Logger

**Files:**
- Create: `sendora/includes/class-sendora-logger.php`
- Create: `sendora/admin/views/logs.php`
- Modify: `class-sendora-settings.php` — submenu Logs
- Modify: activation in `sendora.php` — create custom table `{$wpdb->prefix}sendora_logs` OR capped option array (prefer table)

**Interfaces:**
- Produces: `Sendora_Logger::log(string $source, string $level, string $message, array $context = []): void`
- Produces: `Sendora_Logger::list(int $limit = 100): array`

Columns: `id`, `created_at`, `source`, `level`, `message`, `context` (JSON truncated).

- [ ] **Step 1: Implement logger + admin list (read-only, clear button)**

- [ ] **Step 2: Ensure API client failures call logger**

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(wp): local Sendora sync logs"
```

---

### Task 4: Shortcode form `[sendora_form]`

**Files:**
- Create: `sendora/includes/class-sendora-forms.php`
- Create: `sendora/public/css/form.css`
- Create: `sendora/public/js/form.js`
- Create: `sendora/tests/test-forms-mapping.php`

**Interfaces:**
- Consumes: `upsert_contact`, `trigger_flow`, settings `default_flow_id`
- Produces: shortcode render + `admin-ajax` or REST route `sendora/v1/form-submit`

- [ ] **Step 1: Failing test for required phone validation**

- [ ] **Step 2: Implement shortcode + submit**

Fields: name, phone, email, message. Nonce + honeypot + IP rate limit (transient, e.g. 5/10min).  
On success: upsert; if `default_flow_id` set, trigger with phone + message.  
Log success/failure.

- [ ] **Step 3: Enqueue public assets only when shortcode present**

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(wp): sendora_form shortcode lead capture"
```

---

### Task 5: Contact Form 7 bridge

**Files:**
- Create: `sendora/includes/class-sendora-cf7.php`
- Modify: settings view — CF7 form → field map UI (form id, name/phone/email tags)

**Interfaces:**
- Hook: `wpcf7_mail_sent` (or `wpcf7_before_send_mail` if needed for posted data)
- Only load class if `defined('WPCF7_VERSION')`

- [ ] **Step 1: Settings: list CF7 forms, map tags to Sendora fields**

- [ ] **Step 2: On mail sent → upsert + optional flow**

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(wp): Contact Form 7 mapping to Sendora contacts"
```

---

### Task 6: Widget embed

**Files:**
- Create: `sendora/includes/class-sendora-widget.php`

**Interfaces:**
- If `widget_enabled` && `widget_id`: print official embed in `wp_footer`
- Use Sendora public embed URL pattern from product: `https://api.sendora.com.br/public/widget/embed?id={id}` (confirm against current app embed snippet; do not invent a second chat UI)

- [ ] **Step 1: Implement footer embed matching official snippet**

Verify against live Sendora widget embed docs / app settings. Escape ID.

- [ ] **Step 2: Commit**

```bash
git commit -m "feat(wp): embed official Sendora chat widget"
```

---

### Task 7: WooCommerce basic sync

**Files:**
- Create: `sendora/includes/class-sendora-woocommerce.php`
- Create: `sendora/tests/test-woo-payload.php`
- Modify: settings for woo actions

**Interfaces:**
- Hooks: order created, `woocommerce_payment_complete`, cancelled (optional)
- Order meta: `_sendora_contact_id`, `_sendora_last_sync_at`, `_sendora_last_error`
- Retry: Action Scheduler `as_enqueue_async_action` if function exists; else `wp_schedule_single_event`

- [ ] **Step 1: Unit test billing → contact payload mapping**

- [ ] **Step 2: Implement hooks + retries + order notes on hard fail**

Modes from settings: contact only / contact+flow / off; paid mode flow|message|off.

- [ ] **Step 3: Skip class load if WooCommerce inactive**

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(wp): WooCommerce order sync to Sendora"
```

---

### Task 8: Polish, readme, smoke checklist

**Files:**
- Modify: `sendora/readme.txt` — full WP.org sections
- Create: `docs/SMOKE.md` — manual QA checklist
- Modify: design spec status → `approved` if not already

- [ ] **Step 1: Complete readme (description, installation, FAQ, scopes)**

- [ ] **Step 2: Write `docs/SMOKE.md` covering the six success criteria from the spec

- [ ] **Step 3: Run full PHPUnit suite**

- [ ] **Step 4: Commit**

```bash
git commit -m "docs(wp): readme and smoke checklist for v1 plugin"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| API Key settings + test | 2 |
| Thin API client | 1 |
| Phone normalize | 1 |
| Local logs | 3 |
| `[sendora_form]` | 4 |
| CF7 mapping | 5 |
| Widget embed | 6 |
| Woo created/paid/cancelled | 7 |
| No inbound webhooks / no OAuth | all (omitted) |
| Security (capabilities, nonces, no key in JS) | 2, 4 |

## Execution handoff

After this plan is approved by the user, implement with **subagent-driven-development** (fresh subagent per task) unless the user chooses executing-plans.
