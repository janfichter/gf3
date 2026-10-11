/**
 * ФЗ-152: модальное окно согласия с документами плагина.
 * Показывается на главной странице плагина после активации. Защитный слой
 * не позволяет взаимодействовать с функционалом, пока пользователь не
 * прокрутит тексты до конца и не поставит отметку о согласии.
 */
(function() {
    'use strict';

    var overlay = null;
    var bodyWrap = null;
    var checkbox = null;
    var hint = null;
    var submitBtn = null;
    var declineBtn = null;
    var accepted = false;
    var declined = false;
    var lockHint = 'Прокрутите тексты до конца и подтвердите согласие.';

    function init() {
        overlay = document.getElementById('ft-terms-overlay');
        if (!overlay) {
            return;
        }
        bodyWrap = overlay.querySelector('.ft-terms-modal-body');
        checkbox = document.getElementById('ft-terms-accept-checkbox');
        hint = overlay.querySelector('.ft-terms-hint');
        submitBtn = document.getElementById('ft-terms-submit');
        declineBtn = document.getElementById('ft-terms-decline');
        if (!bodyWrap || !checkbox) {
            return;
        }
        if (overlay.getAttribute('data-lock-hint')) {
            lockHint = overlay.getAttribute('data-lock-hint');
        }

        /* Блокируем прокрутку фоновой страницы. */
        document.body.classList.add('ft-terms-locked');

        /* Чекбокс недоступен, пока пользователь не долистал тексты до конца. */
        checkbox.disabled = true;
        checkbox.classList.add('disabled');
        bodyWrap.addEventListener('scroll', onBodyScroll);
        window.addEventListener('resize', checkScrolledToBottom);

        checkbox.addEventListener('change', onConsentChange);
        if (submitBtn) {
            submitBtn.addEventListener('click', onConsentChange);
        }
        if (declineBtn) {
            declineBtn.addEventListener('click', onDecline);
        }

        /* Запрещаем снять отметку после подтверждения. */
        document.addEventListener('change', onDocChange, true);
        document.addEventListener('mousedown', onBoxMouseDown, true);

        checkScrolledToBottom();
    }

    function onDecline() {
        if (accepted || declined) return;
        var confirmMsg = overlay.getAttribute('data-decline-confirm') || 'Вы действительно отказываетесь от принятия документов? Плагин будет автоматически деактивирован.';
        if (!window.confirm(confirmMsg)) {
            return;
        }
        declined = true;
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        if (declineBtn) {
            declineBtn.disabled = true;
        }
        if (checkbox) {
            checkbox.disabled = true;
        }
        if (hint) {
            hint.textContent = overlay.getAttribute('data-decline-text') || 'Деактивация плагина…';
            hint.classList.remove('warning');
        }

        var nonce = overlay.getAttribute('data-nonce') || '';
        var ajaxurl = overlay.getAttribute('data-ajaxurl') || '';

        var data = new FormData();
        data.append('action', 'family_tree_decline_terms');
        data.append('nonce', nonce);

        function onOk(res) {
            var target = (res && res.data && res.data.redirect) ? res.data.redirect : null;
            if (target) {
                window.location.href = target;
            } else {
                window.location.href = ajaxurl.replace('/admin-ajax.php', '') + '/plugins.php';
            }
        }

        function onFail() {
            declined = false;
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            if (declineBtn) {
                declineBtn.disabled = false;
            }
            if (checkbox) {
                checkbox.disabled = false;
            }
            if (hint) {
                hint.textContent = overlay.getAttribute('data-error-text') || 'Не удалось деактивировать плагин. Попробуйте ещё раз.';
                hint.classList.add('warning');
            }
        }

        if (window.fetch) {
            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            }).then(function(r) { return r.json(); }).then(function(res) {
                if (res && res.success) {
                    onOk(res);
                } else {
                    onFail();
                }
            }).catch(onFail);
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl);
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res && res.success) {
                        onOk(res);
                    } else {
                        onFail();
                    }
                } catch (err) {
                    onFail();
                }
            };
            xhr.onerror = onFail;
            xhr.send(data);
        }
    }

    function onBoxMouseDown(e) {
        if (!accepted || !checkbox) return;
        var el = e.target;
        while (el && el !== document) {
            if (el === checkbox || (el.classList && el.classList.contains('ft-terms-accept'))) {
                e.preventDefault();
                return;
            }
            el = el.parentNode;
        }
    }

    function onDocChange(e) {
        if (!accepted || e.target !== checkbox) return;
        if (!checkbox.checked) {
            checkbox.checked = true;
        }
    }

    function isScrolledToBottom() {
        var el = bodyWrap;
        if (el.scrollHeight <= el.clientHeight) {
            return true;
        }
        return (el.scrollTop + el.clientHeight) >= (el.scrollHeight - 8);
    }

    function checkScrolledToBottom() {
        if (!checkbox || !hint) return;
        var atBottom = isScrolledToBottom();
        if (atBottom) {
            checkbox.disabled = false;
            checkbox.classList.remove('disabled');
            hint.textContent = overlay.getAttribute('data-accept-hint') || 'Отметьте, что ознакомлены с документами и согласны с их условиями.';
            hint.classList.remove('warning');
            if (submitBtn && checkbox.checked) {
                submitBtn.disabled = false;
            }
        } else {
            checkbox.disabled = true;
            checkbox.classList.add('disabled');
            hint.textContent = lockHint;
            hint.classList.add('warning');
        }
    }

    function onBodyScroll() {
        checkScrolledToBottom();
    }

    function onConsentChange() {
        if (accepted) return;
        if (!checkbox.checked) {
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            if (checkbox.disabled) {
                window.alert(lockHint);
            }
            return;
        }
        accepted = true;
        checkbox.disabled = true;
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        if (hint) {
            hint.textContent = overlay.getAttribute('data-saving-text') || 'Сохранение согласия…';
            hint.classList.remove('warning');
        }

        var nonce = overlay.getAttribute('data-nonce') || '';
        var ajaxurl = overlay.getAttribute('data-ajaxurl') || '';

        var data = new FormData();
        data.append('action', 'family_tree_accept_terms');
        data.append('nonce', nonce);

        function onOk() {
            document.body.classList.remove('ft-terms-locked');
            overlay.style.transition = 'opacity 0.3s ease';
            overlay.style.opacity = '0';
            window.setTimeout(function() {
                if (overlay && overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            }, 320);
        }

        function onFail() {
            accepted = false;
            checkbox.checked = false;
            checkbox.disabled = false;
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            if (hint) {
                hint.textContent = overlay.getAttribute('data-error-text') || 'Не удалось сохранить согласие. Попробуйте ещё раз.';
                hint.classList.add('warning');
            }
        }

        if (window.fetch) {
            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            }).then(function(r) { return r.json(); }).then(function(res) {
                if (res && res.success) {
                    onOk();
                } else {
                    onFail();
                }
            }).catch(onFail);
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl);
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res && res.success) {
                        onOk();
                    } else {
                        onFail();
                    }
                } catch (err) {
                    onFail();
                }
            };
            xhr.onerror = onFail;
            xhr.send(data);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();