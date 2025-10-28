<?php
if ( ! defined( 'WPINC') ) { die; }

// --- ADMIN SETTINGS PANEL ---
function aura_chatbot_add_admin_menu() {
    add_options_page('Aura Product Chatbot Settings', 'Aura Chatbot', 'manage_options', 'aura-chatbot', 'aura_chatbot_settings_page');
}
// MODULAR: removed hook -> add_action('admin_menu', 'aura_chatbot_add_admin_menu');

function aura_chatbot_settings_init() {
    register_setting('auraChatbotPage', 'aura_chatbot_settings');
    add_settings_section('aura_chatbot_styling_section', 'Styling Settings', null, 'auraChatbotPage');
    add_settings_field('aura_chatbot_primary_color', 'Primary Color', 'aura_chatbot_primary_color_render', 'auraChatbotPage', 'aura_chatbot_styling_section');
    add_settings_field('aura_chatbot_user_bubble_color', 'User Bubble Color', 'aura_chatbot_user_bubble_color_render', 'auraChatbotPage', 'aura_chatbot_styling_section');
    
    add_settings_section('aura_chatbot_flows_section', 'Chatbot Flow Settings', null, 'auraChatbotPage');
    add_settings_field('aura_chatbot_api_key', 'Gemini API Key', 'aura_chatbot_api_key_render', 'auraChatbotPage', 'aura_chatbot_flows_section');
    add_settings_field('aura_chatbot_welcome_message', 'Welcome Message', 'aura_chatbot_welcome_message_render', 'auraChatbotPage', 'aura_chatbot_flows_section');
    add_settings_field('aura_chatbot_print_placements', 'Print Placements', 'aura_chatbot_print_placements_render', 'auraChatbotPage', 'aura_chatbot_flows_section');
    add_settings_field('aura_chatbot_canned_responses', 'General Canned Responses', 'aura_chatbot_canned_responses_render', 'auraChatbotPage', 'aura_chatbot_flows_section');
}
// MODULAR: removed hook -> add_action('admin_init', 'aura_chatbot_settings_init');

/* styling option removed */

/* styling option removed */

function aura_chatbot_api_key_render() {
    $options = get_option('aura_chatbot_settings');
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    echo '<input type="text" id="aura_api_key_field" name="aura_chatbot_settings[api_key]" value="' . esc_attr( $api_key ) . '" size="50">';
    echo '<button type="button" id="aura_test_api_key" class="button">Test Connection</button>';
    echo '<span id="aura_api_test_result" style="margin-left: 10px;"></span>';
    echo '<p class="description">Enter your Google Gemini API key. AI assist is optional—search still works without it.</p>';
}

function aura_chatbot_welcome_message_render() {
    $options = get_option('aura_chatbot_settings');
    $welcome_message = isset($options['welcome_message']) ? $options['welcome_message'] : "Hello! I can help you find products.";
    echo '<textarea cols="50" rows="3" name="aura_chatbot_settings[welcome_message]">' . esc_textarea($welcome_message) . '</textarea>';
}

function aura_chatbot_print_placements_render() {
    $options = get_option('aura_chatbot_settings');
    $placements = isset($options['print_placements']) ? $options['print_placements'] : "Front Center\nBack Center\nLeft Chest\nRight Sleeve";
    echo '<textarea cols="50" rows="4" name="aura_chatbot_settings[print_placements]">' . esc_textarea($placements) . '</textarea>';
    echo '<p class="description">Enter one print placement option per line.</p>';
}

function aura_chatbot_canned_responses_render() {
    $options = get_option('aura_chatbot_settings');
    $responses = isset($options['canned_responses']) ? $options['canned_responses'] : "Business hours|Our hours are 9 AM to 5 PM, Monday to Friday.";
    echo '<textarea cols="50" rows="5" name="aura_chatbot_settings[canned_responses]">' . esc_textarea($responses) . '</textarea>';
    echo '<p class="description">Format: <code>Question|Answer</code></p>';
}

function aura_chatbot_settings_page() {
    echo '<div class="wrap"><h1>Aura Product Chatbot Settings</h1><form action="options.php" method="post">';
    settings_fields('auraChatbotPage');
    do_settings_sections('auraChatbotPage');
    submit_button();
    echo '</form></div>';
}

// Enqueue admin scripts
function aura_chatbot_admin_scripts($hook) {
    if ($hook != 'settings_page_aura-chatbot') { return; }
    wp_add_inline_script('jquery-core', '
        jQuery(function($){
            $("#aura_test_api_key").on("click", function(){
                var button = $(this);
                var resultSpan = $("#aura_api_test_result");
                var apiKey = $("#aura_api_key_field").val();
                resultSpan.text("Testing...").css("color","");
                button.prop("disabled", true);
                $.ajax({
                    url: "'. admin_url('admin-ajax.php') .'",
                    type: "POST",
                    data: { action: "aura_test_api_key", api_key: apiKey },
                    success: function(res){
                        if(res.success){ resultSpan.text("Success!").css("color","green"); }
                        else { resultSpan.text("Failed. " + (res.data && res.data.message ? res.data.message : "" )).css("color","red"); }
                    },
                    error: function(){ resultSpan.text("AJAX error.").css("color","red"); },
                    complete: function(){ button.prop("disabled", false); }
                });
            });
        });
    ');
}
// MODULAR: removed hook -> add_action('admin_enqueue_scripts', 'aura_chatbot_admin_scripts');

// --- SHORTCODE ---
function aura_chatbot_shortcode() {
    $options = get_option('aura_chatbot_settings');
    $api_key = $options['api_key'] ?? '';
    $primary_color = $options['primary_color'] ?? '#42B8FF';
    $user_bubble_color = $options['user_bubble_color'] ?? '#FF69B4';
    $welcome_message = $options['welcome_message'] ?? "Hello! Let's find a product.";
    $canned_responses_raw = $options['canned_responses'] ?? "";
    $placements_raw = $options['print_placements'] ?? "Front Center\nBack Center";

    wp_enqueue_script('aura-tailwind-cdn');
    wp_enqueue_script('aura-chatbot');
    wp_localize_script(
    'aura-chatbot',
    'aura_chatbot_data',
    array(
        'ajax_url'        => admin_url('admin-ajax.php'),
        'api_key'         => get_option('aura_chatbot_api_key', ''),
        'welcome_message' => get_option('aura_chatbot_welcome', ''),
        // Expect newline-delimited strings in JS
        'canned_responses_raw' => (function(){
            $val = get_option('aura_chatbot_canned_responses', '');
            if (is_array($val)) { $val = implode("
", $val); }
            return is_string($val) ? $val : '';
        })(),
        'placements_raw' => (function(){
            $val = get_option('aura_chatbot_placements', '');
            if (is_array($val)) { $val = implode("
", $val); }
            if (empty($val)) { $val = "Front Center
Back Center
Left Chest
Right Chest
Sleeve Left
Sleeve Right"; }
            return is_string($val) ? $val : '';
        })(),
        'upload_nonce'    => wp_create_nonce('aura_upload_artwork'),
        'uploads_url'     => trailingslashit( wp_upload_dir()['baseurl'] ) . 'aura-artwork/'
    )
);

    ob_start(); ?>
    
<div id="aura-chatbot-root" class="fixed inset-0 p-4 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-8" style="font-family:'MiSans',sans-serif;background:#162633;color:#B8C5D0;">
        <!-- Left: Chat column -->
        <div class="md:col-span-1 h-full flex flex-col rounded-2xl shadow-2xl min-h-0" style="background-color:#1B2E3C;">
            <div id="chatbot-header" class="p-4 rounded-t-2xl flex items-center justify-start gap-3 flex-shrink-0" style="border-bottom:1px solid rgba(255,255,255,.1);">
                <h3 id="chatbot-title" class="text-lg font-semibold text-white" style="font-family:'AlimamaShuHeiTi',sans-serif;">Order Assistant</h3>
            </div>
            <div id="messages" class="flex-1 p-4 overflow-y-auto min-h-0"></div>
            <div id="chat-input-container" class="p-4 flex-shrink-0">
                <div class="flex items-center rounded-lg" style="background:#162633;">
                    <input type="text" id="chat-input" placeholder="Type your message..." class="flex-1 bg-transparent border-none focus:ring-0 rounded-lg p-3 text-sm text-gray-200 placeholder-gray-400">
                    <button id="send-btn" class="p-3" style="color:<?php echo esc_attr($primary_color); ?>;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" /></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right: Product column + slide-in panel -->
        <div class="md:col-span-2 h-full rounded-2xl overflow-hidden p-6 min-h-0 relative" style="background-color:#1B2E3C;">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-white" style="font-family:'AlimamaShuHeiTi',sans-serif;">Products</h2>
                <button id="order-drawer-btn-top" class="px-3 py-1 rounded-lg text-white font-semibold">View Order (0)</button>
            </div>
            <div id="product-search-view" class="h-full overflow-y-auto pr-2">
                <div id="product-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                    <p id="product-placeholder" class="col-span-full text-gray-400">Products will appear here.</p>
                </div>
            </div>

            <!-- Slide-in panel that does NOT cover chat -->
            <div id="side-panel" class="absolute top-0 right-0 h-full w-full md:w-[520px] bg-[#1B2E3C] shadow-2xl border-l border-white/10 hidden">
                <div id="side-panel-content" class="h-full overflow-y-auto"></div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}
// MODULAR: removed shortcode -> add_shortcode('aura_chatbot_interface', 'aura_chatbot_shortcode');

// --- SCRIPT & STYLE ENQUEUEING ---
if (!function_exists('aura_chatbot_enqueue_scripts')){ function aura_chatbot_enqueue_scripts() {
    $style_rel = 'assets/css/aura-chatbot.css';
    $style_abs = plugin_dir_path(AURA_PLUGIN_FILE) . $style_rel;
    $style_ver = file_exists($style_abs) ? filemtime($style_abs) : time();
    wp_enqueue_style('aura-chatbot-css', plugins_url($style_rel, AURA_PLUGIN_FILE), array(), $style_ver);

    // Tailwind CDN (registered only)
    wp_register_script('aura-tailwind-cdn', 'https://cdn.tailwindcss.com', array(), null, false);

    // Version by filemtime for cache-busting
    $asset_rel = 'assets/js/aura-chatbot.js';
    $asset_abs = plugin_dir_path(AURA_PLUGIN_FILE) . $asset_rel;
    $ver = file_exists($asset_abs) ? filemtime($asset_abs) : time();

    wp_register_script('aura-chatbot', plugins_url($asset_rel, AURA_PLUGIN_FILE), array('jquery'), $ver, true);

    // Localize runtime vars (AJAX + nonce + colors/messages already set elsewhere)
    wp_localize_script('aura-chatbot', 'AURA_VARS', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('aura_upload_artwork')
    ));

    // Ensure script actually enqueues on pages where shortcode might render
    wp_enqueue_script('aura-chatbot');
}
} // end enqueue wrapper
// MODULAR: removed hook -> add_action('wp_enqueue_scripts', 'aura_chatbot_enqueue_scripts');

// --- AJAX HANDLERS ---

// API Key Test
function aura_test_api_key_ajax_handler() {
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    if (empty($api_key)) { wp_send_json_error(['message' => 'API Key is empty.']); }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=' . $api_key;
    $args = [
        'method'  => 'POST',
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode([ 'contents' => [['parts' => [['text' => 'hello']]]] ]),
        'timeout' => 15,
    ];
    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) { wp_send_json_error(['message' => 'WordPress HTTP Error: ' . $response->get_error_message()]); }
    $response_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($response_code === 200 && !empty($body['candidates'])) { wp_send_json_success(); }
    $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error.';
    wp_send_json_error(['message' => 'API Error (' . $response_code . '): ' . $error_message]);
}
// MODULAR: removed hook -> add_action('wp_ajax_aura_test_api_key', 'aura_test_api_key_ajax_handler');

// ---- PRODUCT SEARCH (broad) ----
function aura_fetch_products_ajax_handler() {
    global $wpdb;

    $terms = array();
    if ( isset($_POST['terms']) && is_array($_POST['terms']) ) {
        foreach ($_POST['terms'] as $t) { $t = sanitize_text_field( wp_unslash($t) ); if ($t !== '') $terms[] = $t; }
    } elseif ( ! empty($_POST['search_term']) ) {
        $terms[] = sanitize_text_field( wp_unslash($_POST['search_term']) );
    }
    if ( empty($terms) ) { wp_send_json_error(['message' => 'Search term is empty.']); }

    $phrase_set = array(); $token_set  = array();
    $swap_variants = array('tee','t-shirt','tshirt','t shirt');
    foreach ($terms as $raw) {
        $phrase_set[ mb_strtolower($raw) ] = true;
        foreach ($swap_variants as $v) { $alias = preg_replace('/\b(tee|t[\s-]?shirt)\b/i', $v, $raw); $phrase_set[ mb_strtolower($alias) ] = true; }
        $toks = preg_split('/[\s,\-_|]+/', mb_strtolower($raw));
        foreach ($toks as $tk) { if (mb_strlen($tk) >= 2) $token_set[$tk] = true; }
        if (preg_match('/\bt[\s-]?shirt\b/i', $raw)) $token_set['tee'] = true;
    }
    $phrases = array_keys($phrase_set);
    $tokens  = array_keys($token_set);

    $ids_title = array();
    foreach ($phrases as $p) {
        $like = '%' . $wpdb->esc_like($p) . '%';
        $ids_title = array_merge($ids_title, (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND post_title LIKE %s ORDER BY post_date DESC LIMIT 50", $like
        )));
    }
    foreach ($tokens as $tok) {
        $like_tok = '%' . $wpdb->esc_like($tok) . '%';
        $ids_title = array_merge($ids_title, (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND post_title LIKE %s ORDER BY post_date DESC LIMIT 50", $like_tok
        )));
    }

    $ids_tax = array();
    if ( function_exists('wc_get_attribute_taxonomy_names') ) {
        $taxonomies = array_merge( ['product_cat','product_tag'], array_values( wc_get_attribute_taxonomy_names() ) );
        $tax_query  = ['relation' => 'OR'];
        foreach ($taxonomies as $tax) {
            $term_ids = array();
            foreach ($tokens as $tok) {
                $terms_found = get_terms([ 'taxonomy'=>$tax, 'hide_empty'=>false, 'search'=>$tok, 'number'=>50 ]);
                if (!is_wp_error($terms_found) && $terms_found) { $term_ids = array_merge($term_ids, wp_list_pluck($terms_found, 'term_id')); }
            }
            $term_ids = array_values(array_unique($term_ids));
            if (!empty($term_ids)) { $tax_query[] = [ 'taxonomy'=>$tax, 'field'=>'term_id', 'terms'=>$term_ids, 'operator'=>'IN' ]; }
        }
        if (count($tax_query) > 1) {
            $q_tax = new WP_Query([ 'post_type'=>'product', 'post_status'=>'publish', 'fields'=>'ids', 'posts_per_page'=>50, 'tax_query'=>$tax_query ]);
            $ids_tax = !is_wp_error($q_tax) ? (array)$q_tax->posts : array();
        }
    }

    $sku_terms = array_values(array_unique(array_merge($tokens, ['tee','t-shirt','tshirt','t shirt','hoody'])));
    $meta_query = ['relation' => 'OR'];
    foreach ($sku_terms as $tok) { $meta_query[] = [ 'key'=>'_sku', 'value'=>$tok, 'compare'=>'LIKE' ]; }
    $q_sku = new WP_Query([ 'post_type'=>'product', 'post_status'=>'publish', 'fields'=>'ids', 'posts_per_page'=>50, 'meta_query'=>$meta_query ]);
    $ids_sku = !is_wp_error($q_sku) ? (array)$q_sku->posts : array();

    $ids_content = array();
    foreach ($phrases as $p) {
        $q_s = new WP_Query([ 'post_type'=>'product', 'post_status'=>'publish', 'fields'=>'ids', 'posts_per_page'=>50, 's'=>$p ]);
        $ids_content = array_merge($ids_content, !is_wp_error($q_s) ? (array)$q_s->posts : array());
    }

    $ids_all = array_values(array_unique(array_merge($ids_title, $ids_tax, $ids_sku, $ids_content)));
    if ( empty($ids_all) ) { wp_send_json_error(['message' => 'No products found.']); }

    $settings = get_option('aura_chatbot_settings');
    $primary_color = $settings['primary_color'] ?? '#42B8FF';
    $html = '';
    foreach ( array_slice($ids_all, 0, 12) as $pid ) {
        $product = wc_get_product( $pid );
        if ( ! $product ) continue;
        $title     = get_the_title( $pid );
        $image_url = get_the_post_thumbnail_url( $pid, 'medium' ) ?: wc_placeholder_img_src();
        $html .= sprintf(
            '<div class="product-card rounded-lg shadow-md p-4 flex flex-col justify-between" data-id="%1$s" data-name="%2$s">
                <div>
                    <div class="apc-img-wrap"><img src="%3$s" alt="%4$s" class="apc-img"></div>
                    <h3 class="font-semibold text-gray-200 text-sm leading-tight truncate">%5$s</h3>
                </div>
                <p class="font-bold mt-2 text-sm" style="color:%6$s">%7$s</p>
            </div>',
            esc_attr($pid), esc_attr($title), esc_url($image_url), esc_attr($title),
            esc_html($title), esc_attr($primary_color), wp_kses_post($product->get_price_html())
        );
    }
    if ($html === '') { wp_send_json_error(['message' => 'No products found.']); }
    wp_send_json_success(['html' => $html]);
}
// MODULAR: removed hook -> add_action('wp_ajax_aura_fetch_products', 'aura_fetch_products_ajax_handler');
// MODULAR: removed hook -> add_action('wp_ajax_nopriv_aura_fetch_products', 'aura_fetch_products_ajax_handler');

// Helper: collect colors/sizes from attributes or description
function aura_collect_colors_sizes_from_product( $product ) {
    $colors = array();
    $sizes  = array();

    if ( $product->is_type('variable') ) {
        $var_attrs = $product->get_variation_attributes();
        foreach ( $var_attrs as $key => $slugs ) {
            if ( empty($slugs) ) continue;
            $tax = str_replace('attribute_', '', $key);
            if ( stripos($key, 'color') !== false ) {
                foreach ( $slugs as $slug ) {
                    $term = get_term_by( 'slug', $slug, $tax );
                    $colors[] = $term && !is_wp_error($term) ? $term->name : wc_clean($slug);
                }
            } elseif ( stripos($key, 'size') !== false ) {
                foreach ( $slugs as $slug ) {
                    $term = get_term_by( 'slug', $slug, $tax );
                    $sizes[] = $term && !is_wp_error($term) ? $term->name : strtoupper(wc_clean($slug));
                }
            }
        }
    }

    $attrs = $product->get_attributes();
    foreach ( $attrs as $attr ) {
        $name = $attr->get_name();
        if ( stripos($name, 'color') !== false ) {
            if ( $attr->is_taxonomy() ) {
                $terms = wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'names' ) );
                $colors = array_merge( $colors, $terms );
            } else {
                $list = $attr->get_options() ? implode(',', $attr->get_options()) : '';
                $colors = array_merge( $colors, array_map( 'trim', explode( ',', $list ) ) );
            }
        } elseif ( stripos($name, 'size') !== false ) {
            if ( $attr->is_taxonomy() ) {
                $terms = wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'names' ) );
                $sizes = array_merge( $sizes, $terms );
            } else {
                $list = $attr->get_options() ? implode(',', $attr->get_options()) : '';
                $sizes = array_merge( $sizes, array_map( 'trim', explode( ',', $list ) ) );
            }
        }
    }

    $desc = wp_strip_all_tags( $product->get_description() );
    if ( $desc ) {
        if ( preg_match('/colors?\s*:\s*([^\n\r]+)/i', $desc, $m) ) {
            $colors = array_merge( $colors, array_map('trim', explode(',', $m[1])) );
        }
        if ( preg_match('/sizes?\s*:\s*([^\n\r]+)/i', $desc, $m) ) {
            $sizes = array_merge( $sizes, array_map('trim', explode(',', $m[1])) );
        }
    }

    $colors = array_values( array_unique( array_filter( array_map('sanitize_text_field', $colors) ) ) );
    $sizes  = array_values( array_unique( array_filter( array_map('sanitize_text_field', $sizes) ) ) );

    return array( $colors, $sizes );
}

// Fetch Single Product Details (+ available colors/sizes)
function aura_fetch_product_details_ajax_handler() {
    if (!isset($_POST['product_id']) || !function_exists('wc_get_product')) { wp_send_json_error(); }
    $product_id = absint($_POST['product_id']);
    $product = wc_get_product($product_id);
    if (!$product) { wp_send_json_error(); }
    $image_id = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : wc_placeholder_img_src('large');

    list($colors, $sizes) = aura_collect_colors_sizes_from_product( $product );

    $data = [
        'id' => $product->get_id(),
        'name' => $product->get_name(),
        'price_html' => $product->get_price_html(),
        'description' => wpautop($product->get_description()),
        'image_url' => esc_url($image_url),
        'colors' => $colors,
        'sizes'  => $sizes,
    ];
    wp_send_json_success($data);
}
// MODULAR: removed hook -> add_action('wp_ajax_aura_fetch_product_details', 'aura_fetch_product_details_ajax_handler');
// MODULAR: removed hook -> add_action('wp_ajax_nopriv_aura_fetch_product_details', 'aura_fetch_product_details_ajax_handler');

// Send Custom Order Email
function aura_send_order_ajax_handler() {
    if (empty($_POST['order'])) { wp_send_json_error(['message' => 'No order data.']); }
    $order_items = json_decode(stripslashes($_POST['order']), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($order_items) || empty($order_items)) { wp_send_json_error(['message' => 'Invalid order data.']); }
    $artwork_filename = isset($_POST['artwork_filename']) ? sanitize_file_name($_POST['artwork_filename']) : '';
$artwork_url = isset($_POST['artwork_url']) ? esc_url_raw($_POST['artwork_url']) : '';
$artwork_attachment_id = isset($_POST['artwork_attachment_id']) ? intval($_POST['artwork_attachment_id']) : 0;
$artwork_size = isset($_POST['artwork_size']) ? intval($_POST['artwork_size']) : 0;

$admin_email = get_option('admin_email');
    $subject = 'New Custom Garment Order from Website Chatbot';
    $message = "A new custom garment order has been submitted:\n\n============================================\n\n";
    foreach ($order_items as $index => $item) {
        $message .= "--- ITEM " . ($index + 1) . " ---\n";
        $message .= "Product: " . sanitize_text_field($item['product'] ?? 'N/A') . "\n";
        $message .= "Colors: " . sanitize_text_field($item['colors'] ?? 'N/A') . "\n";
        $message .= "Sizes: " . sanitize_text_field($item['sizes'] ?? 'N/A') . "\n";
        $message .= "Quantities: " . sanitize_text_field($item['quantity'] ?? 'N/A') . "\n";
        $message .= "Artwork: " . sanitize_text_field($item['artwork'] ?? 'N/A') . "\n";
        $message .= "Print Placement: " . sanitize_text_field($item['placement'] ?? 'N/A') . "\n\n";
    }
    $message .= "============================================\n\nPlease follow up with the client to finalize details and provide a quote.\n";
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $attachments = array();
$max_mb = 10; $max_bytes = $max_mb*1024*1024;
if ($artwork_attachment_id) { $path = get_attached_file($artwork_attachment_id); if ($path && file_exists($path) && filesize($path) <= $max_bytes) { $attachments[] = $path; } }
if (wp_mail($admin_email, $subject, $message, $headers, $attachments)) { wp_send_json_success(['message' => 'Email sent successfully.']); }
    wp_send_json_error(['message' => 'Failed to send email.']);
}
// MODULAR: removed hook -> add_action('wp_ajax_aura_send_order', 'aura_send_order_ajax_handler');
// MODULAR: removed hook -> add_action('wp_ajax_nopriv_aura_send_order', 'aura_send_order_ajax_handler');

// --- Extra matrix color settings (added by patch 1.8.5a) ---
function aura_chatbot_settings_init_matrix_colors(){
    add_settings_field('aura_chatbot_matrix_header_bg', 'Matrix Header Background', 'aura_chatbot_matrix_header_bg_render', 'auraChatbotPage', 'aura_chatbot_styling_section');
    add_settings_field('aura_chatbot_matrix_header_text', 'Matrix Header Text', 'aura_chatbot_matrix_header_text_render', 'auraChatbotPage', 'aura_chatbot_styling_section');
    add_settings_field('aura_chatbot_matrix_cell_bg', 'Matrix Cell Background', 'aura_chatbot_matrix_cell_bg_render', 'auraChatbotPage', 'aura_chatbot_styling_section');
    add_settings_field('aura_chatbot_matrix_cell_border', 'Matrix Cell Border', 'aura_chatbot_matrix_cell_border_render', 'auraChatbotPage', 'aura_chatbot_styling_section');
    add_settings_field('aura_chatbot_matrix_cell_text', 'Matrix Cell Text', 'aura_chatbot_matrix_cell_text_render', 'auraChatbotPage', 'aura_chatbot_styling_section');
    add_settings_field('aura_chatbot_matrix_cell_focus', 'Matrix Cell Focus', 'aura_chatbot_matrix_cell_focus_render', 'auraChatbotPage', 'aura_chatbot_styling_section');
}
// MODULAR: removed hook -> add_action('admin_init', 'aura_chatbot_settings_init_matrix_colors');

function aura_chatbot_matrix_header_bg_render(){
    $options = get_option('aura_chatbot_settings');
    $v = isset($options['matrix_header_bg']) ? $options['matrix_header_bg'] : '#0B0B0B';
    echo '<input type="color" name="aura_chatbot_settings[matrix_header_bg]" value="'.esc_attr($v).'">';
}
function aura_chatbot_matrix_header_text_render(){
    $options = get_option('aura_chatbot_settings');
    $v = isset($options['matrix_header_text']) ? $options['matrix_header_text'] : '#FFFFFF';
    echo '<input type="color" name="aura_chatbot_settings[matrix_header_text]" value="'.esc_attr($v).'">';
}
function aura_chatbot_matrix_cell_bg_render(){
    $options = get_option('aura_chatbot_settings');
    $v = isset($options['matrix_cell_bg']) ? $options['matrix_cell_bg'] : '#0F2230';
    echo '<input type="color" name="aura_chatbot_settings[matrix_cell_bg]" value="'.esc_attr($v).'">';
}
function aura_chatbot_matrix_cell_border_render(){
    $options = get_option('aura_chatbot_settings');
    $v = isset($options['matrix_cell_border']) ? $options['matrix_cell_border'] : '#2B3B47';
    echo '<input type="color" name="aura_chatbot_settings[matrix_cell_border]" value="'.esc_attr($v).'">';
}
function aura_chatbot_matrix_cell_text_render(){
    $options = get_option('aura_chatbot_settings');
    $v = isset($options['matrix_cell_text']) ? $options['matrix_cell_text'] : '#FFFFFF';
    echo '<input type="color" name="aura_chatbot_settings[matrix_cell_text]" value="'.esc_attr($v).'">';
}
function aura_chatbot_matrix_cell_focus_render(){
    $options = get_option('aura_chatbot_settings');
    $primary = isset($options['primary_color']) ? $options['primary_color'] : '#42B8FF';
    $v = isset($options['matrix_cell_focus']) ? $options['matrix_cell_focus'] : $primary;
    echo '<input type="color" name="aura_chatbot_settings[matrix_cell_focus]" value="'.esc_attr($v).'">';
}

// Inject extra localized vars and CSS variables
add_action('wp_enqueue_scripts', function(){
    $options = get_option('aura_chatbot_settings');
    $vars = array(
        'matrix_header_bg'   => isset($options['matrix_header_bg']) ? $options['matrix_header_bg'] : '#0B0B0B',
        'matrix_header_text' => isset($options['matrix_header_text']) ? $options['matrix_header_text'] : '#FFFFFF',
        'matrix_cell_bg'     => isset($options['matrix_cell_bg']) ? $options['matrix_cell_bg'] : '#0F2230',
        'matrix_cell_border' => isset($options['matrix_cell_border']) ? $options['matrix_cell_border'] : '#2B3B47',
        'matrix_cell_text'   => isset($options['matrix_cell_text']) ? $options['matrix_cell_text'] : '#FFFFFF',
        'matrix_cell_focus'  => isset($options['matrix_cell_focus']) ? $options['matrix_cell_focus'] : (isset($options['primary_color'])?$options['primary_color']:'#42B8FF'),
    );
    wp_add_inline_script('aura-chatbot', 'window.aura_chatbot_data = Object.assign(window.aura_chatbot_data||{}, '.wp_json_encode($vars).');', 'after');

    // CSS variables for the matrix/grid inputs
    $css = ':root #aura-chatbot-root{'
      .'--ac-mh-bg: '.$vars['matrix_header_bg'].';'
      .'--ac-mh-text: '.$vars['matrix_header_text'].';'
      .'--ac-cell-bg: '.$vars['matrix_cell_bg'].';'
      .'--ac-cell-border: '.$vars['matrix_cell_border'].';'
      .'--ac-cell-text: '.$vars['matrix_cell_text'].';'
      .'--ac-cell-focus: '.$vars['matrix_cell_focus'].';'
      .'}'
      .'#aura-chatbot-root table.ac-matrix thead th{background:var(--ac-mh-bg);color:var(--ac-mh-text);padding:10px;border-radius:8px;font-weight:700;}'
      .'#aura-chatbot-root input.qty-matrix,#aura-chatbot-root input.qty-input{background:var(--ac-cell-bg);border:1px solid var(--ac-cell-border);color:var(--ac-cell-text);border-radius:8px;padding:10px 12px;text-align:center;outline:none;transition:border .15s, box-shadow .15s;}'
      .'#aura-chatbot-root input.qty-matrix:focus,#aura-chatbot-root input.qty-input:focus{border-color:var(--ac-cell-focus);box-shadow:0 0 0 2px color-mix(in srgb,var(--ac-cell-focus) 35%, transparent);}'
      .'.ac-matrix-wrap{overflow-x:auto}'
      ;
// MODULAR: removed hook ->     add_action('wp_head', function() use ($css){ echo ''; });
}, 20);
// MODULAR: removed hook -> 

add_action('wp_ajax_aura_upload_artwork','aura_upload_artwork_ajax_handler');
// MODULAR: removed hook -> add_action('wp_ajax_nopriv_aura_upload_artwork','aura_upload_artwork_ajax_handler');
if (!function_exists('aura_upload_artwork_ajax_handler')) {
function aura_upload_artwork_ajax_handler(){
    // Nonce check (expects 'upload_nonce' param)
    check_ajax_referer('aura_upload_artwork', 'upload_nonce');

    if ( empty($_FILES['artwork']) || !isset($_FILES['artwork']['name']) ){
        wp_send_json_error(array('message'=>'No file uploaded.'), 400);
    }
    $file = $_FILES['artwork'];
    if ( $file['error'] !== UPLOAD_ERR_OK ){
        wp_send_json_error(array('message'=>'Upload error code '.$file['error']), 400);
    }

    // Allowed mimes
    $allowed_mimes = array(
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'pdf'  => 'application/pdf'
    );

    $overrides = array(
        'test_form' => false,
        'mimes'     => $allowed_mimes
    );

    // Handle upload via WP API (sideload into uploads)
    // Force uploads into /uploads/aura-artwork (no Y/M folders)
$__aura_rm = add_filter('upload_dir', function($dirs){
    $sub = 'aura-artwork';
    $dirs['subdir'] = '/' . $sub;
    $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
    $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
    return $dirs;
});

$result = wp_handle_upload($file, $overrides);
// Remove temporary upload_dir filter
remove_filter('upload_dir', '__return_false'); // noop cleanup in case of earlier set
if (isset($__aura_rm)) { remove_filter('upload_dir', $__aura_rm); }

    if ( isset($result['error']) ){
        wp_send_json_error(array('message'=>$result['error']), 400);
    }

    $file_path = $result['file'];
    $file_url  = $result['url'];
    $mime      = $result['type'];

    // Create attachment
    $attachment = array(
        'post_title'     => sanitize_file_name( pathinfo($file_path, PATHINFO_FILENAME) ),
        'post_mime_type' => $mime,
        'post_status'    => 'inherit'
    );
    $attach_id = wp_insert_attachment($attachment, $file_path);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $meta = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $meta);

    wp_send_json_success(array(
        'attachment_id' => $attach_id,
        'url'           => $file_url,
        'filename'      => basename($file_path),
        'size'          => (int) filesize($file_path)
    ), 200);
}}






// --- FRONTEND ENQUEUE (added by 2.3.1) ---
if (!function_exists('aura_chatbot_enqueue_scripts')){
function aura_chatbot_enqueue_scripts() {
    // CSS
    $style_rel = 'assets/css/aura-chatbot.css';
    $style_abs = plugin_dir_path(AURA_PLUGIN_FILE) . $style_rel;
    $style_ver = file_exists($style_abs) ? filemtime($style_abs) : time();
    wp_enqueue_style('aura-chatbot-css', plugins_url($style_rel, AURA_PLUGIN_FILE), array(), $style_ver);

    // Tailwind CDN (registered only)
    wp_register_script('aura-tailwind-cdn', 'https://cdn.tailwindcss.com', array(), null, false);

    // JS
    $asset_rel = 'assets/js/aura-chatbot.js';
    $asset_abs = plugin_dir_path(AURA_PLUGIN_FILE) . $asset_rel;
    $ver = file_exists($asset_abs) ? filemtime($asset_abs) : time();
    wp_register_script('aura-chatbot', plugins_url($asset_rel, AURA_PLUGIN_FILE), array('jquery'), $ver, true);

    // Localized vars (nonce + ajax)
    wp_localize_script('aura-chatbot', 'AURA_VARS', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('aura_upload_artwork'),
    ));
}}
