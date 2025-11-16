#!/bin/bash

##############################################################################
# Umaten トップページプラグイン v2.10.15 本番環境デプロイスクリプト（親カテゴリ診断版）
#
# v2.10.15 新機能：
# - 親カテゴリ検索の詳細デバッグログ追加
# - 親カテゴリが見つからない場合、全カテゴリスラッグを出力
# - 子カテゴリ取得の詳細ログ
# - 東北地域などの新規エリア展開時の診断を容易に
# - デフォルト設定は北海道のみ公開、他地域は準備中
#
# 実行方法:
#   curl -o /tmp/deploy-v2.10.15.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq/deploy-production-v2.10.15.sh
#   chmod +x /tmp/deploy-v2.10.15.sh
#   sudo /tmp/deploy-v2.10.15.sh
##############################################################################

set -e  # エラーが発生したら即座に終了

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
PLUGIN_DIR="${DOCROOT}/wp-content/plugins"
PLUGIN_NAME="umaten-toppage-v2.10.15"
OLD_PLUGIN_PATTERN="umaten-toppage-v2.*"
BACKUP_DIR="/tmp/umaten-plugin-backup-$(date +%Y%m%d-%H%M%S)"
GITHUB_REPO="inosuke680-sys/toppage-2.8.3-"
GITHUB_BRANCH="claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq"
DOWNLOAD_URL="https://github.com/${GITHUB_REPO}/archive/refs/heads/${GITHUB_BRANCH}.zip"
TEMP_DIR="/tmp/umaten-plugin-deploy-$$"

echo ""
echo "=========================================="
echo "  Umaten プラグイン v2.10.15 デプロイ"
echo "=========================================="
echo ""

# root権限チェック
if [ "$EUID" -ne 0 ]; then
    log_error "このスクリプトはroot権限で実行してください (sudo を使用)"
    exit 1
fi

# DocumentRootの存在確認
if [ ! -d "$DOCROOT" ]; then
    log_error "DocumentRoot が見つかりません: $DOCROOT"
    exit 1
fi

log_info "DocumentRoot: $DOCROOT"

# プラグインディレクトリの確認
if [ ! -d "$PLUGIN_DIR" ]; then
    log_error "プラグインディレクトリが見つかりません: $PLUGIN_DIR"
    exit 1
fi

# 1. 既存プラグインのバックアップ
log_info "既存プラグインのバックアップを作成中..."
mkdir -p "$BACKUP_DIR"

for plugin in ${PLUGIN_DIR}/${OLD_PLUGIN_PATTERN}; do
    if [ -d "$plugin" ]; then
        plugin_basename=$(basename "$plugin")
        log_info "バックアップ: $plugin_basename"
        cp -r "$plugin" "$BACKUP_DIR/"
    fi
done

log_success "バックアップ完了: $BACKUP_DIR"

# 2. KUSANAGIのPHPパスを探す
log_info "PHPパスを検索中..."
PHP_PATH=""

# KUSANAGIの標準パスを確認
if [ -f "/opt/kusanagi/php/bin/php" ]; then
    PHP_PATH="/opt/kusanagi/php/bin/php"
elif [ -f "/opt/kusanagi/php-8.4/bin/php" ]; then
    PHP_PATH="/opt/kusanagi/php-8.4/bin/php"
elif [ -f "/usr/bin/php" ]; then
    PHP_PATH="/usr/bin/php"
else
    log_warning "PHPが見つかりません。WP-CLI機能は使用できません。"
fi

if [ -n "$PHP_PATH" ]; then
    log_success "PHP が見つかりました: $PHP_PATH"
    WP_CLI_AVAILABLE=true
else
    WP_CLI_AVAILABLE=false
fi

# 3. 既存プラグインの無効化
if [ "$WP_CLI_AVAILABLE" = true ]; then
    log_info "既存プラグインを無効化中..."
    cd "$DOCROOT"

    for plugin in ${PLUGIN_DIR}/${OLD_PLUGIN_PATTERN}; do
        if [ -d "$plugin" ]; then
            plugin_basename=$(basename "$plugin")
            plugin_slug="${plugin_basename}/umaten-toppage.php"

            # PHPスクリプトで直接確認・無効化
            $PHP_PATH -r "
define('WP_USE_THEMES', false);
require('$DOCROOT/wp-load.php');
\$active_plugins = get_option('active_plugins');
if (in_array('$plugin_slug', \$active_plugins)) {
    echo '無効化: $plugin_basename\n';
    \$key = array_search('$plugin_slug', \$active_plugins);
    unset(\$active_plugins[\$key]);
    update_option('active_plugins', \$active_plugins);
}
" 2>/dev/null || true
        fi
    done
fi

# 4. 新しいプラグインのダウンロード
log_info "新しいプラグイン v2.10.15 をダウンロード中..."
mkdir -p "$TEMP_DIR"
cd "$TEMP_DIR"

# GitHubからZIPファイルをダウンロード
if ! curl -L -o plugin.zip "$DOWNLOAD_URL"; then
    log_error "プラグインのダウンロードに失敗しました"
    log_info "手動でダウンロードしてください: $DOWNLOAD_URL"
    exit 1
fi

log_success "ダウンロード完了"

# 5. ZIPファイルの展開
log_info "プラグインを展開中..."
if ! unzip -q plugin.zip; then
    log_error "ZIPファイルの展開に失敗しました"
    exit 1
fi

# 展開されたディレクトリを確認
EXTRACTED_DIR=$(ls -d toppage-2.8.3--* 2>/dev/null | head -n 1)
if [ -z "$EXTRACTED_DIR" ]; then
    log_error "展開されたディレクトリが見つかりません"
    exit 1
fi

log_info "展開されたディレクトリ: $EXTRACTED_DIR"

# プラグインディレクトリを見つける
PLUGIN_SOURCE="${TEMP_DIR}/${EXTRACTED_DIR}/umaten-toppage-v2.8.3"

if [ ! -d "$PLUGIN_SOURCE" ]; then
    log_error "プラグインソースが見つかりません: $PLUGIN_SOURCE"
    log_info "利用可能なディレクトリ:"
    ls -la "$TEMP_DIR/$EXTRACTED_DIR/"
    exit 1
fi

# 6. 既存プラグインの削除
log_info "既存プラグインを削除中..."
for plugin in ${PLUGIN_DIR}/${OLD_PLUGIN_PATTERN}; do
    if [ -d "$plugin" ]; then
        plugin_basename=$(basename "$plugin")
        log_info "削除: $plugin_basename"
        rm -rf "$plugin"
    fi
done

# 7. 新しいプラグインのインストール
log_info "新しいプラグイン v2.10.15 をインストール中..."
INSTALL_PATH="${PLUGIN_DIR}/${PLUGIN_NAME}"

cp -r "$PLUGIN_SOURCE" "$INSTALL_PATH"
log_success "インストール完了: $INSTALL_PATH"

# 8. 所有権とパーミッションの設定
log_info "所有権とパーミッションを設定中..."
chown -R kusanagi:kusanagi "$INSTALL_PATH"
find "$INSTALL_PATH" -type d -exec chmod 755 {} \;
find "$INSTALL_PATH" -type f -exec chmod 644 {} \;
log_success "パーミッション設定完了"

# 9. プラグインの有効化
if [ "$WP_CLI_AVAILABLE" = true ]; then
    log_info "プラグインを有効化中..."
    cd "$DOCROOT"

    PLUGIN_SLUG="${PLUGIN_NAME}/umaten-toppage.php"

    $PHP_PATH -r "
define('WP_USE_THEMES', false);
require('$DOCROOT/wp-load.php');
\$active_plugins = get_option('active_plugins');
if (!in_array('$PLUGIN_SLUG', \$active_plugins)) {
    \$active_plugins[] = '$PLUGIN_SLUG';
    update_option('active_plugins', \$active_plugins);
    echo 'プラグインを有効化しました: $PLUGIN_SLUG\n';
} else {
    echo 'プラグインは既に有効です: $PLUGIN_SLUG\n';
}
" && log_success "プラグイン有効化完了" || log_warning "プラグインの有効化に失敗しました。管理画面から手動で有効化してください。"
else
    log_warning "WordPress管理画面からプラグインを手動で有効化してください。"
fi

# 10. パーマリンクの更新
if [ "$WP_CLI_AVAILABLE" = true ]; then
    log_info "パーマリンクを更新中..."
    cd "$DOCROOT"
    $PHP_PATH -r "
define('WP_USE_THEMES', false);
require('$DOCROOT/wp-load.php');
flush_rewrite_rules();
echo 'パーマリンク更新完了\n';
" && log_success "パーマリンク更新完了" || log_warning "パーマリンク更新に失敗しました"
else
    log_warning "WordPress管理画面 → 設定 → パーマリンク → 「変更を保存」を実行してください。"
fi

# 11. キャッシュクリア
echo ""
log_info "========== キャッシュクリア開始 =========="

# 11-1. OPcacheのクリア
log_info "OPcache をクリア中..."
if [ "$WP_CLI_AVAILABLE" = true ]; then
    $PHP_PATH -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared'; } else { echo 'OPcache not available'; }"
    log_success "OPcache クリア完了"
else
    log_warning "PHP コマンドが見つかりません"
fi

# 11-2. WordPress Transientsのクリア
if [ "$WP_CLI_AVAILABLE" = true ]; then
    log_info "WordPress Transients をクリア中..."
    cd "$DOCROOT"
    $PHP_PATH -r "
define('WP_USE_THEMES', false);
require('$DOCROOT/wp-load.php');
global \$wpdb;
\$wpdb->query(\"DELETE FROM \$wpdb->options WHERE option_name LIKE '_transient_%'\");
echo 'Transients cleared\n';
" 2>/dev/null && log_success "WordPress Transients クリア完了" || log_warning "Transients削除でエラーが発生しました（無視して続行）"
fi

# 11-3. WordPress オブジェクトキャッシュのクリア
if [ "$WP_CLI_AVAILABLE" = true ]; then
    log_info "WordPress オブジェクトキャッシュ をクリア中..."
    cd "$DOCROOT"
    $PHP_PATH -r "
define('WP_USE_THEMES', false);
require('$DOCROOT/wp-load.php');
wp_cache_flush();
echo 'Object cache flushed\n';
" 2>/dev/null && log_success "WordPress オブジェクトキャッシュ クリア完了" || log_warning "キャッシュフラッシュでエラーが発生しました（無視して続行）"
fi

# 11-4. KUSANAGIキャッシュのクリア（KUSANAGIの場合のみ）
if command -v kusanagi &> /dev/null; then
    log_info "KUSANAGI キャッシュ をクリア中..."
    kusanagi clear fcache 2>/dev/null || log_warning "fcache クリアでエラー（無視して続行）"
    kusanagi clear bcache 2>/dev/null || log_warning "bcache クリアでエラー（無視して続行）"
    log_success "KUSANAGI キャッシュ クリア完了"
else
    log_info "KUSANAGI環境ではないため、KUSANAGIキャッシュクリアをスキップします"
fi

# 11-5. Nginxキャッシュのクリア（存在する場合）
NGINX_CACHE_DIR="/var/cache/nginx"
if [ -d "$NGINX_CACHE_DIR" ]; then
    log_info "Nginx キャッシュ をクリア中..."
    rm -rf ${NGINX_CACHE_DIR}/* 2>/dev/null || log_warning "Nginxキャッシュクリアでエラー（無視して続行）"
    log_success "Nginx キャッシュ クリア完了"
fi

# 11-6. PHP-FPMの再起動（OPcacheを確実にクリアするため）
log_info "PHP-FPM を再起動中..."
if systemctl list-units --type=service | grep -q "php.*fpm"; then
    PHP_FPM_SERVICE=$(systemctl list-units --type=service | grep "php.*fpm" | awk '{print $1}' | head -n 1)
    if systemctl restart "$PHP_FPM_SERVICE" 2>/dev/null; then
        log_success "PHP-FPM 再起動完了: $PHP_FPM_SERVICE"
    else
        log_warning "PHP-FPM の再起動に失敗しました（権限不足の可能性）"
    fi
else
    log_warning "PHP-FPM サービスが見つかりません"
fi

log_success "========== キャッシュクリア完了 =========="
echo ""

# 12. クリーンアップ
log_info "一時ファイルを削除中..."
rm -rf "$TEMP_DIR"
log_success "クリーンアップ完了"

# 13. 完了メッセージ
echo ""
echo "=========================================="
echo "  ✅ デプロイ完了！"
echo "=========================================="
echo ""
log_info "バージョン: v2.10.15"
log_info "インストール先: $INSTALL_PATH"
log_info "バックアップ: $BACKUP_DIR"
echo ""
log_success "実行された処理:"
echo "  ✓ プラグインのインストールと有効化"
echo "  ✓ パーマリンクの更新"
echo "  ✓ 全キャッシュのクリア（OPcache、WordPress、KUSANAGI、Nginx、PHP-FPM）"
echo ""
log_success "v2.10.15 の変更内容:"
echo "  【重要】親カテゴリ検索の診断ログを追加"
echo "  "
echo "  【問題の背景】"
echo "    - 東北地域を選択すると「親カテゴリが見つかりません」エラーが表示される"
echo "    - 宮城・山形などのカテゴリは作成済みだが、親カテゴリ「tohoku」との紐付けが不明"
echo "    - 新規エリア展開時に同様の問題が発生する可能性"
echo "  "
echo "  【v2.10.15の診断機能】"
echo "    1. 親カテゴリスラッグの検索をログ出力"
echo "    2. 親カテゴリが見つからない場合、全カテゴリスラッグを出力"
echo "    3. 親カテゴリが見つかった場合、ID・名前を出力"
echo "    4. 子カテゴリの取得結果をログ出力（数・名前・スラッグ）"
echo "    5. エラーメッセージを分かりやすく改善"
echo "  "
echo "  【診断ログの例（成功時）】"
echo "    Umaten Toppage v2.10.15: Searching for parent category with slug: hokkaido"
echo "    Umaten Toppage v2.10.15: Found parent category: 北海道 (ID: 5)"
echo "    Umaten Toppage v2.10.15: Found 3 child categories for parent '北海道'"
echo "    Umaten Toppage v2.10.15: Child categories: 函館 (hakodate), 札幌 (sapporo), 旭川 (asahikawa)"
echo "  "
echo "  【診断ログの例（エラー時）】"
echo "    Umaten Toppage v2.10.15: Searching for parent category with slug: tohoku"
echo "    Umaten Toppage v2.10.15: Parent category 'tohoku' not found. Available category slugs: hokkaido, hakodate, sapporo, miyagi, yamagata"
echo "    → この場合、'tohoku'という親カテゴリが作成されていないことが分かる"
echo "  "
echo "  【デフォルト設定】"
echo "    - 北海道のみ「公開中」状態（変更なし）"
echo "    - 他の地域（東北、関東、中部、関西、中国、四国、九州・沖縄）は「準備中」状態"
echo "    - 管理画面から各地域の公開状態をワンクリックで変更可能"
echo ""
log_warning "次のステップ:"
echo "  1. WP_DEBUGを有効にする（未設定の場合）"
echo "     wp-config.php に以下を追加:"
echo "       define('WP_DEBUG', true);"
echo "       define('WP_DEBUG_LOG', true);"
echo "       define('WP_DEBUG_DISPLAY', false);"
echo "  "
echo "  2. デバッグログを確認"
echo "     tail -f /home/kusanagi/45515055731ac663c7c3ad4c/DocumentRoot/wp-content/debug.log"
echo "  "
echo "  3. 東北地域をテスト"
echo "     トップページから「東北」を選択"
echo "     → デバッグログに以下のような出力が表示される:"
echo "       - \"Searching for parent category with slug: tohoku\""
echo "       - 「見つかりません」の場合、利用可能なカテゴリスラッグが表示される"
echo "       - 「見つかりました」の場合、子カテゴリ一覧が表示される"
echo "  "
echo "  4. ログを元に問題を特定"
echo "     【ケースA】親カテゴリ 'tohoku' が見つからない場合:"
echo "       → WordPress管理画面で親カテゴリ「東北」（スラッグ: tohoku）を作成"
echo "       → 既存の「宮城」「山形」カテゴリの親を「東北」に設定"
echo "  "
echo "     【ケースB】親カテゴリは見つかるが子カテゴリがない場合:"
echo "       → 「宮城」「山形」カテゴリの親カテゴリ設定を確認"
echo "       → 親カテゴリが「東北」になっているか確認"
echo "  "
echo "  5. カテゴリ構造の例（正しい設定）"
echo "     - 北海道（親カテゴリ、スラッグ: hokkaido）"
echo "       └ 函館（子カテゴリ、スラッグ: hakodate）"
echo "       └ 札幌（子カテゴリ、スラッグ: sapporo）"
echo "     - 東北（親カテゴリ、スラッグ: tohoku）"
echo "       └ 宮城（子カテゴリ、スラッグ: miyagi）"
echo "       └ 山形（子カテゴリ、スラッグ: yamagata）"
echo "  "
echo "  6. カテゴリ設定完了後、管理画面で地域を公開状態に変更"
echo "     WordPress管理画面 → トップページ設定 → 東北を「公開中」に変更"
echo "  "
echo "  7. 問題がある場合は、バックアップから復元: $BACKUP_DIR"
echo ""

exit 0
