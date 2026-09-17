<?php
/**
 * My IPTV Theme - Functions and definitions
 */

// Main site URL for cross-site cart (all subsites use main site checkout)
define('IPTV_MAIN_SITE_URL', 'https://nordictv.io');

// Auto-flush rewrite rules when Rank Math sitemap rules go missing.
// On Hostinger/LiteSpeed, rewrite rules can get wiped after server restarts or
// plugin updates, causing sitemap_index.xml to 404 and fall through to the blog page.
add_action('init', function () {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    // Prevent LiteSpeed from caching any sitemap response
    if (strpos($request_uri, 'sitemap') !== false) {
        if (!headers_sent()) {
            header('X-LiteSpeed-Cache-Control: no-cache, no-store');
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }
    }

    // If a sitemap URL is requested but Rank Math's rewrite rule isn't registered,
    // flush rewrite rules once so WordPress picks them up without manual intervention.
    if (strpos($request_uri, 'sitemap') !== false) {
        $rules = get_option('rewrite_rules');
        $has_sitemap_rule = !empty($rules) && !empty(preg_grep('/sitemap/', array_keys($rules)));
        if (!$has_sitemap_rule) {
            flush_rewrite_rules(false);
        }
    }
}, 1);

// Disable WordPress Twemoji to use native system emojis
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

// Meta Pixel - fires on ALL pages (front page, checkout, product pages, subsites).
// Now lives in inc/performance.php, which keeps the PageView synchronous but
// lets fbevents.js download once the page is usable.

/**
 * Cache-busting version for a theme asset, from its file mtime.
 *
 * Hardcoded version strings only bust the cache if someone remembers to bump
 * them by hand, and that is easy to miss: a deploy that edits a stylesheet but
 * leaves its version alone serves returning visitors the stale cached copy off
 * the unchanged URL. mtime changes whenever the file does, so the URL does too.
 *
 * @param string $relative Theme-root-relative path, e.g. 'front-page/css/base.css'.
 * @return string|false mtime, or false so WP falls back to its own version.
 */
function iptv_asset_version($relative)
{
    $path = get_template_directory() . '/' . ltrim($relative, '/');

    return file_exists($path) ? (string) filemtime($path) : false;
}

/**
 * Purge the LiteSpeed page cache once after a deploy.
 *
 * Most templates inline their CSS into a <style> block, so the stylesheet is
 * baked into the cached HTML rather than fetched from a URL -- versioning the
 * enqueues cannot reach it. WP Pusher rewrites the files in place and the page
 * cache never notices, so visitors keep getting HTML built from the previous
 * CSS until it expires on its own (max-age=604800, a week).
 *
 * Fingerprint the inlined stylesheets and purge when it moves. The option is
 * autoloaded, so the common case is a comparison against already-loaded data.
 */
add_action('init', function () {
    $fingerprint = (string) max(
        (int) iptv_asset_version('front-page/css/design-v2.css'),
        (int) iptv_asset_version('front-page/css/design-v2-sections.css'),
        (int) iptv_asset_version('front-page/css/sticky-cta.css')
    );

    if ($fingerprint === '0' || get_option('iptv_deployed_assets') === $fingerprint) {
        return;
    }

    // Written before the purge so a failure here cannot leave every subsequent
    // request purging the whole cache.
    update_option('iptv_deployed_assets', $fingerprint, true);
    do_action('litespeed_purge_all');
}, 5);

// Enqueue theme styles
function my_iptv_enqueue_styles()
{
    wp_enqueue_style('my-iptv-style', get_stylesheet_uri(), array(), iptv_asset_version('style.css'));

    // Design v2 typography is self-hosted now. inc/performance.php inlines the
    // @font-face block and preloads the latin subsets, which removes the
    // render-blocking request to fonts.googleapis.com along with the extra DNS
    // lookup, TLS handshake and redirect it needed.

    // Load product page styles on single product pages
    if (function_exists('is_product') && is_product()) {
        wp_enqueue_style('iptv-variables', get_template_directory_uri() . '/front-page/css/variables.css', array(), iptv_asset_version('front-page/css/variables.css'));
        wp_enqueue_style('iptv-redesign', get_template_directory_uri() . '/front-page/css/redesign-theme.css', array(), iptv_asset_version('front-page/css/redesign-theme.css'));
        wp_enqueue_style('iptv-base', get_template_directory_uri() . '/front-page/css/base.css', array(), iptv_asset_version('front-page/css/base.css'));
        wp_enqueue_style('iptv-header', get_template_directory_uri() . '/front-page/css/header.css', array(), iptv_asset_version('front-page/css/header.css'));
        wp_enqueue_style('iptv-footer', get_template_directory_uri() . '/front-page/css/footer.css', array(), iptv_asset_version('front-page/css/footer.css'));
        wp_enqueue_style('iptv-responsive', get_template_directory_uri() . '/front-page/css/responsive.css', array(), iptv_asset_version('front-page/css/responsive.css'));
        wp_enqueue_style('iptv-product-page', get_template_directory_uri() . '/front-page/css/product-page.css', array(), iptv_asset_version('front-page/css/product-page.css'));
        // Design v2 must load last so its tokens override the older layers.
        wp_enqueue_style('iptv-design-v2', get_template_directory_uri() . '/front-page/css/design-v2.css', array(), iptv_asset_version('front-page/css/design-v2.css'));
        wp_enqueue_style('iptv-design-v2-sections', get_template_directory_uri() . '/front-page/css/design-v2-sections.css', array('iptv-design-v2'), iptv_asset_version('front-page/css/design-v2-sections.css'));
    }
}
add_action('wp_enqueue_scripts', 'my_iptv_enqueue_styles', 20);

// Campaign links may arrive on any WordPress page. Keep their campaign ID when
// visitors use any CTA that goes to the pricing section or panel checkout.
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    wp_enqueue_script(
        'iptv-campaign-attribution',
        get_template_directory_uri() . '/front-page/js/campaign-attribution.js',
        array(),
        iptv_asset_version('front-page/js/campaign-attribution.js'),
        true
    );
}, 20);

// Register FAQ custom post type with a clean /faq/ permalink base
function iptv_register_faq_post_type() {
    register_post_type('faq', [
        'labels' => [
            'name'               => 'FAQs',
            'singular_name'      => 'FAQ',
            'add_new_item'       => 'Add New FAQ',
            'edit_item'          => 'Edit FAQ',
            'view_item'          => 'View FAQ',
            'search_items'       => 'Search FAQs',
            'not_found'          => 'No FAQs found',
            'not_found_in_trash' => 'No FAQs found in trash',
        ],
        'public'            => true,
        'show_in_rest'      => true,
        'supports'          => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
        // The 100 FAQ posts were "Discovered - currently not indexed" in Search
        // Console: nothing on the site linked to them, so the only route in was
        // the sitemap and Google never spent a crawl on one. An archive gives
        // them a hub (/faq, /sv/faq - the latter already has 383 impressions
        // against a 404), and nav menus let that hub be linked like any page.
        'has_archive'       => true,
        'rewrite'           => ['slug' => 'faq', 'with_front' => false],
        'menu_icon'         => 'dashicons-editor-help',
        'show_in_nav_menus' => true,
    ]);
}
add_action('init', 'iptv_register_faq_post_type');

/**
 * An empty FAQ archive is not a page.
 *
 * The FAQ posts exist in Swedish only, so switching the archive on also
 * created /faq, /no/faq, /dk/faq, /fi/faq and /is/faq — five indexable pages
 * with nothing on them. Those are soft 404s, which is the very thing this
 * change set exists to remove; trading 81 uncrawled FAQs for five empty
 * archives would be no trade at all.
 *
 * Serving a real 404 keeps them out of the index and out of the sitemap, and
 * hands them to inc/legacy-redirects.php, which sends each one to that
 * language's FAQ page or, where there is none, to its home page.
 */
add_action('template_redirect', function () {
    global $wp_query;

    if (!is_post_type_archive('faq') || $wp_query->post_count > 0) {
        return;
    }

    $wp_query->set_404();
    status_header(404);
    nocache_headers();
}, 15);

/**
 * Keep the redirecting FAQ archive out of the sitemap.
 *
 * Rank Math lists a post type archive using get_post_type_archive_link(),
 * which resolves in the language the sitemap is built in — the default one,
 * English, which is the single language with no FAQ posts. So faq-sitemap.xml
 * advertised /faq: a URL the rule above 404s and the redirect engine then
 * sends elsewhere. Publishing a redirect in a sitemap is the exact defect this
 * change set is clearing out of Search Console.
 *
 * The archive link has its own filter — `rank_math/sitemap/entry` never sees
 * it, which is why filtering there changed nothing.
 *
 * Only the empty case is dropped. /sv/faq, the archive holding the 92
 * questions, keeps its entry, and is linked from the footer besides.
 */
add_filter('rank_math/sitemap/post_type_archive_link', function ($link, $post_type) {
    if ($post_type !== 'faq' || !$link) {
        return $link;
    }

    // The language has to be named. Rank Math builds one sitemap for the whole
    // site rather than one per language, and in that context Polylang does not
    // scope the query — an unscoped count returns all 92 Swedish FAQs and the
    // archive looks populated in every language, including the one where it is
    // empty. So read the language out of the URL Rank Math is proposing.
    $lang = function_exists('pll_default_language') ? pll_default_language('slug') : '';
    $path = trim((string) wp_parse_url($link, PHP_URL_PATH), '/');
    $head = strtok($path, '/');

    if (function_exists('nordictv_lang_slugs') && in_array($head, nordictv_lang_slugs(), true)) {
        $lang = $head;
    }

    $has_faqs = new WP_Query([
        'post_type'              => 'faq',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'lang'                   => $lang,
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
    ]);

    return empty($has_faqs->posts) ? false : $link;
}, 10, 2);

/**
 * Flush rewrite rules once after a deploy that changes them.
 *
 * register_post_type() adds its rules to the in-memory set but never writes
 * them, so turning on the FAQ archive leaves /faq and /sv/faq 404ing until
 * something flushes. The existing auto-flush at the top of this file only
 * fires for sitemap URLs whose rule has gone missing, which this is not.
 *
 * Same shape as the asset purge above: the option is written before the work,
 * so a failure cannot leave every request flushing. Bump the version string
 * whenever a future change touches rewrite rules.
 */
add_action('init', function () {
    $version = 'faq-archive-1';

    if (get_option('iptv_rewrite_version') === $version) {
        return;
    }

    update_option('iptv_rewrite_version', $version, true);
    flush_rewrite_rules(false);
}, 99);

function iptv_register_faq_rest_meta() {
    $fields = ['rank_math_focus_keyword', 'rank_math_title', 'rank_math_description'];
    foreach ($fields as $field) {
        register_post_meta('faq', $field, [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);
    }
}
add_action('rest_api_init', 'iptv_register_faq_rest_meta');

add_filter('is_protected_meta', function($protected, $meta_key) {
    $rank_math_fields = [
        'rank_math_focus_keyword',
        'rank_math_title',
        'rank_math_description',
    ];
    if (in_array($meta_key, $rank_math_fields)) {
        return false;
    }
    return $protected;
}, 10, 2);

// Add inline CSS for product page fixes
function my_iptv_product_page_inline_css()
{
    if (function_exists('is_product') && is_product()) {
        ?>
        <style id="iptv-product-fixes">
            /* ===== HIDE ELEMENTS ===== */
            .woocommerce div.product .product_meta,
            .woocommerce div.product form.cart .quantity,
            .woocommerce div.product form.cart .reset_variations,
            .woocommerce-product-gallery__trigger {
                display: none !important;
            }

            /* ===== CONTAINER ===== */
            .product-content,
            .wc-content {
                max-width: 1100px !important;
                margin: 0 auto !important;
                padding: 6rem 2rem 2rem !important;
            }

            /* ===== MAIN PRODUCT GRID ===== */
            .woocommerce div.product {
                display: grid !important;
                grid-template-columns: 500px 1fr !important;
                gap: 2.5rem !important;
                background: #fff !important;
                border-radius: 16px !important;
                padding: 2rem !important;
                box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06) !important;
                align-items: start !important;
            }

            /* ===== PRODUCT IMAGE ===== */
            .woocommerce div.product div.images {
                background: linear-gradient(135deg, var(--bg-section) 0%, #FFFFFF 100%) !important;
                border-radius: 12px !important;
                padding: 1.25rem !important;
                width: 100% !important;
                float: none !important;
            }

            .woocommerce div.product div.images img {
                width: 100% !important;
                height: auto !important;
                border-radius: 8px !important;
            }

            /* ===== PRODUCT SUMMARY ===== */
            .woocommerce div.product div.summary {
                padding: 0 !important;
                width: 100% !important;
                float: none !important;
            }

            /* Title */
            .woocommerce div.product .product_title {
                font-size: 1.75rem !important;
                font-weight: 700 !important;
                color: #1F2937 !important;
                margin: 0 0 0.5rem !important;
                line-height: 1.3 !important;
            }

            /* Price - show main price range */
            .woocommerce div.product p.price,
            .woocommerce div.product .summary>.price {
                font-size: 1.5rem !important;
                font-weight: 700 !important;
                color: var(--color-teal) !important;
                margin: 0 0 1.25rem !important;
            }

            /* ===== FORM LAYOUT - Stacked vertically, centered ===== */
            .woocommerce div.product form.cart {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                gap: 1rem !important;
                margin: 0 auto 1.25rem !important;
                max-width: 350px !important;
                width: 100% !important;
            }

            /* Variations table - full width stacked */
            .woocommerce div.product form.cart table.variations {
                width: 100% !important;
                margin: 0 !important;
                border: none !important;
            }

            .woocommerce div.product form.cart table.variations tbody {
                display: block !important;
                width: 100% !important;
            }

            .woocommerce div.product form.cart table.variations tr {
                display: block !important;
                width: 100% !important;
            }

            .woocommerce div.product form.cart table.variations td {
                padding: 0 !important;
                display: block !important;
                width: 100% !important;
            }

            .woocommerce div.product form.cart table.variations td.label {
                margin-bottom: 0.5rem !important;
            }

            .woocommerce div.product form.cart table.variations td.label label {
                font-weight: 600 !important;
                color: #374151 !important;
                font-size: 0.9rem !important;
            }

            .woocommerce div.product form.cart table.variations select {
                width: 100% !important;
                padding: 0.875rem 1rem !important;
                border: 2px solid #E5E7EB !important;
                border-radius: 8px !important;
                font-size: 0.9rem !important;
                background: #fff !important;
                height: auto !important;
                appearance: none !important;
                -webkit-appearance: none !important;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236B7280' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E") !important;
                background-repeat: no-repeat !important;
                background-position: right 1rem center !important;
                padding-right: 2.5rem !important;
            }

            /* Variation price display - HIDE it since main price is shown */
            .woocommerce div.product form.cart .woocommerce-variation.single_variation {
                display: none !important;
            }

            /* Single variation wrap + button */
            .woocommerce div.product form.cart .single_variation_wrap {
                width: 100% !important;
                display: block !important;
            }

            .woocommerce div.product form.cart .woocommerce-variation-add-to-cart {
                width: 100% !important;
                display: block !important;
            }

            .woocommerce div.product form.cart .button {
                width: 100% !important;
                background: linear-gradient(135deg, var(--color-teal) 0%, var(--color-indigo-hover) 100%) !important;
                color: #fff !important;
                padding: 1rem 2rem !important;
                border-radius: 8px !important;
                font-weight: 600 !important;
                font-size: 1rem !important;
                border: none !important;
                cursor: pointer !important;
                height: auto !important;
            }

            .woocommerce div.product form.cart .button:hover {
                opacity: 0.9 !important;
            }

            /* Short Description */
            .woocommerce div.product .woocommerce-product-details__short-description {
                color: #6B7280 !important;
                font-size: 0.9rem !important;
                line-height: 1.6 !important;
                margin: 0 !important;
            }

            /* ===== TABS ===== */
            .woocommerce div.product .woocommerce-tabs {
                grid-column: 1 / -1 !important;
                margin-top: 1.5rem !important;
            }

            .woocommerce div.product .woocommerce-tabs ul.tabs {
                display: flex !important;
                gap: 0 !important;
                border: none !important;
                background: #F3F4F6 !important;
                border-radius: 8px !important;
                padding: 4px !important;
                margin: 0 0 1rem !important;
            }

            .woocommerce div.product .woocommerce-tabs ul.tabs::before {
                display: none !important;
            }

            .woocommerce div.product .woocommerce-tabs ul.tabs li {
                border: none !important;
                background: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .woocommerce div.product .woocommerce-tabs ul.tabs li::before,
            .woocommerce div.product .woocommerce-tabs ul.tabs li::after {
                display: none !important;
            }

            .woocommerce div.product .woocommerce-tabs ul.tabs li a {
                padding: 0.6rem 1.25rem !important;
                color: #6B7280 !important;
                font-weight: 500 !important;
                font-size: 0.875rem !important;
                border-radius: 6px !important;
                display: block !important;
            }

            .woocommerce div.product .woocommerce-tabs ul.tabs li.active a {
                background: #fff !important;
                color: var(--color-teal) !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
            }

            .woocommerce div.product .woocommerce-tabs .panel h2 {
                display: none !important;
            }

            /* ===== RELATED PRODUCTS ===== */
            .woocommerce .related.products {
                grid-column: 1 / -1 !important;
                margin-top: 1.5rem !important;
            }

            .woocommerce .related.products>h2 {
                font-size: 1.125rem !important;
                font-weight: 600 !important;
                margin: 0 0 1rem !important;
                color: #1F2937 !important;
            }

            .woocommerce .related.products ul.products {
                display: flex !important;
                flex-wrap: nowrap !important;
                justify-content: flex-start !important;
                gap: 1rem !important;
                overflow-x: scroll !important;
                -webkit-overflow-scrolling: touch !important;
                scroll-behavior: smooth !important;
                padding: 0.5rem 0 1rem !important;
                margin: 0 !important;
                list-style: none !important;
            }

            .woocommerce .related.products ul.products::-webkit-scrollbar {
                height: 6px !important;
            }

            .woocommerce .related.products ul.products::-webkit-scrollbar-track {
                background: #E5E7EB !important;
                border-radius: 3px !important;
            }

            .woocommerce .related.products ul.products::-webkit-scrollbar-thumb {
                background: var(--color-teal) !important;
                border-radius: 3px !important;
            }

            .woocommerce .related.products ul.products li.product {
                flex: 0 0 200px !important;
                min-width: 200px !important;
                background: #fff !important;
                border-radius: 10px !important;
                box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06) !important;
                overflow: hidden !important;
                text-align: center !important;
                margin: 0 !important;
            }

            .woocommerce .related.products ul.products li.product a img {
                width: 100% !important;
                height: 100px !important;
                object-fit: contain !important;
                background: var(--bg-section) !important;
                padding: 0.5rem !important;
            }

            .woocommerce .related.products ul.products li.product .woocommerce-loop-product__title {
                font-size: 0.8rem !important;
                font-weight: 600 !important;
                padding: 0.5rem 0.5rem 0.25rem !important;
                margin: 0 !important;
                color: #1F2937 !important;
            }

            .woocommerce .related.products ul.products li.product .price {
                font-size: 0.8rem !important;
                color: var(--color-teal) !important;
                font-weight: 600 !important;
                padding: 0 0.5rem 0.5rem !important;
            }

            .woocommerce .related.products ul.products li.product .button {
                display: block !important;
                margin: 0 0.5rem 0.5rem !important;
                padding: 0.4rem !important;
                background: var(--color-teal) !important;
                color: #fff !important;
                border-radius: 6px !important;
                font-size: 0.75rem !important;
                font-weight: 600 !important;
            }

            /* Footer logo */
            .footer-logo-img {
                height: 36px !important;
                max-height: 36px !important;
            }

            /* ===== RESPONSIVE ===== */
            @media (max-width: 768px) {

                /* Fix page overflow */
                body,
                html {
                    overflow-x: hidden !important;
                }

                .product-content,
                .wc-content {
                    padding: 5rem 1rem 1rem !important;
                    max-width: 100% !important;
                    overflow-x: hidden !important;
                }

                .woocommerce div.product {
                    grid-template-columns: 1fr !important;
                    gap: 1.5rem !important;
                    padding: 1rem !important;
                    overflow: hidden !important;
                }

                .woocommerce div.product div.images {
                    max-width: 285px !important;
                    margin: 0 auto !important;
                }

                .woocommerce div.product .product_title {
                    font-size: 1.35rem !important;
                    text-align: center !important;
                }

                .woocommerce div.product p.price {
                    text-align: center !important;
                }

                /* Form - stacked on mobile with padding */
                .woocommerce div.product form.cart {
                    flex-direction: column !important;
                    flex-wrap: wrap !important;
                    align-items: stretch !important;
                    width: 100% !important;
                    padding: 0 1rem !important;
                    gap: 0.75rem !important;
                }

                .woocommerce div.product form.cart table.variations {
                    width: 100% !important;
                }

                .woocommerce div.product form.cart table.variations tbody,
                .woocommerce div.product form.cart table.variations tr {
                    display: block !important;
                    width: 100% !important;
                }

                .woocommerce div.product form.cart table.variations td {
                    display: block !important;
                    width: 100% !important;
                    padding: 0 !important;
                }

                .woocommerce div.product form.cart table.variations td.label {
                    margin-bottom: 0.5rem !important;
                }

                .woocommerce div.product form.cart table.variations select {
                    width: 100% !important;
                    min-width: unset !important;
                    padding: 1rem !important;
                }

                .woocommerce div.product form.cart .single_variation_wrap,
                .woocommerce div.product form.cart .woocommerce-variation-add-to-cart {
                    width: 100% !important;
                    display: block !important;
                }

                .woocommerce div.product form.cart .button {
                    width: 100% !important;
                    min-width: unset !important;
                    padding: 1rem !important;
                    height: auto !important;
                    margin-top: 0.5rem !important;
                }

                /* Tabs - vertical on mobile */
                .woocommerce div.product .woocommerce-tabs ul.tabs {
                    flex-direction: column !important;
                    gap: 4px !important;
                }

                .woocommerce div.product .woocommerce-tabs ul.tabs li {
                    width: 100% !important;
                }

                .woocommerce div.product .woocommerce-tabs ul.tabs li a {
                    text-align: center !important;
                }

                /* Related Products - carousel with no visible scrollbar */
                .woocommerce .related.products {
                    overflow: hidden !important;
                }

                .woocommerce .related.products ul.products {
                    justify-content: flex-start !important;
                    overflow-x: auto !important;
                    scroll-snap-type: x mandatory !important;
                    -webkit-overflow-scrolling: touch !important;
                    scrollbar-width: none !important;
                    -ms-overflow-style: none !important;
                }

                .woocommerce .related.products ul.products::-webkit-scrollbar {
                    display: none !important;
                }

                .woocommerce .related.products ul.products li.product {
                    flex: 0 0 160px !important;
                    scroll-snap-align: start !important;
                }
            }
        </style>
        <script>
            jQuery(function ($) {
                var $form = $('form.variations_form');
                var $mainPrice = $('.summary > .price').first();
                var originalPrice = $mainPrice.html();

                $form.on('found_variation', function (e, variation) {
                    if (variation.price_html) {
                        $mainPrice.html(variation.price_html);
                    }
                });

                $form.on('reset_data', function () {
                    $mainPrice.html(originalPrice);
                });
            });
        </script>
        <?php
    }
}
add_action('wp_head', 'my_iptv_product_page_inline_css', 999);



// Theme setup
function my_iptv_theme_setup()
{
    // Add title tag support
    add_theme_support('title-tag');

    // Add HTML5 support
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));

    // Register Navigation Menus
    register_nav_menus(array(
        'primary' => esc_html__('Header Menu', 'my-iptv'),
        'footer_1' => esc_html__('Footer - Quick Links', 'my-iptv'),
        'footer_2' => esc_html__('Footer - Support', 'my-iptv'),
        'footer_3' => esc_html__('Footer - Legal', 'my-iptv'),
    ));

    // Add WooCommerce support
    add_theme_support('woocommerce');
}
add_action('after_setup_theme', 'my_iptv_theme_setup');

// Language is the visitor's choice, remembered between visits. Replaces the old
// inc/geo-redirect.php, which picked a language from the visitor's IP.
require_once get_template_directory() . '/inc/language-preference.php';

// Dequeues unused plugin assets, self-hosts the webfonts, and defers the
// analytics tags. No markup, copy or layout changes.
require_once get_template_directory() . '/inc/performance.php';

// SEO Manager disabled - Using Rank Math Pro instead
// require_once get_template_directory() . '/inc/seo-manager.php';

// Include Network Cloner utility
require_once get_template_directory() . '/inc/network-cloner.php';

// Include Multi-Currency Pricing Settings
require_once get_template_directory() . '/inc/currency-settings.php';

// Keeps iptv_conversion_rates fed from CurrencyFreaks on a daily cron. Free tier
// is 1,000 requests/month, so the API is never touched on a front-end request.
require_once get_template_directory() . '/inc/currency-rates-api.php';

// Resolve a page by slug and get the current language's translation of it.
// Used by the footer, which previously hardcoded '#' for every legal link.
require_once get_template_directory() . '/inc/page-links.php';

// Support cards shared by the front page section and the [nordictv_contact]
// shortcode used on the Contact page.
require_once get_template_directory() . '/inc/contact-cards.php';

// Front page copy lookup: ACF on the translated front page, then Polylang strings.
// Replaces the old "Content Localizing" admin screen (inc/content-settings.php),
// which stored copy in an `iptv_content` option keyed by the retired multisite slugs.
require_once get_template_directory() . '/inc/iptv-text.php';
require_once get_template_directory() . '/inc/homepage-acf-migration.php';

// Include User Guide Shortcode (displays posts from user-guide category)
require_once get_template_directory() . '/inc/user-guide-shortcode.php';

// Include Product Setup Utility (Ensures WooCommerce products exist)
require_once get_template_directory() . '/inc/product-setup.php';

// Include Bulk Product Editor
require_once get_template_directory() . '/inc/admin-bulk-editor.php';

// Include Price Sync Utility (for syncing variation prices from main site to subsites)
require_once get_template_directory() . '/inc/sync-prices.php';


// Retires the `sport` post type (unregisters it, drafts its posts). Loaded
// before the string helpers below so sport/inc/sport-strings.php can stay out.
require_once get_template_directory() . '/inc/sport-retire.php';

// One-shot scrub of broadcaster / competition names left in post content.
// Safe to delete along with the file once the option records it has run.
require_once get_template_directory() . '/inc/trademark-scrub.php';

// Temporary: exposes the WP Activity Log tables to an admin-only ability so the
// rankmathseo account's history can be audited. Delete file + line when done.
require_once get_template_directory() . '/inc/activity-log-reader.php';

// Temporary: reads the activity log for events on the two competitor posts
// that keep reverting. Read-only, one-shot. Delete with its file once answered.
require_once get_template_directory() . '/inc/post-revert-audit.php';

// Creates the German copy of every page the other languages have, joins each to
// its translation group, and holds the German front-page copy. Idempotent and
// inert after its build number; bump that to repair, delete both to retire it.
require_once get_template_directory() . '/inc/german-pages-setup.php';

// Assigns the Polylang language and translation groups for the six
// competitor-brand posts, which the REST connector could not set. Inert after
// its build number; delete both once it has run.
require_once get_template_directory() . '/inc/competitor-posts-setup.php';

// Include Polylang string helpers for channel / sport / series templates.
// sport-strings.php is not loaded: the post type it serves is retired, and
// registering its strings would keep them in the Polylang translation UI.
require_once get_template_directory() . '/inc/channel-strings.php';
require_once get_template_directory() . '/series/inc/series-strings.php';
require_once get_template_directory() . '/inc/front-page-strings.php';
require_once get_template_directory() . '/inc/offer-strings.php';

// Site Config options page (checkout URLs + pricing configurator defaults)
require_once get_template_directory() . '/inc/site-config.php';

// Rank Math analysis for the front page. The page body is empty — the sections
// render everything — so Rank Math needs to be handed the rendered content.
require_once get_template_directory() . '/inc/front-page-seo.php';

// Sticky mobile CTA bar (countdown + pricing/trial buttons) — loads after
// site-config.php and iptv-text.php, both of which it reads through.
require_once get_template_directory() . '/inc/sticky-cta.php';

// Plan pages (template-plan.php). plan-strings.php first: it holds the
// audience and FAQ default copy that plan-data.php reads, as well as the
// Polylang registrations that make every string on these pages translatable.
require_once get_template_directory() . '/plan/inc/plan-strings.php';

// Prices, savings, checkout links and the sibling-page lookup behind the
// compare table. Loads after site-config.php (checkout_base_url),
// currency-settings.php (the price table) and iptv-text.php (shared labels),
// all of which it reads through.
require_once get_template_directory() . '/plan/inc/plan-data.php';

// One-time provisioning of the 24 plan pages (4 lengths x 6 languages), their
// Polylang languages and their translation groups. Guarded by an option; see
// the file header for how to re-run it.
require_once get_template_directory() . '/plan/inc/plan-pages-setup.php';

// Hands Rank Math the copy a plan page actually renders. Without it the
// content tests score against an all-but-empty post_content.
require_once get_template_directory() . '/plan/inc/plan-seo.php';

// Copies that same followed-domain whitelist into Rank Math's own "Nofollow
// Exclude Domains" setting, so the editor's content analysis agrees with what
// the page actually renders. Loads after plan-seo.php, whose list it reads.
require_once get_template_directory() . '/inc/rankmath-followed-domains.php';

// Keeps the .htaccess rewrite block pointed at the document root now that Site
// Address carries a /nordiciptv path. Core resolves the prefixed URLs on its
// own; this only stops a permalink flush from writing rules for a directory
// that does not exist.
require_once get_template_directory() . '/inc/keyword-prefix-routing.php';

// 301s for the URL space the previous site left behind — retired languages,
// WooCommerce, /setup-guides/, /sports/. Runs only on requests WordPress has
// already resolved to a 404, so it can never shadow a live page. Loads after
// plan-data.php and page-links.php, whose lookups it reuses.
require_once get_template_directory() . '/inc/legacy-redirects.php';

/**
 * WooCommerce: Redirect all cart operations to checkout page
 * Since the cart page is disabled, we redirect to checkout instead
 * NOTE: Commented out — all checkout flows now redirect to https://panel.nordictv.io/
 */
/*
if (class_exists('WooCommerce')) {
    add_filter('woocommerce_get_cart_url', function ($url) {
        return wc_get_checkout_url();
    });

    add_filter('woocommerce_add_to_cart_redirect', function ($url) {
        return wc_get_checkout_url();
    });

    // After removing an item, redirect to checkout page
    add_filter('woocommerce_cart_redirect_after_error', function ($url) {
        return wc_get_checkout_url();
    }, 10, 1);

    // Force only 1 product in cart with quantity 1
    // Clear cart before adding new product
    add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity) {
        WC()->cart->empty_cart();
        return $passed;
    }, 10, 3);

    // Force quantity to 1
    add_filter('woocommerce_add_to_cart_quantity', function ($quantity, $product_id) {
        return 1;
    }, 10, 2);
}
*/

// Redirect cart page to checkout, shop page to home (only if WooCommerce is active)
// NOTE: Commented out — checkout redirects now go to https://panel.nordictv.io/
/*
add_action('template_redirect', function () {
    if (!class_exists('WooCommerce'))
        return;

    if (is_cart()) {
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    if (is_shop() || is_product_category() || is_product_tag()) {
        wp_redirect(home_url('/'));
        exit;
    }
});
*/

// Handle add-by-sku parameter from subsites - lookup product by SKU and add to cart
// NOTE: Commented out — checkout redirects now go to https://panel.nordictv.io/
/*
add_action('template_redirect', function () {
    if (!class_exists('WooCommerce'))
        return;

    // Check for add-by-sku parameter
    if (!isset($_GET['add-by-sku']) || empty($_GET['add-by-sku']))
        return;

    $sku = sanitize_text_field($_GET['add-by-sku']);

    // Lookup product ID by SKU on this site (main site)
    $product_id = wc_get_product_id_by_sku($sku);

    if (!$product_id) {
        // SKU not found, redirect to homepage
        wp_safe_redirect(home_url('/'));
        exit;
    }

    $product = wc_get_product($product_id);

    if (!$product) {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    // Check if this is a variation
    if ($product->is_type('variation')) {
        // Get parent product ID
        $parent_id = $product->get_parent_id();
        $variation_id = $product_id;

        // Get variation attributes
        $variation_attributes = $product->get_attributes();

        // Add variation to cart
        WC()->cart->add_to_cart($parent_id, 1, $variation_id, $variation_attributes);
    } else {
        // Simple product - add directly
        WC()->cart->add_to_cart($product_id);
    }

    // Redirect to checkout
    wp_safe_redirect(wc_get_checkout_url());
    exit;
}, 5); // Run early (priority 5)
*/

/**
 * WooCommerce: Change "Add to cart" to "Buy Now" and redirect to checkout
 * (Only if WooCommerce is active)
 */
if (class_exists('WooCommerce')) {
    // Change button text on single product page
    add_filter('woocommerce_product_single_add_to_cart_text', function () {
        return 'Buy Now';
    });

    // Change button text on product loops
    add_filter('woocommerce_product_add_to_cart_text', function () {
        return 'Buy Now';
    });

    // Redirect to checkout after adding to cart
    // NOTE: Commented out — redirects now go to https://panel.nordictv.io/
    // add_filter('woocommerce_add_to_cart_redirect', function () {
    //     return wc_get_checkout_url();
    // });

    // Hide 24h Trial from related products
    add_filter('woocommerce_related_products', function ($related_posts) {
        // Get product IDs with "24h Trial" in the title
        $trial_products = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            's' => '24h Trial', // Use 's' for keyword search as 'title' isn't a valid arg for get_posts
            'fields' => 'ids',
        ));

        // Also search for "trial" in slug
        $trial_by_slug = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'name' => 'trial', // 'name' queries by slug
            'fields' => 'ids',
        ));

        $exclude_ids = array_merge($trial_products, $trial_by_slug);

        return array_diff($related_posts, $exclude_ids);
    });
}

// Redirect from checkout to home if cart is empty (only if WooCommerce is active)
add_action('template_redirect', function () {
    if (!class_exists('WooCommerce'))
        return;

    if (is_checkout() && !is_wc_endpoint_url()) {
        if (WC()->cart && WC()->cart->is_empty()) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
    }
});

// Product page: redirect "Buy Now" to panel on the main site
add_action('wp_footer', function () {
    if (!class_exists('WooCommerce') || !function_exists('is_product'))
        return;
    if (!is_product())
        return;

    $main_site_url = defined('IPTV_MAIN_SITE_URL') ? IPTV_MAIN_SITE_URL : home_url();
    $current_site_url = home_url();

    // On the main site, intercept the cart form and redirect to panel
    if ($main_site_url === $current_site_url) {
        ?>
        <script>
            (function () {
                var form = document.querySelector('form.cart');
                if (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        window.location.href = 'https://panel.nordictv.io/';
                    });
                }
            })();
        </script>
        <?php
        return;
    }

    // Get current product SKU for main site lookup (subsite flow)
    global $product;
    $product_sku = $product ? $product->get_sku() : '';

    // Get variation SKUs if this is a variable product
    $variation_skus = array();
    if ($product && $product->is_type('variable')) {
        $variations = $product->get_available_variations();
        foreach ($variations as $variation) {
            $var_product = wc_get_product($variation['variation_id']);
            if ($var_product) {
                $var_sku = $var_product->get_sku();
                $attributes = $var_product->get_attributes();
                // Create a key from attributes
                $attr_key = '';
                foreach ($attributes as $key => $value) {
                    $attr_key .= $key . ':' . $value . ';';
                }
                $variation_skus[$attr_key] = $var_sku;
            }
        }
    }
    ?>
    <script>
        (function () {
            // Cross-site cart: Redirect add-to-cart to main site using SKU
            const mainSiteUrl = '<?php echo esc_js($main_site_url); ?>';
            const productSku = '<?php echo esc_js($product_sku); ?>';
            const variationSkus = <?php echo json_encode($variation_skus); ?>;
            const form = document.querySelector('form.cart');

            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    // For variable products, get the selected variation's SKU
                    let skuToUse = productSku;
                    const variationInput = form.querySelector('input[name="variation_id"]');

                    if (variationInput && variationInput.value) {
                        // Build attribute key to match variation SKU
                        const attrs = form.querySelectorAll('select[name^="attribute_"]');
                        let attrKey = '';
                        attrs.forEach(select => {
                            attrKey += select.name.replace('attribute_', '') + ':' + select.value + ';';
                        });

                        if (variationSkus[attrKey]) {
                            skuToUse = variationSkus[attrKey];
                        }
                    }

                    if (!skuToUse) {
                        console.error('No SKU found for product');
                        return;
                    }

                    // Redirect to panel instead of main site checkout
                    window.location.href = 'https://panel.nordictv.io/';
                    // NOTE: Commented out — redirects now go to https://panel.nordictv.io/
                    // const url = mainSiteUrl + '/checkout/?add-by-sku=' + encodeURIComponent(skuToUse);
                    // window.location.href = url;
                });
            }
        })();
    </script>
    <?php
});

/**
 * WooCommerce: Simplify checkout - only require email and phone
 * No default values - user will see only email, phone, note, and subscription type
 */
if (class_exists('WooCommerce')) {
    // Make only email and phone required, remove all other required fields
    add_filter('woocommerce_checkout_fields', function ($fields) {
        // Keep email and phone required
        $fields['billing']['billing_email']['required'] = true;
        $fields['billing']['billing_phone']['required'] = true;

        // Make all other billing fields NOT required
        if (isset($fields['billing']['billing_first_name']))
            $fields['billing']['billing_first_name']['required'] = false;
        if (isset($fields['billing']['billing_last_name']))
            $fields['billing']['billing_last_name']['required'] = false;
        if (isset($fields['billing']['billing_company']))
            $fields['billing']['billing_company']['required'] = false;
        if (isset($fields['billing']['billing_country']))
            $fields['billing']['billing_country']['required'] = false;
        if (isset($fields['billing']['billing_address_1']))
            $fields['billing']['billing_address_1']['required'] = false;
        if (isset($fields['billing']['billing_address_2']))
            $fields['billing']['billing_address_2']['required'] = false;
        if (isset($fields['billing']['billing_city']))
            $fields['billing']['billing_city']['required'] = false;
        if (isset($fields['billing']['billing_state']))
            $fields['billing']['billing_state']['required'] = false;
        if (isset($fields['billing']['billing_postcode']))
            $fields['billing']['billing_postcode']['required'] = false;

        return $fields;
    }, 100);

    // Skip address validation entirely - only require email and phone
    add_filter('woocommerce_checkout_required_field_keys', function ($required) {
        $keep = array('billing_email', 'billing_phone');
        return array_intersect($required, $keep);
    });

    // Change attribute label from 'pa_devices' to 'Devices'
    add_filter('woocommerce_attribute_label', function ($label, $name) {
        if ($name === 'pa_devices') {
            return 'Devices';
        }
        return $label;
    }, 10, 2);

    // Hide excessive WooCommerce notices on product pages (cart added/removed messages)
    add_filter('wc_add_to_cart_message_html', '__return_empty_string');

    // Limit displayed notices to prevent spam
    add_action('wp_footer', function () {
        if (!is_product())
            return;
        ?>
        <style>
            /* Hide WooCommerce notices on product pages */
            .woocommerce-notices-wrapper,
            .woocommerce-message,
            .woocommerce-info,
            .wc-block-components-notice-banner {
                display: none !important;
            }
        </style>
        <?php
    });
}
