(function($) {
    'use strict';

    /**
     * Umaten トップページ JS
     */
    const UmatenToppage = {
        currentParentSlug: '',
        currentChildSlug: '',

        /**
         * 初期化
         */
        init: function() {
            console.log('Umaten Toppage initialized');
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

            // 【v2.10.16】子カテゴリカードのクリックイベント（多階層対応）
            $(document).on('click', '.child-category-item', function(e) {
                e.preventDefault();
                const childSlug = $(this).data('child-slug');
                const childId = $(this).data('child-id');
                const hasChildren = $(this).data('has-children') === '1' || $(this).data('has-children') === 1;

                console.log('[v2.10.16] 子カテゴリがクリックされました:', childSlug, 'hasChildren:', hasChildren);

                self.currentChildSlug = childSlug;
                self.currentChildId = childId;

                // 【v2.10.16】子カテゴリがある場合は再度子カテゴリを読み込み、ない場合はタグを表示
                if (hasChildren) {
                    console.log('[v2.10.16] さらに子カテゴリがあるため、次の階層を読み込みます');
                    self.closeModal('#child-category-modal');
                    setTimeout(function() {
                        self.loadChildCategories(childSlug, false); // 【v2.10.17】カテゴリ階層なのでfalse
                    }, 300);
                } else {
                    console.log('[v2.10.16] 最終階層のため、ジャンル選択へ');
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
                console.log('親カテゴリがクリックされました');
                const parentSlug = $(this).data('parent-slug');
                self.loadChildCategories(parentSlug, true); // 【v2.10.17】地域選択フラグを追加
            });
        },

        /**
         * 子カテゴリを読み込み
         * @param {string} parentSlug - 親カテゴリまたは地域のスラッグ
         * @param {boolean} isRegion - 地域選択かどうか（デフォルト: false）
         */
        loadChildCategories: function(parentSlug, isRegion) {
            const self = this;
            isRegion = isRegion || false; // 【v2.10.17】デフォルトはfalse
            self.currentParentSlug = parentSlug;
            console.log('子カテゴリを読み込み中:', parentSlug, 'isRegion:', isRegion);

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
                    parent_slug: parentSlug,
                    is_region: isRegion ? '1' : '0' // 【v2.10.17】地域選択フラグを追加
                },
                success: function(response) {
                    console.log('子カテゴリ取得成功:', response);
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
                // 【v2.10.16】子カテゴリの有無に応じてアイコンを変更
                const icon = category.has_children ? '📂' : '📍';
                const $item = $('<a>')
                    .attr('href', '#')
                    .addClass('meshimap-tag-item child-category-item')
                    .attr('data-child-slug', category.slug)
                    .attr('data-child-id', category.id)
                    .attr('data-has-children', category.has_children ? '1' : '0')  // 【v2.10.16】子カテゴリの有無
                    .html(`${icon} ${category.name}`);

                $grid.append($item);
            });

            console.log('[v2.10.16] 子カテゴリを', categories.length, '件レンダリングしました');
        },

        /**
         * タグを読み込み
         */
        loadTags: function() {
            const self = this;
            console.log('タグを読み込み中');

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
                    console.log('タグ取得成功:', response);
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
            console.log('[v2.9.0] renderTags called - Parent:', self.currentParentSlug, ', Child:', self.currentChildSlug, ', Tags:', tags.length);

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

            // 【v2.9.0】currentParentSlugとcurrentChildSlugの検証
            if (!self.currentParentSlug || !self.currentChildSlug) {
                console.error('[v2.9.0] ERROR: currentParentSlug or currentChildSlug is empty!',
                    'Parent:', self.currentParentSlug, 'Child:', self.currentChildSlug);
                $grid.html(`
                    <div class="meshimap-coming-soon">
                        <div class="meshimap-coming-soon-icon">&#9888;</div>
                        <h3 class="meshimap-coming-soon-title">エラー</h3>
                        <p class="meshimap-coming-soon-text">親カテゴリまたは子カテゴリが設定されていません。最初からやり直してください。</p>
                    </div>
                `);
                return;
            }

            // 各ジャンルを追加
            $.each(tags, function(index, tag) {
                // 【v2.10.11 DEBUG】変数の状態を確認
                console.log('[v2.10.11 DEBUG] Tag rendering - currentChildId:', self.currentChildId, 'tag.id:', tag.id, 'tag.name:', tag.name);
                console.log('[v2.10.11 DEBUG] searchNonce:', umatenToppage.searchNonce);

                // currentChildIdが未定義の場合はエラーを表示
                if (!self.currentChildId) {
                    console.error('[v2.10.11 ERROR] currentChildId is undefined! Cannot generate search URL.');
                    console.error('[v2.10.11 ERROR] currentChildSlug:', self.currentChildSlug);
                    console.error('[v2.10.11 ERROR] currentParentSlug:', self.currentParentSlug);
                    return; // このタグをスキップ
                }

                // 【v2.10.13】検索ウィジェットURLを直接生成（rewrite rulesに依存しない確実な方法）
                // umaten_category (子カテゴリID) + umaten_tag (タグID) で検索URLを構築
                // 【重要】umaten_keyword= パラメータを追加（空でも必須）
                const searchUrl = umatenToppage.siteUrl + '/?umaten_category=' + self.currentChildId +
                                  '&umaten_tag=' + tag.id +
                                  '&umaten_keyword=' +
                                  '&umaten_search=1' +
                                  '&umaten_search_nonce=' + umatenToppage.searchNonce;

                console.log('[v2.10.13] 検索URL生成:', tag.name, '(カテゴリID:', self.currentChildId, ', タグID:', tag.id, ') ->', searchUrl);

                const $tagItem = $('<a>')
                    .attr('href', searchUrl)
                    .addClass('meshimap-tag-item')
                    .text(tag.name)
                    .attr('data-tag-id', tag.id)
                    .attr('data-tag-slug', tag.slug);

                $tagItem.on('click', function(e) {
                    console.log('[v2.10.11] タグクリック:', tag.name, ', 検索URL:', searchUrl);
                    // デフォルトのリンク動作を許可（href属性で遷移）
                });

                $grid.append($tagItem);
            });

            console.log('[v2.9.0] タグを', tags.length, '件レンダリングしました');
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

            console.log('最終URLに遷移:', finalUrl);
            window.location.href = finalUrl;
        },

        /**
         * モーダルを開く
         */
        openModal: function(modalId) {
            console.log('モーダルを開く:', modalId);
            $(modalId).addClass('active').css('display', 'flex');
            $('body').css('overflow', 'hidden');
        },

        /**
         * モーダルを閉じる
         */
        closeModal: function(modalId) {
            console.log('モーダルを閉じる:', modalId);
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
