<?php
/**
 * AJAX処理ハンドラークラス
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class Umaten_Toppage_Ajax_Handler {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        // 子カテゴリ取得
        add_action('wp_ajax_umaten_get_child_categories', array($this, 'get_child_categories'));
        add_action('wp_ajax_nopriv_umaten_get_child_categories', array($this, 'get_child_categories'));

        // タグ取得
        add_action('wp_ajax_umaten_get_tags', array($this, 'get_tags'));
        add_action('wp_ajax_nopriv_umaten_get_tags', array($this, 'get_tags'));

        // エリア設定取得
        add_action('wp_ajax_umaten_get_area_settings', array($this, 'get_area_settings'));
        add_action('wp_ajax_nopriv_umaten_get_area_settings', array($this, 'get_area_settings'));
    }

    /**
     * 子カテゴリを取得
     */
    public function get_child_categories() {
        check_ajax_referer('umaten_toppage_nonce', 'nonce');

        $parent_slug = isset($_POST['parent_slug']) ? sanitize_text_field($_POST['parent_slug']) : '';

        if (empty($parent_slug)) {
            wp_send_json_error(array('message' => '親カテゴリが指定されていません。'));
            return;
        }

        // 【v2.10.15】デバッグ：親カテゴリスラッグをログ出力
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Umaten Toppage v2.10.15: Searching for parent category with slug: {$parent_slug}");
        }

        // 親カテゴリを取得
        $parent_category = get_category_by_slug($parent_slug);

        if (!$parent_category) {
            // 【v2.10.15】デバッグ：親カテゴリが見つからない場合、全カテゴリをログ出力
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $all_categories = get_categories(array('hide_empty' => false));
                $cat_slugs = array_map(function($cat) {
                    return $cat->slug;
                }, $all_categories);
                error_log("Umaten Toppage v2.10.15: Parent category '{$parent_slug}' not found. Available category slugs: " . implode(', ', $cat_slugs));
            }
            wp_send_json_error(array(
                'message' => "親カテゴリ「{$parent_slug}」が見つかりません。WordPressでカテゴリを作成してください。"
            ));
            return;
        }

        // 【v2.10.15】デバッグ：親カテゴリ発見
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Umaten Toppage v2.10.15: Found parent category: {$parent_category->name} (ID: {$parent_category->term_id})");
        }

        // 子カテゴリを取得
        $child_categories = get_categories(array(
            'parent' => $parent_category->term_id,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        // 【v2.10.15】デバッグ：子カテゴリ取得結果
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Umaten Toppage v2.10.15: Found " . count($child_categories) . " child categories for parent '{$parent_category->name}'");
            if (!empty($child_categories)) {
                $child_names = array_map(function($cat) {
                    return $cat->name . ' (' . $cat->slug . ')';
                }, $child_categories);
                error_log("Umaten Toppage v2.10.15: Child categories: " . implode(', ', $child_names));
            }
        }

        if (empty($child_categories)) {
            wp_send_json_error(array(
                'message' => "「{$parent_category->name}」の子カテゴリが見つかりません。WordPressで「{$parent_category->name}」の子カテゴリを作成してください。"
            ));
            return;
        }

        $categories_data = array();
        foreach ($child_categories as $category) {
            // カテゴリのサムネイル画像を取得（カスタムフィールドやACFから取得する場合）
            $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
            $thumbnail_url = '';

            if ($thumbnail_id) {
                $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'medium');
            }

            // デフォルト画像（サムネイルがない場合）
            if (empty($thumbnail_url)) {
                $thumbnail_url = 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800';
            }

            $categories_data[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->count,
                'thumbnail' => $thumbnail_url
            );
        }

        wp_send_json_success(array(
            'categories' => $categories_data,
            'parent_name' => $parent_category->name
        ));
    }

    /**
     * タグを取得
     */
    public function get_tags() {
        check_ajax_referer('umaten_toppage_nonce', 'nonce');

        // すべてのタグを取得（使用頻度順）
        $tags = get_tags(array(
            'orderby' => 'count',
            'order' => 'DESC',
            'hide_empty' => false,
            'number' => 50 // 最大50個のタグを取得
        ));

        if (empty($tags) || is_wp_error($tags)) {
            wp_send_json_error(array('message' => 'タグが見つかりません。'));
            return;
        }

        $tags_data = array();
        foreach ($tags as $tag) {
            $tags_data[] = array(
                'id' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'count' => $tag->count
            );
        }

        wp_send_json_success(array('tags' => $tags_data));
    }

    /**
     * エリア設定を取得
     */
    public function get_area_settings() {
        check_ajax_referer('umaten_toppage_nonce', 'nonce');

        $area_settings = get_option('umaten_toppage_area_settings', array());

        // デフォルトエリア
        $default_areas = array(
            'hokkaido' => '北海道',
            'tohoku' => '東北',
            'kanto' => '関東',
            'chubu' => '中部',
            'kansai' => '関西',
            'chugoku' => '中国',
            'shikoku' => '四国',
            'kyushu-okinawa' => '九州・沖縄'
        );

        // 設定がない場合はデフォルトを使用
        $formatted_settings = array();
        foreach ($default_areas as $key => $default_label) {
            if (isset($area_settings[$key])) {
                $formatted_settings[$key] = $area_settings[$key];
            } else {
                $formatted_settings[$key] = array(
                    'status' => 'coming_soon',
                    'label' => $default_label
                );
            }
        }

        wp_send_json_success(array('areas' => $formatted_settings));
    }

    /**
     * 親カテゴリスラッグからカテゴリ情報を取得するヘルパー関数
     */
    private function get_parent_category_info($parent_slug) {
        $category = get_category_by_slug($parent_slug);

        if (!$category) {
            return null;
        }

        return array(
            'id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description
        );
    }
}
