/**
 * Фотоальбомы членов семьи: официальный лайтбокс (GLightbox) и прокрутка карусели.
 * GLightbox подключается как вендорный файл и инициализируется здесь.
 */
(function() {
    'use strict';

    function initAlbumLightbox() {
        // Клоны карусели помечаются data-gallery="...-clone" и в лайтбокс не попадают.
        var links = document.querySelectorAll('.family-member-photo-album a[data-gallery]:not([data-gallery="family-member-photo-album-clone"])');
        if (!links.length) {
            return;
        }
        if (typeof window.GLightbox === 'undefined') {
            // Скрипт GLightbox мог ещё не выполниться: пробуем ещё раз на полной загрузке.
            window.addEventListener('load', initAlbumLightbox);
            return;
        }
        if (window.__ftLightboxInit) {
            return;
        }
        window.__ftLightboxInit = true;
        window.GLightbox({
            selector: '.family-member-photo-album a[data-gallery]:not([data-gallery="family-member-photo-album-clone"])',
            touchNavigation: true,
            loop: true,
            keyboardNavigation: true,
            closeButton: true,
            openEffect: 'fade',
            closeEffect: 'fade',
            slideEffect: 'slide'
        });
    }

    function initCarousel() {
        var albums = document.querySelectorAll('.family-member-photo-album.ft-layout-carousel');
        Array.prototype.forEach.call(albums, function(album) {
            var track = album.querySelector('.family-member-photo-track');
            var prev = album.querySelector('.ft-album-prev');
            var next = album.querySelector('.ft-album-next');
            if (!track) {
                return;
            }
            var baseItems = Array.prototype.slice.call(track.children);
            var total = baseItems.length;
            if (total === 0) {
                return;
            }
            var speed = parseInt(album.getAttribute('data-album-speed') || '400', 10);
            if (speed < 200 || speed > 5000) {
                speed = 400;
            }
            var scrollMode = album.getAttribute('data-album-scroll') === 'all' ? 'all' : 'one';
            var loop = album.getAttribute('data-album-loop') === '1';
            // При зацикливании создаём три группы: [копия][оригинал][копия].
            // Копия слева даёт настоящие DOM-слайды при прокрутке назад от первого фото.
            // Копия справа позволяет ПРОВЕСТИ переход «последнее фото → первое» реальной
            // анимацией (плавно), после чего мгновенно перецепиться на перекрывающий кадр
            // оригинала — без «рывка в начало» и без пустых полос на конце ленты.
            // Ленивая загрузка при зацикливании отключается, клоны догружаются принудительно —
            // иначе вместо картинок видны пустые рамки.
            var cloneImgs = [];
            function ftMakeClone(ftEl) {
                var ftCl = ftEl.cloneNode(true);
                ftCl.setAttribute('data-gallery', 'family-member-photo-album-clone');
                var ftClImg = ftCl.querySelector('img');
                var ftSrcEl = ftEl.querySelector('img');
                if (ftClImg) {
                    ftClImg.removeAttribute('loading');
                    ftClImg.setAttribute('loading', 'eager');
                    // Берём реально активный источник (currentSrc), а не атрибут: если
                    // lazy-плагин подменил src на заглушку, клон получит настоящую картинку.
                    var ftSrc = (ftSrcEl && ftSrcEl.currentSrc) ? ftSrcEl.currentSrc
                        : (ftClImg.getAttribute('src') || '');
                    if (ftSrc) {
                        ftClImg.src = ftSrc;
                    }
                    cloneImgs.push(ftClImg);
                }
                return ftCl;
            }
            if (loop && total > 1) {
                var loopIdx;
                for (loopIdx = 0; loopIdx < total; loopIdx++) {
                    var ftOrigEl = baseItems[loopIdx];
                    var ftOrigImg = ftOrigEl.querySelector('img');
                    if (ftOrigImg) {
                        ftOrigImg.removeAttribute('loading');
                        ftOrigImg.setAttribute('loading', 'eager');
                        if (ftOrigImg.getAttribute('src')) {
                            ftOrigImg.src = ftOrigImg.getAttribute('src');
                        }
                    }
                }
                for (loopIdx = total - 1; loopIdx >= 0; loopIdx--) {
                    track.insertBefore(ftMakeClone(baseItems[loopIdx]), track.firstChild);
                }
                for (loopIdx = 0; loopIdx < total; loopIdx++) {
                    track.appendChild(ftMakeClone(baseItems[loopIdx]));
                }
            }
            var slides = Array.prototype.slice.call(track.children);
            // looping — полноценный бесконечный режим (только когда слайдов больше одного).
            // count — всего слайдов в треке. Стартуем с первого слайда (копия слева == оригинал),
            // чтобы при центрировании (justify-content: center) нулевой рендер не уводил ленту влево.
            var looping = loop && total > 1;
            var count = looping ? total * 3 : total;
            var index = 0;
            var itemStep = 0;
            // Текущая (применённая) позиция трека — нужна для перетаскивания мышью.
            var posX = 0;

            function gapPx() {
                return parseFloat(getComputedStyle(track).columnGap) || 0;
            }
            function measure() {
                var first = slides[0];
                if (!first) {
                    itemStep = 0;
                    return;
                }
                var cs = getComputedStyle(first);
                var basis = cs.flexBasis;
                var w = (basis && basis !== 'auto') ? parseFloat(basis) : first.getBoundingClientRect().width;
                if (!w || isNaN(w)) {
                    w = first.getBoundingClientRect().width;
                }
                itemStep = w + gapPx();
            }
            function centerRow() {
                if (album.getAttribute('data-album-ratio') !== '1') {
                    return;
                }
                // centering по ширине ОДНОГО круга (не всего трека с копиями)
                var groupW = itemStep * total;
                track.style.justifyContent = (groupW <= track.clientWidth) ? 'center' : 'flex-start';
            }
            function visibleCount() {
                if (itemStep <= 0) {
                    return 1;
                }
                return Math.max(1, Math.floor((track.clientWidth + 0.5) / itemStep));
            }
            function stepCount() {
                if (scrollMode === 'one') {
                    return 1;
                }
                return visibleCount();
            }
            function offsetOf(slide) {
                if (!slide) {
                    return index * itemStep;
                }
                return Math.round(slide.getBoundingClientRect().left - track.getBoundingClientRect().left);
            }
            // Физический конец ленты: насколько можно уехать вправо, чтобы правый край
            // последнего изображения совпадал с правым краем вьюпорта. Без этого конечная
            // остановка считалась по целым «колонкам» и оставляла пустой участок после
            // последнего фото (колонка минус зазор). В бесконечном режиме не используется —
            // там лента всегда длинная.
            function endX() {
                return Math.max(0, track.scrollWidth - track.clientWidth);
            }
            // Первый индекс, с которого упор приходится ровно на физический конец ленты
            // (правый край последнего изображения = правый край вьюпорта). До этого индекса
            // каждое листание даёт ВИДИМОЕ перемещение; отсюда и дальше кадры одинаковые,
            // поэтому листание останавливается и стрелка «вперёд» скрывается.
            function endIndex() {
                var fx = endX();
                var i = 0;
                while (i < count - 1 && offsetOf(slides[i]) < fx) {
                    i++;
                }
                return i;
            }
            function render(instant) {
                // Клампим позицию к физическому концу ленты в ЛЮБОМ режиме (в т.ч. loop):
                // visibleCount() округлением занижает число колонок, и без клампа индекс
                // мог выехать за последний реальный слайд — вьюпорт показывал пустой участок.
                var x = Math.min(offsetOf(slides[index]), endX());
                posX = x;
                track.style.transitionDuration = instant ? '0ms' : speed + 'ms';
                track.style.transform = 'translateX(' + (-x) + 'px)';
            }
            function updateArrows() {
                if (!prev && !next) {
                    return;
                }
                var maxIndex = looping ? total - 1 : endIndex();
                if (prev) {
                    prev.style.visibility = (index <= 0 && !looping) ? 'hidden' : 'visible';
                }
                if (next) {
                    next.style.visibility = (index >= maxIndex && !looping) ? 'hidden' : 'visible';
                }
            }
            function go(direction) {
                var steps = stepCount() * direction;
                var target = index + steps;
                if (looping) {
                    // Трек: [B][A][C]. Индекс удерживается в «safe-зоне» [safeMin, safeMax]:
                    // vc считается округлением вниз, поэтому граница «вперёд» — последний
                    // индекс с НАТУРАЛЬНОЙ позицией (смещение < физического конца endX),
                    // т.е. endIndex() - 1. Если разрешить кадр с клампом (offset >= endX),
                    // перецепка на -total сдвигает вьюпорт на нецелое число колонок — рывок.
                    var vc = Math.max(1, Math.min(visibleCount(), total));
                    var safeMin = vc - 1;
                    var safeMax = Math.max(safeMin, endIndex() - 1);
                    if (target > safeMax || target < safeMin) {
                        // Плавное «прохождение сквозь конец»: сдвиг на ОДНУ группу (влево при
                        // движении вперёд, вправо назад). Группы структурно идентичны, смещение
                        // меняется ровно на ширину группы, поэтому кадр до и после — те же
                        // картинки; перецепка невидима, лента крутится по кругу без скачков.
                        if (target > safeMax) {
                            index -= total;
                        } else {
                            index += total;
                        }
                        // Предохранитель на крайние случаи (вся лента помещается во вьюпорт:
                        // endX()==0, группы уже не дают смещения) — индекс остаётся в треке.
                        index = Math.max(0, Math.min(count - 1, index));
                        target = index + steps;
                        render(true);                    // невидимая перецепка
                        void track.getBoundingClientRect();
                    }
                    index = target;
                    render(false);
                } else {
                    var idxEnd = endIndex();
                    index = Math.max(0, Math.min(idxEnd, target));
                    render(false);
                }
                updateArrows();
            }
            if (prev) {
                prev.addEventListener('click', function(e) {
                    e.preventDefault();
                    go(-1);
                });
            }
            if (next) {
                next.addEventListener('click', function(e) {
                    e.preventDefault();
                    go(1);
                });
            }
            window.addEventListener('resize', function() {
                measure();
                centerRow();
                if (looping) {
                    var resizeVc = Math.max(1, Math.min(visibleCount(), total));
                    index = Math.max(resizeVc - 1, Math.min(Math.max(resizeVc - 1, endIndex() - 1), index));
                } else {
                    index = Math.max(0, Math.min(endIndex(), index));
                }
                render(true);
                updateArrows();
            });

            // --- Перетаскивание карусели: mouse + touch ---
            var hoverPauseEnabled = album.getAttribute('data-album-hover-pause') === '1';
            var hoverActive = false;
            var dragState = { x: 0, start: 0, last: 0, moved: false, active: false, hadButton: false, history: [], justDragged: false };
            var inertiaId = 0;
            function stopInertia() {
                if (inertiaId) {
                    window.cancelAnimationFrame(inertiaId);
                    inertiaId = 0;
                }
            }
            function applyX(nx) {
                var fx = endX();
                if (looping) {
                    var gw = offsetOf(slides[total]) || (itemStep * total);
                    var guard = 0;
                    while (nx > fx && guard < 8) { nx -= gw; index -= total; guard++; }
                    guard = 0;
                    while (nx < 0 && guard < 8) { nx += gw; index += total; guard++; }
                    index = Math.max(0, Math.min(count - 1, index));
                }
                nx = Math.max(0, Math.min(fx, nx));
                posX = nx;
                track.style.transform = 'translateX(' + (-nx) + 'px)';
            }
            function recordHistory(x) {
                var now = Date.now();
                dragState.history.push({ x: x, t: now });
                while (dragState.history.length > 1 && dragState.history[0].t < now - 150) {
                    dragState.history.shift();
                }
            }
            function velocityFromHistory() {
                var h = dragState.history;
                if (h.length < 2) return 0;
                var dt = h[h.length - 1].t - h[0].t;
                if (dt <= 0) return 0;
                return -(h[h.length - 1].x - h[0].x) * 1000 / dt;
            }
            function resumeAutoplay() {
                if (hoverPauseEnabled && hoverActive) return;
                startAutoplay();
            }
            function suppressClickAfterSwipe() {
                dragState.justDragged = true;
                setTimeout(function() { dragState.justDragged = false; }, 300);
            }
            function doSnap() {
                var best = 0, bd = Infinity;
                for (var di = 0; di < count; di++) {
                    var dd = Math.abs(offsetOf(slides[di]) - posX);
                    if (dd < bd) { bd = dd; best = di; }
                }
                if (looping) {
                    var sVc = Math.max(1, Math.min(visibleCount(), total));
                    best = Math.max(sVc - 1, Math.min(Math.max(sVc - 1, endIndex() - 1), best));
                } else {
                    best = Math.max(0, Math.min(endIndex(), best));
                }
                index = best;
                render(false);
                updateArrows();
                resumeAutoplay();
            }
            function startInertia(v) {
                if (Math.abs(v) < 30) { doSnap(); return; }
                var tPrev = performance.now();
                inertiaId = window.requestAnimationFrame(function step(now) {
                    var dt = Math.min((now - tPrev) / 1000, 0.05);
                    if (!inertiaId) return;
                    tPrev = now;
                    v *= Math.pow(0.001, dt / 0.5);
                    if (Math.abs(v) < 12) { inertiaId = 0; doSnap(); return; }
                    applyX(posX + v * dt);
                    inertiaId = window.requestAnimationFrame(step);
                });
            }
            function finishGesture() {
                if (!dragState.active) return;
                dragState.active = false;
                album.classList.remove('ft-album-dragging');
                document.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                album.removeEventListener('mouseleave', dragLeave);
                if (!dragState.moved) {
                    track.style.transitionDuration = speed + 'ms';
                    dragState.moved = false;
                    resumeAutoplay();
                    return;
                }
                var v = velocityFromHistory();
                dragState.moved = false;
                suppressClickAfterSwipe();
                startInertia(v);
            }
            function onMouseMove(e) {
                if (!dragState.active) return;
                var cur = e.clientX;
                if (!dragState.moved && Math.abs(cur - dragState.start) > 3) {
                    dragState.moved = true;
                    stopInertia();
                    track.style.transitionDuration = '0ms';
                }
                if (!dragState.moved) {
                    if (e.buttons & 1) dragState.hadButton = true;
                    return;
                }
                if (e.buttons & 1) dragState.hadButton = true;
                if (dragState.hadButton && !(e.buttons & 1)) { finishGesture(); return; }
                dragState.last = cur;
                recordHistory(cur);
                applyX(dragState.x - (cur - dragState.start));
                e.preventDefault();
            }
            function onMouseUp() { finishGesture(); }
            function dragLeave() {
                if (dragState.active) finishGesture();
            }
            track.addEventListener('mousedown', function(e) {
                if (e.button !== 0) return;
                if (e.target && e.target.closest && e.target.closest('.ft-album-carousel-controls')) return;
                e.preventDefault();
                dragState.x = posX;
                dragState.start = e.clientX;
                dragState.last = e.clientX;
                dragState.history = [];
                dragState.moved = false;
                dragState.active = true;
                dragState.hadButton = false;
                album.classList.add('ft-album-dragging');
                pauseAutoplay();
                stopInertia();
                document.addEventListener('mousemove', onMouseMove);
                window.addEventListener('mouseup', onMouseUp);
                album.addEventListener('mouseleave', dragLeave);
            });
            window.addEventListener('blur', function() { finishGesture(); });
            // Блокируем клик по изображению только в первые 300мс после свайпа,
            // чтобы лайтбокс не открывался случайно при отпускании мыши.
            album.addEventListener('click', function(ce) {
                if (dragState.justDragged) {
                    ce.preventDefault();
                    ce.stopPropagation();
                    dragState.justDragged = false;
                }
            }, true);

            // --- Touch ---
            function onTouchMove(e) {
                if (!dragState.active || e.touches.length !== 1) return;
                var cur = e.touches[0].clientX;
                if (!dragState.moved && Math.abs(cur - dragState.start) > 3) {
                    dragState.moved = true;
                    stopInertia();
                    track.style.transitionDuration = '0ms';
                }
                if (!dragState.moved) return;
                dragState.last = cur;
                recordHistory(cur);
                applyX(dragState.x - (cur - dragState.start));
                e.preventDefault();
            }
            function onTouchEnd() {
                if (!dragState.active) return;
                document.removeEventListener('touchmove', onTouchMove);
                window.removeEventListener('touchend', onTouchEnd);
                window.removeEventListener('touchcancel', onTouchEnd);
                finishGesture();
            }
            track.addEventListener('touchstart', function(e) {
                if (e.touches.length !== 1) return;
                if (e.target && e.target.closest && e.target.closest('.ft-album-carousel-controls')) return;
                stopInertia();
                dragState.x = posX;
                dragState.start = e.touches[0].clientX;
                dragState.last = e.touches[0].clientX;
                dragState.history = [];
                dragState.moved = false;
                dragState.active = true;
                album.classList.add('ft-album-dragging');
                pauseAutoplay();
                document.addEventListener('touchmove', onTouchMove, { passive: false });
                window.addEventListener('touchend', onTouchEnd);
                window.addEventListener('touchcancel', onTouchEnd);
            }, { passive: true });

            // --- Пауза авто-прокрутки при наведении мыши.
            album.addEventListener('mouseenter', function() {
                hoverActive = true;
                if (hoverPauseEnabled) {
                    pauseAutoplay();
                }
            });
            album.addEventListener('mouseleave', function() {
                hoverActive = false;
                if (hoverPauseEnabled) {
                    startAutoplay();
                }
            });

            var imgs = [];
            Array.prototype.forEach.call(baseItems, function(el) {
                Array.prototype.push.apply(imgs, el.querySelectorAll('img'));
            });
            // Картинки клонов (копия слева/справа) должны догрузиться до первого «оборота»,
            // иначе при перецепке вместо фото виден серый/пустой блок.
            Array.prototype.push.apply(imgs, cloneImgs);
            // Запрещаем нативное перетаскивание картинок, иначе браузер сорвёт жест
            // (touchcancel/нативный drag и картинку утянет) и карусель нельзя будет тащить.
            for (var iv = 0; iv < imgs.length; iv++) {
                imgs[iv].setAttribute('draggable', 'false');
            }
            var pending = imgs.length;
            var autoplayTimer = null;
            function startAutoplay() {
                if (autoplayTimer) {
                    clearInterval(autoplayTimer);
                    autoplayTimer = null;
                }
                if (album.getAttribute('data-album-autoplay') === '1' && total > 1) {
                    autoplayTimer = setInterval(function() {
                        go(1);
                    }, Math.max(2000, speed + 1200));
                }
            }
            function pauseAutoplay() {
                if (autoplayTimer) {
                    clearInterval(autoplayTimer);
                    autoplayTimer = null;
                }
            }
            function afterLoads() {
                measure();
                centerRow();
                render(true);
                updateArrows();
                if (looping) {
                    startAutoplay();
                }
            }
            if (pending > 0) {
                Array.prototype.forEach.call(imgs, function(img) {
                    var done = function() {
                        --pending;
                        // Каждая догрузившаяся картинка сразу перестраивает ленту —
                        // поздняя загрузка не оставляет застрявших пустых/съехавших кадров.
                        measure();
                        centerRow();
                        render(true);
                        updateArrows();
                        if (pending === 0) {
                            afterLoads();
                        }
                    };
                    if (img.complete) {
                        done();
                    } else {
                        img.addEventListener('load', done);
                        img.addEventListener('error', done);
                    }
                });
            } else {
                afterLoads();
            }

            measure();
            centerRow();
            render(true);
            updateArrows();

            if (!looping) {
                startAutoplay();
            }
        });
    }

    function initGridLightbox() {
        // Лайтбокс для сеток страниц альбомов и Гутенберг-блоков
        // ({archive-slug}/photoalbums/… и шорткоды family_member_albums / family_album).
        // В режиме «отдельная страница» ссылки не имеют класса glightbox, поэтому сюда не попадают.
        var links = document.querySelectorAll('.ft-album-photo.glightbox');
        if (!links.length) {
            return;
        }
        if (typeof window.GLightbox === 'undefined') {
            window.addEventListener('load', initGridLightbox);
            return;
        }
        if (window.__ftGridLightboxInit) {
            return;
        }
        window.__ftGridLightboxInit = true;
        window.GLightbox({
            selector: '.ft-album-photo.glightbox',
            touchNavigation: true,
            loop: true,
            keyboardNavigation: true,
            closeButton: true,
            openEffect: 'fade',
            closeEffect: 'fade',
            slideEffect: 'slide'
        });
    }

    function init() {
        initAlbumLightbox();
        initGridLightbox();
        initCarousel();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();