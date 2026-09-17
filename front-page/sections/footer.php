<!-- Footer Section (Design v2) -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">
                    <?php
                    // Light (white) logo, matching the header — the footer is dark.
                    // Same responsive set as the header; see the note there.
                    $iptv_footer_logo_dir = get_template_directory_uri() . '/images/logo/';
                    $iptv_footer_logo_2x  = $iptv_footer_logo_dir . 'light-logo-230x69.png';
                    $iptv_footer_logo_3x  = $iptv_footer_logo_dir . rawurlencode('light logo 500_150.png');
                    ?>
                    <img src="<?php echo esc_url($iptv_footer_logo_2x); ?>"
                        srcset="<?php echo esc_url($iptv_footer_logo_2x); ?> 230w, <?php echo esc_url($iptv_footer_logo_3x); ?> 500w"
                        sizes="120px" width="500" height="150" loading="lazy" decoding="async"
                        alt="Nordic IPTV" class="footer-logo-img">
                </div>
                <p class="footer-desc">
                    <?php echo esc_html(iptv_text('footer_desc', 'The leading IPTV service provider — 40,000+ live channels, 200,000+ movies and series, every sport, in 4K and 8K.')); ?>
                </p>

                <!-- Language selector -->
                <div class="footer-language-selector">
                    <div class="footer-country-selector" id="footerCountrySelector">
                        <button class="footer-country-btn" onclick="toggleFooterDropdown()">
                            <span id="footerSelectedFlag">🇺🇸</span>
                            <span id="footerSelectedCode">English</span>
                            <svg width="10" height="10" viewBox="0 0 10 10">
                                <path d="M1 3L5 7L9 3" stroke="currentColor" stroke-width="1.5" fill="none" />
                            </svg>
                        </button>
                        <div class="footer-country-dropdown" id="footerCountryDropdown">
                            <div class="footer-country-option" onclick="setFooterCurrency('en')">🇺🇸 English</div>
                            <div class="footer-country-option" onclick="setFooterCurrency('sv')">🇸🇪 Svenska</div>
                            <div class="footer-country-option" onclick="setFooterCurrency('no')">🇳🇴 Norsk</div>
                            <div class="footer-country-option" onclick="setFooterCurrency('dk')">🇩🇰 Dansk</div>
                            <div class="footer-country-option" onclick="setFooterCurrency('fi')">🇫🇮 Suomi</div>
                            <div class="footer-country-option" onclick="setFooterCurrency('is')">🇮🇸 Íslenska</div>
                            <div class="footer-country-option" onclick="setFooterCurrency('de')">🇩🇪 Deutsch</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            // Get all menu locations
            $locations = get_nav_menu_locations();

            // Helper to get menu name safely
            if (!function_exists('iptv_get_menu_title')) {
                function iptv_get_menu_title($loc, $default)
                {
                    global $locations;
                    if (isset($locations[$loc])) {
                        $menu = wp_get_nav_menu_object($locations[$loc]);
                        if ($menu)
                            return $menu->name;
                    }
                    return $default;
                }
            }
            ?>

            <div class="footer-col">
                <h4><?php echo esc_html(iptv_get_menu_title('footer_1', iptv_text('footer_head_plans', 'Plans'))); ?></h4>
                <?php if (has_nav_menu('footer_1')): ?>
                    <?php wp_nav_menu(array(
                        'theme_location' => 'footer_1',
                        'container' => false,
                        'fallback_cb' => false,
                    )); ?>
                <?php else: ?>
                    <?php
                    // These were four hardcoded "#pricing" anchors with English
                    // labels, so every language showed "1 Month Plan" and every
                    // one of them jumped to a section that only exists on the
                    // front page — on any other page the link did nothing.
                    //
                    // iptv_plan_url() resolves the plan page for the *current*
                    // language and iptv_plan_label() its duration in that
                    // language. Both are already used by the plan templates, so
                    // the footer cannot drift from them. iptv_plan_url() caches
                    // per months|lang, so this is four queries on a cache miss
                    // and none on a LiteSpeed hit.
                    $footer_home = function_exists('pll_home_url') ? pll_home_url() : home_url('/');
                    ?>
                    <div>
                        <?php foreach (array(1, 3, 6, 12) as $footer_plan_months) : ?>
                            <?php
                            $footer_plan_url = function_exists('iptv_plan_url')
                                ? iptv_plan_url($footer_plan_months)
                                : '';

                            // No plan page published for this length yet: keep a
                            // usable link rather than a dead one.
                            if (!$footer_plan_url) {
                                $footer_plan_url = trailingslashit($footer_home) . '#pricing';
                            }

                            $footer_plan_label = function_exists('iptv_plan_label')
                                ? iptv_plan_label($footer_plan_months)
                                : '';
                            if (!$footer_plan_label) {
                                continue;
                            }
                            ?>
                            <a href="<?php echo esc_url($footer_plan_url); ?>"><?php echo esc_html($footer_plan_label); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="footer-col">
                <h4><?php echo esc_html(iptv_get_menu_title('footer_2', iptv_text('footer_head_links', 'Useful Links'))); ?></h4>
                <?php if (has_nav_menu('footer_2')): ?>
                    <?php wp_nav_menu(array(
                        'theme_location' => 'footer_2',
                        'container' => false,
                        'fallback_cb' => false,
                    )); ?>
                <?php else: ?>
                    <?php
                    // home_url() ignores the current language, so this column used
                    // to send Swedish visitors to the English blog and guide.
                    $home = function_exists('pll_home_url') ? pll_home_url() : home_url('/');
                    // The FAQ hub is a post type archive, not a page, so it is
                    // passed as a URL. This link is the point of the archive:
                    // without it the 100 FAQ posts stay orphaned, which is why
                    // Google discovered them from the sitemap and never crawled
                    // one. get_post_type_archive_link() is language-aware.
                    $faq_archive = get_post_type_archive_link('faq');

                    iptv_footer_links(array(
                        array('slug' => 'blog', 'key' => 'footer_link_blog', 'label' => 'Blog',
                              'fallback' => trailingslashit($home) . 'blog/'),
                        array('slug' => 'iptv-guide-setup-apps-devices-tips', 'key' => 'footer_link_guide', 'label' => 'Setup Guide'),
                        array('slug' => 'm3u-playlist-convert-your-m3u-url', 'key' => 'footer_link_m3u', 'label' => 'M3U Converter'),
                        array('url' => $faq_archive ? $faq_archive : '', 'key' => 'footer_link_faq', 'label' => 'FAQ'),
                        array('slug' => 'contact-us', 'key' => 'footer_link_contact', 'label' => 'Contact Us'),
                        array('url' => 'https://panel.nordictv.io/login', 'key' => 'footer_link_account', 'label' => 'My Account'),
                    ));
                    ?>
                <?php endif; ?>
            </div>

            <div class="footer-col">
                <h4><?php echo esc_html(iptv_get_menu_title('footer_3', iptv_text('footer_head_legal', 'Legal'))); ?></h4>
                <?php if (has_nav_menu('footer_3')): ?>
                    <?php wp_nav_menu(array(
                        'theme_location' => 'footer_3',
                        'container' => false,
                        'fallback_cb' => false,
                    )); ?>
                <?php else: ?>
                    <?php
                    // These legal pages resolve through Polylang, so each footer
                    // points to the equivalent page in the current language.
                    iptv_footer_links(array(
                        array('slug' => 'about-us', 'key' => 'footer_link_about', 'label' => 'About Us'),
                        array('slug' => 'privacy-policy-2', 'key' => 'footer_link_privacy', 'label' => 'Privacy Policy'),
                        array('slug' => 'terms-of-service', 'key' => 'footer_link_terms', 'label' => 'Terms of Service'),
                        array('slug' => 'return-refund-policy', 'key' => 'footer_link_refund', 'label' => 'Return & Refund Policy'),
                        array('slug' => 'dmca-copyright-notice', 'key' => 'footer_link_dmca', 'label' => 'DMCA & Copyright Notice'),
                    ));
                    ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer-bottom">
            Nordic IPTV | <?php echo esc_html(iptv_text('footer_copyright', 'All Rights Reserved')); ?>
            <?php echo date('Y'); ?>
        </div>
    </div>
</footer>
