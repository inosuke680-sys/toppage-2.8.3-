# Umaten トップページプラグイン v2.8.3 - SEO最適化版

## バージョン情報

- **現在のバージョン**: v2.8.3
- **ベースバージョン**: v2.8.2
- **リリース日**: 2025年11月15日

## v2.8.3の変更点

**SEOに強い実装**：アイキャッチ画像を設定してSEO効果を最大化しつつ、記事ページでは完全に非表示にします。

### 記事ページでのアイキャッチ完全非表示（3段階フィルター）

v2.8.2では`post_thumbnail_html`フィルターのみでしたが、SWELLテーマなどが独自の方法でアイキャッチを取得する場合に対応するため、3段階のフィルターで完全にブロックします。

```
記事ページでのアイキャッチ取得をブロック:

レベル1: post_thumbnail_html
  → アイキャッチHTMLを空文字に

レベル2: has_post_thumbnail
  → has_post_thumbnail()をfalseに

レベル3: get_post_metadata (_thumbnail_id)
  → get_post_meta()で_thumbnail_idを空に
```

テーマがどんな方法でアイキャッチを取得しようとしても、記事ページでは常に「アイキャッチなし」として扱われます。

### SEO効果を維持

アイキャッチ画像は設定されているため、以下のSEO効果があります：

- **OGP（og:image）**: TwitterやFacebookなどでシェア時に画像が表示される
- **構造化データ（Schema.org）**: 検索エンジンが記事の画像を認識
- **Google検索結果**: 検索結果に画像が表示される
- **一覧ページ**: カテゴリ、タグ、アーカイブページでサムネイル表示

## インストール方法

### 自動デプロイ

```bash
curl -o /tmp/deploy-v2.8.3.sh https://raw.githubusercontent.com/inosuke680-sys/toppage-WP-INSIDE-/claude/plugin-v2.6.0-upgrade-015K3j6rBvErzVhU5LxoG5Vj/deploy-production-v2.8.3.sh
chmod +x /tmp/deploy-v2.8.3.sh
sudo /tmp/deploy-v2.8.3.sh
```

### プラグイン有効化

WordPress管理画面 → プラグイン:
1. 既存のUmatenプラグイン（v2.8.2など）を無効化
2. 「Umaten トップページ v2.8.3」を有効化
3. **重要**: 設定 → パーマリンク → 「変更を保存」

### 既存投稿へのアイキャッチ設定

v2.8.2と同じスクリプトを使用できます：

```bash
cd /home/kusanagi/45515055731ac663c7c3ad4c/DocumentRoot && \
curl -o bulk-set-hero-images-v2.8.2.php https://raw.githubusercontent.com/inosuke680-sys/toppage-WP-INSIDE-/claude/plugin-v2.6.0-upgrade-015K3j6rBvErzVhU5LxoG5Vj/bulk-set-hero-images-v2.8.2.php && \
php bulk-set-hero-images-v2.8.2.php
```

## 動作確認

### ✓ 確認1: 一覧ページでアイキャッチが表示される

カテゴリページやタグページで、アイキャッチ画像が表示されることを確認してください。

### ✓ 確認2: 記事ページでアイキャッチが重複しない

記事ページで、本文中のヒーロー画像のみが表示され、アイキャッチ画像が重複表示されないことを確認してください。

### ✓ 確認3: SEO要素が正常

- ブラウザの開発者ツールで`<meta property="og:image">`タグにアイキャッチ画像のURLが設定されていることを確認
- TwitterやFacebookのシェアプレビューツールで画像が表示されることを確認

## 技術仕様

### 3段階フィルターの仕組み

```php
// レベル1: HTMLを空文字にする
add_filter('post_thumbnail_html', 'hide_thumbnail_on_single', 10, 5);

// レベル2: has_post_thumbnail()をfalseにする
add_filter('has_post_thumbnail', 'disable_thumbnail_check_on_single', 10, 2);

// レベル3: get_post_metadata()で_thumbnail_idを空にする
add_filter('get_post_metadata', 'hide_thumbnail_id_on_single', 10, 4);
```

これにより、SWELLテーマが以下のどの方法でアイキャッチを取得しようとしても、記事ページでは空になります：

- `the_post_thumbnail()` → レベル1でブロック
- `has_post_thumbnail()` → レベル2でブロック
- `get_post_thumbnail_id()` → レベル3でブロック
- `get_post_meta($post_id, '_thumbnail_id')` → レベル3でブロック

### SEO要素は影響を受けない

以下のSEO要素は正常に動作します：

- OGPタグの生成（多くのプラグインは`wp_head`アクション内で生成するため、フィルターの影響を受けない）
- Schema.org構造化データ（同様に`wp_head`で生成）
- XMLサイトマップ（管理画面やサイトマップ生成時は`is_single()`がfalseのため）

## トラブルシューティング

### 記事ページでアイキャッチが表示されてしまう場合

v2.8.3では3段階フィルターで完全にブロックしていますが、もし表示される場合は：

1. テーマが独自のカスタムフィールドでアイキャッチを保存している可能性があります
2. キャッシュをクリアしてください（ブラウザ、WordPress、CDN）
3. デバッグログを確認してください

### OGP画像が表示されない場合

1. 使用しているSEOプラグイン（Yoast、All in One SEO等）の設定を確認
2. テーマがOGPタグを生成している場合、設定を確認
3. ブラウザの開発者ツールで`<meta property="og:image">`タグが存在するか確認

## v2.8.2からの変更点

- v2.8.2: `post_thumbnail_html`フィルターのみ
- **v2.8.3**: 3段階フィルター（post_thumbnail_html + has_post_thumbnail + get_post_metadata）

より確実に記事ページでアイキャッチを非表示にできます。

## v2.8.3の設計思想

**SEO効果を最大化しつつ、重複を完全に防止**

- アイキャッチを設定してSEO効果を維持（OGP、Schema.org、検索結果）
- 記事ページでは3段階フィルターで完全にブロック（重複回避）
- v2.8.2の安定性を維持したシンプル実装
- SWELLテーマとの完全な互換性

## ライセンス

GPL v2 or later

## 開発者

Umaten - https://umaten.jp
