#!/bin/bash

##############################################################################
# 包括的デバッグプラグイン インストールスクリプト
#
# 用途：すべてのページタイプでリダイレクトが発生している原因を詳細調査
# 実行方法:
#   curl -o /tmp/debug-comprehensive.php https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq/debug-comprehensive.php
#   curl -o /tmp/install-comprehensive-debug.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq/install-comprehensive-debug.sh
#   chmod +x /tmp/install-comprehensive-debug.sh
#   sudo /tmp/install-comprehensive-debug.sh
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
MU_PLUGINS_DIR="${DOCROOT}/wp-content/mu-plugins"
DEBUG_PLUGIN_SOURCE="/tmp/debug-comprehensive.php"
DEBUG_PLUGIN_DEST="${MU_PLUGINS_DIR}/debug-comprehensive.php"
LOG_FILE="/tmp/umaten-comprehensive-debug.log"

echo ""
echo "=========================================="
echo "  包括的デバッグプラグイン インストール"
echo "=========================================="
echo ""

# root権限チェック
if [ "$EUID" -ne 0 ]; then
    log_error "このスクリプトはroot権限で実行してください (sudo を使用)"
    exit 1
fi

# DocumentRoot確認
if [ ! -d "$DOCROOT" ]; then
    log_error "DocumentRoot が見つかりません: $DOCROOT"
    exit 1
fi

# mu-pluginsディレクトリ作成
if [ ! -d "$MU_PLUGINS_DIR" ]; then
    log_info "mu-pluginsディレクトリを作成中..."
    mkdir -p "$MU_PLUGINS_DIR"
    chown kusanagi:kusanagi "$MU_PLUGINS_DIR"
fi

# デバッグプラグインをインストール
if [ -f "$DEBUG_PLUGIN_SOURCE" ]; then
    log_info "デバッグプラグインをインストール中..."
    cp "$DEBUG_PLUGIN_SOURCE" "$DEBUG_PLUGIN_DEST"
    chown kusanagi:kusanagi "$DEBUG_PLUGIN_DEST"
    chmod 644 "$DEBUG_PLUGIN_DEST"
    log_success "インストール完了: $DEBUG_PLUGIN_DEST"
else
    log_error "デバッグプラグインのソースが見つかりません: $DEBUG_PLUGIN_SOURCE"
    exit 1
fi

# ログファイル初期化
log_info "ログファイルを初期化中..."
> "$LOG_FILE"
chmod 666 "$LOG_FILE"

# OPcacheクリア
log_info "OPcacheをクリア中..."
if [ -f "/opt/kusanagi/php/bin/php" ]; then
    /opt/kusanagi/php/bin/php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared'; }"
    echo ""
fi

echo ""
log_success "デバッグプラグインのインストール完了"
echo ""

log_warning "次のステップ:"
echo ""
echo "  1. 複数のページタイプにアクセスしてログを収集："
echo ""
echo "     【投稿ページ】"
echo "     curl -s https://umaten.jp/hokkaido/menya-kagetsu-hakodate-kikyo-2/ > /dev/null"
echo "     echo '--- 投稿ページ完了 ---'"
echo ""
echo "     【カテゴリ+タグページ】"
echo "     curl -s https://umaten.jp/hokkaido/hakodate/ramen/ > /dev/null"
echo "     echo '--- カテゴリ+タグページ完了 ---'"
echo ""
echo "     【カテゴリページ】"
echo "     curl -s https://umaten.jp/hokkaido/hakodate/ > /dev/null"
echo "     echo '--- カテゴリページ完了 ---'"
echo ""
echo "     【トップページ】"
echo "     curl -s https://umaten.jp/ > /dev/null"
echo "     echo '--- トップページ完了 ---'"
echo ""
echo "  2. ログを確認："
echo "     cat /tmp/umaten-comprehensive-debug.log"
echo ""
echo "  3. ログをコピーして分析"
echo ""
echo "  4. デバッグが完了したら、プラグインを削除："
echo "     sudo rm ${DEBUG_PLUGIN_DEST}"
echo "     sudo rm ${LOG_FILE}"
echo ""

exit 0
