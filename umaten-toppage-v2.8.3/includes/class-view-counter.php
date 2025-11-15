<?php
/**
 * アクセスカウントヘルパークラス
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class Umaten_Toppage_View_Counter {

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
        // 投稿が表示されるときにカウントを増やす
        add_action('wp_head', array($this, 'track_post_view'));

        // REST APIエンドポイントを追加（オプション）
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * 投稿ビューをトラッキング
     */
    public function track_post_view() {
        if (!is_singular('post')) {
            return;
        }

        global $post;
        if (empty($post)) {
            return;
        }

        // 管理者を除外（オプション）
        // 注: ボット・クローラーも含めてすべてのアクセスをカウント
        if (current_user_can('manage_options')) {
            return;
        }

        $post_id = $post->ID;
        $this->increment_view_count($post_id);
        $this->update_daily_stats();

        // ボットかどうかを判定して別途記録
        if ($this->is_bot()) {
            $this->increment_bot_count($post_id);
        }
    }

    /**
     * ビューカウントを増やす
     */
    private function increment_view_count($post_id) {
        $count = get_post_meta($post_id, 'post_views_count', true);
        $count = empty($count) ? 0 : intval($count);
        $count++;
        update_post_meta($post_id, 'post_views_count', $count);

        // 最終閲覧日時を記録
        update_post_meta($post_id, 'post_last_viewed', current_time('mysql'));
    }

    /**
     * ボットビューカウントを増やす
     */
    private function increment_bot_count($post_id) {
        $count = get_post_meta($post_id, 'post_bot_views_count', true);
        $count = empty($count) ? 0 : intval($count);
        $count++;
        update_post_meta($post_id, 'post_bot_views_count', $count);
    }

    /**
     * 日次統計を更新
     */
    private function update_daily_stats() {
        $today = date('Y-m-d');
        $option_key = 'umaten_daily_views_' . $today;

        $views = get_option($option_key, 0);
        $views++;
        update_option($option_key, $views);

        // 古い統計データを削除（90日以上前）
        $this->cleanup_old_stats();
    }

    /**
     * 古い統計データをクリーンアップ
     */
    private function cleanup_old_stats() {
        // 1日1回だけ実行
        $last_cleanup = get_option('umaten_last_cleanup', 0);
        if (time() - $last_cleanup < DAY_IN_SECONDS) {
            return;
        }

        global $wpdb;
        $cutoff_date = date('Y-m-d', strtotime('-90 days'));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE 'umaten_daily_views_%'
                 AND option_name < %s",
                'umaten_daily_views_' . $cutoff_date
            )
        );

        update_option('umaten_last_cleanup', time());
    }

    /**
     * 月間アクセス数を取得
     */
    public function get_monthly_views() {
        global $wpdb;

        // 過去30日間の日次統計を集計
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');

        $pattern = 'umaten_daily_views_%';
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value
                 FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 AND option_name >= %s
                 AND option_name <= %s",
                $pattern,
                'umaten_daily_views_' . $start_date,
                'umaten_daily_views_' . $end_date
            ),
            ARRAY_A
        );

        $total_views = 0;
        if (!empty($result)) {
            foreach ($result as $row) {
                $total_views += intval($row['option_value']);
            }
        }

        return $total_views;
    }

    /**
     * 今日のアクセス数を取得
     */
    public function get_today_views() {
        $today = date('Y-m-d');
        $option_key = 'umaten_daily_views_' . $today;
        return intval(get_option($option_key, 0));
    }

    /**
     * 全期間のアクセス数を取得
     */
    public function get_total_views() {
        global $wpdb;

        // すべての投稿のビュー数を合計
        $total = $wpdb->get_var(
            "SELECT SUM(meta_value)
             FROM {$wpdb->postmeta}
             WHERE meta_key = 'post_views_count'"
        );

        return intval($total);
    }

    /**
     * ボットの総アクセス数を取得
     */
    public function get_total_bot_views() {
        global $wpdb;

        $total = $wpdb->get_var(
            "SELECT SUM(meta_value)
             FROM {$wpdb->postmeta}
             WHERE meta_key = 'post_bot_views_count'"
        );

        return intval($total);
    }

    /**
     * 人間のアクセス数を取得（総アクセス - ボット）
     */
    public function get_human_views() {
        return $this->get_total_views() - $this->get_total_bot_views();
    }

    /**
     * ボット・クローラーかどうかを判定
     */
    private function is_bot() {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return true;
        }

        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        // ボットのユーザーエージェントパターン
        $bot_patterns = array(
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'google', 'yahoo', 'bing', 'baidu', 'yandex',
            'facebook', 'twitter', 'pinterest', 'linkedin',
            'whatsapp', 'telegram', 'slack'
        );

        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * REST APIルートを登録
     */
    public function register_rest_routes() {
        register_rest_route('umaten/v1', '/views/monthly', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_monthly_views'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('umaten/v1', '/views/today', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_today_views'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('umaten/v1', '/views/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_view_stats'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * REST API: 月間アクセス数
     */
    public function rest_get_monthly_views() {
        return array(
            'monthly_views' => $this->get_monthly_views(),
            'period' => '過去30日間'
        );
    }

    /**
     * REST API: 今日のアクセス数
     */
    public function rest_get_today_views() {
        return array(
            'today_views' => $this->get_today_views(),
            'date' => date('Y-m-d')
        );
    }

    /**
     * REST API: 詳細統計情報
     */
    public function rest_get_view_stats() {
        $total_views = $this->get_total_views();
        $bot_views = $this->get_total_bot_views();
        $human_views = $total_views - $bot_views;
        $monthly_views = $this->get_monthly_views();
        $today_views = $this->get_today_views();

        return array(
            'total_views' => $total_views,
            'bot_views' => $bot_views,
            'human_views' => $human_views,
            'bot_percentage' => $total_views > 0 ? round(($bot_views / $total_views) * 100, 2) : 0,
            'monthly_views' => $monthly_views,
            'today_views' => $today_views,
            'note' => 'ボット・クローラーを含むすべてのアクセスを記録'
        );
    }
}
