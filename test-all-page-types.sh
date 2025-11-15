#!/bin/bash

##############################################################################
# 全ページタイプテストスクリプト
#
# 用途：各種ページがリダイレクトされているか確認
# 実行方法：
#   curl -o /tmp/test-all-pages.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/remove-popular-genre-update-layout-01Q3CQHiR3WZZMyJCDjpyMhq/test-all-page-types.sh
#   chmod +x /tmp/test-all-pages.sh
#   bash /tmp/test-all-pages.sh
##############################################################################

# 色の定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

test_url() {
    local url="$1"
    local expected_title="$2"
    local description="$3"

    echo ""
    echo -e "${CYAN}========================================${NC}"
    echo -e "${CYAN}テスト: ${description}${NC}"
    echo -e "${CYAN}========================================${NC}"
    echo "URL: $url"

    # HTTPヘッダーを取得
    local headers=$(curl -sI "$url" 2>&1)
    local http_status=$(echo "$headers" | grep -i "^HTTP" | tail -1 | awk '{print $2}')
    local location=$(echo "$headers" | grep -i "^Location:" | cut -d' ' -f2- | tr -d '\r')

    echo "HTTPステータス: $http_status"

    if [ -n "$location" ]; then
        echo -e "${RED}リダイレクト検出!${NC}"
        echo "リダイレクト先: $location"
        log_error "リダイレクトが発生しています"
        return 1
    fi

    # HTMLを取得してタイトルを確認
    local html=$(curl -s "$url")
    local actual_title=$(echo "$html" | grep -oP '<title>\K[^<]+' | head -1)

    echo "取得されたタイトル: $actual_title"
    echo "期待されるタイトル: $expected_title"

    # タイトルが一致するか確認（部分一致）
    if echo "$actual_title" | grep -q "$expected_title"; then
        log_success "正常に表示されています"
        return 0
    else
        log_error "タイトルが期待と異なります（リダイレクトまたはコンテンツエラー）"

        # カノニカルURLも確認
        local canonical=$(echo "$html" | grep -oP '<link rel="canonical" href="\K[^"]+')
        if [ -n "$canonical" ]; then
            echo "カノニカルURL: $canonical"
        fi

        return 1
    fi
}

echo ""
echo "=========================================="
echo "  全ページタイプテスト"
echo "=========================================="
echo ""
echo "現在時刻: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# テスト結果カウンター
total=0
passed=0
failed=0

# 1. トップページ
total=$((total + 1))
if test_url "https://umaten.jp/" "ウマ店" "トップページ"; then
    passed=$((passed + 1))
else
    failed=$((failed + 1))
fi

# 2. 投稿ページ
total=$((total + 1))
if test_url "https://umaten.jp/hokkaido/menya-kagetsu-hakodate-kikyo-2/" "麺屋 華月" "投稿ページ"; then
    passed=$((passed + 1))
else
    failed=$((failed + 1))
fi

# 3. カテゴリページ（函館）
total=$((total + 1))
if test_url "https://umaten.jp/hokkaido/hakodate/" "函館" "カテゴリページ（函館）"; then
    passed=$((passed + 1))
else
    failed=$((failed + 1))
fi

# 4. カテゴリ+タグページ（函館 × ラーメン）
total=$((total + 1))
if test_url "https://umaten.jp/hokkaido/hakodate/ramen/" "函館.*ラーメン" "カテゴリ+タグページ（函館×ラーメン）"; then
    passed=$((passed + 1))
else
    failed=$((failed + 1))
fi

# 5. カテゴリページ（ニセコ）
total=$((total + 1))
if test_url "https://umaten.jp/hokkaido/niseko/" "ニセコ" "カテゴリページ（ニセコ）"; then
    passed=$((passed + 1))
else
    failed=$((failed + 1))
fi

# 6. カテゴリ+タグページ（ニセコ × ラーメン）
total=$((total + 1))
if test_url "https://umaten.jp/hokkaido/niseko/ramen/" "ニセコ.*ラーメン" "カテゴリ+タグページ（ニセコ×ラーメン）"; then
    passed=$((passed + 1))
else
    failed=$((failed + 1))
fi

# 7. 別の投稿ページ（もしあれば）
# total=$((total + 1))
# if test_url "https://umaten.jp/hokkaido/another-post/" "期待タイトル" "別の投稿ページ"; then
#     passed=$((passed + 1))
# else
#     failed=$((failed + 1))
# fi

echo ""
echo "=========================================="
echo "  テスト結果サマリー"
echo "=========================================="
echo ""
echo "総テスト数: $total"
echo -e "${GREEN}成功: $passed${NC}"
echo -e "${RED}失敗: $failed${NC}"
echo ""

if [ $failed -eq 0 ]; then
    echo -e "${GREEN}✓ すべてのページが正常に動作しています${NC}"
    exit 0
else
    echo -e "${RED}✗ $failed 個のページでリダイレクトまたはエラーが検出されました${NC}"
    echo ""
    echo "次のステップ:"
    echo "  1. 包括的デバッグプラグインをインストールして詳細調査"
    echo "  2. またはv2.9.5にロールバック"
    exit 1
fi
