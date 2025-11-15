<?php
/**
 * 管理画面設定クラス
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class Umaten_Toppage_Admin_Settings {

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * 管理メニューの追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'Umaten トップページ設定',
            'トップページ設定',
            'manage_options',
            'umaten-toppage-settings',
            array($this, 'render_settings_page'),
            'dashicons-admin-site-alt3',
            30
        );
    }

    /**
     * 設定の登録
     */
    public function register_settings() {
        register_setting(
            'umaten_toppage_settings',
            'umaten_toppage_area_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );

        register_setting(
            'umaten_toppage_settings',
            'umaten_toppage_monthly_access',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_monthly_access')
            )
        );
    }

    /**
     * 管理画面用スクリプトの読み込み
     */
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_umaten-toppage-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'umaten-toppage-admin',
            UMATEN_TOPPAGE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            UMATEN_TOPPAGE_VERSION
        );
    }

    /**
     * 設定のサニタイズ
     */
    public function sanitize_settings($input) {
        if (!is_array($input)) {
            return array();
        }

        $sanitized = array();
        foreach ($input as $key => $value) {
            $sanitized_key = sanitize_key($key);
            $sanitized[$sanitized_key] = array(
                'status' => in_array($value['status'], array('published', 'coming_soon', 'hidden'))
                    ? $value['status']
                    : 'hidden',
                'label' => sanitize_text_field($value['label'])
            );
        }

        return $sanitized;
    }

    /**
     * 月間アクセス数のサニタイズ
     */
    public function sanitize_monthly_access($input) {
        // カンマを除去して数値のみを保持
        $value = preg_replace('/[^0-9]/', '', $input);
        return $value;
    }

    /**
     * 設定ページのレンダリング
     */
    public function render_settings_page() {
        // 設定を保存
        if (isset($_POST['umaten_toppage_settings_nonce']) &&
            wp_verify_nonce($_POST['umaten_toppage_settings_nonce'], 'umaten_toppage_settings')) {

            $area_settings = array();
            if (isset($_POST['area_settings']) && is_array($_POST['area_settings'])) {
                foreach ($_POST['area_settings'] as $area_key => $area_data) {
                    $area_settings[sanitize_key($area_key)] = array(
                        'status' => sanitize_text_field($area_data['status']),
                        'label' => sanitize_text_field($area_data['label'])
                    );
                }
            }

            update_option('umaten_toppage_area_settings', $area_settings);

            // 月間アクセス数を保存
            if (isset($_POST['monthly_access'])) {
                $monthly_access = preg_replace('/[^0-9]/', '', $_POST['monthly_access']);
                update_option('umaten_toppage_monthly_access', $monthly_access);
            }

            echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>';
        }

        $area_settings = get_option('umaten_toppage_area_settings', array());
        $monthly_access = get_option('umaten_toppage_monthly_access', '');

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

        ?>
        <div class="wrap umaten-toppage-settings">
            <h1>Umaten トップページ設定</h1>
            <p class="description">各親カテゴリ（都道府県エリア）の公開状態を管理します。</p>

            <form method="post" action="">
                <?php wp_nonce_field('umaten_toppage_settings', 'umaten_toppage_settings_nonce'); ?>

                <!-- 月間アクセス数設定 -->
                <h2>月間アクセス数設定</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="monthly_access">月間アクセス数</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="monthly_access"
                                   name="monthly_access"
                                   value="<?php echo esc_attr($monthly_access); ?>"
                                   class="regular-text"
                                   placeholder="例: 1500">
                            <p class="description">
                                数字のみを入力してください（例: 1500）。表示時には自動的に「+」が付加されます（例: 1,500+）。<br>
                                空欄の場合は、自動計算された値が使用されます。
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 style="margin-top: 30px;">エリア設定</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;">エリア名</th>
                            <th style="width: 30%;">表示ラベル</th>
                            <th style="width: 40%;">公開状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($default_areas as $area_key => $default_label):
                            $current_settings = isset($area_settings[$area_key])
                                ? $area_settings[$area_key]
                                : array('status' => 'coming_soon', 'label' => $default_label);
                            $current_status = $current_settings['status'];
                            $current_label = $current_settings['label'];
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($default_label); ?></strong>
                                <input type="hidden" name="area_settings[<?php echo esc_attr($area_key); ?>][label]"
                                       value="<?php echo esc_attr($current_label); ?>">
                            </td>
                            <td>
                                <input type="text"
                                       name="area_settings[<?php echo esc_attr($area_key); ?>][label]"
                                       value="<?php echo esc_attr($current_label); ?>"
                                       class="regular-text">
                            </td>
                            <td>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="radio"
                                           name="area_settings[<?php echo esc_attr($area_key); ?>][status]"
                                           value="published"
                                           <?php checked($current_status, 'published'); ?>>
                                    <span style="color: #46b450; font-weight: 600;">✓ 公開中</span>
                                </label>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="radio"
                                           name="area_settings[<?php echo esc_attr($area_key); ?>][status]"
                                           value="coming_soon"
                                           <?php checked($current_status, 'coming_soon'); ?>>
                                    <span style="color: #ffb900; font-weight: 600;">⏳ 準備中</span>
                                </label>
                                <label style="display: inline-block;">
                                    <input type="radio"
                                           name="area_settings[<?php echo esc_attr($area_key); ?>][status]"
                                           value="hidden"
                                           <?php checked($current_status, 'hidden'); ?>>
                                    <span style="color: #dc3232; font-weight: 600;">✕ 非表示</span>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary button-large" value="設定を保存">
                </p>
            </form>

            <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-left: 4px solid #667eea; border-radius: 4px;">
                <h2 style="margin-top: 0;">📖 使い方</h2>
                <ol style="line-height: 1.8;">
                    <li><strong>公開中：</strong> エリアが通常表示され、クリック可能です。</li>
                    <li><strong>準備中：</strong> エリアは表示されますが「準備中」マークが付き、クリックできません。</li>
                    <li><strong>非表示：</strong> エリアが完全に非表示になります。</li>
                </ol>
                <p><strong>ショートコード：</strong> <code>[umaten_toppage]</code> を固定ページに挿入してください。</p>
            </div>
        </div>

        <style>
        .umaten-toppage-settings h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .umaten-toppage-settings .description {
            font-size: 14px;
            margin-bottom: 30px;
            color: #666;
        }
        .umaten-toppage-settings table {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .umaten-toppage-settings th {
            font-weight: 600;
            background: #f8f9fa;
        }
        .umaten-toppage-settings td {
            vertical-align: middle;
            padding: 15px 10px;
        }
        .umaten-toppage-settings label {
            cursor: pointer;
        }
        .umaten-toppage-settings input[type="radio"] {
            margin-right: 5px;
        }
        </style>
        <?php
    }
}
