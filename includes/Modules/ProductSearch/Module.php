<?php
declare(strict_types=1);

namespace AuraModular\Modules\ProductSearch;

use AuraModular\Modules\BaseModule;

final class Module extends BaseModule
{
    protected string $slug = 'aura-product-search';

    public function register(): void
    {
        add_action('wp_ajax_aura_fetch_products', [$this, 'fetch_products_ajax_handler']);
        add_action('wp_ajax_nopriv_aura_fetch_products', [$this, 'fetch_products_ajax_handler']);

        add_action('wp_ajax_aura_fetch_product_details', [$this, 'fetch_product_details_ajax_handler']);
        add_action('wp_ajax_nopriv_aura_fetch_product_details', [$this, 'fetch_product_details_ajax_handler']);
    }

    public function fetch_products_ajax_handler(): void
    {
        if (empty($_POST)) {
            wp_send_json_error(['message' => 'No input provided.']);
        }

        global $wpdb;

        $terms = [];
        if (isset($_POST['terms']) && is_array($_POST['terms'])) {
            foreach ($_POST['terms'] as $t) { $t = sanitize_text_field(wp_unslash($t)); if ($t !== '') $terms[] = $t; }
        } elseif (!empty($_POST['search_term'])) {
            $terms[] = sanitize_text_field(wp_unslash($_POST['search_term']));
        }
        if (empty($terms)) { wp_send_json_error(['message' => 'Search term is empty.']); }

        // Build phrase/token sets
        $phrase_set = []; $token_set = [];
        $swap_variants = ['tee','t-shirt','tshirt','t shirt'];
        foreach ($terms as $raw) {
            $phrase_set[mb_strtolower($raw)] = true;
            foreach ($swap_variants as $v) {
                $alias = preg_replace('/\b(tee|t[\s-]?shirt)\b/i', $v, $raw);
                $phrase_set[mb_strtolower($alias)] = true;
            }
            $toks = preg_split('/[\s,\-_|]+/', mb_strtolower($raw));
            foreach ($toks as $tk) { if (mb_strlen($tk) >= 2) $token_set[$tk] = true; }
            if (preg_match('/\bt[\s-]?shirt\b/i', $raw)) $token_set['tee'] = true;
        }
        $phrases = array_keys($phrase_set);
        $tokens  = array_keys($token_set);

        $ids_title = [];
        foreach ($phrases as $p) {
            $like = '%' . $wpdb->esc_like($p) . '%';
            $ids_title = array_merge($ids_title, (array)$wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND post_title LIKE %s ORDER BY post_date DESC LIMIT 50", $like
            )));
        }
        foreach ($tokens as $tok) {
            $like_tok = '%' . $wpdb->esc_like($tok) . '%';
            $ids_title = array_merge($ids_title, (array)$wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND post_title LIKE %s ORDER BY post_date DESC LIMIT 50", $like_tok
            )));
        }

        $ids_tax = [];
        if (function_exists('wc_get_attribute_taxonomy_names')) {
            $taxonomies = array_merge(['product_cat','product_tag'], array_values(wc_get_attribute_taxonomy_names()));
            $tax_query  = ['relation' => 'OR'];
            foreach ($taxonomies as $tax) {
                $term_ids = [];
                foreach ($tokens as $tok) {
                    $terms_found = get_terms(['taxonomy'=>$tax, 'hide_empty'=>false, 'search'=>$tok, 'number'=>50]);
                    if (!is_wp_error($terms_found) && $terms_found) { $term_ids = array_merge($term_ids, wp_list_pluck($terms_found, 'term_id')); }
                }
                $term_ids = array_values(array_unique($term_ids));
                if (!empty($term_ids)) { $tax_query[] = ['taxonomy'=>$tax, 'field'=>'term_id', 'terms'=>$term_ids, 'operator'=>'IN']; }
            }
            if (count($tax_query) > 1) {
                $q_tax = new \WP_Query(['post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>50,'tax_query'=>$tax_query]);
                $ids_tax = !is_wp_error($q_tax) ? (array)$q_tax->posts : [];
            }
        }

        $sku_terms = array_values(array_unique(array_merge($tokens, ['tee','t-shirt','tshirt','t shirt','hoody'])));
        $meta_query = ['relation' => 'OR'];
        foreach ($sku_terms as $tok) { $meta_query[] = ['key'=>'_sku','value'=>$tok,'compare'=>'LIKE']; }
        $q_sku = new \WP_Query(['post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>50,'meta_query'=>$meta_query]);
        $ids_sku = !is_wp_error($q_sku) ? (array)$q_sku->posts : [];

        $ids_content = [];
        foreach ($phrases as $p) {
            $q_s = new \WP_Query(['post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>50,'s'=>$p]);
            $ids_content = array_merge($ids_content, !is_wp_error($q_s) ? (array)$q_s->posts : []);
        }

        $ids_all = array_values(array_unique(array_merge($ids_title, $ids_tax, $ids_sku, $ids_content)));
        if (empty($ids_all)) { wp_send_json_error(['message' => 'No products found.']); }

        $settings = get_option('aura_chatbot_settings', []);
        $primary_color = $settings['primary_color'] ?? '#42B8FF';
        $html = '';
        foreach (array_slice($ids_all, 0, 12) as $pid) {
            $product = wc_get_product($pid);
            if (!$product) continue;
            $title = get_the_title($pid);
            $image_url = get_the_post_thumbnail_url($pid, 'medium') ?: wc_placeholder_img_src();
            $html .= sprintf(
                '<div class="product-card rounded-lg shadow-md p-4 flex flex-col justify-between" data-id="%1$s" data-name="%2$s"><div><div class="apc-img-wrap"><img src="%3$s" alt="%4$s" class="apc-img"></div><h3 class="font-semibold text-gray-200 text-sm leading-tight truncate">%5$s</h3></div><p class="font-bold mt-2 text-sm" style="color:%6$s">%7$s</p></div>',
                esc_attr($pid), esc_attr($title), esc_url($image_url), esc_attr($title), esc_html($title), esc_attr($primary_color), wp_kses_post($product->get_price_html())
            );
        }
        if ($html === '') { wp_send_json_error(['message' => 'No products found.']); }
        wp_send_json_success(['html' => $html]);
    }

    public function fetch_product_details_ajax_handler(): void
    {
        if (!isset($_POST['product_id']) || !function_exists('wc_get_product')) { wp_send_json_error(); }
        $product_id = absint($_POST['product_id']);
        $product = wc_get_product($product_id);
        if (!$product) { wp_send_json_error(); }
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : wc_placeholder_img_src('large');

        // collect colors/sizes helper (kept local to this module)
        list($colors, $sizes) = $this->collect_colors_sizes_from_product($product);

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

    private function collect_colors_sizes_from_product($product): array
    {
        $colors = []; $sizes = [];

        if ($product->is_type('variable')) {
            $var_attrs = $product->get_variation_attributes();
            foreach ($var_attrs as $key => $slugs) {
                if (empty($slugs)) continue;
                $tax = str_replace('attribute_', '', $key);
                if (stripos($key, 'color') !== false) {
                    foreach ($slugs as $slug) {
                        $term = get_term_by('slug', $slug, $tax);
                        $colors[] = $term && !is_wp_error($term) ? $term->name : wc_clean($slug);
                    }
                } elseif (stripos($key, 'size') !== false) {
                    foreach ($slugs as $slug) {
                        $term = get_term_by('slug', $slug, $tax);
                        $sizes[] = $term && !is_wp_error($term) ? $term->name : strtoupper(wc_clean($slug));
                    }
                }
            }
        }

        $attrs = $product->get_attributes();
        foreach ($attrs as $attr) {
            $name = $attr->get_name();
            if (stripos($name, 'color') !== false) {
                if ($attr->is_taxonomy()) {
                    $terms = wc_get_product_terms($product->get_id(), $name, ['fields' => 'names']);
                    $colors = array_merge($colors, $terms);
                } else {
                    $list = $attr->get_options() ? implode(',', $attr->get_options()) : '';
                    $colors = array_merge($colors, array_map('trim', explode(',', $list)));
                }
            } elseif (stripos($name, 'size') !== false) {
                if ($attr->is_taxonomy()) {
                    $terms = wc_get_product_terms($product->get_id(), $name, ['fields' => 'names']);
                    $sizes = array_merge($sizes, $terms);
                } else {
                    $list = $attr->get_options() ? implode(',', $attr->get_options()) : '';
                    $sizes = array_merge($sizes, array_map('trim', explode(',', $list)));
                }
            }
        }

        $desc = wp_strip_all_tags($product->get_description());
        if ($desc) {
            if (preg_match('/colors?\s*:\s*([^\n\r]+)/i', $desc, $m)) {
                $colors = array_merge($colors, array_map('trim', explode(',', $m[1])));
            }
            if (preg_match('/sizes?\s*:\s*([^\n\r]+)/i', $desc, $m)) {
                $sizes = array_merge($sizes, array_map('trim', explode(',', $m[1])));
            }
        }

        $colors = array_values(array_unique(array_filter(array_map('sanitize_text_field', $colors))));
        $sizes  = array_values(array_unique(array_filter(array_map('sanitize_text_field', $sizes))));

        return [$colors, $sizes];
    }
}