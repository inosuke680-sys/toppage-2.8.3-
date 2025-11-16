(function($) {
    'use strict';

    /**
     * Umaten トップページ JS
     */
    const UmatenToppage = {
        version: '2.10.17',
        currentParentSlug: '',
        currentChildSlug: '',
        categoryStack: [], // 無限ループ防止用スタック

        /**
         * 初期化
         */
        init: function() {
            console.log('[v2.10.17] Umaten Toppage initialized');
            this.loadAreaSettings();
            this.bindEvents();
        },

        /**
         * イベントバインド
         */
        bindEvents: function() {
            const self = this;

            // モーダルクローズボタン
            $(document).on('click', '#modal-close-btn, #tag-modal-close-btn', function() {
                self.closeModal('#child-category-modal');
                self.closeModal('#tag-modal');
            });

            // モーダル外側クリックで閉じる
            $(document).on('click', '.umaten-modal', function(e) {
                if ($(e.target).hasClass('umaten-modal')) {
                    self.closeModal('#' + $(this).attr('id'));
                }
            });

            // ESCキーでモーダルを閉じる
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModal('#child-category-modal');
                    self.closeModal('#tag-modal');
                }
            });

            // 子カテゴリカードのクリックイベント（委譲）
            $(document).on('click', '.child-category-item', function(e) {
                e.preventDefault();
                const childSlug = $(this).data('child-slug');
                const hasChildren = $(this).data('has-children');

                console.log('[v2.10.17] 子カテゴリがクリックされました:', childSlug, 'hasChildren:', hasChildren);

                // 無限ループ検出: 親と子が同じ場合
                if (childSlug === self.currentParentSlug) {
                    console.warn('[v2.10.17] 警告: 親カテゴリと子カテゴリが同じです。循環参照を検出しました。');
                    self.currentChildSlug = childSlug;
                    self.closeModal('#child-category-modal');
                    setTimeout(function() {
                        self.loadTags();
                    }, 300);
                    return;
                }

                // スタックに追加して循環参照をチェック
                if (self.categoryStack.includes(childSlug)) {
                    console.error('[v2.10.17] エラー: 無限ループを検出しました。カテゴリスタック:', self.categoryStack);
                    alert('カテゴリの循環参照が検出されました。最初からやり直してください。');
                    self.categoryStack = [];
                    self.closeModal('#child-category-modal');
                    return;
                }

                self.currentChildSlug = childSlug;

                // hasChildrenがtrueの場合でも、スタックの深さをチェック
                if (hasChildren && self.categoryStack.length < 3) {
                    console.log('[v2.10.17] さらに子カテゴリがあるため、次の階層を読み込みます');
                    self.categoryStack.push(childSlug);
                    self.closeModal('#child-category-modal');
                    setTimeout(function() {
                        self.loadChildCategories(childSlug);
                    }, 300);
                } else {
                    // hasChildrenがfalseまたはスタックが深すぎる場合はタグを読み込む
                    if (self.categoryStack.length >= 3) {
                        console.warn('[v2.10.17] カテゴリ階層が深すぎます。タグ選択に進みます。');
                    }
                    self.closeModal('#child-category-modal');
                    setTimeout(function() {
                        self.loadTags();
                    }, 300);
                }
            });
        },

        /**
         * エリア設定をロード
         */
        loadAreaSettings: function() {
            const self = this;

            $.ajax({
                url: umatenToppage.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'umaten_get_area_settings',
                    nonce: umatenToppage.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderAreaTabs(response.data.areas);
                    } else {
                        console.error('エリア設定の取得に失敗しました。');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX エラー:', error);
                }
            });
        },

        /**
         * エリアタブをレンダリング
         */
        renderAreaTabs: function(areas) {
            const self = this;
            const $tabsContainer = $('#area-tabs-container');
            const $contentContainer = $('#area-content-container');

            $tabsContainer.empty();
            $contentContainer.empty();

            let firstPublishedArea = null;

            $.each(areas, function(areaKey, areaData) {
                if (areaData.status === 'hidden') {
                    return;
                }

                const isComingSoon = areaData.status === 'coming_soon';
                const isPublished = areaData.status === 'published';
                const comingSoonText = isComingSoon ? ' <span style="font-size: 11px; opacity: 0.8;">（準備中）</span>' : '';

                const $tab = $('<a>')
                    .attr('href', '#')
                    .addClass('meshimap-area-tab')
                    .attr('data-area', areaKey)
                    .html(areaData.label + comingSoonText);

                if (isComingSoon) {
                    $tab.addClass('coming-soon');
                } else if (isPublished) {
                    if (!firstPublishedArea) {
                        firstPublishedArea = areaKey;
                        $tab.addClass('active');
                    }
                }

                $tabsContainer.append($tab);

                const $content = $('<div>')
                    .addClass('meshimap-area-content')
                    .attr('id', 'area-' + areaKey);

                if (isPublished && areaKey === firstPublishedArea) {
                    $content.addClass('active');
                }

                if (isComingSoon) {
                    $content.html(`
                        <div class="meshimap-coming-soon">
                            <div class="meshimap-coming-soon-icon">&#128679;</div>
                            <h3 class="meshimap-coming-soon-title">${areaData.label}エリア 準備中</h3>
                            <p class="meshimap-coming-soon-text">
                                現在、${areaData.label}エリアの店舗情報を準備中です。<br>
                                近日公開予定ですので、今しばらくお待ちください。
                            </p>
                        </div>
                    `);
                } else if (isPublished) {
                    // すべての公開エリアで親カテゴリカードを表示（北海道方式を全エリアに適用）
                    const defaultImage = 'https://umaten.jp/wp-content/uploads/2025/11/fuji-san-pagoda-view.webp';
                    $content.html(`
                        <div class="meshimap-category-grid">
                            <a href="#" class="meshimap-category-card parent-category-card" data-parent-slug="${areaKey}">
                                <img src="${defaultImage}" alt="${areaData.label}" class="meshimap-category-image">
                                <div class="meshimap-category-overlay">
                                    <div class="meshimap-category-name">${areaData.label}</div>
                                </div>
                            </a>
                        </div>
                    `);
                }

                $contentContainer.append($content);
            });

            // タブクリックイベント
            $tabsContainer.on('click', '.meshimap-area-tab', function(e) {
                e.preventDefault();

                if ($(this).hasClass('coming-soon')) {
                    return;
                }

                $('.meshimap-area-tab').removeClass('active');
                $(this).addClass('active');

                const targetArea = $(this).data('area');
                $('.meshimap-area-content').removeClass('active');
                $('#area-' + targetArea).addClass('active');
            });

            // 親カテゴリカードクリックイベント
            $(document).on('click', '.parent-category-card', function(e) {
                e.preventDefault();
                console.log('[v2.10.17] 親カテゴリがクリックされました');
                const parentSlug = $(this).data('parent-slug');
                // 新しい親カテゴリをクリックしたらスタックをリセット
                self.categoryStack = [];
                self.loadChildCategories(parentSlug);
            });
        },

        /**
         * 子カテゴリを読み込み
         */
        loadChildCategories: function(parentSlug) {
            const self = this;
            self.currentParentSlug = parentSlug;
            console.log('[v2.10.17] 子カテゴリを読み込み中:', parentSlug);

            self.openModal('#child-category-modal');

            $('#child-categories-grid').html(`
                <div class="umaten-loading">
                    <div class="umaten-spinner"></div>
                    <p style="margin-top: 16px; color: #666;">子カテゴリを読み込み中...</p>
                </div>
            `);

            $.ajax({
                url: umatenToppage.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'umaten_get_child_categories',
                    nonce: umatenToppage.nonce,
                    parent_slug: parentSlug
                },
                success: function(response) {
                    console.log('[v2.10.17] 子カテゴリ取得成功:', response);
                    if (response.success) {
                        const categories = response.data.categories;
                        const parentName = response.data.parent_name;

                        $('#modal-title').text(parentName + ' のエリアを選択');
                        self.renderChildCategories(categories);
                    } else {
                        $('#child-categories-grid').html(`
                            <div class="meshimap-coming-soon">
                                <div class="meshimap-coming-soon-icon">&#9888;</div>
                                <h3 class="meshimap-coming-soon-title">子カテゴリが見つかりません</h3>
                                <p class="meshimap-coming-soon-text">${response.data.message || 'エラーが発生しました。'}</p>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX エラー:', error);
                    $('#child-categories-grid').html(`
                        <div class="meshimap-coming-soon">
                            <div class="meshimap-coming-soon-icon">&#9888;</div>
                            <h3 class="meshimap-coming-soon-title">エラー</h3>
                            <p class="meshimap-coming-soon-text">子カテゴリの読み込みに失敗しました。</p>
                        </div>
                    `);
                }
            });
        },

        /**
         * 子カテゴリをレンダリング（画像なし、テキストのみ）
         */
        renderChildCategories: function(categories) {
            const self = this;
            const $grid = $('#child-categories-grid');
            $grid.empty();

            if (categories.length === 0) {
                $grid.html(`
                    <div class="meshimap-coming-soon">
                        <div class="meshimap-coming-soon-icon">&#128679;</div>
                        <h3 class="meshimap-coming-soon-title">子カテゴリがありません</h3>
                        <p class="meshimap-coming-soon-text">このエリアにはまだ子カテゴリが登録されていません。</p>
                    </div>
                `);
                return;
            }

            // グリッドスタイルを変更（画像なしバージョン）
            $grid.removeClass('meshimap-category-grid').addClass('meshimap-tags-grid');

            $.each(categories, function(index, category) {
                const $item = $('<a>')
                    .attr('href', '#')
                    .addClass('meshimap-tag-item child-category-item')
                    .attr('data-child-slug', category.slug)
                    .html(`📍 ${category.name}`);

                // hasChildren属性をセット（バックエンドから提供される場合）
                if (category.hasChildren !== undefined) {
                    $item.attr('data-has-children', category.hasChildren);
                } else {
                    // デフォルトはfalse
                    $item.attr('data-has-children', false);
                }

                $grid.append($item);
            });

            console.log('[v2.10.17] 子カテゴリを', categories.length, '件レンダリングしました');
        },

        /**
         * タグを読み込み
         */
        loadTags: function() {
            const self = this;
            console.log('[v2.10.17] タグを読み込み中');

            self.openModal('#tag-modal');

            $('#tags-grid').html(`
                <div class="umaten-loading">
                    <div class="umaten-spinner"></div>
                    <p style="margin-top: 16px; color: #666;">ジャンルを読み込み中...</p>
                </div>
            `);

            $.ajax({
                url: umatenToppage.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'umaten_get_tags',
                    nonce: umatenToppage.nonce
                },
                success: function(response) {
                    console.log('[v2.10.17] タグ取得成功:', response);
                    if (response.success) {
                        const tags = response.data.tags;
                        $('#tag-modal-title').text('ジャンルを選択');
                        self.renderTags(tags);
                    } else {
                        $('#tags-grid').html(`
                            <div class="meshimap-coming-soon">
                                <div class="meshimap-coming-soon-icon">&#9888;</div>
                                <h3 class="meshimap-coming-soon-title">ジャンルが見つかりません</h3>
                                <p class="meshimap-coming-soon-text">${response.data.message || 'エラーが発生しました。'}</p>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX エラー:', error);
                    $('#tags-grid').html(`
                        <div class="meshimap-coming-soon">
                            <div class="meshimap-coming-soon-icon">&#9888;</div>
                            <h3 class="meshimap-coming-soon-title">エラー</h3>
                            <p class="meshimap-coming-soon-text">ジャンルの読み込みに失敗しました。</p>
                        </div>
                    `);
                }
            });
        },

        /**
         * タグをレンダリング
         */
        renderTags: function(tags) {
            const self = this;
            const $grid = $('#tags-grid');
            $grid.empty();

            // デバッグログ
            console.log('[v2.10.17] renderTags called - Parent:', self.currentParentSlug, ', Child:', self.currentChildSlug, ', Tags:', tags.length);

            if (tags.length === 0) {
                $grid.html(`
                    <div class="meshimap-coming-soon">
                        <div class="meshimap-coming-soon-icon">&#128679;</div>
                        <h3 class="meshimap-coming-soon-title">ジャンルがありません</h3>
                        <p class="meshimap-coming-soon-text">ジャンル（タグ）が登録されていません。</p>
                    </div>
                `);
                return;
            }

            // 【v2.10.17修正】currentParentSlugとcurrentChildSlugの検証
            if (!self.currentParentSlug || !self.currentChildSlug) {
                console.error('[v2.10.17] ERROR: currentParentSlug or currentChildSlug is empty!');
                $grid.html(`
                    <div class="meshimap-coming-soon">
                        <div class="meshimap-coming-soon-icon">&#9888;</div>
                        <h3 class="meshimap-coming-soon-title">エラー</h3>
                        <p class="meshimap-coming-soon-text">親カテゴリまたは子カテゴリが設定されていません。最初からやり直してください。</p>
                    </div>
                `);
                return;
            }

            // 「すべてのジャンル」ボタンを最初に追加
            const allGenresUrl = umatenToppage.siteUrl + '/' + self.currentParentSlug + '/' + self.currentChildSlug + '/';
            console.log('[v2.10.17] All genres URL:', allGenresUrl);

            const $allGenresItem = $('<a>')
                .attr('href', allGenresUrl)
                .addClass('meshimap-tag-item meshimap-tag-item-all')
                .html('🍴 すべてのジャンル')
                .attr('data-tag-slug', '')
                .attr('data-full-url', allGenresUrl);

            $allGenresItem.on('click', function(e) {
                console.log('[v2.10.17] すべてのジャンルクリック - URL:', allGenresUrl);
                // デフォルトのリンク動作を許可（href属性で遷移）
                // e.preventDefault()は削除
            });

            $grid.append($allGenresItem);

            // 各ジャンルを追加
            $.each(tags, function(index, tag) {
                // 【v2.10.17修正】実際のURLを生成してhref属性に設定
                const tagUrl = umatenToppage.siteUrl + '/' + self.currentParentSlug + '/' + self.currentChildSlug + '/' + tag.slug + '/';
                console.log('[v2.10.17] Tag URL generated:', tag.name, '->', tagUrl);

                const $tagItem = $('<a>')
                    .attr('href', tagUrl)
                    .addClass('meshimap-tag-item')
                    .text(tag.name)
                    .attr('data-tag-slug', tag.slug)
                    .attr('data-full-url', tagUrl);

                $tagItem.on('click', function(e) {
                    console.log('[v2.10.17] タグクリック:', tag.name, ', URL:', tagUrl);
                    // デフォルトのリンク動作を許可（href属性で遷移）
                    // e.preventDefault()は削除
                });

                $grid.append($tagItem);
            });

            console.log('[v2.10.17] タグを', tags.length, '件レンダリングしました（すべてのジャンルを含む）');
        },

        /**
         * 最終URLに遷移
         */
        navigateToFinalUrl: function(tagSlug) {
            const self = this;

            const finalUrl = umatenToppage.siteUrl + '/' +
                             self.currentParentSlug + '/' +
                             self.currentChildSlug + '/' +
                             tagSlug + '/';

            console.log('[v2.10.17] 最終URLに遷移:', finalUrl);
            window.location.href = finalUrl;
        },

        /**
         * モーダルを開く
         */
        openModal: function(modalId) {
            console.log('[v2.10.17] モーダルを開く:', modalId);
            $(modalId).addClass('active').css('display', 'flex');
            $('body').css('overflow', 'hidden');
        },

        /**
         * モーダルを閉じる
         */
        closeModal: function(modalId) {
            console.log('[v2.10.17] モーダルを閉じる:', modalId);
            $(modalId).removeClass('active').css('display', 'none');
            $('body').css('overflow', 'auto');
        }
    };

    /**
     * DOM読み込み完了時に初期化
     */
    $(document).ready(function() {
        if ($('.meshimap-wrapper').length > 0) {
            UmatenToppage.init();
        }
    });

})(jQuery);
