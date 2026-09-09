// assets/js/family-tree.js

(function() {
    'use strict';

    // Хранилище состояний зума для каждого контейнера
    const treeStates = new Map();

    initializeAllFamilyTrees();

    function initializeAllFamilyTrees() {
        const containers = document.querySelectorAll('.f3[data-root-id]');
        if (containers.length === 0) return;
        containers.forEach(function(container) {
            initializeFamilyTree(container);
        });
    }

    function initializeFamilyTree(container) {
        if (!container) return;

        let rootId = container.dataset.rootId;
        let groupId = container.dataset.groupId || 0;

        if (!rootId && typeof familyTreeAjax !== 'undefined' && familyTreeAjax.root_member_id) {
            rootId = familyTreeAjax.root_member_id;
        }

        if (!rootId) {
            container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Не указан корневой элемент древа. Пожалуйста, установите корневой элемент в настройках.</p>';
            return;
        }

        loadFamilyTreeData(rootId, container, groupId);
    }

    function loadFamilyTreeData(rootId, container, groupId) {
        if (typeof familyTreeAjax === 'undefined' || !familyTreeAjax.ajaxurl || !familyTreeAjax.nonce) {
            container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Ошибка конфигурации AJAX</p>';
            return;
        }

        container.innerHTML = '<div style="text-align: center; padding: 50px;"><div class="spinner"></div><p>Загрузка семейного древа...</p></div>';

        const formData = new FormData();
        formData.append('action', 'get_family_tree_data');
        formData.append('root_id', rootId);
        formData.append('group_id', groupId);
        formData.append('nonce', familyTreeAjax.nonce);

        fetch(familyTreeAjax.ajaxurl, { method: 'POST', body: formData })
            .then(function(response) {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(function(data) {
                if (data && data.nodes && data.nodes.length > 0 && data.links) {
                    renderFamilyTreeWithLibrary(data, container, rootId);
                } else {
                    container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Нет данных для отображения</p>';
                }
            })
            .catch(function(error) {
                console.error('Ошибка загрузки данных:', error);
                container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Ошибка загрузки данных: ' + error.message + '</p>';
            });
    }

    function renderFamilyTreeWithLibrary(data, container, rootId) {
        try {
            if (typeof d3 === 'undefined' || typeof f3 === 'undefined') {
                container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Не загружены необходимые библиотеки</p>';
                return;
            }

            container.innerHTML = '';

            const f3Data = convertDataToF3Format(data);

            const store = f3.createStore({
                data: f3Data,
                node_separation: 250,
                level_separation: 150
            });

            const view = f3.d3AnimationView({
                store: store,
                cont: container
            });

            const svgElement = container.querySelector('svg');
            if (svgElement) {
                svgElement.setAttribute('width', '100%');
                svgElement.setAttribute('height', '600');
            }

            const Card = f3.elements.Card({
                store: store,
                svg: view.svg,
                card_dim: {
                    w: (typeof familyTreeAjax !== 'undefined' && familyTreeAjax.card_width) ? parseInt(familyTreeAjax.card_width, 10) : 220,
                    h: (typeof familyTreeAjax !== 'undefined' && familyTreeAjax.card_height) ? parseInt(familyTreeAjax.card_height, 10) : 100,
                    text_x: 80,
                    text_y: 15,
                    img_w: 60,
                    img_h: 60,
                    img_x: 10,
                    img_y: Math.max(0, Math.round(((typeof familyTreeAjax !== 'undefined' && familyTreeAjax.card_height) ? parseInt(familyTreeAjax.card_height, 10) : 100) / 2 - 30))
                },
                card_display: [
                    function(d) { return d.data["first name"] || "Неизвестно"; },
                    function(d) { return d.data["middle name"] || ""; },
                    function(d) { return d.data["last name"] || ""; },
                    function(d) { return d.data["maiden name"] ? "(" + d.data["maiden name"] + ")" : ""; },
                    function(d) {
                        var birthday = d.data["birthday"] || "";
                        var death = d.data["death"] || "";
                        if (death === "Умер" || death === "Умерла" || death) {
                            return birthday ? birthday + " - " + death : "? - " + death;
                        }
                        return birthday || "";
                    }
                ],
                mini_tree: true,
                link_break: true
            });

            view.setCard(Card);

            store.setOnUpdate(function(props) {
                view.update(props || {});
                setTimeout(function() {
                    addLinkIconsToCards(data.nodes, container);
                    attachClickHandlers(container, store);
                }, 50);
            });

            store.update.tree({ initial: true });

            var centerOnNodeId = container.dataset.centerOn;
            if (centerOnNodeId && typeof store.centerOnNode === 'function') {
                var attemptCenter = function() {
                    if (store.getNodeById && store.getNodeById(centerOnNodeId)) {
                        try { store.centerOnNode(centerOnNodeId); } catch (e) {}
                    } else {
                        setTimeout(attemptCenter, 200);
                    }
                };
                setTimeout(attemptCenter, 500);
            }

            // Инициализация зума
            var disableWheelZoom = (typeof familyTreeAjax !== 'undefined' && familyTreeAjax.disable_wheel_zoom) ? parseInt(familyTreeAjax.disable_wheel_zoom, 10) === 1 : false;
            var zoomBehavior = d3.zoom()
                .scaleExtent([0.1, 4.0])
                .filter(function(event) {
                    if (disableWheelZoom && event.type === 'wheel') {
                        return false;
                    }
                    return true;
                })
                .on('zoom', function(event) {
                    d3.select(container).select('svg').select('g.view').attr('transform', event.transform);
                    if (treeStates.has(container)) {
                        treeStates.get(container).zoomK = event.transform.k;
                    }
                });

            setTimeout(function() {
                d3.select(container).select('svg').call(zoomBehavior);
            }, 100);

            // Сохраняем состояние дерева
            treeStates.set(container, {
                store: store,
                rootId: rootId,
                zoomBehavior: zoomBehavior,
                zoomK: 1,
                expanded: false
            });

            // Создаём тулбар
            createTreeToolbar(container);

        } catch (error) {
            console.error('Ошибка отображения древа:', error);
            container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Ошибка отображения древа: ' + error.message + '</p>';
        }
    }

    // ===========================
    // ТУЛБАР
    // ===========================

    function createTreeToolbar(container) {
        var toolbar = document.createElement('div');
        var toolbarPosition = (typeof familyTreeAjax !== 'undefined' && familyTreeAjax.toolbar_position) ? familyTreeAjax.toolbar_position : 'bottom-right';
        toolbar.className = 'ft-tree-toolbar ft-pos-' + toolbarPosition;

        // 1. Кнопка "Поделиться"
        var shareWrap = document.createElement('div');
        shareWrap.className = 'ft-toolbar-share-wrap';

        var shareBtn = document.createElement('button');
        shareBtn.className = 'ft-toolbar-btn ft-toolbar-share';
        shareBtn.title = 'Поделиться';
        shareBtn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="12" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="18" cy="18" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>';

        var shareDropdown = document.createElement('div');
        shareDropdown.className = 'ft-share-dropdown';

        var pageUrl = encodeURIComponent(window.location.href);
        var pageTitle = encodeURIComponent(document.title);

        var shareLinks = [
            { label: 'ВКонтакте', url: 'https://vk.com/share.php?url=' + pageUrl, icon: 'VK' },
            { label: 'Telegram', url: 'https://t.me/share/url?url=' + pageUrl + '&text=' + pageTitle, icon: 'TG' },
            { label: 'Одноклассники', url: 'https://ok.ru/dk?st.cmd=addShare&st.surl=' + pageUrl, icon: 'OK' },
            { label: 'WhatsApp', url: 'https://api.whatsapp.com/send?text=' + pageTitle + '%20' + pageUrl, icon: 'WA' },
            { label: 'Twitter / X', url: 'https://twitter.com/intent/tweet?url=' + pageUrl + '&text=' + pageTitle, icon: 'X' }
        ];

        shareLinks.forEach(function(item) {
            var link = document.createElement('a');
            link.href = item.url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'ft-share-item';
            link.innerHTML = '<span class="ft-share-icon">' + item.icon + '</span>' + item.label;
            shareDropdown.appendChild(link);
        });

        var copyBtn = document.createElement('button');
        copyBtn.className = 'ft-share-item ft-copy-link';
        copyBtn.innerHTML = '<span class="ft-share-icon">&#128203;</span>Копировать ссылку';
        copyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navigator.clipboard.writeText(window.location.href).then(function() {
                copyBtn.innerHTML = '<span class="ft-share-icon">&#10003;</span>Скопировано!';
                setTimeout(function() {
                    copyBtn.innerHTML = '<span class="ft-share-icon">&#128203;</span>Копировать ссылку';
                }, 2000);
            }).catch(function() {
                var tmp = document.createElement('input');
                tmp.value = window.location.href;
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                document.body.removeChild(tmp);
                copyBtn.innerHTML = '<span class="ft-share-icon">&#10003;</span>Скопировано!';
                setTimeout(function() {
                    copyBtn.innerHTML = '<span class="ft-share-icon">&#128203;</span>Копировать ссылку';
                }, 2000);
            });
        });
        shareDropdown.appendChild(copyBtn);

        shareBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var isVisible = shareDropdown.classList.contains('ft-show');
            closeAllShareDropdowns();
            if (!isVisible) {
                shareDropdown.classList.add('ft-show');
            }
        });

        shareWrap.appendChild(shareBtn);
        shareWrap.appendChild(shareDropdown);
        toolbar.appendChild(shareWrap);

        // 2. Кнопка "+"
        var zoomInBtn = document.createElement('button');
        zoomInBtn.className = 'ft-toolbar-btn ft-zoom-in';
        zoomInBtn.title = 'Увеличить';
        zoomInBtn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
        zoomInBtn.addEventListener('click', function() {
            zoomTree(container, 1.3);
        });
        toolbar.appendChild(zoomInBtn);

        // 3. Кнопка "-"
        var zoomOutBtn = document.createElement('button');
        zoomOutBtn.className = 'ft-toolbar-btn ft-zoom-out';
        zoomOutBtn.title = 'Уменьшить';
        zoomOutBtn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>';
        zoomOutBtn.addEventListener('click', function() {
            zoomTree(container, 1 / 1.3);
        });
        toolbar.appendChild(zoomOutBtn);

        // 4. Кнопка "Развернуть/Свернуть"
        var expandBtn = document.createElement('button');
        expandBtn.className = 'ft-toolbar-btn ft-expand';
        expandBtn.title = 'Развернуть древо';
        expandBtn.innerHTML = '<svg class="ft-expand-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
        expandBtn.innerHTML += '<svg class="ft-collapse-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
        expandBtn.addEventListener('click', function() {
            toggleExpandTree(container);
        });
        toolbar.appendChild(expandBtn);

        // 5. Кнопка "Сохранить как изображение" (фотоаппарат)
        var screenshotBtn = document.createElement('button');
        screenshotBtn.className = 'ft-toolbar-btn ft-screenshot';
        screenshotBtn.title = 'Сохранить как изображение';
        screenshotBtn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>';
        screenshotBtn.addEventListener('click', function() {
            saveTreeAsImage(container);
        });
        toolbar.appendChild(screenshotBtn);

        container.appendChild(toolbar);

        // Закрытие меню шаринга при клике вне
        document.addEventListener('click', function(e) {
            if (!shareWrap.contains(e.target)) {
                shareDropdown.classList.remove('ft-show');
            }
        });
    }

    function closeAllShareDropdowns() {
        var allDropdowns = document.querySelectorAll('.ft-share-dropdown.ft-show');
        allDropdowns.forEach(function(d) { d.classList.remove('ft-show'); });
    }

    // ===========================
    // 1. ШАРИНГ
    // ===========================
    // (обработчики уже встроены в createTreeToolbar)

    // ===========================
    // 2. ЗУМ +/-
    // ===========================

    function zoomTree(container, factor) {
        var state = treeStates.get(container);
        if (!state) return;

        var svg = d3.select(container).select('svg');
        var currentK = state.zoomK || 1;
        var newK = currentK * factor;

        // Ограничиваем диапазон
        newK = Math.max(0.1, Math.min(4.0, newK));

        svg.call(state.zoomBehavior.scaleTo, newK);
    }

    // ===========================
    // 3. РАЗВЕРНУТЬ / СВЕРНУТЬ
    // ===========================

    function toggleExpandTree(container) {
        var state = treeStates.get(container);
        if (!state) return;

        var expandBtn = container.querySelector('.ft-expand');
        var expandIcon = expandBtn.querySelector('.ft-expand-icon');
        var collapseIcon = expandBtn.querySelector('.ft-collapse-icon');

        if (state.expanded) {
            // Свернуть — центрируем на корневой персоне
            state.expanded = false;
            expandIcon.style.display = '';
            collapseIcon.style.display = 'none';
            expandBtn.title = 'Развернуть древо';

            if (state.store.centerOnNode && typeof state.store.centerOnNode === 'function') {
                try {
                    state.store.update.tree({ id: state.rootId, animated: true });
                    setTimeout(function() {
                        try { state.store.centerOnNode(state.rootId); } catch(e) {}
                    }, 300);
                } catch(e) {
                    try { state.store.centerOnNode(state.rootId); } catch(e2) {}
                }
            }

            // Сбрасываем зум к 1
            var svg = d3.select(container).select('svg');
            svg.call(state.zoomBehavior.scaleTo, 1);

        } else {
            // Развернуть — показать всё дерево целиком
            state.expanded = true;
            expandIcon.style.display = 'none';
            collapseIcon.style.display = '';
            expandBtn.title = 'Свернуть к корневой персоне';

            fitTreeToView(container);
        }
    }

    function fitTreeToView(container) {
        var state = treeStates.get(container);
        if (!state) return;

        var svgEl = container.querySelector('svg');
        var viewGroup = svgEl ? svgEl.querySelector('g.view') : null;
        if (!svgEl || !viewGroup) return;

        var bbox = viewGroup.getBBox();
        if (bbox.width === 0 || bbox.height === 0) return;

        var svgRect = svgEl.getBoundingClientRect();
        var svgWidth = svgRect.width || svgEl.clientWidth || 800;
        var svgHeight = svgRect.height || svgEl.clientHeight || 600;

        var padding = 40;
        var scaleX = (svgWidth - padding * 2) / bbox.width;
        var scaleY = (svgHeight - padding * 2) / bbox.height;
        var newK = Math.min(scaleX, scaleY, 4.0);
        newK = Math.max(0.1, newK);

        var centerX = bbox.x + bbox.width / 2;
        var centerY = bbox.y + bbox.height / 2;

        var tx = svgWidth / 2 - newK * centerX;
        var ty = svgHeight / 2 - newK * centerY;

        var transform = d3.zoomIdentity.translate(tx, ty).scale(newK);

        var svg = d3.select(container).select('svg');
        svg.call(state.zoomBehavior.transform, transform);
    }

    // ===========================
    // 4. СОХРАНИТЬ КАК ИЗОБРАЖЕНИЕ
    // ===========================

    function saveTreeAsImage(container) {
        var svgEl = container.querySelector('svg');
        if (!svgEl) {
            alert('Дерево не найдено');
            return;
        }

        var btn = container.querySelector('.ft-screenshot');
        if (btn) btn.classList.add('ft-loading');

        // Высота шапки с логотипом (резервируется сверху, дерево не перекрывается)
        var HEADER_H = 48;

        try {
            var clonedSvg = svgEl.cloneNode(true);

            // Используем фактический drawable viewport без учёта шапки
            var drawableW = container.clientWidth || svgEl.clientWidth || 800;
            var drawableH = container.clientHeight || svgEl.clientHeight || 600;

            // Сдвигаем всё содержимое дерева вниз на высоту шапки,
            // чтобы шапка никогда не заползала на дерево.
            // Оборачиваем g.view в новый <g>, сохраняя текущий zoom-трансформ.
            var contentGroup = clonedSvg.querySelector('g.view');
            if (contentGroup && contentGroup.parentNode) {
                var wrapper = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                wrapper.setAttribute('transform', 'translate(0,' + HEADER_H + ')');
                contentGroup.parentNode.insertBefore(wrapper, contentGroup);
                wrapper.appendChild(contentGroup);
            }

            // Полная высота картинки = шапка + область дерева
            var fullW = drawableW;
            var fullH = drawableH + HEADER_H;

            clonedSvg.setAttribute('width', fullW);
            clonedSvg.setAttribute('height', fullH);
            clonedSvg.setAttribute('viewBox', '0 0 ' + fullW + ' ' + fullH);
            clonedSvg.removeAttribute('class');
            clonedSvg.removeAttribute('style');

            // Фон на всю картинку
            var bgFill = getComputedStyle(svgEl).backgroundColor;
            if (!bgFill || bgFill === 'rgba(0, 0, 0, 0)' || bgFill === 'transparent') {
                bgFill = '#f2f2f2';
            }
            var bgRect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            bgRect.setAttribute('x', '0');
            bgRect.setAttribute('y', '0');
            bgRect.setAttribute('width', fullW);
            bgRect.setAttribute('height', fullH);
            bgRect.setAttribute('fill', bgFill);
            clonedSvg.insertBefore(bgRect, clonedSvg.firstChild);

            // Внедряем критичные стили (f3 задаёт цвета через CSS-классы)
            var styleEl = document.createElementNS('http://www.w3.org/2000/svg', 'style');
            styleEl.textContent = buildScreenshotStyles(svgEl);
            clonedSvg.insertBefore(styleEl, clonedSvg.firstChild);

            // Встраиваем изображения как data URLs и отрисовываем canvas
            inlineImageDataUrls(clonedSvg).then(function() {
                buildScreenshotCanvas(clonedSvg, svgEl, fullW, fullH, HEADER_H, btn);
            });

        } catch (e) {
            console.error('Ошибка скриншота:', e);
            if (btn) btn.classList.remove('ft-loading');
            alert('Ошибка при создании скриншота: ' + e.message);
        }
    }

    function buildScreenshotStyles(svgEl) {
        function cs(selector, prop, fallback) {
            var el = svgEl.querySelector(selector);
            if (!el) return fallback;
            var val = getComputedStyle(el)[prop];
            return (val && val !== 'none') ? val : fallback;
        }

        var maleFill = cs('rect.card-male', 'fill', '#ADD8E6');
        var femaleFill = cs('rect.card-female', 'fill', '#FFB6C1');
        var maleBody = cs('.card-male .card-body-rect', 'fill', '#ffffff');
        var femaleBody = cs('.card-female .card-body-rect', 'fill', '#ffffff');
        var maleOutline = cs('.card-male .card-outline', 'stroke', maleFill);
        var femaleOutline = cs('.card-female .card-outline', 'stroke', femaleFill);
        var textColor = cs('.card-body text', 'fill', '#3b5560');
        var textFontFamily = cs('.card-body text', 'fontFamily', 'Open Sans, sans-serif');
        var textFontSize = cs('.card-body text', 'fontSize', '12px');
        var spouseStroke = cs('.link.spouse', 'stroke', '#ff6b6b');
        var childStroke = cs('.link.child', 'stroke', '#4ecdc4');

        return [
            'svg.main_svg{background-color:' + cs('svg.main_svg', 'backgroundColor', '#f2f2f2') + ';}',
            'rect.card-male{fill:' + maleFill + ';}',
            'rect.card-female{fill:' + femaleFill + ';}',
            '.card-male .card-body-rect,.card-male .text-overflow-mask{fill:' + maleBody + ';}',
            '.card-female .card-body-rect,.card-female .text-overflow-mask{fill:' + femaleBody + ';}',
            '.card-male .card-outline{stroke:' + maleOutline + ';stroke-width:3;}',
            '.card-female .card-outline{stroke:' + femaleOutline + ';stroke-width:3;}',
            '.card-body-rect{rx:8;ry:8;}',
            'text{fill:' + textColor + ';font-family:' + textFontFamily + ';font-weight:600;}',
            '.card-body text{font-size:' + textFontSize + ';}',
            '.link.spouse{stroke:' + spouseStroke + ';stroke-width:3;stroke-opacity:0.7;}',
            '.link.child{stroke:' + childStroke + ';stroke-width:2;stroke-opacity:0.7;}',
            '.card text{pointer-events:none;}'
        ].join('');
    }

    function inlineImageDataUrls(cloneSvg) {
        return new Promise(function(resolve) {
            var images = cloneSvg.querySelectorAll('image');
            var pending = images.length;
            if (pending === 0) { resolve(); return; }

            function done() { pending--; if (pending <= 0) resolve(); }

            images.forEach(function(img) {
                var href = img.getAttributeNS('http://www.w3.org/1999/xlink', 'href') ||
                           img.getAttribute('href') ||
                           img.getAttribute('xlink:href');
                if (!href || href.indexOf('data:') === 0) { done(); return; }

                var absUrl;
                try {
                    absUrl = new URL(href, (familyTreeAjax && familyTreeAjax.site_url) ? familyTreeAjax.site_url : window.location.origin).href;
                } catch (e) { done(); return; }

                fetch(absUrl)
                    .then(function(resp) { if (!resp.ok) throw new Error(';'); return resp.blob(); })
                    .then(function(blob) {
                        return new Promise(function(res2) {
                            var fr = new FileReader();
                            fr.onload = function() { res2(fr.result); };
                            fr.onerror = function() { res2(null); };
                            fr.readAsDataURL(blob);
                        });
                    })
                    .then(function(dataUrl) {
                        if (dataUrl) {
                            img.removeAttributeNS('http://www.w3.org/1999/xlink', 'href');
                            img.setAttribute('href', dataUrl);
                        }
                        done();
                    })
                    .catch(function() { done(); });
            });
        });
    }
function buildScreenshotCanvas(clonedSvg, svgEl, fullW, fullH, headerH, btn) {
        try {
            var serializer = new XMLSerializer();
            var svgString = serializer.serializeToString(clonedSvg);
            var svgBlob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
            var url = URL.createObjectURL(svgBlob);

            var canvas = document.createElement('canvas');
            var scale = 2;
            canvas.width = fullW * scale;
            canvas.height = fullH * scale;
            var ctx = canvas.getContext('2d');
            ctx.scale(scale, scale);

            var img = new Image();
            img.onload = function() {
                URL.revokeObjectURL(url);

                // Фон на всю картинку (дерево + шапка)
                ctx.fillStyle = getComputedStyle(svgEl).backgroundColor || '#f2f2f2';
                ctx.fillRect(0, 0, fullW, fullH);

                // Рисуем склонированный SVG (дерево уже сдвинуто вниз на headerH)
                ctx.drawImage(img, 0, 0, fullW, fullH);

                // Шапка с логотипом рисуется ПОВЕРХ — но дерево уже начинается ниже,
                // поэтому шапка не заползает на него
                drawWatermark(ctx, fullW, headerH, function() {
                    canvas.toBlob(function(blob) {
                        if (btn) btn.classList.remove('ft-loading');
                        if (!blob) { alert('Не удалось создать изображение'); return; }
                        var link = document.createElement('a');
                        link.download = 'family-tree-' + Date.now() + '.png';
                        link.href = URL.createObjectURL(blob);
                        link.click();
                        setTimeout(function() { URL.revokeObjectURL(link.href); }, 100);
                    }, 'image/png');
                });
            };

            img.onerror = function() {
                URL.revokeObjectURL(url);
                if (btn) btn.classList.remove('ft-loading');
                alert('Ошибка при загрузке изображения дерева');
            };

            img.src = url;
        } catch (e) {
            console.error('Ошибка при создании canvas:', e);
            if (btn) btn.classList.remove('ft-loading');
            alert('Ошибка при создании скриншота: ' + e.message);
        }
    }

    function drawWatermark(ctx, width, height, callback) {
        var logoUrl = (typeof familyTreeAjax !== 'undefined' && familyTreeAjax.logo_url) ? familyTreeAjax.logo_url : '';
        var siteUrl = (typeof familyTreeAjax !== 'undefined' && familyTreeAjax.site_url) ? familyTreeAjax.site_url : '';

        var watermarkH = 48;
        var yEnd = watermarkH;

        // Полупрозрачная полоса сверху
        ctx.fillStyle = 'rgba(255,255,255,0.88)';
        ctx.fillRect(0, 0, width, watermarkH);

        // Тонкая линия-разделитель
        ctx.strokeStyle = '#dcdcde';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(0, yEnd + 0.5);
        ctx.lineTo(width, yEnd + 0.5);
        ctx.stroke();

        if (logoUrl) {
            var logoImg = new Image();
            logoImg.crossOrigin = 'anonymous';
            logoImg.onload = function() {
                var logoH = 34;
                var logoW = (logoImg.width / logoImg.height) * logoH;
                var logoX = 14;
                var logoY = (watermarkH - logoH) / 2;
                ctx.drawImage(logoImg, logoX, logoY, logoW, logoH);

                if (siteUrl) {
                    ctx.fillStyle = '#8A857A';
                    ctx.font = '12px Arial, sans-serif';
                    ctx.textAlign = 'right';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(siteUrl.replace(/^https?:\/\//, ''), width - 14, watermarkH / 2);
                }
                if (callback) callback();
            };
            logoImg.onerror = function() {
                drawTextWatermark(ctx, width, watermarkH, siteUrl);
                if (callback) callback();
            };
            logoImg.src = logoUrl;
        } else {
            drawTextWatermark(ctx, width, watermarkH, siteUrl);
            if (callback) callback();
        }
    }

    function drawTextWatermark(ctx, width, watermarkH, siteUrl) {
        ctx.fillStyle = '#7B872C';
        ctx.font = 'bold 15px Arial, sans-serif';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText('Genius Family Tree', 14, watermarkH / 2);

        if (siteUrl) {
            ctx.fillStyle = '#8A857A';
            ctx.font = '12px Arial, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(siteUrl.replace(/^https?:\/\//, ''), width - 14, watermarkH / 2);
        }
    }

    // ===========================
    // КЛИКИ ПО КАРТОЧКАМ
    // ===========================

    function attachClickHandlers(container, store) {
        container.querySelectorAll('.card').forEach(function(card) {
            card.addEventListener('click', function(event) {
                event.stopPropagation();
                var nodeId = this.getAttribute('data-id');
                if (nodeId) {
                    store.update.tree({ id: nodeId, animated: true });
                }
            });
        });
    }

    // ===========================
    // ИКОНКИ ССЫЛОК НА КАРТОЧКАХ
    // ===========================

    function addLinkIconsToCards(nodesData, container) {
        try {
            nodesData.forEach(function(node) {
                var personId = node.id;
                var personUrl = node.permalink;
                if (!personId || !personUrl) return;

                var cardElement = container.querySelector('.card[data-id="' + personId + '"]');
                if (!cardElement) return;

                if (cardElement.querySelector('.person-link-icon-container')) return;

                try {
                    var cardBody = cardElement.querySelector('.card-body');
                    if (!cardBody) return;

                    var iconContainer = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                    iconContainer.setAttribute('class', 'person-link-icon-container');

                    var linkRect = cardBody.querySelector('.card-body-rect');
                    var linkCardWidth = linkRect ? (parseFloat(linkRect.getAttribute('width')) || 220) : 220;
                    var linkIconSize = 20;
                    var linkIconMargin = 5;
                    var linkIconX = Math.max(linkIconMargin, linkCardWidth - linkIconSize - linkIconMargin);
                    iconContainer.setAttribute('transform', 'translate(' + linkIconX + ', ' + linkIconMargin + ')');
                    iconContainer.style.cursor = 'pointer';

                    iconContainer.addEventListener('click', function(event) {
                        event.stopPropagation();
                        window.open(personUrl, '_blank');
                    });

                    var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('cx', '10');
                    circle.setAttribute('cy', '10');
                    circle.setAttribute('r', '9');
                    circle.setAttribute('fill', 'white');
                    circle.setAttribute('stroke', '#ccc');
                    circle.setAttribute('stroke-width', '1');

                    var iconPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    iconPath.setAttribute('d', 'M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z');
                    iconPath.setAttribute('fill', '#000000');
                    iconPath.setAttribute('transform', 'scale(0.5) translate(10, 10)');

                    var titleElement = document.createElementNS('http://www.w3.org/2000/svg', 'title');
                    titleElement.textContent = 'Перейти к странице персоны';

                    iconContainer.appendChild(circle);
                    iconContainer.appendChild(iconPath);
                    iconContainer.appendChild(titleElement);
                    cardBody.appendChild(iconContainer);
                } catch (e) {}
            });
        } catch (error) {
            console.error('Ошибка в addLinkIconsToCards:', error);
        }
    }

    // ===========================
    // КОНВЕРТАЦИЯ ДАННЫХ
    // ===========================

    function convertDataToF3Format(data) {
        var f3Nodes = [];

        data.nodes.forEach(function(node) {
            var firstName = node.firstName || "";
            var middleName = node.middleName || "";
            var lastName = node.lastName || "";
            var maidenName = node.maidenName || "";

            f3Nodes.push({
                id: String(node.id),
                rels: {},
                data: {
                    "first name": firstName,
                    "last name": lastName,
                    "middle name": middleName,
                    "maiden name": maidenName,
                    "birthday": node.birthDate || "",
                    "death": node.deathDate || "",
                    "avatar": node.img || "",
                    "gender": node.gender === 'female' ? 'F' : 'M',
                    "permalink": node.permalink || ""
                }
            });
        });

        data.links.forEach(function(link) {
            var sourceNode = f3Nodes.find(function(n) { return n.id === String(link.source); });
            var targetNode = f3Nodes.find(function(n) { return n.id === String(link.target); });

            if (sourceNode && targetNode) {
                if (link.type === 'child') {
                    if (sourceNode.data.gender === 'M') {
                        if (!targetNode.rels.father) targetNode.rels.father = sourceNode.id;
                    } else if (sourceNode.data.gender === 'F') {
                        if (!targetNode.rels.mother) targetNode.rels.mother = sourceNode.id;
                    }
                    if (!sourceNode.rels.children) sourceNode.rels.children = [];
                    if (!sourceNode.rels.children.includes(targetNode.id)) {
                        sourceNode.rels.children.push(targetNode.id);
                    }
                } else if (link.type === 'spouse') {
                    if (!sourceNode.rels.spouses) sourceNode.rels.spouses = [];
                    if (!sourceNode.rels.spouses.includes(targetNode.id)) {
                        sourceNode.rels.spouses.push(targetNode.id);
                    }
                    if (!targetNode.rels.spouses) targetNode.rels.spouses = [];
                    if (!targetNode.rels.spouses.includes(sourceNode.id)) {
                        targetNode.rels.spouses.push(sourceNode.id);
                    }
                }
            }
        });

        return f3Nodes;
    }
})();
