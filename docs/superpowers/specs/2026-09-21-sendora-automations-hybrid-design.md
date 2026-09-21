# Automações híbridas (WordPress plugin) — Design

**Status:** approved (user: híbrido + “pode fazer”)  
**Date:** 2026-09-21  
**Defaults for unanswered scope:** triggers = Woo events + native form + CF7 only (no arbitrary WP hooks in v1)

## Goal

Admin page **Automações** with custom rules: **trigger → optional simple conditions → delay → actions**, FluentCRM-like cards. Rich logic (AI, long sequences) stays in **Sendora flows**; the plugin only decides *when* to fire API actions.

## Architecture

```
WP hook (Woo / form / CF7)
    → Sendora_Automations::dispatch(trigger, context)
        → match enabled rules
        → evaluate simple conditions
        → schedule delay (AS / cron) or run now
        → actions: upsert CRM fields + send message (QR/Meta) and/or trigger_flow
```

## Rule model (`sendora_settings['automations']`)

```php
[
  'id' => 'auto_xxx',           // stable id
  'name' => 'Pedido pago → flow',
  'enabled' => true,
  'trigger' => 'woo.payment_approved', // see catalog
  'conditions' => [               // AND; empty = always
    ['field' => 'order_total', 'op' => 'gte', 'value' => '100'],
  ],
  'delay_minutes' => 0,
  'actions' => [
    'channel' => 'evolution|meta',
    'instance_id' => '',
    'template_id' => '',          // optional message
    'template_language' => 'pt_BR',
    'body_vars' => [],
    'flow_id' => '',              // optional Sendora flow
    'funnel_id' => '',
    'stage' => '',
    'tags' => [],
  ],
]
```

## Triggers (v1)

| Key | Source |
|-----|--------|
| `woo.order_created` … `woo.order_delivered`, `woo.cart_abandoned` | existing Woo events |
| `form.native` | `[sendora_form]` submit |
| `form.cf7` | CF7 mail_sent (+ optional `form_id` condition) |

## Conditions (v1, simple)

Fields: `order_total`, `order_status`, `payment_method`, `product` (contains), `cf7_form_id`, `has_phone`  
Ops: `eq`, `neq`, `gte`, `lte`, `contains`, `empty`, `not_empty`

## Actions

At least one of: `template_id`, `flow_id`, or CRM (`funnel_id`+`stage` / `tags`).  
Reuse `Sendora_Outbound` + `trigger_flow` + contact upsert CRM fields.

## Non-goals (v1)

- Branching / multiple action steps / wait-for-reply  
- Arbitrary `do_action` hooks  
- Hosting AI/agents in WP  
- Replacing Woo/Forms cards (those stay; automations are additive)

## UI

Replace `automations.php` placeholder with rule cards (same visual language as Woo). Add / enable / delete. Test send reuses phone field pattern where applicable.
