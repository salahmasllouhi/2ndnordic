<?php
/**
 * iptv_text() – front page copy lookup
 *
 * Every string on the front page (and in the header and footer, which render on
 * every template) goes through this function.
 *
 * Resolution order:
 *   1. ACF field on the front page. Polylang filters `page_on_front`, so this
 *      returns the English page on `/` and the Swedish one under `/sv/` — which is
 *      what makes the whole page translatable from the page editor.
 *   2. The Polylang string translation of the English default, for the handful of
 *      strings registered in inc/front-page-strings.php. pll__() returns its input
 *      unchanged for anything unregistered, so this is a safe catch-all.
 *   3. The English default written into the template.
 *
 * This replaces IPTV_Content_Settings::get_text(), which also consulted an
 * `iptv_content` option keyed by the site slugs of the old multisite install
 * (se/no/dk/fi/is). Polylang's Swedish slug is `sv`, so that layer never matched
 * and always fell through to English.
 *
 * @package Nordic_IPTV
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('iptv_text')) {
    /**
     * Get the current language's copy for a front page key.
     *
     * @param string $key     Field name on the front page ACF group.
     * @param string $default English fallback, also the Polylang lookup key.
     * @return string
     */
    function iptv_text($key, $default = '')
    {
        // Keys backed by an ACF link field (an array). Templates read those
        // directly, so the lookup here would only ever return the wrong shape.
        static $acf_skip_keys = array('hero_cta');

        // NOTE: the old get_text() mapped 'hero_title_span' to a field named
        // 'hero_title_gradient_text'. No such field exists — the ACF field is
        // named 'hero_title_span' and only its *label* says "Gradient Text" — so
        // the lookup always missed and the hero's second line silently fell back
        // to the template default, ignoring whatever was typed in the editor.

        $default_front_page_id = (int) get_option('page_on_front');
        $front_page_id = $default_front_page_id;

        // `page_on_front` stores the default-language page ID. Resolve its
        // Polylang translation before reading ACF so every localized homepage
        // reads and writes its own page-level content rather than English data.
        if ($front_page_id && function_exists('pll_get_post')) {
            $translated_front_page_id = pll_get_post($front_page_id);

            if ($translated_front_page_id) {
                $front_page_id = (int) $translated_front_page_id;
            }
        }

        // Prefer the current language's ACF value. Until an editor saves a
        // localized value, fall back to the default-language ACF record (never
        // a hard-coded template string), keeping the public page complete while
        // allowing translations to be edited field-by-field.
        $acf_post_ids = array_unique(array_filter(array(
            $front_page_id,
            $default_front_page_id,
        )));

        if (function_exists('get_field') && !in_array($key, $acf_skip_keys, true)) {
            foreach ($acf_post_ids as $acf_post_id) {
                $value = get_field($key, $acf_post_id);

                if ($value !== null && $value !== '' && !is_array($value)) {
                    return $value;
                }
            }
        }

        // get_field() resolves nothing for a field ACF has not registered, which
        // is the case for any field added to acf-json/ but not yet synced into
        // the database. The value is still plain post meta under the same key, so
        // read it directly rather than falling through to the English default.
        foreach ($acf_post_ids as $acf_post_id) {
            $meta = get_post_meta($acf_post_id, $key, true);
            if (is_string($meta) && $meta !== '') {
                return $meta;
            }
        }

        // Homepage copy is managed in WordPress (ACF/post meta). Do not fall
        // back to template strings here: that would make removed or untranslated
        // content silently reappear from the theme instead of remaining editable
        // in the page database.
        return '';
    }
}
