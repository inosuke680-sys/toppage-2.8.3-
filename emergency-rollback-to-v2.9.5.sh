#!/bin/bash

##############################################################################
# 緊急ロールバックスクリプト - v2.9.5に戻す
#
# v2.9.6/v2.9.7で問題が発生した場合、v2.9.5に戻すためのスクリプト
#
# 実行方法:
#   curl -o /tmp/rollback-v2.9.5.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq/emergency-rollback-to-v2.9.5.sh
#   chmod +x /tmp/rollback-v2.9.5.sh
#   sudo /tmp/rollback-v2.9.5.sh
##############################################################################

set -e

# 色の定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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
PLUGIN_NAME="umaten-toppage-v2.9.5"
OLD_PLUGIN_PATTERN="umaten-toppage-v2.*"
BACKUP_DIR="/tmp/umaten-plugin-backup-$(date +%Y%m%d-%H%M%S)"

echo ""
echo "=========================================="
echo "  緊急ロールバック → v2.9.5"
echo "=========================================="
echo ""

# root権限チェック
if [ "$EUID" -ne 0 ]; then
    log_error "このスクリプトはroot権限で実行してください (sudo を使用)"
    exit 1
fi

# PHPパスを探す
PHP_PATH=""
if [ -f "/opt/kusanagi/php/bin/php" ]; then
    PHP_PATH="/opt/kusanagi/php/bin/php"
elif [ -f "/opt/kusanagi/php-8.4/bin/php" ]; then
    PHP_PATH="/opt/kusanagi/php-8.4/bin/php"
elif [ -f "/usr/bin/php" ]; then
    PHP_PATH="/usr/bin/php"
fi

# 現在のプラグインをバックアップ
log_info "現在のプラグインをバックアップ中..."
mkdir -p "$BACKUP_DIR"
for plugin in ${PLUGIN_DIR}/${OLD_PLUGIN_PATTERN}; do
    if [ -d "$plugin" ]; then
        plugin_basename=$(basename "$plugin")
        cp -r "$plugin" "$BACKUP_DIR/"
        log_info "バックアップ: $plugin_basename"
    fi
done

# 既存プラグインを無効化
if [ -n "$PHP_PATH" ]; then
    log_info "既存プラグインを無効化中..."
    cd "$DOCROOT"
    for plugin in ${PLUGIN_DIR}/${OLD_PLUGIN_PATTERN}; do
        if [ -d "$plugin" ]; then
            plugin_basename=$(basename "$plugin")
            plugin_slug="${plugin_basename}/umaten-toppage.php"
            $PHP_PATH -r "
define('WP_USE_THEMES', false);
require('$DOCROOT/wp-load.php');
\$active_plugins = get_option('active_plugins');
if (in_array('$plugin_slug', \$active_plugins)) {
    \$key = array_search('$plugin_slug', \$active_plugins);
    unset(\$active_plugins[\$key]);
    update_option('active_plugins', \$active_plugins);
    echo '無効化: $plugin_basename\n';
}
" 2>/dev/null || true
        fi
    done
fi

# 既存プラグインを削除
log_info "v2.9.6/v2.9.7を削除中..."
for plugin in ${PLUGIN_DIR}/${OLD_PLUGIN_PATTERN}; do
    if [ -d "$plugin" ]; then
        plugin_basename=$(basename "$plugin")
        log_info "削除: $plugin_basename"
        rm -rf "$plugin"
    fi
done

# v2.9.5をインストール
log_info "v2.9.5をインストール中..."

# GitHubから特定のコミットをダウンロード（v2.9.5のコミット）
TEMP_DIR="/tmp/umaten-rollback-$$"
mkdir -p "$TEMP_DIR"
cd "$TEMP_DIR"

# v2.9.5のコミットハッシュ: 2854fdb
COMMIT_HASH="2854fdb"
DOWNLOAD_URL="https://github.com/inosuke680-sys/toppage-2.8.3-/archive/${COMMIT_HASH}.zip"

if ! curl -L -o plugin.zip "$DOWNLOAD_URL"; then
    log_error "v2.9.5のダウンロードに失敗しました"
    exit 1
fi

unzip -q plugin.zip
EXTRACTED_DIR=$(ls -d toppage-2.8.3--* 2>/dev/null | head -n 1)
PLUGIN_SOURCE="${TEMP_DIR}/${EXTRACTED_DIR}/umaten-toppage-v2.8.3"

if [ ! -d "$PLUGIN_SOURCE" ]; then
    log_error "プラグインソースが見つかりません"
    exit 1
fi

INSTALL_PATH="${PLUGIN_DIR}/${PLUGIN_NAME}"
cp -r "$PLUGIN_SOURCE" "$INSTALL_PATH"

# 所有権とパーミッション
chown -R kusanagi:kusanagi "$INSTALL_PATH"
find "$INSTALL_PATH" -type d -exec chmod 755 {} \;
find "$INSTALL_PATH" -type f -exec chmod 644 {} \;

log_success "v2.9.5インストール完了: $INSTALL_PATH"

# プラグイン有効化
if [ -n "$PHP_PATH" ]; then
    log_info "v2.9.5を有効化中..."
    cd "$DOCROOT"
    PLUGIN_SLUG="${PLUGIN_NAME}/umaten-toppage.php"
    $PHP_PATH -r "
define('WP_USE_THEMES', false);
require('$DOCROOT/wp-load.php');
\$active_plugins = get_option('active_plugins');
if (!in_array('$PLUGIN_SLUG', \$active_plugins)) {
    \$active_plugins[] = '$PLUGIN_SLUG';
    update_option('active_plugins', \$active_plugins);
    echo 'プラグインを有効化しました\n';
}
" && log_success "v2.9.5有効化完了"
fi

# キャッシュクリア
log_info "キャッシュクリア中..."
if [ -n "$PHP_PATH" ]; then
    $PHP_PATH -r "if (function_exists('opcache_reset')) { opcache_reset(); }"
fi

if command -v kusanagi &> /dev/null; then
    kusanagi clear fcache 2>/dev/null || true
    kusanagi clear bcache 2>/dev/null || true
fi

# PHP-FPM再起動
if systemctl list-units --type=service | grep -q "php.*fpm"; then
    PHP_FPM_SERVICE=$(systemctl list-units --type=service | grep "php.*fpm" | awk '{print $1}' | head -n 1)
    systemctl restart "$PHP_FPM_SERVICE" 2>/dev/null || true
fi

# クリーンアップ
rm -rf "$TEMP_DIR"

echo ""
echo "=========================================="
echo "  ✅ ロールバック完了！"
echo "=========================================="
echo ""
log_success "v2.9.5に正常にロールバックしました"
log_info "バックアップ: $BACKUP_DIR"
echo ""
log_warning "v2.9.5の既知の問題:"
echo "  - /hokkaido/hakodate/ramen/ がトップページにリダイレクトされる"
echo "  - restaurant-review-category-tags との衝突が残っている"
echo ""
log_info "今後の対応:"
echo "  - デバッグプラグインで詳細調査を実施"
echo "  - 根本原因を特定してv2.9.8で修正"
echo ""

exit 0
