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
        // 【v2.9.7】parse_query フックを使用し、WP_Query解析直後に処理
        // これにより、すべてのプラグインの処理後に確実に実行され、
        // WP_Queryの状態を直接確認できるため、より正確な判定が可能
        add_action('parse_query', array($this, 'handle_plugin_conflicts'), 999);

        // 【v2.9.9】404リダイレクト処理を再度有効化（rrct_activeチェック付き）
        // このメソッドは投稿ページやアーカイブページを正しく表示するために必要
        // ただし、restaurant-review-category-tagsが処理中の場合はスキップ
        add_action('template_redirect', array($this, 'handle_404_redirect'), 999);
    }

    /**
     * 他プラグインとの衝突を完全に回避
     *
     * 以下のプラグインが追加する広範囲なリライトルールが、
     * 投稿ページやWordPress標準のタクソノミーと衝突するのを防ぐ：
     *
     * 1. umaten-restaurant-search-widget (umaten_region, umaten_area, umaten_genre)
     * 2. restaurant-review-category-tags (rrct_category, rrct_parent_category, rrct_child_category, rrct_tag)
     *
     * WordPress標準のクエリ変数が同時に設定されている場合のみ、
     * 他プラグインのクエリ変数をクリアすることで、すべてのプラグインが共存できるようにする。
     *
     * @since 2.9.2
     * @since 2.9.3 条件を修正：WordPress標準のクエリ変数が設定されている場合のみクリア
     * @since 2.9.4 restaurant-review-category-tagsプラグインとの衝突も回避
     * @since 2.9.5 rrct_activeフラグのチェックを追加
     * @since 2.9.6 優先度を15に変更し、他プラグインがquery varsを設定する時間を確保
     * @since 2.9.7 parse_queryフックに変更し、WP_Queryの状態を直接確認
     * @since 2.10.0 【重要】投稿URLの誤認識を修正：widget varsが実際には投稿slugの場合を検出して修正
     *
     * @param WP_Query $query クエリオブジェクト
     */
    public function handle_plugin_conflicts($query) {
        // メインクエリかつフロントエンドリクエストのみ処理
        if (!$query->is_main_query() || !$this->is_frontend_request()) {
            return;
        }

        // 他プラグインのクエリ変数が設定されているか確認
        $has_search_widget_vars = (
            $query->get('umaten_region') ||
            $query->get('umaten_area') ||
            $query->get('umaten_genre')
        );

        $has_rrct_vars = (
            $query->get('rrct_category') ||
            $query->get('rrct_parent_category') ||
            $query->get('rrct_child_category') ||
            $query->get('rrct_tag') ||
            $query->get('rrct_active')
        );

        // どちらのプラグインのクエリ変数も設定されていない場合は何もしない
        if (!$has_search_widget_vars && !$has_rrct_vars) {
            return;
        }

        // 【v2.9.5修正】rrct_activeフラグがある場合、restaurant-review-category-tagsが意図的に処理中
        // この場合は何もしない（category_nameとtagはプラグインが意図的に設定したもの）
        if ($query->get('rrct_active')) {
            return;
        }

        // 【v2.10.0 重要な修正】
        // umaten-restaurant-search-widget の広範なリライトルールが投稿URLを誤ってキャプチャする問題を修正
        //
        // 問題：
        // - /hokkaido/menya-kagetsu-hakodate-kikyo-2/ のような投稿URLが
        // - ^([^/]+)/([^/]+)/?$ のルールにマッチして umaten_region/umaten_area として扱われる
        // - その結果、WordPressは投稿を見つけられず、ホームページにフォールバックしてしまう
        //
        // 解決策：
        // - umaten_area や umaten_genre が実際には投稿スラッグかどうかをチェック
        // - もし投稿が見つかれば、適切な WordPress クエリ変数（category_name + name）に変換
        if ($has_search_widget_vars && !$query->get('name') && !$query->get('category_name')) {
            $umaten_region = $query->get('umaten_region');
            $umaten_area = $query->get('umaten_area');
            $umaten_genre = $query->get('umaten_genre');

            // 2セグメントURL: /region/area/ または /category/post-slug/
            if ($umaten_region && $umaten_area && !$umaten_genre) {
                // umaten_area が投稿スラッグかチェック
                $post = $this->find_post_by_slug($umaten_area);
                if ($post) {
                    // umaten_region がカテゴリスラッグかチェック
                    $category = get_term_by('slug', $umaten_region, 'category');
                    if ($category) {
                        // これは /category/post-slug/ のパターン
                        $query->set('category_name', $umaten_region);
                        $query->set('name', $umaten_area);
                        $query->set('umaten_region', '');
                        $query->set('umaten_area', '');

                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("Umaten Toppage v2.10.0: Detected post URL misidentified as widget vars. Converting umaten_region={$umaten_region}, umaten_area={$umaten_area} → category_name={$umaten_region}, name={$umaten_area} (Post ID: {$post->ID})");
                        }
                        return;
                    }
                }
            }

            // 3セグメントURL: /region/area/genre/ または /parent-cat/child-cat/post-slug/ または /parent-cat/child-cat/tag/
            if ($umaten_region && $umaten_area && $umaten_genre) {
                // umaten_genre が投稿スラッグかチェック
                $post = $this->find_post_by_slug($umaten_genre);
                if ($post) {
                    // これは /parent-category/child-category/post-slug/ のパターンの可能性
                    // WordPress の /%category%/%postname%/ では、category は階層的に結合される
                    $parent_cat = get_term_by('slug', $umaten_region, 'category');
                    $child_cat = get_term_by('slug', $umaten_area, 'category');

                    if ($parent_cat && $child_cat) {
                        // 親/子カテゴリの組み合わせとして設定
                        $query->set('category_name', $umaten_region . '/' . $umaten_area);
                        $query->set('name', $umaten_genre);
                        $query->set('umaten_region', '');
                        $query->set('umaten_area', '');
                        $query->set('umaten_genre', '');

                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("Umaten Toppage v2.10.1: Detected post URL with hierarchy misidentified as widget vars. Converting umaten_region={$umaten_region}, umaten_area={$umaten_area}, umaten_genre={$umaten_genre} → category_name={$umaten_region}/{$umaten_area}, name={$umaten_genre} (Post ID: {$post->ID})");
                        }
                        return;
                    } else if ($parent_cat || $child_cat) {
                        // 少なくとも1つがカテゴリの場合も試す
                        $category_path = '';
                        if ($parent_cat) $category_path = $umaten_region;
                        if ($child_cat) $category_path = $category_path ? $category_path . '/' . $umaten_area : $umaten_area;

                        $query->set('category_name', $category_path);
                        $query->set('name', $umaten_genre);
                        $query->set('umaten_region', '');
                        $query->set('umaten_area', '');
                        $query->set('umaten_genre', '');

                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("Umaten Toppage v2.10.1: Detected post URL misidentified as widget vars. Converting → category_name={$category_path}, name={$umaten_genre} (Post ID: {$post->ID})");
                        }
                        return;
                    }
                } else {
                    // 【v2.10.1 新規追加】投稿でない場合、タグかチェック
                    // これにより /hokkaido/hakodate/ramen/ のようなカテゴリ+タグURLを正しく処理
                    $tag = get_term_by('slug', $umaten_genre, 'post_tag');
                    if ($tag) {
                        // umaten_region と umaten_area がカテゴリかチェック
                        $parent_cat = get_term_by('slug', $umaten_region, 'category');
                        $child_cat = get_term_by('slug', $umaten_area, 'category');

                        if ($child_cat) {
                            // カテゴリ+タグのアーカイブページとして処理
                            // WordPressの標準クエリ変数に変換
                            $category_path = '';

                            // 親子関係をチェック
                            if ($parent_cat && $child_cat->parent == $parent_cat->term_id) {
                                // 正しい階層: /parent/child/tag/
                                $category_path = $umaten_region . '/' . $umaten_area;
                            } else {
                                // 子カテゴリのみ使用: /child/tag/
                                $category_path = $umaten_area;
                            }

                            $query->set('category_name', $category_path);
                            $query->set('tag', $umaten_genre);
                            $query->set('umaten_region', '');
                            $query->set('umaten_area', '');
                            $query->set('umaten_genre', '');

                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("Umaten Toppage v2.10.1: Detected category+tag URL misidentified as widget vars. Converting umaten_region={$umaten_region}, umaten_area={$umaten_area}, umaten_genre={$umaten_genre} → category_name={$category_path}, tag={$umaten_genre} (Tag ID: {$tag->term_id}, Tag Name: '{$tag->name}')");
                            }
                            return;
                        }
                    }
                }
            }
        }

        // 【v2.9.4拡張】WordPress標準のクエリ変数が設定されている場合のみ、他プラグインのクエリ変数をクリア
        // これにより、投稿ページやWordPress標準のタクソノミーは正常に表示され、
        // 各プラグインのカスタムアーカイブページも正常に動作する

        $has_wp_standard_query = (
            // 投稿名（例: /hokkaido/menya-kagetsu-hakodate-kikyo-2/）
            $query->get('name') ||
            $query->get('pagename') ||
            // カテゴリ名（例: /category/news/）
            $query->get('category_name') ||
            // タグ（例: /tag/ramen/）
            $query->get('tag') ||
            // 投稿ID
            $query->get('p') ||
            $query->get('page_id') ||
            // カスタムタクソノミー（region, area, genreなど）
            $query->get('region') ||
            $query->get('area') ||
            $query->get('genre')
        );

        if ($has_wp_standard_query) {
            // WordPress標準のクエリが優先されるべきなので、他プラグインのクエリ変数をクリア

            // 1. umaten-restaurant-search-widget のクエリ変数をクリア
            if ($has_search_widget_vars) {
                $query->set('umaten_region', '');
                $query->set('umaten_area', '');
                $query->set('umaten_genre', '');
            }

            // 2. restaurant-review-category-tags のクエリ変数をクリア
            if ($has_rrct_vars) {
                $query->set('rrct_category', '');
                $query->set('rrct_parent_category', '');
                $query->set('rrct_child_category', '');
                $query->set('rrct_tag', '');
                $query->set('rrct_active', '');
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $detected_vars = array();
                if ($query->get('name')) $detected_vars[] = 'name=' . $query->get('name');
                if ($query->get('pagename')) $detected_vars[] = 'pagename=' . $query->get('pagename');
                if ($query->get('category_name')) $detected_vars[] = 'category_name=' . $query->get('category_name');
                if ($query->get('tag')) $detected_vars[] = 'tag=' . $query->get('tag');
                if ($query->get('region')) $detected_vars[] = 'region=' . $query->get('region');
                if ($query->get('area')) $detected_vars[] = 'area=' . $query->get('area');
                if ($query->get('genre')) $detected_vars[] = 'genre=' . $query->get('genre');

                $cleared_plugins = array();
                if ($has_search_widget_vars) $cleared_plugins[] = 'umaten-restaurant-search-widget';
                if ($has_rrct_vars) $cleared_plugins[] = 'restaurant-review-category-tags';

                error_log(sprintf(
                    "Umaten Toppage v2.10.0: Cleared plugin query vars from [%s] (WP standard query detected: %s)",
                    implode(', ', $cleared_plugins),
                    implode(', ', $detected_vars)
                ));
            }
        }
    }

    /**
     * 404エラー時のカスタムリダイレクト処理
     */
    public function handle_404_redirect() {
        // 【v2.5.0 最重要】フロントエンドのページリクエストのみ処理
        if (!$this->is_frontend_request()) {
            return;
        }

        // 【v2.9.9】restaurant-review-category-tagsが処理中の場合は何もしない
        // このプラグインが独自のカテゴリ+タグアーカイブページを処理している
        if (get_query_var('rrct_active')) {
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
        // 【v2.9.1】通常のフラッシュのみ実行（カスタムリライトルールは削除済み）
        flush_rewrite_rules();
    }
}
