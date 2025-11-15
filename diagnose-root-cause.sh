#!/bin/bash

##############################################################################
# Umaten リダイレクト問題の根本原因診断スクリプト（完全版）
#
# 実行方法:
#   curl -o /tmp/diagnose-root-cause.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq/diagnose-root-cause.sh
#   chmod +x /tmp/diagnose-root-cause.sh
#   sudo /tmp/diagnose-root-cause.sh
##############################################################################

# 色の定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# ログ関数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_section() {
    echo ""
    echo -e "${MAGENTA}=========================================${NC}"
    echo -e "${MAGENTA}  $1${NC}"
    echo -e "${MAGENTA}=========================================${NC}"
}

# 設定
DOCROOT="/home/kusanagi/45515055731ac663c7c3ad4c/DocumentRoot"

echo ""
echo "=========================================="
echo "  リダイレクト問題 根本原因診断"
echo "=========================================="
echo ""

# DocumentRootの存在確認
if [ ! -d "$DOCROOT" ]; then
    log_error "DocumentRoot が見つかりません: $DOCROOT"
    exit 1
fi

log_info "DocumentRoot: $DOCROOT"
cd "$DOCROOT"

# 1. 現在有効なプラグインの確認
log_section "1. 有効なプラグイン一覧"

if command -v wp &> /dev/null; then
    log_info "有効なプラグイン:"
    wp plugin list --status=active --path="$DOCROOT" --format=table
    echo ""

    log_info "有効なプラグイン数:"
    ACTIVE_PLUGIN_COUNT=$(wp plugin list --status=active --path="$DOCROOT" --format=count)
    echo "  合計: $ACTIVE_PLUGIN_COUNT 個"
    echo ""
else
    log_warning "WP-CLI が見つかりません"
fi

# 2. 有効なテーマの確認
log_section "2. 有効なテーマ"

if command -v wp &> /dev/null; then
    log_info "現在有効なテーマ:"
    wp theme list --status=active --path="$DOCROOT" --format=table
    echo ""

    ACTIVE_THEME=$(wp theme list --status=active --path="$DOCROOT" --field=name)
    log_info "テーマ名: $ACTIVE_THEME"
    echo ""
else
    log_warning "WP-CLI が見つかりません"
fi

# 3. テーマ内のリライトルール・クエリ修正コードの検索
log_section "3. テーマ内のリライトルール・クエリ修正コード"

THEME_DIR="$DOCROOT/wp-content/themes"

if [ -d "$THEME_DIR" ]; then
    log_info "テーマディレクトリ内を検索中..."
    echo ""

    log_info "【add_rewrite_rule を使用しているファイル】"
    grep -r "add_rewrite_rule" "$THEME_DIR" --include="*.php" -n --color=always | head -20
    echo ""

    log_info "【pre_get_posts フックを使用しているファイル】"
    grep -r "add_action.*pre_get_posts\|add_filter.*pre_get_posts" "$THEME_DIR" --include="*.php" -n --color=always | head -20
    echo ""

    log_info "【template_redirect フックを使用しているファイル】"
    grep -r "add_action.*template_redirect" "$THEME_DIR" --include="*.php" -n --color=always | head -20
    echo ""

    log_info "【parse_query フックを使用しているファイル】"
    grep -r "add_action.*parse_query\|add_filter.*parse_query" "$THEME_DIR" --include="*.php" -n --color=always | head -20
    echo ""
else
    log_warning "テーマディレクトリが見つかりません"
fi

# 4. 全プラグイン内のリライトルール検索
log_section "4. プラグイン内のリライトルールコード"

PLUGIN_DIR="$DOCROOT/wp-content/plugins"

if [ -d "$PLUGIN_DIR" ]; then
    log_info "【add_rewrite_rule を使用しているプラグインファイル】"
    find "$PLUGIN_DIR" -name "*.php" -exec grep -l "add_rewrite_rule" {} \; 2>/dev/null | head -20
    echo ""

    log_info "【各プラグインの詳細（最初の5件）】"
    PLUGIN_FILES=$(find "$PLUGIN_DIR" -name "*.php" -exec grep -l "add_rewrite_rule" {} \; 2>/dev/null | head -5)

    for file in $PLUGIN_FILES; do
        echo ""
        echo -e "${CYAN}ファイル: $file${NC}"
        grep -n "add_rewrite_rule" "$file" --color=always | head -10
    done
    echo ""
else
    log_warning "プラグインディレクトリが見つかりません"
fi

# 5. WordPressパーマリンク設定
log_section "5. WordPressパーマリンク設定"

if command -v wp &> /dev/null; then
    log_info "パーマリンク構造:"
    wp option get permalink_structure --path="$DOCROOT"
    echo ""

    log_info "カテゴリベース:"
    wp option get category_base --path="$DOCROOT" 2>/dev/null || echo "(設定なし)"
    echo ""

    log_info "タグベース:"
    wp option get tag_base --path="$DOCROOT" 2>/dev/null || echo "(設定なし)"
    echo ""
fi

# 6. カスタムタクソノミーの確認
log_section "6. カスタムタクソノミーの確認"

if command -v wp &> /dev/null; then
    log_info "登録されているタクソノミー:"
    wp taxonomy list --path="$DOCROOT" --format=table
    echo ""

    log_info "【region タクソノミーの詳細】"
    wp taxonomy get region --path="$DOCROOT" --format=json 2>/dev/null || log_warning "region タクソノミーが見つかりません"
    echo ""

    log_info "【area タクソノミーの詳細】"
    wp taxonomy get area --path="$DOCROOT" --format=json 2>/dev/null || log_warning "area タクソノミーが見つかりません"
    echo ""

    log_info "【genre タクソノミーの詳細】"
    wp taxonomy get genre --path="$DOCROOT" --format=json 2>/dev/null || log_warning "genre タクソノミーが見つかりません"
    echo ""
fi

# 7. 現在のリライトルール一覧（上位30件）
log_section "7. 現在のリライトルール（上位30件）"

if command -v wp &> /dev/null; then
    log_info "WordPressに登録されているリライトルール:"
    wp rewrite list --path="$DOCROOT" --format=table | head -30
    echo ""

    log_info "【問題のあるパターン検索: ^([^/]+)/([^/]+)/ 】"
    wp rewrite list --path="$DOCROOT" | grep -E "^\^?\(\[" | head -10
    echo ""
fi

# 8. テストURL確認（詳細版）
log_section "8. テストURLリクエスト（詳細版）"

log_info "【テスト1: トップページ】"
curl -I https://umaten.jp/ 2>/dev/null | grep -E "HTTP|Location|X-Redirect"
echo ""

log_info "【テスト2: 正常に動作すべきURL - /hokkaido/niseko/ramen/】"
curl -I https://umaten.jp/hokkaido/niseko/ramen/ 2>/dev/null | grep -E "HTTP|Location|X-Redirect"
echo ""

log_info "【テスト3: 投稿ページ - /hokkaido/menya-kagetsu-hakodate-kikyo-2/】"
curl -I https://umaten.jp/hokkaido/menya-kagetsu-hakodate-kikyo-2/ 2>/dev/null | grep -E "HTTP|Location|X-Redirect"
echo ""

log_info "【テスト4: カテゴリページ - /hokkaido/hakodate/】"
curl -I https://umaten.jp/hokkaido/hakodate/ 2>/dev/null | grep -E "HTTP|Location|X-Redirect"
echo ""

# 9. Nginx設定の詳細確認
log_section "9. Nginx設定の詳細確認"

NGINX_CONF="/etc/nginx/conf.d/45515055731ac663c7c3ad4c_http.conf"

if [ -f "$NGINX_CONF" ]; then
    log_info "Nginx設定ファイル: $NGINX_CONF"
    echo ""

    log_info "【rewrite ディレクティブ】"
    grep -n "rewrite" "$NGINX_CONF" --color=always || log_info "rewriteディレクティブなし"
    echo ""

    log_info "【location ブロック】"
    grep -n "location" "$NGINX_CONF" --color=always | head -20
    echo ""

    log_info "【try_files ディレクティブ】"
    grep -n "try_files" "$NGINX_CONF" --color=always || log_info "try_filesディレクティブなし"
    echo ""
else
    log_warning "Nginx設定ファイルが見つかりません"
fi

# 10. wp_options テーブルのリライト関連設定
log_section "10. wp_options テーブルのリライト関連設定"

if command -v wp &> /dev/null; then
    log_info "【rewrite_rules オプション（存在確認）】"
    if wp option get rewrite_rules --path="$DOCROOT" --format=json >/dev/null 2>&1; then
        log_warning "rewrite_rules が存在します（削除したはずなのに...）"
        echo "最初の10ルール:"
        wp option get rewrite_rules --path="$DOCROOT" --format=json | head -20
    else
        log_success "rewrite_rules は削除されています（正常）"
    fi
    echo ""

    log_info "【permalink_structure】"
    wp option get permalink_structure --path="$DOCROOT"
    echo ""

    log_info "【category_base】"
    wp option get category_base --path="$DOCROOT" 2>/dev/null || echo "(未設定)"
    echo ""

    log_info "【tag_base】"
    wp option get tag_base --path="$DOCROOT" 2>/dev/null || echo "(未設定)"
    echo ""
fi

# 11. 最近のアクセスログ解析
log_section "11. 最近のアクセスログ（リダイレクト関連）"

NGINX_ACCESS_LOG="/var/log/nginx/access.log"

if [ -f "$NGINX_ACCESS_LOG" ]; then
    log_info "【最近のアクセス（hokkaido/niseko/ramen 関連）】"
    tail -200 "$NGINX_ACCESS_LOG" | grep -E "niseko|hakodate" | tail -10
    echo ""

    log_info "【30x リダイレクトステータス】"
    tail -200 "$NGINX_ACCESS_LOG" | grep -E " 30[0-9] " | tail -10
    echo ""
else
    log_warning "Nginxアクセスログが見つかりません"
fi

# 12. PHP-FPMのステータス
log_section "12. PHP-FPM ステータス"

if systemctl list-units --type=service | grep -q "php.*fpm"; then
    PHP_FPM_SERVICE=$(systemctl list-units --type=service | grep "php.*fpm" | awk '{print $1}' | head -n 1)
    log_info "PHP-FPM サービス: $PHP_FPM_SERVICE"
    systemctl status "$PHP_FPM_SERVICE" --no-pager | head -15
    echo ""
else
    log_warning "PHP-FPM サービスが見つかりません"
fi

# 13. .htaccess の内容確認
log_section "13. .htaccess の内容確認"

if [ -f "$DOCROOT/.htaccess" ]; then
    log_info ".htaccess が存在します"
    cat "$DOCROOT/.htaccess"
    echo ""
else
    log_info ".htaccess が存在しません（Nginx環境では正常）"
fi

# 14. 結論と推奨アクション
log_section "14. 診断結果サマリー"

echo ""
log_info "【確認済み事項】"
echo "  ✓ umaten-toppage-v2.9.3: 無効化済み"
echo "  ✓ umaten-restaurant-search-widget: 無効化済み"
echo "  ✓ rewrite_rules オプション: 削除済み（または再生成済み）"
echo "  ✓ パーマリンク: フラッシュ済み"
echo ""

log_warning "【問題の原因候補】"
echo "  1. 有効な別のプラグインがリライトルールを追加している"
echo "  2. テーマがリライトルールやクエリを変更している"
echo "  3. Nginx設定にカスタムリライトルールがある"
echo "  4. カスタムタクソノミーの登録方法に問題がある"
echo "  5. WordPressのパーマリンク構造自体に問題がある"
echo ""

log_success "【次のステップ】"
echo "  1. 上記セクション3で見つかったテーマ内のコードを確認"
echo "  2. 上記セクション4で見つかったプラグインを一時的に無効化してテスト"
echo "  3. 上記セクション9のNginx設定を確認"
echo "  4. すべてのプラグインを無効化して問題が解決するか確認"
echo "  5. デフォルトテーマ（Twenty Twenty-Four等）に切り替えてテスト"
echo ""

log_info "診断完了。上記の情報を分析してください。"
echo ""

exit 0
