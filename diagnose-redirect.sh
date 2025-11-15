#!/bin/bash

##############################################################################
# Umaten リダイレクト問題診断スクリプト
#
# 実行方法:
#   curl -o /tmp/diagnose-redirect.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq/diagnose-redirect.sh
#   chmod +x /tmp/diagnose-redirect.sh
#   sudo /tmp/diagnose-redirect.sh
##############################################################################

# 色の定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

# 設定
DOCROOT="/home/kusanagi/45515055731ac663c7c3ad4c/DocumentRoot"

echo ""
echo "=========================================="
echo "  リダイレクト問題診断"
echo "=========================================="
echo ""

# 1. WordPressリライトルールの確認
echo "=========================================="
log_info "1. WordPressリライトルールを確認中..."
echo "=========================================="

if command -v wp &> /dev/null; then
    cd "$DOCROOT"

    log_info "現在のリライトルール一覧:"
    wp rewrite list --path="$DOCROOT" | head -20

    echo ""
    log_info "問題のあるパターンを検索中..."
    wp rewrite list --path="$DOCROOT" | grep -E "^\^?\(\[" | head -10

    echo ""
else
    log_warning "WP-CLI が見つかりません"
fi

# 2. .htaccessの確認
echo ""
echo "=========================================="
log_info "2. .htaccess を確認中..."
echo "=========================================="

if [ -f "$DOCROOT/.htaccess" ]; then
    log_info ".htaccess の内容:"
    cat "$DOCROOT/.htaccess"
    echo ""
else
    log_warning ".htaccess が見つかりません"
fi

# 3. Nginx設定の確認（KUSANAGI）
echo ""
echo "=========================================="
log_info "3. Nginx設定を確認中..."
echo "=========================================="

NGINX_CONF="/etc/nginx/conf.d/45515055731ac663c7c3ad4c_http.conf"
if [ -f "$NGINX_CONF" ]; then
    log_info "Nginx設定ファイル: $NGINX_CONF"
    log_info "リライトルール部分を抽出:"
    grep -A 10 "rewrite" "$NGINX_CONF" || log_info "リライトルールなし"
    echo ""
else
    log_warning "Nginx設定ファイルが見つかりません"
    log_info "利用可能な設定ファイル:"
    ls -la /etc/nginx/conf.d/ | grep -E "\.conf$"
fi

# 4. KUSANAGIの設定確認
echo ""
echo "=========================================="
log_info "4. KUSANAGI設定を確認中..."
echo "=========================================="

if command -v kusanagi &> /dev/null; then
    log_info "KUSANAGI プロビジョン一覧:"
    kusanagi status || true
    echo ""
else
    log_warning "KUSANAGI コマンドが見つかりません"
fi

# 5. パーマリンク設定の確認
echo ""
echo "=========================================="
log_info "5. WordPressパーマリンク設定を確認中..."
echo "=========================================="

if command -v wp &> /dev/null; then
    cd "$DOCROOT"

    log_info "パーマリンク構造:"
    wp option get permalink_structure --path="$DOCROOT"
    echo ""

    log_info "カテゴリベース:"
    wp option get category_base --path="$DOCROOT" || echo "(設定なし)"
    echo ""

    log_info "タグベース:"
    wp option get tag_base --path="$DOCROOT" || echo "(設定なし)"
    echo ""
fi

# 6. テストURL確認
echo ""
echo "=========================================="
log_info "6. テストURLリクエストを実行中..."
echo "=========================================="

log_info "テスト1: トップページ"
curl -I https://umaten.jp/ 2>/dev/null | grep -E "HTTP|Location" || log_error "リクエスト失敗"
echo ""

log_info "テスト2: ジャンルアーカイブページ（例: /hokkaido/hakodate/ramen/）"
curl -I https://umaten.jp/hokkaido/hakodate/ramen/ 2>/dev/null | grep -E "HTTP|Location" || log_error "リクエスト失敗"
echo ""

log_info "テスト3: 投稿ページ（例: /hokkaido/menya-kagetsu-hakodate-kikyo-2/）"
curl -I https://umaten.jp/hokkaido/menya-kagetsu-hakodate-kikyo-2/ 2>/dev/null | grep -E "HTTP|Location" || log_error "リクエスト失敗"
echo ""

# 7. Nginxアクセスログの確認
echo ""
echo "=========================================="
log_info "7. 最新のNginxアクセスログを確認中..."
echo "=========================================="

NGINX_LOG="/var/log/nginx/access.log"
if [ -f "$NGINX_LOG" ]; then
    log_info "最新10件のアクセス（ジャンル関連）:"
    tail -100 "$NGINX_LOG" | grep -E "hokkaido|hakodate" | tail -10
    echo ""
else
    log_warning "Nginxアクセスログが見つかりません"
fi

# 8. WordPressデバッグログの確認
echo ""
echo "=========================================="
log_info "8. WordPressデバッグログを確認中..."
echo "=========================================="

WP_DEBUG_LOG="$DOCROOT/wp-content/debug.log"
if [ -f "$WP_DEBUG_LOG" ]; then
    log_info "最新のデバッグログ（Umaten関連）:"
    tail -50 "$WP_DEBUG_LOG" | grep -E "Umaten|toppage" || log_info "Umaten関連のログなし"
    echo ""
else
    log_info "WordPressデバッグログが見つかりません（debug.logが無効の可能性）"
fi

# 9. リライトルールのフラッシュを試す
echo ""
echo "=========================================="
log_info "9. リライトルールのフラッシュを試しますか？"
echo "=========================================="

read -p "リライトルールをフラッシュしますか？ (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    if command -v wp &> /dev/null; then
        cd "$DOCROOT"
        log_info "リライトルールをフラッシュ中..."
        wp rewrite flush --path="$DOCROOT"
        log_success "リライトルールをフラッシュしました"

        log_info "更新後のリライトルール:"
        wp rewrite list --path="$DOCROOT" | head -20
    else
        log_error "WP-CLI が見つかりません"
    fi
fi

# 10. 推奨アクション
echo ""
echo "=========================================="
echo "  推奨アクション"
echo "=========================================="
echo ""

log_info "以下の情報を確認してください:"
echo ""
echo "1. リライトルールに ^([^/]+)/([^/]+)/ のようなパターンがないか"
echo "   → あれば削除する必要があります"
echo ""
echo "2. Nginx設定に不要なrewriteルールがないか"
echo "   → /etc/nginx/conf.d/*.conf を確認"
echo ""
echo "3. WordPressのパーマリンク設定を再保存"
echo "   → 管理画面 → 設定 → パーマリンク → 変更を保存"
echo ""
echo "4. すべてのキャッシュをクリア"
echo "   → kusanagi clear fcache"
echo "   → kusanagi clear bcache"
echo ""
echo "5. ブラウザのキャッシュをクリア"
echo "   → Ctrl+F5 または Cmd+Shift+R"
echo ""

echo "診断完了"
exit 0
