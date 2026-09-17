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

        $front_page_id = get_option('page_on_front');

        if (function_exists('get_field') && !in_array($key, $acf_skip_keys, true)) {
            $value = $front_page_id
                ? get_field($key, $front_page_id)
                : get_field($key);

            if ($value !== null && $value !== '' && !is_array($value)) {
                return $value;
            }
        }

        // get_field() resolves nothing for a field ACF has not registered, which
        // is the case for any field added to acf-json/ but not yet synced into
        // the database. The value is still plain post meta under the same key, so
        // read it directly rather than falling through to the English default.
        if ($front_page_id) {
            $meta = get_post_meta($front_page_id, $key, true);
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
