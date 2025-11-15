<?php
/**
 * ヒーロー画像自動設定クラス (v2.8.3 - SEO最適化版)
 *
 * 本文から画像を抽出し、WordPressのアイキャッチ画像（_thumbnail_id）として自動設定します。
 * SEO効果：OGP画像、構造化データ、検索結果での画像表示に対応。
 * 記事ページでは本文中の画像を使用し、一覧ページではアイキャッチを表示します。
 *
 * v2.8.3: 記事ページでのアイキャッチ完全非表示（SEO最適化）
 * - アイキャッチを設定してSEO効果を維持（OGP、Schema.org対応）
 * - 記事ページでは複数フィルターでアイキャッチを完全ブロック（重複回避）
 * - has_post_thumbnail/get_post_metadata/post_thumbnail_htmlの3段階フィルター
 * - v2.8.2の安定性を維持したシンプル実装
 * - 公開API: Umaten_Toppage_Hero_Image::get_hero_image_url($post_id)
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class Umaten_Toppage_Hero_Image {

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
        // 投稿保存時にヒーロー画像URLをメタデータとして保存
        add_action('save_post', array($this, 'save_hero_image_metadata'), 10, 2);

        // 記事ページではアイキャッチを完全非表示（3段階フィルター）
        // レベル1: HTMLを空文字にする
        add_filter('post_thumbnail_html', array($this, 'hide_thumbnail_on_single'), 10, 5);

        // レベル2: has_post_thumbnail()をfalseにする
        add_filter('has_post_thumbnail', array($this, 'disable_thumbnail_check_on_single'), 10, 2);

        // レベル3: get_post_metadata()で_thumbnail_idを空にする
        add_filter('get_post_metadata', array($this, 'hide_thumbnail_id_on_single'), 10, 4);

        // 既存の投稿にヒーロー画像メタデータを設定するためのフック（オプション）
        add_action('admin_init', array($this, 'maybe_add_admin_notice'));
    }

    /**
     * 投稿保存時にヒーロー画像URLをメタデータとして保存
     */
    public function save_hero_image_metadata($post_id, $post) {
        // 自動保存時はスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 投稿タイプが post でない場合はスキップ
        if ($post->post_type !== 'post') {
            return;
        }

        // リビジョンの場合はスキップ
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // 既にヒーロー画像メタデータが設定されている場合はスキップ
        $existing_hero_image = get_post_meta($post_id, '_umaten_hero_image_url', true);
        if (!empty($existing_hero_image)) {
            return;
        }

        // 本文から画像を抽出してメタデータとして保存
        $this->extract_and_save_hero_image($post_id, $post->post_content);
    }

    /**
     * 本文から画像を抽出してメタデータとして保存
     */
    private function extract_and_save_hero_image($post_id, $content) {
        $image_url = null;

        // 優先順位1: restaurant-hero-imageクラスを持つ画像
        if (preg_match('/<img[^>]+class=["\'][^"\']*restaurant-hero-image[^"\']*["\'][^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $image_url = $matches[1];
        }
        // 優先順位2: ls-is-cached lazyloadedクラスを持つ画像
        elseif (preg_match('/<img[^>]+class=["\'][^"\']*ls-is-cached[^"\']*lazyloaded[^"\']*["\'][^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $image_url = $matches[1];
        }
        // 優先順位3: data-src属性を持つ画像（Lazy Load用）
        elseif (preg_match('/<img[^>]+data-src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $image_url = $matches[1];
        }
        // 優先順位4: 最初の通常の画像
        elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $image_url = $matches[1];
        }

        // 画像URLが見つかった場合、アイキャッチとメタデータに保存
        if ($image_url) {
            // 相対URLを絶対URLに変換
            if (strpos($image_url, 'http') !== 0) {
                $image_url = site_url($image_url);
            }

            // attachment IDを取得
            $attachment_id = attachment_url_to_postid($image_url);

            // attachment IDが見つかった場合、アイキャッチとして設定
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);

                // デバッグログ
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Umaten Toppage v2.8.3: Set thumbnail for post {$post_id}: attachment ID {$attachment_id}");
                }
            }

            // メタデータとしても保存（バックアップ）
            update_post_meta($post_id, '_umaten_hero_image_url', esc_url($image_url));

            // デバッグログ
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Umaten Toppage v2.8.3: Saved hero image URL for post {$post_id}: {$image_url}");
            }

            return true;
        }

        return false;
    }

    /**
     * レベル1: 記事ページではアイキャッチHTMLを空文字にする
     */
    public function hide_thumbnail_on_single($html, $post_id, $post_thumbnail_id, $size, $attr) {
        // 記事ページ（シングルページ）の場合は空文字を返す
        if (is_single() || is_singular('post')) {
            return '';
        }

        // それ以外（一覧ページなど）ではそのまま表示
        return $html;
    }

    /**
     * レベル2: 記事ページではhas_post_thumbnail()をfalseにする
     */
    public function disable_thumbnail_check_on_single($has_thumbnail, $post_id) {
        // 記事ページの場合はfalseを返す
        if (is_single() || is_singular('post')) {
            return false;
        }

        // それ以外（一覧ページなど）ではそのまま
        return $has_thumbnail;
    }

    /**
     * レベル3: 記事ページではget_post_metadata()で_thumbnail_idを空にする
     *
     * これにより、テーマが直接get_post_meta()などでアイキャッチIDを取得しようとしても
     * 記事ページでは空として扱われます。
     */
    public function hide_thumbnail_id_on_single($value, $object_id, $meta_key, $single) {
        // _thumbnail_idメタデータの取得時のみ処理
        if ($meta_key !== '_thumbnail_id') {
            return $value;
        }

        // 記事ページの場合は空を返す
        if (is_single() || is_singular('post')) {
            return $single ? '' : array();
        }

        // それ以外（一覧ページなど）ではそのまま
        return $value;
    }

    /**
     * 投稿のヒーロー画像URLを取得（公開API）
     *
     * @param int $post_id 投稿ID
     * @return string|false ヒーロー画像URLまたはfalse
     */
    public static function get_hero_image_url($post_id) {
        // まずメタデータから取得
        $hero_image_url = get_post_meta($post_id, '_umaten_hero_image_url', true);

        if (!empty($hero_image_url)) {
            return esc_url($hero_image_url);
        }

        // メタデータがない場合、アイキャッチ画像を取得（後方互換性）
        if (has_post_thumbnail($post_id)) {
            return get_the_post_thumbnail_url($post_id, 'large');
        }

        return false;
    }

    /**
     * 管理画面に通知を表示（既存投稿の一括処理用）
     */
    public function maybe_add_admin_notice() {
        // 管理者のみ
        if (!current_user_can('manage_options')) {
            return;
        }

        // 一括処理が実行されたかチェック
        if (isset($_GET['umaten_bulk_hero_image']) && $_GET['umaten_bulk_hero_image'] === 'done') {
            add_action('admin_notices', array($this, 'bulk_process_notice'));
        }
    }

    /**
     * 一括処理完了通知
     */
    public function bulk_process_notice() {
        $processed = isset($_GET['processed']) ? intval($_GET['processed']) : 0;
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . sprintf(__('%d件の投稿にヒーロー画像メタデータを設定しました。', 'umaten-toppage'), $processed) . '</p>';
        echo '</div>';
    }

    /**
     * 既存投稿に一括でヒーロー画像メタデータを設定（管理画面から実行）
     */
    public static function bulk_set_hero_images() {
        // 管理者のみ実行可能
        if (!current_user_can('manage_options')) {
            return;
        }

        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_umaten_hero_image_url',
                    'compare' => 'NOT EXISTS'
                )
            )
        );

        $posts = get_posts($args);
        $processed = 0;

        $instance = self::get_instance();

        foreach ($posts as $post) {
            if ($instance->extract_and_save_hero_image($post->ID, $post->post_content)) {
                $processed++;
            }
        }

        // リダイレクト
        wp_redirect(add_query_arg(array(
            'umaten_bulk_hero_image' => 'done',
            'processed' => $processed
        ), admin_url('edit.php')));
        exit;
    }
}
