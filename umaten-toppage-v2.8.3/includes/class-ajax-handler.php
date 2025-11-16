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
     * 【v2.10.16】子カテゴリを取得（地域ベースと従来の親子カテゴリの両方に対応）
     */
    public function get_child_categories() {
        check_ajax_referer('umaten_toppage_nonce', 'nonce');

        $parent_slug = isset($_POST['parent_slug']) ? sanitize_text_field($_POST['parent_slug']) : '';

        if (empty($parent_slug)) {
            wp_send_json_error(array('message' => '親カテゴリが指定されていません。'));
            return;
        }

        // 【v2.10.16】デバッグ：親カテゴリスラッグをログ出力
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Umaten Toppage v2.10.16: Searching for categories with parent_slug: {$parent_slug}");
        }

        // 【v2.10.16】地域設定から該当地域のカテゴリリストを取得
        $area_settings = get_option('umaten_toppage_area_settings', array());
        $parent_name = '';
        $child_categories = array();

        // 地域スラッグかどうかをチェック
        if (isset($area_settings[$parent_slug]) && isset($area_settings[$parent_slug]['categories'])) {
            // 地域ベースの場合：設定から都道府県カテゴリスラッグを取得
            $category_slugs = $area_settings[$parent_slug]['categories'];
            $parent_name = $area_settings[$parent_slug]['label'];

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Umaten Toppage v2.10.16: Region '{$parent_slug}' found with " . count($category_slugs) . " category slugs: " . implode(', ', $category_slugs));
            }

            // 各カテゴリスラッグからカテゴリ情報を取得
            foreach ($category_slugs as $cat_slug) {
                $category = get_category_by_slug($cat_slug);
                if ($category) {
                    $child_categories[] = $category;
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Umaten Toppage v2.10.16: Category '{$cat_slug}' not found in WordPress");
                    }
                }
            }
        } else {
            // 従来の親子カテゴリ方式の場合
            $parent_category = get_category_by_slug($parent_slug);

            if (!$parent_category) {
                // デバッグ：親カテゴリが見つからない場合
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $all_categories = get_categories(array('hide_empty' => false));
                    $cat_slugs = array_map(function($cat) {
                        return $cat->slug;
                    }, $all_categories);
                    error_log("Umaten Toppage v2.10.16: Parent category '{$parent_slug}' not found. Available category slugs: " . implode(', ', $cat_slugs));
                }
                wp_send_json_error(array(
                    'message' => "親カテゴリ「{$parent_slug}」が見つかりません。WordPressでカテゴリを作成してください。"
                ));
                return;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Umaten Toppage v2.10.16: Found parent category: {$parent_category->name} (ID: {$parent_category->term_id})");
            }

            $parent_name = $parent_category->name;

            // 子カテゴリを取得
            $child_categories = get_categories(array(
                'parent' => $parent_category->term_id,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC'
            ));
        }

        // デバッグ：取得結果
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Umaten Toppage v2.10.16: Found " . count($child_categories) . " child categories for '{$parent_name}'");
            if (!empty($child_categories)) {
                $child_names = array_map(function($cat) {
                    return $cat->name . ' (' . $cat->slug . ')';
                }, $child_categories);
                error_log("Umaten Toppage v2.10.16: Child categories: " . implode(', ', $child_names));
            }
        }

        if (empty($child_categories)) {
            wp_send_json_error(array(
                'message' => "「{$parent_name}」の子カテゴリが見つかりません。WordPressで「{$parent_name}」の子カテゴリを作成してください。"
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

            // 【v2.10.16】子カテゴリの有無をチェック
            $has_children = false;
            $children_count = get_categories(array(
                'parent' => $category->term_id,
                'hide_empty' => false,
                'count' => true
            ));
            if (!empty($children_count)) {
                $has_children = true;
            }

            $categories_data[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->count,
                'thumbnail' => $thumbnail_url,
                'has_children' => $has_children  // 【v2.10.16】子カテゴリの有無
            );
        }

        wp_send_json_success(array(
            'categories' => $categories_data,
            'parent_name' => $parent_name
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
