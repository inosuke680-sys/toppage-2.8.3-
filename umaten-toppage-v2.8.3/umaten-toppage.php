<?php
/**
 * Plugin Name: Umaten トップページ
 * Plugin URI: https://umaten.jp
 * Description: 動的なカテゴリ・タグ表示を備えたトップページ用プラグイン。全エリア対応の3ステップナビゲーション（親→子カテゴリ→ジャンル）。SEO最適化・URLリライト完全修正（タグ・投稿判定改善）・ヒーロー画像メタデータ保存（SWELLテーマ完全対応）。検索結果ページ対応（モダンUI）。独自アクセスカウント機能搭載。投稿とタグの完全な区別。デバッグログ強化・エラーハンドリング改善。v2.10.14：全地域（北海道、東北、関東、中部、関西、中国、四国、九州・沖縄）をデフォルトで公開状態に。確実にすべての地域対応。
 * Version: 2.10.14
 * Author: Umaten
 * Author URI: https://umaten.jp
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: umaten-toppage
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの定数定義
define('UMATEN_TOPPAGE_VERSION', '2.10.14');
define('UMATEN_TOPPAGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMATEN_TOPPAGE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * メインプラグインクラス
 */
class Umaten_Toppage_Plugin {

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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * 依存ファイルの読み込み
     */
    private function load_dependencies() {
        require_once UMATEN_TOPPAGE_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once UMATEN_TOPPAGE_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once UMATEN_TOPPAGE_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once UMATEN_TOPPAGE_PLUGIN_DIR . 'includes/class-view-counter.php';
        require_once UMATEN_TOPPAGE_PLUGIN_DIR . 'includes/class-search-results.php';
        require_once UMATEN_TOPPAGE_PLUGIN_DIR . 'includes/class-url-rewrite.php';
        require_once UMATEN_TOPPAGE_PLUGIN_DIR . 'includes/class-seo-meta.php';
        require_once UMATEN_TOPPAGE_PLUGIN_DIR . 'includes/class-hero-image.php';
    }

    /**
     * フックの初期化
     */
    private function init_hooks() {
        // プラグイン有効化時のフック
        register_activation_hook(__FILE__, array($this, 'activate'));

        // プラグイン無効化時のフック
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // 初期化
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * プラグイン初期化
     */
    public function init() {
        // 管理画面の初期化
        if (is_admin()) {
            Umaten_Toppage_Admin_Settings::get_instance();
        }

        // AJAX処理の初期化
        Umaten_Toppage_Ajax_Handler::get_instance();

        // ショートコードの初期化
        Umaten_Toppage_Shortcode::get_instance();

        // 検索結果ページの初期化
        Umaten_Toppage_Search_Results::get_instance();

        // ビューカウンターの初期化
        Umaten_Toppage_View_Counter::get_instance();

        // URLリライトの初期化
        Umaten_Toppage_URL_Rewrite::get_instance();

        // SEOメタタグの初期化
        Umaten_Toppage_SEO_Meta::get_instance();

        // ヒーロー画像メタデータ保存の初期化
        Umaten_Toppage_Hero_Image::get_instance();

        // 【v2.10.4 新機能】/hokkaido/hakodate/ramen/ へのリンクを検索ウィジェットURLに書き換え
        add_action('wp_loaded', array($this, 'start_output_buffer'));
    }

    /**
     * 【v2.10.4】出力バッファを開始して、HTMLのリンクを書き換える
     */
    public function start_output_buffer() {
        // 管理画面、AJAX、REST APIでは実行しない
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        ob_start(array($this, 'replace_hakodate_ramen_links'));
    }

    /**
     * 【v2.10.8】/hokkaido/hakodate/ramen/ へのリンクを検索ウィジェットURLに置換
     * トレーリングスラッシュあり・なし両方に対応
     * 検索ウィジェットプラグインのnonce検証に対応（正しいnonce名 'umaten_search_action' を使用）
     *
     * @param string $buffer 出力バッファの内容
     * @return string 置換後の内容
     */
    public function replace_hakodate_ramen_links($buffer) {
        // 函館カテゴリとramenタグのIDを取得
        $hakodate_cat = get_term_by('slug', 'hakodate', 'category');
        $ramen_tag = get_term_by('slug', 'ramen', 'post_tag');

        if (!$hakodate_cat || !$ramen_tag) {
            return $buffer;
        }

        // nonceを生成（検索ウィジェットプラグインのnonce検証用）
        // 重要: 検索ウィジェット側は 'umaten_search_action' という名前で検証している
        $nonce = wp_create_nonce('umaten_search_action');

        // 検索ウィジェットのURL（nonceを含める）
        $search_url = home_url('/?umaten_category=' . $hakodate_cat->term_id . '&umaten_tag=' . $ramen_tag->term_id . '&umaten_search=1&umaten_search_nonce=' . $nonce);

        // /hokkaido/hakodate/ramen または /hokkaido/hakodate/ramen/ へのリンクを置換
        // トレーリングスラッシュあり・なし両方に対応
        $patterns = array(
            '/href=["\']https?:\/\/[^"\']*\/hokkaido\/hakodate\/ramen\/?["\']/',
            '/href=["\']\/?hokkaido\/hakodate\/ramen\/?["\']/',
        );

        $replacement = 'href="' . esc_url($search_url) . '"';

        foreach ($patterns as $pattern) {
            $buffer = preg_replace($pattern, $replacement, $buffer);
        }

        return $buffer;
    }

    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // 【v2.10.14】デフォルト設定の作成 - 全地域を公開状態に
        $default_settings = array(
            'hokkaido' => array(
                'status' => 'published',
                'label' => '北海道'
            ),
            'tohoku' => array(
                'status' => 'published',  // 【v2.10.14】coming_soon → published
                'label' => '東北'
            ),
            'kanto' => array(
                'status' => 'published',  // 【v2.10.14】coming_soon → published
                'label' => '関東'
            ),
            'chubu' => array(
                'status' => 'published',  // 【v2.10.14】coming_soon → published
                'label' => '中部'
            ),
            'kansai' => array(
                'status' => 'published',  // 【v2.10.14】coming_soon → published
                'label' => '関西'
            ),
            'chugoku' => array(
                'status' => 'published',  // 【v2.10.14】coming_soon → published
                'label' => '中国'
            ),
            'shikoku' => array(
                'status' => 'published',  // 【v2.10.14】coming_soon → published
                'label' => '四国'
            ),
            'kyushu-okinawa' => array(
                'status' => 'published',  // 【v2.10.14】coming_soon → published
                'label' => '九州・沖縄'
            )
        );

        // 【v2.10.14】既存の設定を取得して更新
        $existing_settings = get_option('umaten_toppage_area_settings', array());

        if (empty($existing_settings)) {
            // 設定がない場合：デフォルト設定を保存
            update_option('umaten_toppage_area_settings', $default_settings);
        } else {
            // 【v2.10.14】既存設定がある場合：すべての地域を'published'に更新
            foreach ($default_settings as $area_key => $area_data) {
                if (isset($existing_settings[$area_key])) {
                    // 既存設定を'published'に更新（ラベルは保持）
                    $existing_settings[$area_key]['status'] = 'published';
                } else {
                    // 設定がないエリアはデフォルト値を設定
                    $existing_settings[$area_key] = $area_data;
                }
            }
            update_option('umaten_toppage_area_settings', $existing_settings);
        }

        // リライトルールをフラッシュ
        Umaten_Toppage_URL_Rewrite::flush_rewrite_rules();
    }

    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        // リライトルールをクリア
        flush_rewrite_rules();
    }
}

/**
 * プラグインのインスタンスを起動
 */
function umaten_toppage() {
    return Umaten_Toppage_Plugin::get_instance();
}

// プラグイン起動
umaten_toppage();
