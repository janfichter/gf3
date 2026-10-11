/**
 * ФЗ-152: блокировка публикации/сохранения персоны без подтверждения согласия.
 * Работает в классическом редакторе и в блочном редакторе (Gutenberg).
 */
(function() {
    'use strict';

    var PUBLISH_SELECTORS = [
        '#publish',
        '#save-post',
        '.editor-post-save-draft',
        '.editor-post-update',
        '.editor-post-publish-button__button',
        '.editor-post-publish-button',
        '.editor-post-publish-panel__toggle',
        '.editor-post-publish-panel__header-publish-button',
        '.editor-post-publish-panel__footer .editor-post-publish-button',
        '.editor-header__settings .editor-post-publish-button__button',
        '.components-button.is-primary.editor-post-publish-button',
        '.editor-post-publish-panel__button'
    ].join(',');

    var box = null;
    var checkbox = null;
    var lockHint = 'Подтвердите согласие на публикацию данных.';

    function init() {
        box = document.getElementById('ft-consent-box');
        if (!box) {
            return;
        }
        checkbox = document.getElementById('ft-consent-checkbox');
        if (!checkbox) {
            return;
        }
        if (box.getAttribute('data-consent-lock-hint')) {
            lockHint = box.getAttribute('data-consent-lock-hint');
        }

        /* Классический редактор: переносим блок над заголовком. */
        var titlediv = document.getElementById('titlediv');
        var postbox = box.closest('.postbox');
        if (titlediv && postbox) {
            titlediv.parentNode.insertBefore(postbox, titlediv);
            postbox.classList.remove('closed');
            var inside = postbox.querySelector('.inside');
            if (inside) {
                inside.style.display = '';
            }
        }

        var isGiven = box.getAttribute('data-consent-given') === '1';

        /* Визуальная блокировка кнопок. В Gutenberg React пересоздаёт кнопки,
           поэтому повторяем через интервал, пока согласие не подтверждено. */
        applyDisabled();
        var intervalId = 0;
        if (!isGiven) {
            intervalId = window.setInterval(applyDisabled, 300);
        }

        /* Главный перехват: capture-слушатель на document срабатывает раньше
           любых обработчиков (в т.ч. React в Gutenberg). */
        document.addEventListener('click', onDocumentClick, true);
        document.addEventListener('keydown', onDocumentKeydown, true);

        /* Классический редактор: блокируем отправку формы #post. */
        if (window.jQuery) {
            jQuery(document).on('submit.ftp152', 'form#post', function(e) {
                if (!isLocked()) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                window.alert(lockHint);
            });
        }

        checkbox.addEventListener('change', onConsentChange);

        /*
         * НЕОТКЛЮЧАЕМОСТЬ: глобальные capture-слушатели на весь документ,
         * чтобы галочку нельзя было снять ни кликом, ни переключением через
         * label, ни после перерисовки метабокса Gutenberg. Обрабатываем на
         * этапе mousedown (до смены состояния чекбокса) и change (страховка).
         */
        document.addEventListener('mousedown', onBoxMouseDown, true);
        document.addEventListener('change', onDocChange, true);
        document.addEventListener('keydown', onBoxKeydown, true);

        /* Периодически перепривязываемся (на всякий случай, если метабокс был
           перерисован Gutenberg'ом после автосохранения). */
        window.setInterval(function() {
            var cb = document.getElementById('ft-consent-checkbox');
            if (cb && cb !== checkbox) {
                checkbox = cb;
                checkbox.addEventListener('change', onConsentChange);
                applyDisabled();
            }
        }, 1000);
    }

    function onBoxMouseDown(e) {
        var cb = document.getElementById('ft-consent-checkbox');
        if (!cb || !cb.checked) return;
        var el = e.target;
        while (el && el !== document) {
            if (el === cb || (el.classList && el.classList.contains('ft-consent-box'))) {
                e.preventDefault();
                return;
            }
            el = el.parentNode;
        }
    }

    function onBoxKeydown(e) {
        if (e.key !== ' ' && e.key !== 'Spacebar') return;
        var cb = document.getElementById('ft-consent-checkbox');
        if (!cb || !cb.checked) return;
        var el = e.target;
        while (el && el !== document) {
            if (el === cb || (el.classList && el.classList.contains('ft-consent-box'))) {
                e.preventDefault();
                return;
            }
            el = el.parentNode;
        }
    }

    function onDocChange(e) {
        var cb = document.getElementById('ft-consent-checkbox');
        if (!cb || e.target !== cb) return;
        if (!cb.checked) {
            /* Нельзя снять галочку. */
            cb.checked = true;
            onConsentChange();
        }
    }

    function isLocked() {
        var cb = document.getElementById('ft-consent-checkbox');
        if (!cb) {
            return false;
        }
        return !cb.checked;
    }

    function applyDisabled() {
        if (!isLocked()) {
            if (window.__ftConsentInterval) {
                window.clearInterval(window.__ftConsentInterval);
                window.__ftConsentInterval = 0;
            }
            return;
        }
        document.querySelectorAll(PUBLISH_SELECTORS).forEach(function(btn) {
            btn.disabled = true;
        });
    }

    function onConsentChange() {
        var cb = document.getElementById('ft-consent-checkbox');
        if (!cb) return;
        if (!cb.checked) {
            /* Нельзя снять галочку после отметки. */
            cb.checked = true;
            return;
        }
        cb.checked = true;
        if (box) {
            box.classList.add('is-given');
            var $ok = box.querySelector('.ft-consent-status');
            if (!$ok) {
                $ok = document.createElement('p');
                $ok.className = 'ft-consent-status';
                box.appendChild($ok);
            }
            $ok.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> '
                + (box.getAttribute('data-consent-saved-text') || 'Согласие подтверждено. Отметка сохраняется навсегда.');
        }
        applyDisabled();

        /* Немедленно сохраняем согласие. */
        var postIdEl = document.getElementById('post_ID');
        var postId = postIdEl ? postIdEl.value : '';
        var nonce = box ? box.getAttribute('data-consent-nonce') : '';
        var ajaxurl = box ? (box.getAttribute('data-consent-ajaxurl') || '') : '';
        if (!postId || !nonce || !ajaxurl) {
            return;
        }
        var data = new FormData();
        data.append('action', 'family_tree_confirm_consent');
        data.append('post_id', postId);
        data.append('nonce', nonce);
        var onOk = function() {
            var cb = document.getElementById('ft-consent-checkbox');
            if (!cb) return;
            cb.disabled = true;
            applyDisabled();
        };
        if (window.fetch) {
            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            }).then(onOk).catch(function() {
                applyDisabled();
            });
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl);
            xhr.onload = onOk;
            xhr.onerror = function() { applyDisabled(); };
            xhr.send(data);
        }
    }

    function onDocumentClick(e) {
        if (!isLocked()) return;
        var el = e.target;
        while (el && el !== document) {
            if (el.matches && el.matches(PUBLISH_SELECTORS)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                window.alert(lockHint);
                return;
            }
            el = el.parentNode;
        }
    }

    function onDocumentKeydown(e) {
        if (!isLocked()) return;
        /* Ctrl+S / Cmd+S — сохранение. */
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            e.stopPropagation();
            window.alert(lockHint);
        }
        /* Ctrl+Shift+P / Cmd+Shift+P — публикация (Gutenberg). */
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'p' || e.key === 'P')) {
            e.preventDefault();
            e.stopPropagation();
            window.alert(lockHint);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();