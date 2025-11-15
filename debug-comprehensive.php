<?php
/**
 * Comprehensive Debug Plugin - 全ページタイプの詳細ログ
 *
 * 用途：すべてのページタイプでリダイレクトが発生している原因を特定
 * 安装：wp-content/mu-plugins/debug-comprehensive.php
 * ログ：/tmp/umaten-comprehensive-debug.log
 *
 * 使用後は必ず削除してください！
 */

class Umaten_Comprehensive_Debug {
    private $log_file = '/tmp/umaten-comprehensive-debug.log';

    public function __construct() {
        // 各フックで詳細ログ
        add_action('init', array($this, 'log_init'), 1);
        add_action('parse_request', array($this, 'log_parse_request'), 1);
        add_action('wp', array($this, 'log_wp'), 1);
        add_action('parse_query', array($this, 'log_parse_query'), 1);
        add_action('parse_query', array($this, 'log_parse_query_late'), 9999);
        add_action('template_redirect', array($this, 'log_template_redirect'), 1);
        add_action('template_redirect', array($this, 'log_template_redirect_late'), 9999);
        add_filter('template_include', array($this, 'log_template_include'), 9999);
    }

    private function write_log($message) {
        $timestamp = date('Y-m-d H:i:s.u');
        $log_entry = "[{$timestamp}] {$message}\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }

    public function log_init() {
        $this->write_log("========== NEW REQUEST ==========");
        $this->write_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        $this->write_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
        $this->write_log("HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'N/A'));
    }

    public function log_parse_request($wp) {
        $this->write_log("--- parse_request (priority 1) ---");
        $this->write_log("Matched rule: " . ($wp->matched_rule ?? 'none'));
        $this->write_log("Matched query: " . ($wp->matched_query ?? 'none'));
        $this->write_log("Request: " . ($wp->request ?? 'none'));

        if (!empty($wp->query_vars)) {
            $this->write_log("Query vars at parse_request:");
            foreach ($wp->query_vars as $key => $value) {
                if (!empty($value)) {
                    $val_str = is_array($value) ? json_encode($value) : $value;
                    $this->write_log("  {$key} = {$val_str}");
                }
            }
        }
    }

    public function log_wp() {
        global $wp, $wp_query;

        $this->write_log("--- wp hook (priority 1) ---");

        // Query vars
        $important_vars = array(
            'name', 'pagename', 'category_name', 'tag', 'p', 'page_id',
            'region', 'area', 'genre',
            'umaten_region', 'umaten_area', 'umaten_genre',
            'rrct_category', 'rrct_parent_category', 'rrct_child_category',
            'rrct_tag', 'rrct_active', 'rrct_parent', 'rrct_child'
        );

        $this->write_log("Query vars at wp:");
        foreach ($important_vars as $var) {
            $value = get_query_var($var);
            if (!empty($value)) {
                $val_str = is_array($value) ? json_encode($value) : $value;
                $this->write_log("  {$var} = {$val_str}");
            }
        }

        // WP_Query conditional tags
        $this->write_log("WP_Query conditionals:");
        $conditionals = array(
            'is_home', 'is_front_page', 'is_single', 'is_page',
            'is_category', 'is_tag', 'is_archive', 'is_404'
        );
        foreach ($conditionals as $conditional) {
            $result = $wp_query->$conditional() ? 'TRUE' : 'false';
            $this->write_log("  {$conditional}: {$result}");
        }

        $this->write_log("  found_posts: " . $wp_query->found_posts);
        $this->write_log("  post_count: " . $wp_query->post_count);

        if ($wp_query->post && isset($wp_query->post->post_title)) {
            $this->write_log("  post_title: " . $wp_query->post->post_title);
        }
    }

    public function log_parse_query($query) {
        if (!$query->is_main_query()) {
            return;
        }

        $this->write_log("--- parse_query hook (priority 1, main query) ---");
        $this->log_query_state($query, 'parse_query:1');
    }

    public function log_parse_query_late($query) {
        if (!$query->is_main_query()) {
            return;
        }

        $this->write_log("--- parse_query hook (priority 9999, main query) ---");
        $this->log_query_state($query, 'parse_query:9999');

        // umaten-toppage の handle_plugin_conflicts が実行されているか確認
        if (did_action('parse_query')) {
            $this->write_log("  parse_query action count: " . did_action('parse_query'));
        }
    }

    public function log_template_redirect() {
        global $wp_query;

        $this->write_log("--- template_redirect hook (priority 1) ---");
        $this->log_query_state($wp_query, 'template_redirect:1');

        // リダイレクトが設定されているか確認
        $redirect_status = http_response_code();
        $this->write_log("  HTTP status code: " . $redirect_status);
    }

    public function log_template_redirect_late() {
        global $wp_query;

        $this->write_log("--- template_redirect hook (priority 9999) ---");
        $this->log_query_state($wp_query, 'template_redirect:9999');
    }

    public function log_template_include($template) {
        global $wp_query;

        $this->write_log("--- template_include filter (priority 9999) ---");
        $this->write_log("Template file: " . $template);
        $this->log_query_state($wp_query, 'template_include');

        // すべての template_include フィルターをリスト
        global $wp_filter;
        if (isset($wp_filter['template_include'])) {
            $this->write_log("template_include filters:");
            foreach ($wp_filter['template_include']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $callback_name = $this->get_callback_name($callback['function']);
                    $this->write_log("  Priority {$priority}: {$callback_name}");
                }
            }
        }

        $this->write_log("========== REQUEST END ==========\n");

        return $template;
    }

    private function log_query_state($query, $context) {
        $important_vars = array(
            'name', 'pagename', 'category_name', 'tag', 'p', 'page_id',
            'region', 'area', 'genre',
            'umaten_region', 'umaten_area', 'umaten_genre',
            'rrct_category', 'rrct_parent_category', 'rrct_child_category',
            'rrct_tag', 'rrct_active', 'rrct_parent', 'rrct_child'
        );

        $this->write_log("Query state at {$context}:");
        $has_vars = false;
        foreach ($important_vars as $var) {
            $value = $query->get($var);
            if (!empty($value)) {
                $has_vars = true;
                $val_str = is_array($value) ? json_encode($value) : $value;
                $this->write_log("  {$var} = {$val_str}");
            }
        }

        if (!$has_vars) {
            $this->write_log("  (no significant query vars)");
        }

        // Conditional tags
        $conditionals = array('is_home', 'is_front_page', 'is_single', 'is_404');
        $cond_str = array();
        foreach ($conditionals as $cond) {
            if ($query->$cond()) {
                $cond_str[] = $cond;
            }
        }
        if (!empty($cond_str)) {
            $this->write_log("  Conditionals: " . implode(', ', $cond_str));
        }

        $this->write_log("  found_posts: " . $query->found_posts);
    }

    private function get_callback_name($callback) {
        if (is_string($callback)) {
            return $callback;
        } elseif (is_array($callback)) {
            if (is_object($callback[0])) {
                return get_class($callback[0]) . '->' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        } elseif (is_object($callback)) {
            if ($callback instanceof Closure) {
                return 'Closure';
            } else {
                return get_class($callback);
            }
        }
        return 'Unknown';
    }
}

// 管理画面では実行しない
if (!is_admin() && !wp_doing_ajax() && !wp_doing_cron()) {
    new Umaten_Comprehensive_Debug();
}
