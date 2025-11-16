<?php
/**
 * ショートコードクラス
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class Umaten_Toppage_Shortcode {

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
        add_shortcode('umaten_toppage', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * スクリプトとスタイルの登録
     */
    public function enqueue_scripts() {
        // CSSの登録
        wp_register_style(
            'umaten-toppage-style',
            UMATEN_TOPPAGE_PLUGIN_URL . 'assets/css/toppage.css',
            array(),
            UMATEN_TOPPAGE_VERSION
        );

        // JavaScriptの登録
        wp_register_script(
            'umaten-toppage-script',
            UMATEN_TOPPAGE_PLUGIN_URL . 'assets/js/toppage.js',
            array('jquery'),
            UMATEN_TOPPAGE_VERSION,
            true
        );

        // AJAX用のデータをローカライズ
        wp_localize_script('umaten-toppage-script', 'umatenToppage', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('umaten_toppage_nonce'),
            'siteUrl' => home_url()
        ));
    }

    /**
     * ショートコードのレンダリング
     */
    public function render_shortcode($atts) {
        // 【v2.7.0修正】記事ページ（single.php）では何も表示しない
        if (is_single() || is_singular('post')) {
            return '';
        }

        // スクリプトとスタイルをエンキュー
        wp_enqueue_style('umaten-toppage-style');
        wp_enqueue_script('umaten-toppage-script');

        // 属性のデフォルト値
        $atts = shortcode_atts(array(
            'show_hero' => 'yes',
            'show_stats' => 'yes',
            'show_genres' => 'yes',
            'show_areas' => 'yes'
        ), $atts);

        ob_start();
        ?>
        <div class="meshimap-wrapper">
            <?php if ($atts['show_hero'] === 'yes'): ?>
            <!-- ヒーローセクション -->
            <section class="meshimap-hero">
                <div class="meshimap-hero-bg"></div>
                <div class="meshimap-hero-content">
                    <h1 class="meshimap-hero-title">日本最大級のグルメポータル</h1>
                    <p class="meshimap-hero-subtitle">全国の美味しいお店を探そう</p>
                    <div class="meshimap-search-wrapper">
                        <form class="meshimap-search-form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                            <input type="text" class="meshimap-search-input" name="s" placeholder="エリア・駅名・店名・ジャンルで検索" required>
                            <button type="submit" class="meshimap-search-button">&#128269; 検索する</button>
                        </form>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($atts['show_stats'] === 'yes'): ?>
            <!-- 統計バー -->
            <section class="meshimap-stats">
                <div class="meshimap-stats-inner">
                    <div class="meshimap-stat-item">
                        <div class="meshimap-stat-number"><?php echo $this->get_posts_count(); ?></div>
                        <div class="meshimap-stat-label">掲載店舗</div>
                    </div>
                    <div class="meshimap-stat-item">
                        <div class="meshimap-stat-number"><?php echo $this->get_review_count(); ?>+</div>
                        <div class="meshimap-stat-label">口コミ</div>
                    </div>
                    <div class="meshimap-stat-item">
                        <div class="meshimap-stat-number"><?php echo $this->get_monthly_access_count(); ?></div>
                        <div class="meshimap-stat-label">月間アクセス</div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($atts['show_genres'] === 'yes'): ?>
            <!-- 人気ジャンル -->
            <section class="meshimap-section" id="genre-section">
                <div class="meshimap-container">
                    <h2>&#127860; 人気のジャンル</h2>
                    <div class="meshimap-category-grid">
                        <?php echo $this->render_genre_cards(); ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($atts['show_areas'] === 'yes'): ?>
            <!-- エリアから探す -->
            <section class="meshimap-section meshimap-section-gray" id="area-section">
                <div class="meshimap-container">
                    <h2>&#128205; エリアから探す</h2>
                    <div class="meshimap-area-tabs" id="area-tabs-container">
                        <!-- JavaScriptで動的に生成 -->
                    </div>

                    <div id="area-content-container">
                        <!-- JavaScriptで動的に生成 -->
                    </div>

                    <!-- 子カテゴリ選択モーダル -->
                    <div id="child-category-modal" class="umaten-modal" style="display: none;">
                        <div class="umaten-modal-content">
                            <div class="umaten-modal-header">
                                <h3 id="modal-title">エリアを選択</h3>
                                <button class="umaten-modal-close" id="modal-close-btn">&times;</button>
                            </div>
                            <div class="umaten-modal-body">
                                <div id="child-categories-grid" class="meshimap-category-grid">
                                    <!-- JavaScriptで動的に生成 -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ジャンル選択モーダル -->
                    <div id="tag-modal" class="umaten-modal" style="display: none;">
                        <div class="umaten-modal-content">
                            <div class="umaten-modal-header">
                                <h3 id="tag-modal-title">ジャンルを選択</h3>
                                <button class="umaten-modal-close" id="tag-modal-close-btn">&times;</button>
                            </div>
                            <div class="umaten-modal-body">
                                <div id="tags-grid" class="meshimap-tags-grid">
                                    <!-- JavaScriptで動的に生成 -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 投稿数を取得（掲載店舗数）
     */
    private function get_posts_count() {
        $count = wp_count_posts('post');
        return number_format($count->publish);
    }

    /**
     * 口コミ数を取得（投稿数の3倍）
     */
    private function get_review_count() {
        $count = wp_count_posts('post');
        $review_count = $count->publish * 3;
        return number_format($review_count);
    }

    /**
     * 月間アクセス数を取得
     */
    private function get_monthly_access_count() {
        // 独自のビューカウンターを使用（優先）
        $view_counter = Umaten_Toppage_View_Counter::get_instance();
        $monthly_views = $view_counter->get_monthly_views();

        if ($monthly_views > 0) {
            return number_format($monthly_views);
        }

        // WP Statisticsプラグインがインストールされている場合
        if (function_exists('wp_statistics_pages')) {
            $stats = wp_statistics_pages('total', 'uri', 30); // 過去30日
            if (!empty($stats)) {
                return number_format(array_sum(wp_list_pluck($stats, 'count')));
            }
        }

        // Jetpackのサイト統計がある場合
        if (function_exists('stats_get_csv')) {
            $stats = stats_get_csv('views', array('days' => 30));
            if (!empty($stats)) {
                $total = array_sum(array_column($stats, 1));
                if ($total > 0) {
                    return number_format($total);
                }
            }
        }

        // 全期間の投稿ビュー数を取得
        global $wpdb;
        $total_views = $wpdb->get_var(
            "SELECT SUM(meta_value) FROM {$wpdb->postmeta}
             WHERE meta_key = 'post_views_count'"
        );

        if ($total_views && $total_views > 0) {
            // 全期間のビュー数を30日分として概算表示
            return number_format($total_views);
        }

        // デフォルト値（投稿数 × 100 の概算）
        $count = wp_count_posts('post');
        $estimated_views = $count->publish * 100;
        return number_format($estimated_views);
    }

    /**
     * ジャンルカードをレンダリング
     */
    private function render_genre_cards() {
        $genres = array(
            array(
                'name' => 'ラーメン',
                'slug' => 'ramen',
                'image' => 'https://umaten.jp/wp-content/uploads/2025/11/ramen-tamago-topping.webp',
                'badge' => '人気No.1'
            ),
            array(
                'name' => '寿司・和食',
                'slug' => 'washoku',
                'image' => 'https://umaten.jp/wp-content/uploads/2025/11/sushi-shoyu-sosu-set.webp',
                'badge' => ''
            ),
            array(
                'name' => 'イタリアン',
                'slug' => 'italian',
                'image' => 'https://umaten.jp/wp-content/uploads/2025/11/bbq-tori-pizza.webp',
                'badge' => ''
            ),
            array(
                'name' => '中華料理',
                'slug' => 'chuka',
                'image' => 'https://umaten.jp/wp-content/uploads/2025/11/tori-karaage-orenjisarada.webp',
                'badge' => ''
            ),
            array(
                'name' => '焼肉・ステーキ',
                'slug' => 'yakiniku',
                'image' => 'https://umaten.jp/wp-content/uploads/2025/11/indian-thali-meal.webp',
                'badge' => ''
            ),
            array(
                'name' => 'カフェ・スイーツ',
                'slug' => 'cafe',
                'image' => 'https://umaten.jp/wp-content/uploads/2025/11/cooking-workspace-top-view.webp',
                'badge' => ''
            )
        );

        $output = '';
        foreach ($genres as $genre) {
            $url = home_url('/' . $genre['slug'] . '/');
            $output .= sprintf(
                '<a href="%s" class="meshimap-category-card">
                    <img src="%s" alt="%s" class="meshimap-category-image">
                    <div class="meshimap-category-overlay">
                        <div class="meshimap-category-name">%s</div>
                    </div>
                    %s
                </a>',
                esc_url($url),
                esc_url($genre['image']),
                esc_attr($genre['name']),
                esc_html($genre['name']),
                !empty($genre['badge']) ? '<div class="meshimap-category-badge">' . esc_html($genre['badge']) . '</div>' : ''
            );
        }

        return $output;
    }
}
