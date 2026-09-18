# Sendora for WordPress

Official WordPress plugin to connect your site to [Sendora](https://sendora.com.br) — CRM, WhatsApp messaging, chat widget, form lead capture, and WooCommerce sync.

![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)
![License](https://img.shields.io/badge/License-GPLv2%20or%20later-green)

## Features

- **Connection** — Secure API key (`sk_…`), connection test, disconnect, workspace status
- **Dashboard** — Modern SaaS-style admin (Fluent Forms–inspired): overview, integrations, recent logs
- **Lead forms** — Shortcode `[sendora_form]` and Contact Form 7 field mapping
- **Chat widget** — Official Sendora embed with all pages or specific pages
- **WooCommerce** — Sync contacts on order created / paid / cancelled (optional flows & messages)
- **Event bus** — Foundation for automations (flows & templates)
- **Local logs** — Diagnostics without storing API secrets

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.2+ |
| PHP | 8.0+ |
| Sendora account | [app.sendora.com.br](https://app.sendora.com.br) |
| WooCommerce | Optional |
| Contact Form 7 | Optional |

## Installation

1. Download or clone this repository into `wp-content/plugins/sendora`
2. Activate **Sendora** in **Plugins**
3. Open **Sendora → Conexão** in wp-admin
4. Paste your Public API key (`sk_…`) from the Sendora app
5. Click **Salvar**, then **Testar conexão**

### Recommended API scopes

- `contacts:write` — upsert contacts from forms and orders  
- `flows:write` — trigger automation flows  
- `messages:write` — only if you send messages from WooCommerce actions  

## Usage

### Shortcode

```
[sendora_form]
```

Renders a simple lead form (name, phone, email, message). Submissions upsert a contact in Sendora and can trigger the flow configured under **Formulários**.

### Chat widget

1. Go to **Sendora → Widget**
2. Enable the widget and paste your Widget UUID (from the Sendora app)
3. Choose all pages or specific pages

### WooCommerce

With WooCommerce active, configure actions under **Sendora → WooCommerce** for order created, payment complete, and cancelled.

## Privacy & security

- API keys are stored in WordPress options with **autoload disabled**
- Keys are never printed in full in the admin UI (last four characters only)
- Keys are never exposed to site visitors or frontend JavaScript
- Local logs redact `sk_…` secrets
- Uninstall removes settings, connection status, and the log table

## Development

```bash
composer install
./vendor/bin/phpunit --configuration phpunit.xml.dist
```

Build a release zip (excludes tests/vendor):

```powershell
powershell -File .\bin\build-release.ps1
```

## WordPress.org

This plugin ships with `readme.txt` and header fields required for [WordPress.org plugin directory](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) submission (`Contributors: sendora`, GPLv2 or later).

## Links

- Product: [sendora.com.br](https://sendora.com.br)
- App: [app.sendora.com.br](https://app.sendora.com.br)
- API: [api.sendora.com.br](https://api.sendora.com.br)

## License

Licensed under the **GNU General Public License v2.0 or later**, as required for WordPress plugins.

See [LICENSE](LICENSE) for the full text.
