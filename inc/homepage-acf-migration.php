<?php
/**
 * One-time migration of the homepage's ACF defaults into the WordPress DB.
 *
 * ACF can display a field's JSON default without storing it on the page. That
 * makes the front page appear editable while the visible value is still
 * supplied by the theme. This migration writes each missing default to the
 * selected static front page as real ACF post meta, while preserving anything
 * an editor has already saved.
 */

if (!function_exists('nordictv_seed_homepage_acf_defaults')) {
    function nordictv_seed_homepage_acf_defaults($fields, $page_id) {
        foreach ((array) $fields as $field) {
            if (empty($field['key']) || empty($field['name'])) {
                continue;
            }

            // Group fields contain their own leaf fields. ACF stores those
            // leaves directly, so seed them rather than the group container.
            if ($field['type'] === 'group' && !empty($field['sub_fields'])) {
                nordictv_seed_homepage_acf_defaults($field['sub_fields'], $page_id);
                continue;
            }

            // Preserve every value that has already been saved by an editor.
            if (metadata_exists('post', $page_id, $field['name'])) {
                continue;
            }

            if (array_key_exists('default_value', $field) && $field['default_value'] !== '') {
                update_field($field['key'], $field['default_value'], $page_id);
            }
        }
    }
}

if (!function_exists('nordictv_migrate_homepage_acf_content')) {
    function nordictv_migrate_homepage_acf_content() {
        if (!function_exists('acf_get_fields') || !function_exists('update_field')) {
            return;
        }

        $page_id = (int) get_option('page_on_front');
        $marker  = '_nordictv_homepage_acf_migration_20260917';

        if (!$page_id || get_post_meta($page_id, $marker, true)) {
            return;
        }

        $fields = acf_get_fields('group_homepage_fields');
        if (empty($fields)) {
            return;
        }

        nordictv_seed_homepage_acf_defaults($fields, $page_id);

        // Link fields do not support ACF JSON default values. These are the
        // existing homepage actions, stored as normal ACF link values.
        $links = array(
            'field_hero_cta'      => array('title' => 'Get Access Now', 'url' => '#pricing', 'target' => ''),
            'field_showcase_cta'  => array('title' => 'Explore the full channel lineup', 'url' => '#pricing', 'target' => ''),
            'field_features_cta'  => array('title' => 'See plans & pricing', 'url' => '#pricing', 'target' => ''),
            'field_sports_cta'    => array('title' => 'Watch live sport now', 'url' => '#pricing', 'target' => ''),
            'field_comp_cta'      => array('title' => 'See plans & pricing', 'url' => '#pricing', 'target' => ''),
            'field_steps_cta'     => array('title' => 'Unlock Instant Access Today!', 'url' => '#pricing', 'target' => ''),
            'field_cta_btn_text'  => array('title' => 'Start Streaming Now', 'url' => '#pricing', 'target' => ''),
        );

        foreach ($links as $field_key => $value) {
            $field = function_exists('acf_get_field') ? acf_get_field($field_key) : false;
            if ($field && !metadata_exists('post', $page_id, $field['name'])) {
                update_field($field_key, $value, $page_id);
            }
        }

        update_post_meta($page_id, $marker, current_time('mysql', true));
    }

    // It runs once after ACF's local JSON fields are available. The hook is
    // intentionally late so it also works immediately after a WP Pusher deploy.
    add_action('init', 'nordictv_migrate_homepage_acf_content', 30);
}
