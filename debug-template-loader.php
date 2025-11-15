<?php
/**
 * Debug Template Loader - 临时调试插件
 *
 * 用途：追踪浏览器请求时的模板选择过程
 * 安装：将此文件复制到 wp-content/mu-plugins/debug-template-loader.php
 * 日志位置：/tmp/template-debug.log
 *
 * 使用后请删除！
 */

class Debug_Template_Loader {
    private $log_file = '/tmp/template-debug.log';

    public function __construct() {
        // 在最早的钩子上记录请求信息
        add_action('init', array($this, 'log_init'), 1);

        // 在 template_redirect 上记录（很多插件在这里工作）
        add_action('template_redirect', array($this, 'log_template_redirect'), 1);
        add_action('template_redirect', array($this, 'log_template_redirect_late'), 999);

        // 在 template_include 上记录（最终模板选择）
        add_filter('template_include', array($this, 'log_template_include'), 1);
        add_filter('template_include', array($this, 'log_template_include_late'), 999);

        // 在 wp 钩子上记录（查询解析后）
        add_action('wp', array($this, 'log_wp'), 999);
    }

    private function write_log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }

    public function log_init() {
        global $wp;

        $this->write_log("========== NEW REQUEST ==========");
        $this->write_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
        $this->write_log("QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? ''));
    }

    public function log_wp() {
        global $wp_query, $wp;

        $this->write_log("--- WP Hook (priority 999) ---");
        $this->write_log("Request: " . $wp->request);
        $this->write_log("Matched Rule: " . ($wp->matched_rule ?? 'none'));
        $this->write_log("Matched Query: " . ($wp->matched_query ?? 'none'));

        // 查询变量
        $query_vars = $wp->query_vars;
        $important_vars = array('category_name', 'tag', 'rrct_active', 'rrct_parent', 'rrct_child',
                               'post_type', 'name', 'pagename', 'umaten_toppage');

        $this->write_log("Query Vars:");
        foreach ($important_vars as $var) {
            if (isset($query_vars[$var])) {
                $value = is_array($query_vars[$var]) ? json_encode($query_vars[$var]) : $query_vars[$var];
                $this->write_log("  {$var}: {$value}");
            }
        }

        // WP_Query 状态
        $this->write_log("WP_Query state:");
        $this->write_log("  is_home: " . ($wp_query->is_home() ? 'true' : 'false'));
        $this->write_log("  is_front_page: " . ($wp_query->is_front_page() ? 'true' : 'false'));
        $this->write_log("  is_single: " . ($wp_query->is_single() ? 'true' : 'false'));
        $this->write_log("  is_category: " . ($wp_query->is_category() ? 'true' : 'false'));
        $this->write_log("  is_tag: " . ($wp_query->is_tag() ? 'true' : 'false'));
        $this->write_log("  is_archive: " . ($wp_query->is_archive() ? 'true' : 'false'));
        $this->write_log("  found_posts: " . $wp_query->found_posts);
        $this->write_log("  post_count: " . $wp_query->post_count);
    }

    public function log_template_redirect() {
        $this->write_log("--- template_redirect Hook (priority 1) ---");
        $this->log_query_state();
    }

    public function log_template_redirect_late() {
        $this->write_log("--- template_redirect Hook (priority 999) ---");
        $this->log_query_state();
    }

    public function log_template_include($template) {
        $this->write_log("--- template_include Filter (priority 1) ---");
        $this->write_log("Template: " . $template);
        $this->log_query_state();

        return $template;
    }

    public function log_template_include_late($template) {
        $this->write_log("--- template_include Filter (priority 999) ---");
        $this->write_log("FINAL Template: " . $template);
        $this->log_query_state();

        // 列出所有在 template_include 上的过滤器
        global $wp_filter;
        if (isset($wp_filter['template_include'])) {
            $this->write_log("template_include filters registered:");
            foreach ($wp_filter['template_include']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $callback_name = $this->get_callback_name($callback['function']);
                    $this->write_log("  Priority {$priority}: {$callback_name}");
                }
            }
        }

        return $template;
    }

    private function log_query_state() {
        global $wp_query;

        $rrct_active = get_query_var('rrct_active');
        $category_name = get_query_var('category_name');
        $tag = get_query_var('tag');

        $this->write_log("  rrct_active: " . ($rrct_active ? json_encode($rrct_active) : 'false'));
        $this->write_log("  category_name: " . ($category_name ? json_encode($category_name) : 'false'));
        $this->write_log("  tag: " . ($tag ? json_encode($tag) : 'false'));
        $this->write_log("  is_home: " . ($wp_query->is_home() ? 'true' : 'false'));
        $this->write_log("  found_posts: " . $wp_query->found_posts);
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

new Debug_Template_Loader();
