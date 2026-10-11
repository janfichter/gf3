/**
 * Отметка людей на фотографии («Кто на фото»).
 * Клик по фото ставит маркер, затем из выпадающего поиска выбирается персоналия.
 * Координаты хранятся в процентах, данные — в скрытом поле ft_photo_people (JSON).
 */
(function ($) {
    'use strict';

    var stage, img, input, listEl, popup, popupInput, popupList,
        members = (window.ftPhotoPeople && ftPhotoPeople.members) ? ftPhotoPeople.members : [],
        tags = [],
        pendingX = 0,
        pendingY = 0,
        pendingIndex = null,
        takenTimer = null,
        takenDateInput = null,
        takenLocationInput = null;

    function memberName(id) {
        for (var i = 0; i < members.length; i++) {
            if (String(members[i].id) === String(id)) {
                return members[i].name;
            }
        }
        return '';
    }

    function persistTaken() {
        if (!window.ftPhotoTaken || !ftPhotoTaken.postId) {
            return;
        }
        if (takenTimer) {
            clearTimeout(takenTimer);
        }
        takenTimer = setTimeout(function () {
            var ajaxUrl = ftPhotoTaken.ajaxUrl || (window.ajaxurl || '');
            if (!ajaxUrl) {
                return;
            }
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ft_save_photo_taken',
                    nonce: ftPhotoTaken.nonce,
                    post_id: ftPhotoTaken.postId,
                    taken_date: takenDateInput.val() || '',
                    taken_location: takenLocationInput.val() || ''
                }
            });
        }, 400);
    }

    function initTaken() {
        takenDateInput = $('#ft-photo-taken-date');
        takenLocationInput = $('#ft-photo-taken-location');
        if (!takenDateInput.length || !takenLocationInput.length) {
            return;
        }
        takenDateInput.on('change keyup', persistTaken);
        takenLocationInput.on('change keyup', persistTaken);
    }

    function updateInput() {
        input.val(JSON.stringify(tags));
        input.trigger('change');
        persist();
    }

    var persistTimer = null;
    function persist() {
        if (!window.ftPhotoPeople || !ftPhotoPeople.postId) {
            return;
        }
        if (persistTimer) {
            clearTimeout(persistTimer);
        }
        persistTimer = setTimeout(function () {
            var ajaxUrl = (window.ftPhotoPeople && ftPhotoPeople.ajaxUrl) ? ftPhotoPeople.ajaxUrl : (window.ajaxurl || '');
            if (!ajaxUrl) {
                return;
            }
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ft_save_photo_people',
                    nonce: ftPhotoPeople.nonce,
                    post_id: ftPhotoPeople.postId,
                    tags: JSON.stringify(tags)
                }
            });
        }, 400);
    }

    function renderMarkers() {
        stage.find('.ft-photo-people__marker').remove();
        $.each(tags, function (i, t) {
            var $m = $('<div class="ft-photo-people__marker" data-index="' + i + '" title="' + (memberName(t.member) || '') + '">' + (i + 1) + '</div>');
            $m.css({ left: t.x + '%', top: t.y + '%' });
            $m.on('click', function (e) {
                e.stopPropagation();
                openPopupFor(i);
            });
            stage.append($m);
        });
    }

    function renderList() {
        if (!listEl.length) {
            return;
        }
        listEl.empty();
        $.each(tags, function (i, t) {
            var name = memberName(t.member);
            if (!name) {
                return;
            }
            var $li = $('<li class="ft-photo-people__item">' +
                '<span class="ft-photo-people__num">' + (i + 1) + '</span>' +
                '<span class="ft-photo-people__name">' + $('<span>').text(name).html() + '</span>' +
                '<button type="button" class="ft-photo-people__remove" data-index="' + i + '" title="Удалить метку">&times;</button>' +
                '</li>');
            $li.on('click', '.ft-photo-people__remove', function () {
                tags.splice(parseInt($(this).data('index'), 10), 1);
                updateInput();
                renderMarkers();
                renderList();
            });
            listEl.append($li);
        });
    }

    function closePopup() {
        popup.hide();
        pendingIndex = null;
    }

    function filterMembers(query) {
        var q = query.toLowerCase();
        var res = [];
        for (var i = 0; i < members.length; i++) {
            if (members[i].name.toLowerCase().indexOf(q) !== -1) {
                res.push(members[i]);
            }
        }
        return res;
    }

    function renderSuggestions(query) {
        popupList.empty();
        var items = filterMembers(query || '');
        var limit = Math.min(items.length, 15);
        for (var i = 0; i < limit; i++) {
            (function (m) {
                var $opt = $('<button type="button" class="ft-photo-people__option">' + $('<span>').text(m.name).html() + '</button>');
                $opt.on('click', function () {
                    if (pendingIndex === null) {
                        tags.push({ x: pendingX, y: pendingY, member: m.id });
                    } else {
                        tags[pendingIndex].member = m.id;
                    }
                    updateInput();
                    renderMarkers();
                    renderList();
                    closePopup();
                });
                popupList.append($opt);
            })(items[i]);
        }
        if (!items.length) {
            popupList.append($('<div class="ft-photo-people__empty">Никого не найдено</div>'));
        }
    }

    function openPopupFor(index) {
        var t = tags[index];
        if (!t) {
            return;
        }
        pendingIndex = index;
        popupInput.val('');
        popup.find('.ft-photo-people__remove-btn').show();
        positionPopup(t.x, t.y, true);
        renderSuggestions('');
        popupInput.trigger('focus');
    }

    function openPopupAt(x, y, px, py) {
        pendingIndex = null;
        pendingX = x;
        pendingY = y;
        popupInput.val('').trigger('keyup');
        popup.find('.ft-photo-people__remove-btn').hide();
        positionPopup(x, y, false, px, py);
        popupInput.trigger('focus');
    }

    function positionPopup(xPct, yPct, editMode, px, py) {
        var stageW = stage.width();
        var stageH = stage.height();
        var popW = 260;
        var popH = 230;
        var leftPx, topPx;
        if (px !== undefined && py !== undefined) {
            leftPx = px;
            topPx = py + 12;
        } else {
            leftPx = (xPct / 100) * stageW;
            topPx = (yPct / 100) * stageH + 12;
        }
        leftPx = Math.min(Math.max(leftPx, 8), stageW - popW - 8);
        if (topPx + popH > stageH - 8 && topPx - popH - 20 > 0) {
            topPx = topPx - popH - 20;
        }
        popup.css({ left: leftPx + 'px', top: topPx + 'px' });
        popup.show();
    }

    function initPopup() {
        popup = $(
            '<div class="ft-photo-people__popup" id="ft-people-popup">' +
            '<button type="button" class="ft-photo-people__close">&times;</button>' +
            '<div class="ft-photo-people__popup-title">Отметить человека</div>' +
            '<input type="text" class="ft-photo-people__search" placeholder="Поиск персоналии…" />' +
            '<div class="ft-photo-people__options"></div>' +
            '<button type="button" class="ft-photo-people__remove-btn">Удалить метку</button>' +
            '</div>'
        );
        popup.appendTo(stage);
        popupInput = popup.find('.ft-photo-people__search');
        popupList = popup.find('.ft-photo-people__options');

        popup.on('click', function (e) {
            e.stopPropagation();
        });
        popup.find('.ft-photo-people__close').on('click', closePopup);
        popup.find('.ft-photo-people__remove-btn').on('click', function () {
            if (pendingIndex !== null) {
                tags.splice(pendingIndex, 1);
                updateInput();
                renderMarkers();
                renderList();
            }
            closePopup();
        });
        popupInput.on('keyup', function () {
            renderSuggestions($(this).val());
        });
        popupInput.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    }

    function bindStage() {
        stage.on('click', function () {
            closePopup();
        });
        stage.on('click', '.ft-photo-people__img', function (e) {
            var rect = img[0].getBoundingClientRect();
            var x = ((e.clientX - rect.left) / rect.width) * 100;
            var y = ((e.clientY - rect.top) / rect.height) * 100;
            x = Math.min(Math.max(x, 1), 99);
            y = Math.min(Math.max(y, 1), 99);
            openPopupAt(x, y, e.clientX - rect.left, e.clientY - rect.top);
            return false;
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                closePopup();
            }
        });
        $(document).on('click', function () {
            if (popup && popup.is(':visible')) {
                closePopup();
            }
        });
    }

    function init() {
        stage = $('#ft-people-stage');
        if (!stage.length) {
            return;
        }
        img = $('#ft-people-img');
        input = $('#ft-photo-people-input');
        listEl = $('#ft-people-list');
        try {
            tags = input.val() ? JSON.parse(input.val()) : [];
        } catch (err) {
            tags = [];
        }
        if (!$.isArray(tags)) {
            tags = [];
        }
        initPopup();
        bindStage();
        renderMarkers();
        renderList();
    }

    $(function () {
        init();
        initTaken();
    });
})(jQuery);