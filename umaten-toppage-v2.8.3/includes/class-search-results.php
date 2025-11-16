<?php
/**
 * 検索結果ページクラス
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class Umaten_Toppage_Search_Results {

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
        add_shortcode('umaten_search_results', array($this, 'render_search_results'));
    }

    /**
     * 検索結果ページのレンダリング
     */
    public function render_search_results($atts) {
        // スクリプトとスタイルをエンキュー
        wp_enqueue_style('umaten-toppage-style');

        // 属性のデフォルト値
        $atts = shortcode_atts(array(
            'posts_per_page' => 12,
            'orderby' => 'date',
            'order' => 'DESC'
        ), $atts);

        // URLリライトからの情報を取得
        $parent_term = get_query_var('umaten_parent_term');
        $child_term = get_query_var('umaten_child_term');
        $tag_term = get_query_var('umaten_tag_term');

        // URLリライトからの情報があればそれを使用
        if ($parent_term || $child_term || $tag_term) {
            $path_info = array(
                'parent_slug' => $parent_term ? $parent_term->slug : '',
                'child_slug' => $child_term ? $child_term->slug : '',
                'tag_slug' => $tag_term ? $tag_term->slug : '',
                'parent_term' => $parent_term,
                'child_term' => $child_term,
                'tag_term' => $tag_term
            );
            // グローバルクエリを使用
            global $wp_query;
            $query = $wp_query;
        } else {
            // URLパスを解析（通常のショートコード使用時）
            $path_info = $this->parse_url_path();

            if (!$path_info) {
                return $this->render_no_results('URLが正しくありません。');
            }

            // 投稿を取得
            $query_args = $this->build_query_args($path_info, $atts);
            $query = new WP_Query($query_args);
        }

        ob_start();
        ?>
        <div class="meshimap-wrapper">
            <!-- パンくずリスト -->
            <?php echo $this->render_breadcrumbs($path_info); ?>

            <!-- ヒーローセクション -->
            <?php echo $this->render_hero($path_info); ?>

            <!-- 検索結果 -->
            <section class="meshimap-section">
                <div class="meshimap-container">
                    <?php if ($query->have_posts()): ?>
                        <div class="meshimap-results-header">
                            <h2 class="meshimap-results-title">
                                <?php echo esc_html($this->get_page_title($path_info)); ?>
                            </h2>
                            <p class="meshimap-results-count">
                                <?php echo number_format($query->found_posts); ?>件の店舗が見つかりました
                            </p>
                        </div>

                        <div class="meshimap-posts-grid">
                            <?php while ($query->have_posts()): $query->the_post(); ?>
                                <?php echo $this->render_post_card(get_post()); ?>
                            <?php endwhile; ?>
                        </div>

                        <!-- ペジネーション -->
                        <?php echo $this->render_pagination($query); ?>

                    <?php else: ?>
                        <?php echo $this->render_no_results('該当する店舗が見つかりませんでした。'); ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * URLパスを解析
     */
    private function parse_url_path() {
        global $wp;
        $current_url = home_url($wp->request);
        $site_url = home_url('/');

        // サイトURLを除去してパスを取得
        $path = str_replace($site_url, '', $current_url);
        $path = trim($path, '/');

        // パスを分解
        $parts = array_filter(explode('/', $path));

        if (empty($parts)) {
            return false;
        }

        $result = array(
            'parent_slug' => isset($parts[0]) ? $parts[0] : '',
            'child_slug' => isset($parts[1]) ? $parts[1] : '',
            'tag_slug' => isset($parts[2]) ? $parts[2] : '',
            'parent_term' => null,
            'child_term' => null,
            'tag_term' => null
        );

        // 親カテゴリを取得
        if ($result['parent_slug']) {
            $result['parent_term'] = get_term_by('slug', $result['parent_slug'], 'category');
        }

        // 子カテゴリを取得
        if ($result['child_slug']) {
            $result['child_term'] = get_term_by('slug', $result['child_slug'], 'category');
        }

        // タグを取得
        if ($result['tag_slug']) {
            $result['tag_term'] = get_term_by('slug', $result['tag_slug'], 'post_tag');
        }

        return $result;
    }

    /**
     * クエリ引数を構築
     */
    private function build_query_args($path_info, $atts) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['posts_per_page']),
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1
        );

        // タックスクエリを構築
        $tax_query = array('relation' => 'AND');

        // 子カテゴリがある場合は子カテゴリで絞り込み
        if ($path_info['child_term']) {
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $path_info['child_term']->term_id
            );
        }
        // 子カテゴリがなく親カテゴリのみの場合
        elseif ($path_info['parent_term']) {
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $path_info['parent_term']->term_id
            );
        }

        // タグで絞り込み
        if ($path_info['tag_term']) {
            $tax_query[] = array(
                'taxonomy' => 'post_tag',
                'field' => 'term_id',
                'terms' => $path_info['tag_term']->term_id
            );
        }

        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }

        return $args;
    }

    /**
     * ページタイトルを取得
     */
    private function get_page_title($path_info) {
        $parts = array();

        if ($path_info['parent_term']) {
            $parts[] = $path_info['parent_term']->name;
        }

        if ($path_info['child_term']) {
            $parts[] = $path_info['child_term']->name;
        }

        if ($path_info['tag_term']) {
            $parts[] = $path_info['tag_term']->name;
        }

        if (empty($parts)) {
            return '検索結果';
        }

        return implode(' › ', $parts);
    }

    /**
     * パンくずリストをレンダリング
     */
    private function render_breadcrumbs($path_info) {
        $breadcrumbs = array();
        $breadcrumbs[] = '<a href="' . esc_url(home_url('/')) . '">ホーム</a>';

        if ($path_info['parent_term']) {
            $breadcrumbs[] = '<span>' . esc_html($path_info['parent_term']->name) . '</span>';
        }

        if ($path_info['child_term']) {
            $breadcrumbs[] = '<span>' . esc_html($path_info['child_term']->name) . '</span>';
        }

        if ($path_info['tag_term']) {
            $breadcrumbs[] = '<span>' . esc_html($path_info['tag_term']->name) . '</span>';
        }

        return '<div class="meshimap-breadcrumbs">' . implode(' <span class="separator">›</span> ', $breadcrumbs) . '</div>';
    }

    /**
     * ヒーローセクションをレンダリング
     */
    private function render_hero($path_info) {
        $title = $this->get_page_title($path_info);
        $description = $this->get_page_description($path_info);

        return sprintf(
            '<section class="meshimap-hero meshimap-hero-small">
                <div class="meshimap-hero-bg"></div>
                <div class="meshimap-hero-content">
                    <h1 class="meshimap-hero-title">%s</h1>
                    <p class="meshimap-hero-subtitle">%s</p>
                </div>
            </section>',
            esc_html($title),
            esc_html($description)
        );
    }

    /**
     * ページ説明文を取得
     */
    private function get_page_description($path_info) {
        $parts = array();

        if ($path_info['child_term']) {
            $parts[] = $path_info['child_term']->name;
        } elseif ($path_info['parent_term']) {
            $parts[] = $path_info['parent_term']->name;
        }

        if ($path_info['tag_term']) {
            $parts[] = $path_info['tag_term']->name;
        }

        if (empty($parts)) {
            return 'あなたにぴったりのお店を見つけよう';
        }

        return implode('の', $parts) . 'のお店をご紹介';
    }

    /**
     * 投稿カードをレンダリング
     */
    private function render_post_card($post) {
        // v2.6.0: ヒーロー画像メタデータから取得
        $thumbnail = Umaten_Toppage_Hero_Image::get_hero_image_url($post->ID);
        if (!$thumbnail) {
            $thumbnail = 'https://umaten.jp/wp-content/uploads/2025/11/fuji-san-pagoda-view.webp';
        }

        $categories = get_the_category($post->ID);
        $category_name = !empty($categories) ? $categories[0]->name : '';

        $excerpt = wp_trim_words(get_the_excerpt($post), 30, '...');

        // ビュー数を取得
        $views = get_post_meta($post->ID, 'post_views_count', true);
        $views = $views ? number_format($views) : '0';

        return sprintf(
            '<article class="meshimap-post-card">
                <a href="%s" class="meshimap-post-card-link">
                    <div class="meshimap-post-card-image" style="background-image: url(%s);">
                        %s
                    </div>
                    <div class="meshimap-post-card-content">
                        <h3 class="meshimap-post-card-title">%s</h3>
                        <p class="meshimap-post-card-excerpt">%s</p>
                        <div class="meshimap-post-card-meta">
                            <span class="meshimap-post-card-date">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                %s
                            </span>
                            <span class="meshimap-post-card-views">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                %s
                            </span>
                        </div>
                    </div>
                </a>
            </article>',
            esc_url(get_permalink($post->ID)),
            esc_url($thumbnail),
            !empty($category_name) ? '<span class="meshimap-post-card-badge">' . esc_html($category_name) . '</span>' : '',
            esc_html(get_the_title($post)),
            esc_html($excerpt),
            esc_html(get_the_date('Y.m.d', $post)),
            esc_html($views)
        );
    }

    /**
     * ペジネーションをレンダリング
     */
    private function render_pagination($query) {
        if ($query->max_num_pages <= 1) {
            return '';
        }

        $output = '<div class="meshimap-pagination">';

        $output .= paginate_links(array(
            'total' => $query->max_num_pages,
            'current' => max(1, get_query_var('paged')),
            'prev_text' => '&laquo; 前へ',
            'next_text' => '次へ &raquo;',
            'type' => 'list'
        ));

        $output .= '</div>';

        return $output;
    }

    /**
     * 結果なしメッセージをレンダリング
     */
    private function render_no_results($message) {
        return sprintf(
            '<div class="meshimap-coming-soon">
                <div class="meshimap-coming-soon-icon">&#128269;</div>
                <h3 class="meshimap-coming-soon-title">検索結果が見つかりません</h3>
                <p class="meshimap-coming-soon-text">%s<br>別の条件でお試しください。</p>
                <a href="%s" class="meshimap-button">トップページに戻る</a>
            </div>',
            esc_html($message),
            esc_url(home_url('/'))
        );
    }
}
