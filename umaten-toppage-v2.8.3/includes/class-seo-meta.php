<?php
/**
 * SEOメタタグ生成クラス
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class Umaten_Toppage_SEO_Meta {

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
        add_action('wp_head', array($this, 'add_meta_tags'), 1);
    }

    /**
     * メタタグを追加
     */
    public function add_meta_tags() {
        // カスタムクエリの場合
        $parent_term = get_query_var('umaten_parent_term');
        $child_term = get_query_var('umaten_child_term');
        $tag_term = get_query_var('umaten_tag_term');

        if ($parent_term || $child_term || $tag_term) {
            $this->output_custom_meta_tags($parent_term, $child_term, $tag_term);
            return;
        }

        // 通常のカテゴリページ
        if (is_category()) {
            $category = get_queried_object();
            $this->output_category_meta_tags($category);
            return;
        }

        // 通常のタグページ
        if (is_tag()) {
            $tag = get_queried_object();
            $this->output_tag_meta_tags($tag);
            return;
        }

        // 個別投稿ページ
        if (is_single()) {
            $this->output_post_meta_tags();
            return;
        }
    }

    /**
     * カスタムページのメタタグを出力
     */
    private function output_custom_meta_tags($parent_term, $child_term, $tag_term) {
        $title = $this->get_custom_title($parent_term, $child_term, $tag_term);
        $description = $this->get_custom_description($parent_term, $child_term, $tag_term);
        $url = home_url($_SERVER['REQUEST_URI']);
        $image = $this->get_default_ogp_image();

        $this->output_meta_tags_html($title, $description, $url, $image);
    }

    /**
     * カテゴリページのメタタグを出力
     */
    private function output_category_meta_tags($category) {
        $title = $category->name . ' | ' . get_bloginfo('name');
        $description = !empty($category->description)
            ? wp_trim_words($category->description, 30, '...')
            : $category->name . 'の店舗一覧。' . get_bloginfo('name') . 'であなたにぴったりのお店を見つけよう。';
        $url = get_term_link($category);
        $image = $this->get_category_ogp_image($category);

        $this->output_meta_tags_html($title, $description, $url, $image);
    }

    /**
     * タグページのメタタグを出力
     */
    private function output_tag_meta_tags($tag) {
        $title = $tag->name . ' | ' . get_bloginfo('name');
        $description = !empty($tag->description)
            ? wp_trim_words($tag->description, 30, '...')
            : $tag->name . 'の店舗一覧。' . get_bloginfo('name') . 'であなたにぴったりのお店を見つけよう。';
        $url = get_term_link($tag);
        $image = $this->get_default_ogp_image();

        $this->output_meta_tags_html($title, $description, $url, $image);
    }

    /**
     * 投稿ページのメタタグを出力
     */
    private function output_post_meta_tags() {
        global $post;

        $title = get_the_title() . ' | ' . get_bloginfo('name');
        $description = !empty($post->post_excerpt)
            ? wp_trim_words($post->post_excerpt, 30, '...')
            : wp_trim_words(strip_tags($post->post_content), 30, '...');
        $url = get_permalink();
        $image = $this->get_post_ogp_image($post->ID);

        $this->output_meta_tags_html($title, $description, $url, $image);
    }

    /**
     * カスタムページのタイトルを取得
     */
    private function get_custom_title($parent_term, $child_term, $tag_term) {
        $parts = array();

        if ($parent_term) {
            $parts[] = $parent_term->name;
        }

        if ($child_term) {
            $parts[] = $child_term->name;
        }

        if ($tag_term) {
            $parts[] = $tag_term->name;
        }

        $title = implode(' › ', $parts);
        return $title . ' | ' . get_bloginfo('name');
    }

    /**
     * カスタムページの説明文を取得
     */
    private function get_custom_description($parent_term, $child_term, $tag_term) {
        $parts = array();

        if ($child_term) {
            $parts[] = $child_term->name;
        } elseif ($parent_term) {
            $parts[] = $parent_term->name;
        }

        if ($tag_term) {
            $parts[] = $tag_term->name;
        }

        if (empty($parts)) {
            return get_bloginfo('description');
        }

        return implode('の', $parts) . 'のお店一覧。' . get_bloginfo('name') . 'であなたにぴったりのお店を見つけよう。美味しいグルメ情報をご紹介します。';
    }

    /**
     * 投稿のOGP画像を取得
     */
    private function get_post_ogp_image($post_id) {
        // アイキャッチ画像
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $image = wp_get_attachment_image_url($thumbnail_id, 'full');
            if ($image) {
                return $image;
            }
        }

        // 本文からヒーロー画像を抽出
        $post = get_post($post_id);
        if ($post) {
            $content = $post->post_content;

            // restaurant-hero-imageクラスを持つ画像を検索
            preg_match('/<img[^>]+class=["\'][^"\']*restaurant-hero-image[^"\']*["\'][^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

            if (!empty($matches[1])) {
                return $matches[1];
            }

            // 通常の最初の画像を検索
            preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

            if (!empty($matches[1])) {
                return $matches[1];
            }
        }

        return $this->get_default_ogp_image();
    }

    /**
     * カテゴリのOGP画像を取得
     */
    private function get_category_ogp_image($category) {
        // カテゴリに関連する最新の投稿の画像を取得
        $posts = get_posts(array(
            'category' => $category->term_id,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));

        if (!empty($posts)) {
            return $this->get_post_ogp_image($posts[0]->ID);
        }

        return $this->get_default_ogp_image();
    }

    /**
     * デフォルトOGP画像を取得
     */
    private function get_default_ogp_image() {
        // サイトのロゴまたはデフォルト画像
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $image = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($image) {
                return $image;
            }
        }

        // デフォルト画像
        return 'https://umaten.jp/wp-content/uploads/2025/11/fuji-san-pagoda-view.webp';
    }

    /**
     * メタタグHTMLを出力
     */
    private function output_meta_tags_html($title, $description, $url, $image) {
        echo "\n<!-- Umaten SEO Meta Tags -->\n";

        // 基本メタタグ
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta name="robots" content="index, follow">' . "\n";
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";

        // OGP (Open Graph Protocol)
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
        echo '<meta property="og:locale" content="ja_JP">' . "\n";

        // Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";

        echo "<!-- /Umaten SEO Meta Tags -->\n\n";
    }
}
