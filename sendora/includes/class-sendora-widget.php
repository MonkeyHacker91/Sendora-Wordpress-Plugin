<?php

declare(strict_types=1);

final class Sendora_Widget
{
    public function run(): void
    {
        add_action('wp_footer', [self::class, 'render_embed'], 20);
    }

    public static function render_embed(): void
    {
        $settings = Sendora_Settings::get_settings();

        if (empty($settings['widget_enabled'])) {
            return;
        }

        $widget_id = trim((string) ($settings['widget_id'] ?? ''));
        if ($widget_id === '') {
            return;
        }

        $api_base = rtrim((string) ($settings['api_base'] ?? ''), '/');
        if ($api_base === '') {
            return;
        }

        $script_url = add_query_arg(
            [
                'id' => $widget_id,
                'v' => '6',
            ],
            $api_base . '/public/widget/embed'
        );

        $script_url = esc_url($script_url);
        if ($script_url === '') {
            return;
        }

        $encoded_url = wp_json_encode($script_url);
        if ($encoded_url === false) {
            return;
        }

        echo "<!-- Sendora Chat Widget -->\n";
        echo "<script>\n";
        echo "(function(w,d,u){\n";
        echo "  function load(){\n";
        echo "    if(w.__sendora_widget)return;\n";
        echo "    var s=d.createElement(\"script\");\n";
        echo "    s.src=u;\n";
        echo "    s.async=true;\n";
        echo "    (d.head||d.body).appendChild(s);\n";
        echo "  }\n";
        echo "  if(d.readyState===\"loading\"){d.addEventListener(\"DOMContentLoaded\",load);}\n";
        echo "  else{load();}\n";
        echo '})(window,document,' . $encoded_url . ");\n";
        echo "</script>\n";
    }
}
