#!/bin/bash

##############################################################################
# Umaten トップページプラグイン v2.8.5 本番環境デプロイスクリプト
#
# 実行方法:
#   curl -o /tmp/deploy-v2.8.5.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq/deploy-production-v2.8.5.sh
#   chmod +x /tmp/deploy-v2.8.5.sh
#   sudo /tmp/deploy-v2.8.5.sh
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
PLUGIN_NAME="umaten-toppage-v2.8.5"
OLD_PLUGIN_PATTERN="umaten-toppage-v2.8.*"
BACKUP_DIR="/tmp/umaten-plugin-backup-$(date +%Y%m%d-%H%M%S)"
GITHUB_REPO="inosuke680-sys/toppage-2.8.3-"
GITHUB_BRANCH="claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq"
DOWNLOAD_URL="https://github.com/${GITHUB_REPO}/archive/refs/heads/${GITHUB_BRANCH}.zip"
TEMP_DIR="/tmp/umaten-plugin-deploy-$$"

echo ""
echo "=========================================="
echo "  Umaten プラグイン v2.8.5 デプロイ"
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

# 2. WP-CLIの確認
if ! command -v wp &> /dev/null; then
    log_warning "WP-CLI が見つかりません。プラグインの有効化/無効化は手動で行ってください。"
    WP_CLI_AVAILABLE=false
else
    WP_CLI_AVAILABLE=true
    log_success "WP-CLI が利用可能です"
fi

# 3. 既存プラグインの無効化
if [ "$WP_CLI_AVAILABLE" = true ]; then
    log_info "既存プラグインを無効化中..."
    cd "$DOCROOT"

    for plugin in ${PLUGIN_DIR}/${OLD_PLUGIN_PATTERN}; do
        if [ -d "$plugin" ]; then
            plugin_basename=$(basename "$plugin")
            plugin_slug="${plugin_basename}/umaten-toppage.php"

            if wp plugin is-active "$plugin_slug" --path="$DOCROOT" 2>/dev/null; then
                log_info "無効化: $plugin_basename"
                wp plugin deactivate "$plugin_slug" --path="$DOCROOT" || true
            fi
        fi
    done
fi

# 4. 新しいプラグインのダウンロード
log_info "新しいプラグイン v2.8.5 をダウンロード中..."
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
log_info "新しいプラグイン v2.8.5 をインストール中..."
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

    if wp plugin activate "$PLUGIN_SLUG" --path="$DOCROOT"; then
        log_success "プラグインを有効化しました: $PLUGIN_SLUG"
    else
        log_warning "プラグインの有効化に失敗しました。管理画面から手動で有効化してください。"
    fi
else
    log_warning "WordPress管理画面からプラグインを手動で有効化してください。"
fi

# 10. パーマリンクの更新
if [ "$WP_CLI_AVAILABLE" = true ]; then
    log_info "パーマリンクを更新中..."
    cd "$DOCROOT"
    wp rewrite flush --path="$DOCROOT"
    log_success "パーマリンク更新完了"
else
    log_warning "WordPress管理画面 → 設定 → パーマリンク → 「変更を保存」を実行してください。"
fi

# 11. クリーンアップ
log_info "一時ファイルを削除中..."
rm -rf "$TEMP_DIR"
log_success "クリーンアップ完了"

# 12. 完了メッセージ
echo ""
echo "=========================================="
echo "  ✅ デプロイ完了！"
echo "=========================================="
echo ""
log_info "バージョン: v2.8.5"
log_info "インストール先: $INSTALL_PATH"
log_info "バックアップ: $BACKUP_DIR"
echo ""
log_success "新機能:"
echo "  1. 人気のジャンル → サイト説明セクションに変更"
echo "  2. エリアから探す → 掲載店舗エリアに変更"
echo "  3. 月間アクセス数の手動入力機能を追加"
echo ""
log_warning "次のステップ:"
echo "  1. WordPress管理画面 → トップページ設定 → 月間アクセス数を設定"
echo "  2. サイトを確認して、正常に動作していることを確認"
echo "  3. 問題がある場合は、バックアップから復元: $BACKUP_DIR"
echo ""

exit 0
