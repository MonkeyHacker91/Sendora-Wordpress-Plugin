# Sendora WordPress plugin — smoke test (v1)

Manual QA checklist mapped to [design success criteria](superpowers/specs/2026-09-18-sendora-wordpress-plugin-design.md#success-criteria-v1).

**Prerequisites:** WordPress 6.2+, PHP 8.0+, valid Sendora `sk_` key with recommended scopes (`contacts:write`, `flows:write`, and `messages:write` if sending messages). Staging site preferred.

---

## 1. API key and connection test (criterion 1)

- [ ] **Sendora → Settings:** API base URL is HTTPS (default `https://api.sendora.com.br`).
- [ ] Save a valid `sk_` key; page shows masked key (last four characters only).
- [ ] **Test connection** returns success (green) with a healthy key.
- [ ] With an invalid or revoked key, test shows failure (red) with a readable error.
- [ ] View page source / network: API key does not appear in HTML or front-end JS.

## 2. `[sendora_form]` lead capture (criterion 2)

- [ ] Add shortcode `[sendora_form]` to a published page; form renders (name, phone, email, message).
- [ ] Submit with valid phone; UI shows success.
- [ ] Contact appears in Sendora (phone/email/name match).
- [ ] With **Default flow** set in settings, submit again; flow runs in Sendora (or flow trigger visible in logs).
- [ ] **Sendora → Logs:** entry for `form` with success status.

## 3. Contact Form 7 mapping (criterion 3)

- [ ] CF7 installed; published form with name, phone, email fields.
- [ ] Map tags on **Sendora → Settings → Contact Form 7**; save.
- [ ] Submit CF7 form on front end.
- [ ] Same contact upserted in Sendora as for the shortcode path.
- [ ] **Sendora → Logs:** entry for `cf7` linked to form id.

## 4. Chat widget embed (criterion 4)

- [ ] Enable **Chat widget** and set a valid **Widget ID** from Sendora; save.
- [ ] Front-end page load: official embed script present in footer (inspect `wp_footer` output).
- [ ] Widget UI loads for visitors (bubble / chat as configured in Sendora).
- [ ] Disable widget or clear ID; embed script no longer enqueued.

## 5. WooCommerce order sync (criterion 5)

- [ ] WooCommerce active; **New order action** = sync contact only (or contact + default flow).
- [ ] Place test order with billing name, phone, email.
- [ ] Contact upserted in Sendora; order meta `_sendora_contact_id` set (order screen / meta).
- [ ] Set **Paid order action** to trigger flow (or message if scoped); complete payment.
- [ ] Expected flow/message occurs in Sendora; **Logs** show `woo` success or documented failure.
- [ ] Optional: enable cancel sync; cancel order and confirm log/note behavior.

## 6. Outbound-only architecture (criterion 6)

- [ ] No Sendora webhook URL required in Sendora app for this plugin to function.
- [ ] No OAuth or “Connect with Sendora” UI in wp-admin.
- [ ] Firewall / access logs: site initiates HTTPS to Sendora API; no inbound Sendora→WP endpoints required for v1 features.

---

## Quick regression

- [ ] PHPUnit suite passes locally (`vendor/bin/phpunit` in `sendora/`).
- [ ] Plugin deactivates/reactivates without PHP fatals.
- [ ] With Woo or CF7 deactivated, remaining features (connection, shortcode, widget) still work.

**Sign-off:** _______________ **Date:** _______________ **Environment:** _______________
