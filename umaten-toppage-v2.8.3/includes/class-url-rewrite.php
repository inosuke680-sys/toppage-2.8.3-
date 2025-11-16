<?php
/**
 * URLリライトルールクラス (v2.8.0 タグ・投稿判定完全修正版)
 * 投稿とタグを正しく識別して適切なページを表示（管理画面・REST API・AJAXでは無効）
 *
 * v2.8.0完全修正：
 * - タグと投稿の優先順位を修正（タグが存在する場合は投稿として検索しない）
 * - /親/子/タグ/ のURLで投稿ページに誤遷移する問題を完全解決
 * - デバッグログとエラーハンドリングを大幅強化
 * - データベースクエリのエラー処理を追加
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class Umaten_Toppage_URL_Rewrite {

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
        // 404時のみカスタム処理を実行（優先度を999に設定）
        add_action('template_redirect', array($this, 'handle_404_redirect'), 999);
    }

    /**
     * 404エラー時のカスタムリダイレクト処理
     */
    public function handle_404_redirect() {
        // 【v2.5.0 最重要】フロントエンドのページリクエストのみ処理
        if (!$this->is_frontend_request()) {
            return;
        }

        // 404でない場合は何もしない
        if (!is_404()) {
            return;
        }

        // 投稿やページが見つかっている場合は何もしない
        if (is_singular() || is_page() || is_single()) {
            return;
        }

        global $wp;
        $current_path = trim($wp->request, '/');

        // パスが空の場合は処理しない
        if (empty($current_path)) {
            return;
        }

        // URLパスを分解
        $parts = explode('/', $current_path);

        // 2段階または3段階のパスのみ処理
        if (count($parts) < 2 || count($parts) > 3) {
            return;
        }

        $parent_slug = isset($parts[0]) ? $parts[0] : '';
        $child_slug = isset($parts[1]) ? $parts[1] : '';
        $tag_slug = isset($parts[2]) ? $parts[2] : '';

        // 【v2.8.0改善】デバッグログ - リクエスト情報を記録
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Umaten Toppage v2.8.0: Processing URL - Parts: " . count($parts) . ", Parent: '{$parent_slug}', Child: '{$child_slug}', Tag: '{$tag_slug}'");
        }

        // 【v2.8.0重要】まずタグとカテゴリの存在を確認
        $parent_term = get_term_by('slug', $parent_slug, 'category');
        $child_term = get_term_by('slug', $child_slug, 'category');
        $tag_term = !empty($tag_slug) ? get_term_by('slug', $tag_slug, 'post_tag') : null;

        // デバッグログ - タグ・カテゴリ情報
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Umaten Toppage v2.8.0: Terms found - Parent: " . ($parent_term ? $parent_term->name : 'none') . ", Child: " . ($child_term ? $child_term->name : 'none') . ", Tag: " . ($tag_term ? $tag_term->name : 'none'));
        }

        // 【v2.8.0完全修正】2段階URL（/親/子/）の場合
        if (count($parts) == 2) {
            // 子カテゴリが存在する場合はアーカイブページとして処理
            if ($child_term) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Umaten Toppage v2.8.0: Child category '{$child_slug}' exists, displaying as archive");
                }
                $this->setup_archive_query($parent_term, $child_term, null);
                return;
            }

            // カテゴリが存在しない場合のみ、投稿として検索
            $post = $this->find_post_by_slug($child_slug);
            if ($post) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Umaten Toppage v2.8.0: Found post by slug '{$child_slug}' (ID: {$post->ID}, Title: '{$post->post_title}') - displaying as single post");
                }
                $this->setup_single_post_query($post);
                return;
            }

            // カテゴリも投稿も見つからない場合は404
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Umaten Toppage v2.8.0: No category or post found for '{$child_slug}' - returning 404");
            }
            return;
        }

        // 【v2.8.0完全修正】3段階URL（/親/子/第3セグメント/）の場合
        if (count($parts) == 3) {
            // 【重要】タグが存在する場合は、投稿として検索しない（アーカイブページとして処理）
            if ($tag_term) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Umaten Toppage v2.8.0: Tag '{$tag_slug}' exists (ID: {$tag_term->term_id}, Name: '{$tag_term->name}') - displaying as tag archive, NOT checking for post");
                }
                $this->setup_archive_query($parent_term, $child_term, $tag_term);
                return;
            }

            // タグが存在しない場合のみ、投稿として検索
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Umaten Toppage v2.8.0: No tag found for '{$tag_slug}', checking for post");
            }

            $post = $this->find_post_by_slug($tag_slug);
            if ($post) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Umaten Toppage v2.8.0: Found post by slug '{$tag_slug}' (ID: {$post->ID}, Title: '{$post->post_title}') - displaying as single post");
                }
                $this->setup_single_post_query($post);
                return;
            }

            // タグも投稿も見つからない場合
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Umaten Toppage v2.8.0: No tag or post found for '{$tag_slug}' - checking for category archive");
            }

            // カテゴリのみのアーカイブページとして処理
            if ($parent_term || $child_term) {
                $this->setup_archive_query($parent_term, $child_term, null);
                return;
            }

            // 何も見つからない場合は404
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Umaten Toppage v2.8.0: No terms or posts found - returning 404");
            }
            return;
        }
    }

    /**
     * 【v2.5.0 新機能】フロントエンドのページリクエストかどうかを判定
     *
     * @return bool フロントエンドのページリクエストの場合true
     */
    private function is_frontend_request() {
        // 管理画面は除外
        if (is_admin()) {
            return false;
        }

        // AJAX リクエストは除外
        if (wp_doing_ajax()) {
            return false;
        }

        // REST API リクエストは除外（複数の方法でチェック）
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        // REST API のパスを含むリクエストは除外
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
            return false;
        }

        // XMLRPC リクエストは除外
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return false;
        }

        // Cron リクエストは除外
        if (defined('DOING_CRON') && DOING_CRON) {
            return false;
        }

        // WP-CLI は除外
        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }

        // すべてのチェックをパスした場合のみtrue
        return true;
    }

    /**
     * 【v2.8.0改善】投稿スラッグから投稿を検索（エラーハンドリング強化）
     *
     * @param string $slug 投稿スラッグ
     * @return WP_Post|null 投稿オブジェクトまたはnull
     */
    private function find_post_by_slug($slug) {
        global $wpdb;

        // 空のスラッグはスキップ
        if (empty($slug)) {
            return null;
        }

        // 投稿を検索
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
            WHERE post_name = %s
            AND post_type = 'post'
            AND post_status = 'publish'
            LIMIT 1",
            $slug
        ));

        // データベースエラーチェック
        if ($wpdb->last_error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Umaten Toppage v2.8.0 DB Error in find_post_by_slug: " . $wpdb->last_error);
            }
            return null;
        }

        if (!$post_id) {
            return null;
        }

        $post = get_post($post_id);

        // デバッグログ
        if ($post && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Umaten Toppage v2.8.0: find_post_by_slug('{$slug}') found post ID {$post->ID} (Title: '{$post->post_title}')");
        }

        return $post;
    }

    /**
     * 【v2.8.0改善版】投稿ページとして表示するクエリをセットアップ
     *
     * @param WP_Post $post 投稿オブジェクト
     */
    private function setup_single_post_query($post) {
        global $wp_query, $wp_the_query;

        // デバッグログ
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Umaten Toppage v2.8.0: Setting up single post query for post ID " . $post->ID . " (Title: '{$post->post_title}', Slug: '{$post->post_name}')");
        }

        // 投稿クエリを作成
        $args = array(
            'p' => $post->ID,
            'post_type' => 'post',
            'post_status' => 'publish'
        );

        // 新しいクエリで上書き
        $wp_query = new WP_Query($args);

        // 【v2.6.0 重要】メインクエリも同期
        $wp_the_query = $wp_query;

        // 404状態を解除し、投稿ページとして設定
        $wp_query->is_404 = false;
        $wp_query->is_single = true;
        $wp_query->is_singular = true;
        $wp_query->is_archive = false;
        $wp_query->is_home = false;
        $wp_query->is_category = false;
        $wp_query->is_tag = false;
        status_header(200);

        // 【v2.6.0 改善】グローバル$postの設定を安全に行う
        // the_post()を呼び出して、WordPressの標準的な方法で設定
        if ($wp_query->have_posts()) {
            $wp_query->the_post();

            // デバッグログ
            if (defined('WP_DEBUG') && WP_DEBUG) {
                global $post;
                error_log("Umaten Toppage v2.6.0: Post loaded successfully - ID: " . $post->ID . ", Title: " . $post->post_title);
            }
        }

        // カスタムテンプレート変数を設定
        set_query_var('umaten_is_single_post', true);
        set_query_var('umaten_post_id', $post->ID);

        // テンプレートをロード
        add_filter('template_include', array($this, 'load_single_template'), 99);
    }

    /**
     * 【v2.8.0改善版】アーカイブクエリをセットアップ（デバッグログ強化）
     */
    private function setup_archive_query($parent_term, $child_term, $tag_term) {
        global $wp_query;

        // デバッグログ
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $parent_name = $parent_term ? $parent_term->name . " (ID: {$parent_term->term_id})" : 'none';
            $child_name = $child_term ? $child_term->name . " (ID: {$child_term->term_id})" : 'none';
            $tag_name = $tag_term ? $tag_term->name . " (ID: {$tag_term->term_id})" : 'none';
            error_log("Umaten Toppage v2.8.0: Setting up archive query - Parent: {$parent_name}, Child: {$child_name}, Tag: {$tag_name}");
        }

        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1
        );

        $tax_query = array('relation' => 'AND');

        // 子カテゴリ優先
        if ($child_term) {
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $child_term->term_id
            );
        } elseif ($parent_term) {
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $parent_term->term_id
            );
        }

        // タグで絞り込み
        if ($tag_term) {
            $tax_query[] = array(
                'taxonomy' => 'post_tag',
                'field' => 'term_id',
                'terms' => $tag_term->term_id
            );
        }

        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }

        // 新しいクエリで上書き
        $wp_query = new WP_Query($args);

        // 404状態を解除
        $wp_query->is_404 = false;
        $wp_query->is_archive = true;
        status_header(200);

        // カスタムテンプレート変数を設定
        set_query_var('umaten_parent_term', $parent_term);
        set_query_var('umaten_child_term', $child_term);
        set_query_var('umaten_tag_term', $tag_term);
        set_query_var('umaten_is_archive', true);

        // テンプレートをロード
        add_filter('template_include', array($this, 'load_custom_template'), 99);
    }

    /**
     * 【v2.5.0】投稿ページテンプレートをロード
     */
    public function load_single_template($template) {
        // umaten_is_single_postフラグがある場合のみ投稿テンプレートを使用
        if (!get_query_var('umaten_is_single_post')) {
            return $template;
        }

        // 投稿テンプレートとして扱う
        $single_template = locate_template(array('single.php', 'singular.php', 'index.php'));

        if ($single_template) {
            // デバッグログ
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Umaten Toppage v2.5.0: Loading single template - " . $single_template);
            }
            return $single_template;
        }

        return $template;
    }

    /**
     * カスタムテンプレートをロード（アーカイブ用）
     */
    public function load_custom_template($template) {
        // umaten_is_archiveフラグがある場合のみカスタムテンプレートを使用
        if (!get_query_var('umaten_is_archive')) {
            return $template;
        }

        // アーカイブテンプレートとして扱う
        $custom_template = locate_template(array('archive.php', 'index.php'));

        if ($custom_template) {
            return $custom_template;
        }

        return $template;
    }

    /**
     * リライトルールをフラッシュ（プラグイン有効化時）
     */
    public static function flush_rewrite_rules() {
        // v2.5.0では特別なリライトルールを追加しないため、
        // 通常のフラッシュのみ実行
        flush_rewrite_rules();
    }
}
