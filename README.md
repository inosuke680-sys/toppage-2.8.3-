# Umaten Toppage v2.10.17

北海道カテゴリ無限ループ問題を修正したバージョンです。

## 🚀 本番環境へのデプロイ方法

SSHで本番サーバーに接続してから、以下のコマンドを実行してください：

```bash
# デプロイスクリプトをダウンロード
curl -o /tmp/deploy-v2.10.17.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-2.8.3-/claude/fix-hokkaido-category-loop-01AS3DQzNqAtBrdXLnDbxgSP/deploy-production.sh

# 実行権限を付与
chmod +x /tmp/deploy-v2.10.17.sh

# スクリプトを実行（root権限が必要）
sudo /tmp/deploy-v2.10.17.sh
```

### デプロイスクリプトが行うこと

1. ✅ GitHubから最新のプラグインをダウンロード
2. ✅ 既存のプラグインを自動バックアップ
3. ✅ 新しいバージョンを配置
4. ✅ ファイル権限を適切に設定
5. ✅ クリーンアップ

## 🐛 v2.10.17で修正されたバグ

### 北海道カテゴリ無限ループ問題

**現象:**
- 北海道エリアを選択 → 北海道を選択すると無限ループが発生
- 同じモーダルが繰り返し表示される

**修正内容:**
- ✅ 循環参照の自動検出
- ✅ 親と子が同じ場合は自動的にタグ選択へ進む
- ✅ カテゴリスタックによる重複アクセス防止
- ✅ 階層制限（3レベルまで）

## 📋 動作確認手順

デプロイ後、以下の手順で動作確認を行ってください：

1. WordPressの管理画面でプラグインバージョンが **2.10.17** であることを確認
2. 北海道タブをクリック → 北海道カードをクリック
3. 北海道のエリア選択で「📍 北海道」をクリック
4. **無限ループせず、ジャンル選択画面に進むことを確認**
5. ブラウザのコンソールに `[v2.10.17]` のログが表示されることを確認

## 📁 ディレクトリ構成

```
umaten-toppage-v2.8.3/
├── assets/
│   ├── css/
│   │   └── toppage.css
│   └── js/
│       └── toppage.js          # 循環参照検出ロジック追加
├── includes/
│   ├── class-ajax-handler.php  # hasChildren判定追加
│   ├── class-admin-settings.php
│   ├── class-hero-image.php
│   ├── class-search-results.php
│   ├── class-seo-meta.php
│   ├── class-shortcode.php
│   ├── class-url-rewrite.php
│   └── class-view-counter.php
└── umaten-toppage.php           # v2.10.17
```

## 🔄 ロールバック方法

問題が発生した場合、自動的に作成されたバックアップからロールバックできます：

```bash
# バックアップを確認
ls -la /var/www/html/wp-content/plugins/umaten-toppage.backup.*

# ロールバック（最新のバックアップに戻す）
cd /var/www/html/wp-content/plugins
sudo rm -rf umaten-toppage
sudo mv umaten-toppage.backup.YYYYMMDD_HHMMSS umaten-toppage
sudo chown -R www-data:www-data umaten-toppage
```

## 📝 変更履歴

詳細は [CHANGELOG-v2.10.17.md](./CHANGELOG-v2.10.17.md) を参照してください。

## 🆘 トラブルシューティング

### デプロイスクリプトが失敗する場合

1. **権限エラー**: `sudo` を使用しているか確認
2. **ディレクトリが見つからない**: WordPressのパスを確認
3. **ダウンロード失敗**: インターネット接続を確認

### 手動デプロイ

デプロイスクリプトが使えない場合は、手動で行うこともできます：

```bash
# 1. GitHubからダウンロード
wget https://github.com/inosuke680-sys/toppage-2.8.3-/archive/refs/heads/claude/fix-hokkaido-category-loop-01AS3DQzNqAtBrdXLnDbxgSP.zip

# 2. 展開
unzip claude-fix-hokkaido-category-loop-01AS3DQzNqAtBrdXLnDbxgSP.zip

# 3. 既存をバックアップ
sudo mv /var/www/html/wp-content/plugins/umaten-toppage /var/www/html/wp-content/plugins/umaten-toppage.backup

# 4. 新バージョンをコピー
sudo cp -r toppage-2.8.3--claude-fix-hokkaido-category-loop-01AS3DQzNqAtBrdXLnDbxgSP/umaten-toppage-v2.8.3 /var/www/html/wp-content/plugins/umaten-toppage

# 5. 権限設定
sudo chown -R www-data:www-data /var/www/html/wp-content/plugins/umaten-toppage
sudo find /var/www/html/wp-content/plugins/umaten-toppage -type d -exec chmod 755 {} \;
sudo find /var/www/html/wp-content/plugins/umaten-toppage -type f -exec chmod 644 {} \;
```

## 📞 サポート

問題が発生した場合は、GitHubのIssuesでお知らせください。
