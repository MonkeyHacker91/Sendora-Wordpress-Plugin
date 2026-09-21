<?php
/**
 * Resolve Sendora message template variables (same codes as the app).
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Template_Vars
{
    /**
     * @param array{
     *   name?: string,
     *   first_name?: string,
     *   phone?: string,
     *   company?: string,
     *   order_id?: string,
     *   order_number?: string,
     *   order_total?: string,
     *   order_status?: string,
     *   order_date?: string,
     *   payment_method?: string,
     *   shipping_address?: string,
     *   product?: string,
     *   tracking?: string,
     *   order_link?: string,
     *   store_name?: string,
     *   coupon?: string
     * } $ctx
     */
    public static function resolve(string $text, array $ctx): string
    {
        $name = (string) ($ctx['name'] ?? '');
        $first = (string) ($ctx['first_name'] ?? '');
        if ($first === '' && $name !== '') {
            $parts = preg_split('/\s+/', $name) ?: [];
            $first = (string) ($parts[0] ?? $name);
        }

        $phone = (string) ($ctx['phone'] ?? '');
        $company = (string) ($ctx['company'] ?? '');
        $store = (string) ($ctx['store_name'] ?? $company);
        $order_number = (string) ($ctx['order_number'] ?? ($ctx['order_id'] ?? ''));
        $order_total = (string) ($ctx['order_total'] ?? '');
        $order_status = (string) ($ctx['order_status'] ?? '');
        $order_date = (string) ($ctx['order_date'] ?? '');
        $payment = (string) ($ctx['payment_method'] ?? '');
        $shipping = (string) ($ctx['shipping_address'] ?? '');
        $product = (string) ($ctx['product'] ?? '');
        $tracking = (string) ($ctx['tracking'] ?? '');
        $link = (string) ($ctx['order_link'] ?? '');
        $coupon = (string) ($ctx['coupon'] ?? '');

        if ($order_date === '') {
            $order_date = wp_date('d/m/Y');
        }

        $map = [
            '{{nome}}' => $name,
            '{{primeiro_nome}}' => $first,
            '{{telefone}}' => $phone,
            '{{empresa}}' => $company !== '' ? $company : $store,
            '{{data}}' => $order_date,
            '{{hora}}' => wp_date('H:i'),
            '{{pedido}}' => $order_number,
            '{{valor_pedido}}' => $order_total,
            '{{status_pedido}}' => $order_status,
            '{{nome_site}}' => $store,
            '{{produto}}' => $product,
            '{{rastreio}}' => $tracking,
            '{{link_pedido}}' => $link,
            '{{cupom}}' => $coupon,
            // Conceptual aliases used in docs / UX copy.
            '{{customer.name}}' => $name,
            '{{customer.first_name}}' => $first,
            '{{customer.phone}}' => $phone,
            '{{order.id}}' => (string) ($ctx['order_id'] ?? $order_number),
            '{{order.number}}' => $order_number,
            '{{order.total}}' => $order_total,
            '{{order.status}}' => $order_status,
            '{{order.date}}' => $order_date,
            '{{order.payment_method}}' => $payment,
            '{{order.shipping_address}}' => $shipping,
            '{{order.tracking_code}}' => $tracking,
            '{{store.name}}' => $store,
            '{{contact.name}}' => $name,
            '{{contact.phone}}' => $phone,
        ];

        $resolved = $text;
        foreach ($map as $token => $value) {
            $resolved = str_ireplace($token, $value, $resolved);
        }

        return $resolved;
    }
}
