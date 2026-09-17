<?php
/**
 * Front Page – Polylang String Registration
 *
 * Most front page strings are ACF fields on the front page itself (field group
 * `group_homepage_fields`), so they are translated per language in the page
 * editor rather than in this table. Two sets are the exception.
 *
 * The printf-format strings carry `%` placeholders that sprintf() consumes in
 * front-page/sections/pricing.php. Dropping a placeholder turns the pricing
 * panel into a PHP warning or a wrong number, so they are kept out of reach of
 * the page editor and translated in Languages → Translations instead.
 *
 * The sticky bar strings are registered because that bar renders on every
 * template, not just the front page. iptv_text() still prefers the ACF field
 * when one is set, so these are the fallback that makes the bar translatable
 * before — or without — the field group being synced into the database.
 *
 * Usage in templates: iptv_text('key', 'Default English text')
 */

add_action('init', function () {
    if (!function_exists('pll_register_string')) {
        return;
    }

    $group = 'Front Page';

    // ── Pricing panel format strings ─────────────────────────────────────────
    // Keep the placeholders. Translate only the words around them.

    // %d = discount percent. Note the doubled %% renders a literal "%".
    pll_register_string('save_percent_format', 'Save %d%%', $group);

    // %1$s = money saved, %2$d = discount percent.
    pll_register_string('total_save_format', 'Save %1$s (%2$d%%)', $group);

    // %s = price per month.
    pll_register_string('total_meta_format', 'one-time · %s/mo', $group);

    // %d = discount percent.
    pll_register_string('total_lock_format', 'Your %d%% discount is locked for', $group);

    // ── Sticky CTA bar ───────────────────────────────────────────────────────
    // Rendered site-wide by inc/sticky-cta.php. Keep the button labels short:
    // both share one phone-width row, and they are clipped rather than wrapped.
    pll_register_string('sticky_timer_label', 'Offer ends in', $group);
    pll_register_string('sticky_pricing_label', 'See Pricing', $group);
    pll_register_string('sticky_trial_label', '24h Trial', $group);

    // Day marker in "4d 07:12:39" — sv/no/dk take "d", Finnish "pv".
    pll_register_string('sticky_days_suffix', 'd', $group);

    // ── Footer ───────────────────────────────────────────────────────────────
    // Column headings and link labels. These only apply when no nav menu is
    // assigned to the matching footer location; an assigned menu is translated
    // through Polylang's own menu handling instead.
    pll_register_string('footer_head_plans', 'Plans', $group);
    pll_register_string('footer_head_links', 'Useful Links', $group);
    pll_register_string('footer_head_legal', 'Legal', $group);

    pll_register_string('footer_link_blog', 'Blog', $group);
    pll_register_string('footer_link_guide', 'Setup Guide', $group);
    pll_register_string('footer_link_m3u', 'M3U Converter', $group);
    pll_register_string('footer_link_faq', 'FAQ', $group);
    pll_register_string('footer_link_contact', 'Contact Us', $group);
    pll_register_string('footer_link_account', 'My Account', $group);

    pll_register_string('footer_link_about', 'About Us', $group);
    pll_register_string('footer_link_privacy', 'Privacy Policy', $group);
    pll_register_string('footer_link_terms', 'Terms of Service', $group);
    pll_register_string('footer_link_refund', 'Return & Refund Policy', $group);
    pll_register_string('footer_link_dmca', 'DMCA & Copyright Notice', $group);

    // ── FAQ hub (archive-faq.php) ────────────────────────────────────────────
    // The archive has no page behind it, so there is no ACF field for iptv_text()
    // to prefer and these registrations are the only way its copy is translated.
    // The group headings are labels for a slug-keyword grouping; renaming one
    // here is safe, the grouping itself keys off the needle lists in the
    // template (filter: nordictv_faq_hub_groups).
    pll_register_string('faq_hub_title', 'IPTV questions and answers', $group);
    pll_register_string(
        'faq_hub_intro',
        'Everything people ask us about IPTV — what it costs, which devices it runs on, how to set it up, and where the law stands.',
        $group
    );
    pll_register_string('faq_hub_group_legal', 'Legality and safety', $group);
    pll_register_string('faq_hub_group_price', 'Price and payment', $group);
    pll_register_string('faq_hub_group_devices', 'Devices and apps', $group);
    pll_register_string('faq_hub_group_setup', 'Setup and troubleshooting', $group);
    pll_register_string('faq_hub_group_choosing', 'Choosing a provider', $group);
    pll_register_string('faq_hub_group_other', 'More questions', $group);
});
