/**
 * ФЗ-152: блокировка сохранения/публикации персоны внутри блочного редактора.
 * Подключается через enqueue_block_editor_assets, поэтому работает и в
 * классическом, и в iframe-режиме редактора (WP 6.9+/7.x). Инициализация
 * отложена до готовности стора, чтобы не ломать bootstrap редактора
 * (пустой канвас при первом заходе). Состояние чекбокса читаем из
 * родительского документа (same-origin).
 */
(function() {
    'use strict';

    var cfg = window.familyTreeConsentEditor || {};
    var LOCK_NAME = 'family-tree-consent-lock';
    var locked = false;

    function safe(fn) {
        try { fn(); } catch (e) {}
    }

    function isConsentGiven() {
        /* 1) Из родительского документа (Gutenberg: метабокс рендерится
              в родителе, inside iframe — before). */
        try {
            if (window.parent && window.parent.document) {
                var cb = window.parent.document.getElementById('ft-consent-checkbox');
                if (cb) {
                    return cb.checked;
                }
                var box = window.parent.document.getElementById('ft-consent-box');
                if (box && box.getAttribute('data-consent-given') === '1') {
                    return true;
                }
            }
        } catch (e) {}
        /* 2) Fallback: значение, отданное сервером при загрузке. */
        return !!cfg.given;
    }

    function currentPostId() {
        try {
            if (window.wp && window.wp.data && window.wp.data.select) {
                var editor = window.wp.data.select('core/editor');
                if (editor && typeof editor.getCurrentPostId === 'function') {
                    var id = editor.getCurrentPostId();
                    if (id) return parseInt(id, 10);
                }
            }
        } catch (e) {}
        return parseInt(cfg.postId || 0, 10);
    }

    function doLock() {
        var editor = window.wp && window.wp.data && window.wp.data.dispatch && window.wp.data.dispatch('core/editor');
        if (!editor) return;
        if (typeof editor.lockPostSaving === 'function') editor.lockPostSaving(LOCK_NAME);
        if (typeof editor.lockPublishSaving === 'function') editor.lockPublishSaving(LOCK_NAME);
        locked = true;
    }

    function doUnlock() {
        var editor = window.wp && window.wp.data && window.wp.data.dispatch && window.wp.data.dispatch('core/editor');
        if (!editor) return;
        if (typeof editor.unlockPostSaving === 'function') editor.unlockPostSaving(LOCK_NAME);
        if (typeof editor.unlockPublishSaving === 'function') editor.unlockPublishSaving(LOCK_NAME);
        locked = false;
    }

    function applyLock() {
        var given = isConsentGiven();
        if (given) {
            doUnlock();
            return;
        }
        /* У новой записи (нет поста/ID) пока нет ID для сохранения согласия —
           блокируем публикацию, но разрешаем сохранить черновик. Как только
           чекбокс отмечен — AJAX сохранит согласие и блок снимется. */
        if (currentPostId() > 0) {
            doLock();
        } else {
            var editor = window.wp && window.wp.data && window.wp.data.dispatch && window.wp.data.dispatch('core/editor');
            if (editor && typeof editor.lockPublishSaving === 'function') {
                editor.lockPublishSaving(LOCK_NAME);
            }
            locked = true;
        }
    }

    function whenEditorReady(cb) {
        if (window.wp && window.wp.data && window.wp.data.select &&
            window.wp.data.select('core/editor')) {
            safe(cb);
            return;
        }
        var tries = 0;
        var iv = window.setInterval(function() {
            tries++;
            if (window.wp && window.wp.data && window.wp.data.select &&
                window.wp.data.select('core/editor')) {
                window.clearInterval(iv);
                safe(cb);
                return;
            }
            if (tries > 100) window.clearInterval(iv);
        }, 100);
    }

    function init() {
        safe(function() {
            whenEditorReady(function() {
                /* Отсрочка: не трогаем редактор, пока он не завершил
                   первичную инициализацию/автосохранение. */
                window.setTimeout(function() {
                    applyLock();
                    /* Переприменяем после сохранений (кнопки пересоздаются). */
                    window.setInterval(safe.bind(null, applyLock), 800);
                }, 900);
            });
        });
    }

    if (typeof window.wp !== 'undefined' && window.wp && window.wp.domReady) {
        window.wp.domReady(init);
    } else {
        init();
    }
})();