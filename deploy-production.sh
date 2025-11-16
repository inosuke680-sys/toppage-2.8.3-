#!/bin/bash

#####################################################################
# Umaten Toppage v2.10.17 本番デプロイスクリプト
#
# 使用方法:
# curl -o /tmp/deploy-v2.10.17.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/fix-hokkaido-category-loop-01AS3DQzNqAtBrdXLnDbxgSP/deploy-production.sh
# chmod +x /tmp/deploy-v2.10.17.sh
# sudo /tmp/deploy-v2.10.17.sh
#####################################################################

set -e  # エラーが発生したら即座に終了

# カラー出力
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 設定
GITHUB_REPO="inosuke680-sys/toppage-2.8.3-"
GITHUB_BRANCH="claude/fix-hokkaido-category-loop-01AS3DQzNqAtBrdXLnDbxgSP"
PLUGIN_NAME="umaten-toppage"
VERSION="2.10.17"
DOWNLOAD_URL="https://github.com/${GITHUB_REPO}/archive/refs/heads/${GITHUB_BRANCH}.zip"
TEMP_DIR="/tmp/umaten-toppage-deploy-$$"
WP_PLUGINS_DIR="/var/www/html/wp-content/plugins"

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}Umaten Toppage v${VERSION} 本番デプロイ${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

# rootチェック
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}エラー: このスクリプトはroot権限で実行してください${NC}"
    echo "sudo $0"
    exit 1
fi

# WordPressディレクトリの存在確認
if [ ! -d "$WP_PLUGINS_DIR" ]; then
    echo -e "${YELLOW}警告: WordPressプラグインディレクトリが見つかりません: $WP_PLUGINS_DIR${NC}"
    read -p "プラグインディレクトリのパスを入力してください: " CUSTOM_PATH
    if [ ! -d "$CUSTOM_PATH" ]; then
        echo -e "${RED}エラー: 指定されたパスが存在しません${NC}"
        exit 1
    fi
    WP_PLUGINS_DIR="$CUSTOM_PATH"
fi

echo -e "${BLUE}[1/6]${NC} 作業ディレクトリを作成中..."
mkdir -p "$TEMP_DIR"
cd "$TEMP_DIR"

echo -e "${BLUE}[2/6]${NC} GitHubからプラグインをダウンロード中..."
echo "URL: $DOWNLOAD_URL"
curl -L -o plugin.zip "$DOWNLOAD_URL"

if [ ! -f "plugin.zip" ]; then
    echo -e "${RED}エラー: ダウンロードに失敗しました${NC}"
    exit 1
fi

echo -e "${BLUE}[3/6]${NC} ZIPファイルを展開中..."
unzip -q plugin.zip

# 展開されたディレクトリ名を取得（GitHubのzipは通常 repo-name-branch-name/ となる）
EXTRACTED_DIR=$(ls -d */ | head -n 1)
if [ ! -d "$EXTRACTED_DIR" ]; then
    echo -e "${RED}エラー: 展開に失敗しました${NC}"
    exit 1
fi

# プラグインディレクトリを探す
if [ -d "${EXTRACTED_DIR}umaten-toppage-v2.8.3" ]; then
    PLUGIN_SOURCE="${EXTRACTED_DIR}umaten-toppage-v2.8.3"
else
    echo -e "${RED}エラー: プラグインディレクトリが見つかりません${NC}"
    ls -la "$EXTRACTED_DIR"
    exit 1
fi

echo -e "${BLUE}[4/6]${NC} 既存のプラグインをバックアップ中..."
BACKUP_DIR="${WP_PLUGINS_DIR}/${PLUGIN_NAME}.backup.$(date +%Y%m%d_%H%M%S)"
if [ -d "${WP_PLUGINS_DIR}/${PLUGIN_NAME}" ]; then
    mv "${WP_PLUGINS_DIR}/${PLUGIN_NAME}" "$BACKUP_DIR"
    echo -e "${GREEN}バックアップ完了: $BACKUP_DIR${NC}"
else
    echo -e "${YELLOW}既存のプラグインが見つかりません（新規インストール）${NC}"
fi

echo -e "${BLUE}[5/6]${NC} 新しいプラグインを配置中..."
cp -r "$PLUGIN_SOURCE" "${WP_PLUGINS_DIR}/${PLUGIN_NAME}"

# 権限設定
echo -e "${BLUE}[6/6]${NC} ファイル権限を設定中..."
chown -R www-data:www-data "${WP_PLUGINS_DIR}/${PLUGIN_NAME}"
find "${WP_PLUGINS_DIR}/${PLUGIN_NAME}" -type d -exec chmod 755 {} \;
find "${WP_PLUGINS_DIR}/${PLUGIN_NAME}" -type f -exec chmod 644 {} \;

# クリーンアップ
echo -e "${BLUE}クリーンアップ中...${NC}"
cd /
rm -rf "$TEMP_DIR"

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}✅ デプロイ完了！${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "プラグイン: ${GREEN}Umaten Toppage v${VERSION}${NC}"
echo -e "場所: ${GREEN}${WP_PLUGINS_DIR}/${PLUGIN_NAME}${NC}"
if [ -d "$BACKUP_DIR" ]; then
    echo -e "バックアップ: ${YELLOW}${BACKUP_DIR}${NC}"
fi
echo ""
echo -e "${YELLOW}次のステップ:${NC}"
echo "1. WordPressの管理画面にログイン"
echo "2. プラグイン > インストール済みプラグイン に移動"
echo "3. 「Umaten トップページ」が v2.10.17 であることを確認"
echo "4. プラグインが有効化されていることを確認"
echo "5. 北海道エリアで動作確認を実施"
echo ""
echo -e "${BLUE}バグ修正内容:${NC}"
echo "- 北海道カテゴリの無限ループ問題を修正"
echo "- 循環参照検出機能を追加"
echo "- カテゴリ階層の深さ制限を追加"
echo ""
echo -e "${GREEN}デプロイが成功しました！${NC}"
