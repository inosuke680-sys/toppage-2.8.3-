#!/bin/bash

##############################################################################
# Umaten トップページプラグイン v2.10.14 本番環境デプロイスクリプト（全地域対応版）
#
# v2.10.14 新機能：
# - 全地域（北海道、東北、関東、中部、関西、中国、四国、九州・沖縄）をデフォルトで公開状態に
# - プラグイン再有効化で既存設定も自動的に更新
# - 東北など他の地域でも北海道と同じように3ステップナビゲーションが動作
# - WordPress管理画面の「トップページ設定」で各地域の公開状態を個別に管理可能
#
# 実行方法:
#   curl -o /tmp/deploy-v2.10.14.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq/deploy-production-v2.10.14.sh
#   chmod +x /tmp/deploy-v2.10.14.sh
#   sudo /tmp/deploy-v2.10.14.sh
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
PLUGIN_NAME="umaten-toppage-v2.10.14"
OLD_PLUGIN_PATTERN="umaten-toppage-v2.*"
BACKUP_DIR="/tmp/umaten-plugin-backup-$(date +%Y%m%d-%H%M%S)"
GITHUB_REPO="inosuke680-sys/toppage-2.8.3-"
GITHUB_BRANCH="claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq"
DOWNLOAD_URL="https://github.com/${GITHUB_REPO}/archive/refs/heads/${GITHUB_BRANCH}.zip"
TEMP_DIR="/tmp/umaten-plugin-deploy-$$"

echo ""
echo "=========================================="
echo "  Umaten プラグイン v2.10.14 デプロイ"
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
log_info "新しいプラグイン v2.10.14 をダウンロード中..."
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
log_info "新しいプラグイン v2.10.14 をインストール中..."
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
log_info "バージョン: v2.10.14"
log_info "インストール先: $INSTALL_PATH"
log_info "バックアップ: $BACKUP_DIR"
echo ""
log_success "実行された処理:"
echo "  ✓ プラグインのインストールと有効化"
echo "  ✓ パーマリンクの更新"
echo "  ✓ 全キャッシュのクリア（OPcache、WordPress、KUSANAGI、Nginx、PHP-FPM）"
echo ""
log_success "v2.10.14 の変更内容:"
echo "  【重要】全地域（北海道、東北、関東、中部、関西、中国、四国、九州・沖縄）を公開状態に"
echo "  "
echo "  【問題の背景】"
echo "    - v2.10.13まで、北海道のみが公開状態（'published'）"
echo "    - 他の地域はすべて準備中（'coming_soon'）ステータス"
echo "    - ユーザーが管理画面で手動変更する必要があった"
echo "    - 東北のカテゴリ・ジャンルは作成済みなのに使えない状態"
echo "  "
echo "  【v2.10.14の変更内容】"
echo "    1. デフォルトで全地域を'published'に設定"
echo "    2. 既存設定がある場合も自動的に'published'に更新"
echo "    3. プラグインを再有効化するだけで全地域が有効化"
echo "  "
echo "  【技術的な変更】"
echo "    - umaten-toppage.php activate()メソッドを更新"
echo "    - すべての地域のデフォルトステータスを'published'に変更"
echo "    - 既存設定も更新するロジックを追加"
echo "  "
echo "  【動作】"
echo "    - トップページで「東北」「関東」などのタブが表示される"
echo "    - 各地域をクリックすると子カテゴリモーダルが開く"
echo "    - 北海道と同じように3ステップナビゲーションが動作"
echo "  "
echo "  【管理画面】"
echo "    - WordPress管理画面 → トップページ設定"
echo "    - 各地域を個別に「公開中」「準備中」「非表示」に設定可能"
echo "    - 必要に応じて特定地域のみ非公開にできる"
echo ""
log_warning "次のステップ:"
echo "  1. トップページにアクセス"
echo "     https://umaten.jp/"
echo "  "
echo "  2. 地域タブを確認"
echo "     → 「北海道」「東北」「関東」などすべての地域タブが表示される"
echo "     → 「準備中」マークがないことを確認"
echo "  "
echo "  3. 東北エリアをテスト"
echo "     → 「東北」タブをクリック"
echo "     → 東北の親カテゴリカードが表示される"
echo "     → カードをクリックして子カテゴリモーダルが開くことを確認"
echo "  "
echo "  4. 東北の子カテゴリとジャンルをテスト"
echo "     → 子カテゴリを選択"
echo "     → ジャンルモーダルが開くことを確認"
echo "     → ジャンルを選択して検索結果が表示されることを確認"
echo "  "
echo "  5. 他の地域もテスト（関東、中部、関西等）"
echo "     → 各地域で同じように動作することを確認"
echo "  "
echo "  6. 管理画面で設定を確認（任意）"
echo "     → WordPress管理画面 → トップページ設定"
echo "     → すべての地域が「公開中」になっていることを確認"
echo "     → 必要に応じて特定地域を「準備中」や「非表示」に変更可能"
echo "  "
echo "  7. ブラウザのキャッシュもクリア（Ctrl+F5 または Cmd+Shift+R）"
echo "  "
echo "  8. 問題がある場合は、バックアップから復元: $BACKUP_DIR"
echo ""

exit 0
