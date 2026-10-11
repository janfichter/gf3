// === Защита и ленивая инициализация цветов (wp-color-picker / iris) ===
// Когда сервер не догружает jquery-ui (draggable — обычно из-за 503 на load-scripts.php),
// iris бросает исключение на каждый вызов wpColorPicker, и первая же такая инициализация
// обрывает весь $(document).ready страницы настроек (не биндятся вкладки и т.д.).
// Оборачиваем вызов в try/catch: если палитра не смогла подняться, страница продолжает жить.
// Дополнительно: на странице настроек до ~200 полей цвета на всех вкладках сразу — чтобы
// не строить палитры в скрытых местах, инициализируем только видимые поля (активная вкладка
// и открытые панели), остальные — по открытии вкладки/панели/блока «Обычный/При наведении».
(function ($) {
    var ftPending = [];
    window.ftFlushVisiblePickers = function (scope) {
        if (!ftPending.length) return;
        var $scope = $(scope || document);
        var kept = [];
        for (var i = 0; i < ftPending.length; i++) {
            var item = ftPending[i];
            var $el = item.el;
            var el0 = $el[0];
            try {
                if (!el0) { continue; }
                if (el0.offsetParent === null) { kept.push(item); continue; }
                if (el0.classList.contains('wp-color-picker')) { continue; }
                if ($scope.length && !$scope.find(el0).length) { kept.push(item); continue; }
                try {
                    $.fn.wpColorPicker._ftReal.apply($el, item.args);
                    $el.removeData('ft-pending-picker');
                } catch (errInit) {
                    $el.removeData('ft-pending-picker');
                }
            } catch (errOuter) {}
        }
        ftPending = kept;
    };
    if (typeof $.fn.wpColorPicker === 'function') {
        $.fn.wpColorPicker._ftReal = $.fn.wpColorPicker;
        $.fn.wpColorPicker = function () {
            var args = arguments;
            var $self = $(this);
            try {
                if (args.length && typeof args[0] === 'object' && args[0] !== null) {
                    var el = $self[0];
                    if (!el || el.offsetParent === null) {
                        if (!$self.data('ft-pending-picker')) {
                            $self.data('ft-pending-picker', true);
                            ftPending.push({ el: $self, args: args });
                        }
                        return $self;
                    }
                }
                return $.fn.wpColorPicker._ftReal.apply(this, args);
            } catch (err) {
                return $self;
            }
        };
    } else {
        $.fn.wpColorPicker = function () { return $(this); };
    }
})(jQuery);

// === Единый контрол фона (ft-bg-control): табы «Классический/Градиент», палитра, изображение ===
(function ($) {
    var ftColors = {
        parse: function (input) {
            if (typeof input !== 'string') { return null; }
            var s = input.trim();
            if (!s) { return null; }
            var rgba = { r: 0, g: 0, b: 0, a: 1 };
            var m;
            if ((m = s.match(/^#([0-9a-f]{8})$/i))) {
                rgba.r = parseInt(m[1].substr(0, 2), 16);
                rgba.g = parseInt(m[1].substr(2, 2), 16);
                rgba.b = parseInt(m[1].substr(4, 2), 16);
                rgba.a = Math.round(parseInt(m[1].substr(6, 2), 16) / 255 * 100) / 100;
                return rgba;
            }
            if ((m = s.match(/^#([0-9a-f]{6})$/i))) {
                rgba.r = parseInt(m[1].substr(0, 2), 16);
                rgba.g = parseInt(m[1].substr(2, 2), 16);
                rgba.b = parseInt(m[1].substr(4, 2), 16);
                rgba.a = 1;
                return rgba;
            }
            if ((m = s.match(/^#([0-9a-f]{4})$/i))) {
                rgba.r = parseInt(m[1][0] + m[1][0], 16);
                rgba.g = parseInt(m[1][1] + m[1][1], 16);
                rgba.b = parseInt(m[1][2] + m[1][2], 16);
                rgba.a = Math.round(parseInt(m[1][3] + m[1][3], 16) / 255 * 100) / 100;
                return rgba;
            }
            if ((m = s.match(/^#([0-9a-f]{3})$/i))) {
                rgba.r = parseInt(m[1][0] + m[1][0], 16);
                rgba.g = parseInt(m[1][1] + m[1][1], 16);
                rgba.b = parseInt(m[1][2] + m[1][2], 16);
                rgba.a = 1;
                return rgba;
            }
            if ((m = s.match(/^([0-9a-f]{6})$/i))) {
                return ftColors.parse('#' + m[1]);
            }
            if ((m = s.match(/^([0-9a-f]{8})$/i))) {
                return ftColors.parse('#' + m[1]);
            }
            if ((m = s.match(/^([0-9a-f]{3})$/i))) {
                return ftColors.parse('#' + m[1]);
            }
            if ((m = s.match(/^rgba?\(([^)]+)\)$/i))) {
                var parts = m[1].replace(/[\s]/g, '').split(',');
                if (parts.length >= 3) {
                    rgba.r = Math.max(0, Math.min(255, Math.round(parseFloat(parts[0]) || 0)));
                    rgba.g = Math.max(0, Math.min(255, Math.round(parseFloat(parts[1]) || 0)));
                    rgba.b = Math.max(0, Math.min(255, Math.round(parseFloat(parts[2]) || 0)));
                    rgba.a = parts.length >= 4 ? Math.max(0, Math.min(1, parseFloat(parts[3]) || 1)) : 1;
                    return rgba;
                }
            }
            return null;
        },
        css: function (c) {
            return 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + c.a + ')';
        },
        hex: function (c) {
            return '#' + [c.r, c.g, c.b].map(function (v) {
                return ('0' + Math.round(v).toString(16)).slice(-2);
            }).join('');
        },
        hexCss: function (c) {
            if (c.a !== undefined && c.a < 1) {
                return '#' + [c.r, c.g, c.b, Math.round(c.a * 255)].map(function (v) {
                    return ('0' + Math.round(v).toString(16)).slice(-2);
                }).join('');
            }
            return this.hex(c);
        },
        rgb2hsl: function (c) {
            var r = c.r / 255, g = c.g / 255, b = c.b / 255;
            var max = Math.max(r, g, b), min = Math.min(r, g, b);
            var h = 0, s = 0, l = (max + min) / 2;
            if (max !== min) {
                var d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                if (max === r) { h = (g - b) / d + (g < b ? 6 : 0); }
                else if (max === g) { h = (b - r) / d + 2; }
                else { h = (r - g) / d + 4; }
                h *= 60;
            }
            return { h: h, s: s, l: l };
        },
        hsl2rgb: function (h, s, l) {
            h = ((h % 360) + 360) % 360;
            var c = (1 - Math.abs(2 * l - 1)) * s;
            var x = c * (1 - Math.abs((h / 60) % 2 - 1));
            var m = l - c / 2;
            var r = 0, g = 0, b = 0;
            if (h < 60) { r = c; g = x; }
            else if (h < 120) { r = x; g = c; }
            else if (h < 180) { g = c; b = x; }
            else if (h < 240) { g = x; b = c; }
            else if (h < 300) { r = x; b = c; }
            else { r = c; b = x; }
            return {
                r: Math.round((r + m) * 255),
                g: Math.round((g + m) * 255),
                b: Math.round((b + m) * 255),
                a: 1
            };
        }
    };

    function ftBgGetCtrl($root) {
        return $root.closest('.ft-bg-control');
    }

    function ftBgGetType($ctrl) {
        return $ctrl.find('.ft-bg-type-input').val() === 'gradient' ? 'gradient' : 'classic';
    }

    function ftBgSetType($ctrl, type) {
        $ctrl.find('.ft-bg-type-input').val(type === 'gradient' ? 'gradient' : 'classic');
        $ctrl.find('.ft-bg-type-tab').removeClass('is-active').filter('[data-ft-bg-type="' + type + '"]').addClass('is-active');
        var isGrad = type === 'gradient';
        $ctrl.find('.ft-bg-pane-classic').css('display', isGrad ? 'none' : 'flex');
        $ctrl.find('.ft-bg-pane-gradient').css('display', isGrad ? 'flex' : 'none');
    }

    function ftBgSetValue($root, color) {
        var $ctrl = ftBgGetCtrl($root);
        var $input = $ctrl.find('.ft-bg-color-input');
        var parsed = (typeof color === 'string') ? ftColors.parse(color) : color;
        var cssVal = parsed ? ftColors.hexCss(parsed) : ('' !== $input.val() ? $input.val() : '');
        if (color !== null && color !== undefined) {
            $input.val(cssVal);
        }
        $input.trigger('change');
        ftBgRefreshClassic($ctrl);
    }

    function ftBgHexFromCss(c) {
        return ftColors.parse(c) ? ftColors.hex(ftColors.parse(c)) : '';
    }

    // Показывает в свотче актуальный цвет поля.
    // Порядок источников: собственное значение -> значение Десктопа из
    // placeholder -> белый. На Планшете и Мобильных поле намеренно пустое
    // (наследование), и реальный цвет лежит именно в placeholder, поэтому
    // раньше свотч всегда оставался белым.
    function ftBgCurrentColor($ctrl) {
        var $input = $ctrl.find('.ft-bg-color-input').first();
        var color = ftColors.parse($input.val());
        if (color) { return color; }
        color = ftColors.parse($input.attr('placeholder'));
        if (color) { return color; }
        return null;
    }

    function ftBgRefreshClassic($ctrl) {
        var color = ftBgCurrentColor($ctrl);
        if (!color) {
            $ctrl.find('.ft-bg-swatch').css('background', '#ffffff');
            return;
        }
        $ctrl.find('.ft-bg-swatch').css('background', ftColors.css(color));
    }

    function ftBgOpenPopover($ctrl, color, $anchor) {
        var $pop = $ctrl.find('.ft-bg-popover');
        // Стартуем с унаследованного цвета, а не с красного: на Планшете и
        // Мобильных поле пустое, и палитра должна открываться на том цвете,
        // который сейчас показан в свотче.
        var c = color || ftBgCurrentColor($ctrl) || { r: 255, g: 0, b: 0, a: 1 };
        var aVal = Math.round(c.a * 100);
        var hsl = ftColors.rgb2hsl(c);
        $pop.find('.ft-bg-alpha').val(aVal);
        $pop.find('.ft-bg-alpha-value').text(aVal + '%');
        $pop.find('.ft-bg-hex').val(ftBgHexFromCss(ftColors.css(c)));
        $pop.find('.ft-bg-r').val(c.r);
        $pop.find('.ft-bg-g').val(c.g);
        $pop.find('.ft-bg-b').val(c.b);
        $pop.find('.ft-bg-a').val(c.a);
        ftBgPosSv($ctrl, hsl.h);
        ftBgPosThumb($ctrl, c);
        ftBgPosHue($ctrl, hsl.h);
        $pop.addClass('is-open');
        if ($anchor && $anchor.length) {
            $ctrl.find('.ft-bg-remove-stop').css('display', $anchor.hasClass('ft-bg-grad-marker') ? '' : 'none');
            ftBgPosPopoverAt($ctrl, $pop, $anchor);
        } else {
            $ctrl.find('.ft-bg-remove-stop').css('display', 'none');
            $pop.css({ top: '', bottom: '', left: '' });
            var r = $pop[0].getBoundingClientRect();
            if (r.top < 32) {
                $pop.addClass('ft-bg-drop-down');
            } else {
                $pop.removeClass('ft-bg-drop-down');
            }
        }
    }

    // Позиционирование палитры относительно якоря (маркер градиента / свитч цвета)
    function ftBgPosPopoverAt($ctrl, $pop, $anchor) {
        var ctrlRect = $ctrl[0].getBoundingClientRect();
        var anchorRect = $anchor[0].getBoundingClientRect();
        var popW = 260;
        var popH = $pop.outerHeight() || 300;
        var leftPx = anchorRect.left - ctrlRect.left - popW / 2 + anchorRect.width / 2;
        leftPx = Math.max(2, Math.min(ctrlRect.width - popW - 2, leftPx));
        var anchorTopOffset = anchorRect.top - ctrlRect.top;
        var topPx = anchorTopOffset - popH - 8;
        if (topPx < 4) {
            topPx = (anchorRect.bottom - ctrlRect.top) + 8;
        }
        $pop.css({ top: topPx + 'px', bottom: 'auto', left: leftPx + 'px' });
        $pop.removeClass('ft-bg-drop-down');
    }

    // Закрытие палитры кликом вне контрола (один делегированный обработчик)
    $(document).on('click', function (e) {
        var $target = $(e.target);
        if ($target.closest('.ft-bg-popover.is-open').length) {
            return;
        }
        if ($target.closest('.ft-bg-swatch').length) {
            return;
        }
        if ($target.closest('.ft-bg-grad-bar').length) {
            return;
        }
        $('.ft-bg-popover.is-open').removeClass('is-open');
        $('.ft-bg-control').each(function () {
            var $c = $(this);
            $c.removeData('ft-bg-active-stop');
            $c.find('.ft-bg-grad-marker.selected').removeClass('selected');
        });
    });

    function ftBgPosThumb($ctrl, color) {
        var $sv = $ctrl.find('.ft-bg-sv');
        var hsl = ftColors.rgb2hsl({ r: color.r, g: color.g, b: color.b, a: 1 });
        var s = hsl.s, l = hsl.l;
        var x = s;
        var y = 1 - l;
        if (hsl.h === 0 && s === 0 && l === 1) { x = 1; y = 0; }
        if (hsl.h === 0 && s === 0 && l === 0) { x = 0; y = 1; }
        $ctrl.find('.ft-bg-sv-thumb').css({ left: (x * 100) + '%', top: (y * 100) + '%' });
    }

    function ftBgPosSv($ctrl, h) {
        $ctrl.find('.ft-bg-sv').css('background', 'hsl(' + h + ',100%,50%)');
    }

    function ftBgPosHue($ctrl, h) {
        var pct = ((h % 360) + 360) % 360 / 360 * 100;
        $ctrl.find('.ft-bg-hue-thumb').css('left', pct + '%');
    }

    function ftBgPickFromSVPointer($ctrl, xPct, yPct) {
        var hsl0 = ftColors.rgb2hsl(ftColors.parse($ctrl.find('.ft-bg-sv').css('background-color')) || { r: 255, g: 0, b: 0, a: 1 });
        var h = hsl0.h;
        var s = Math.max(0, Math.min(1, xPct));
        var l = Math.max(0, Math.min(1, 1 - yPct));
        var base = ftColors.hsl2rgb(h, s, l);
        var a = parseFloat($ctrl.find('.ft-bg-a').val()) || 1;
        var newColor = { r: base.r, g: base.g, b: base.b, a: Math.max(0, Math.min(1, a)) };
        $ctrl.find('.ft-bg-sv-thumb').css({ left: (s * 100) + '%', top: ((1 - l) * 100) + '%' });
        ftBgApplyPopoverColor($ctrl, newColor);
    }

    function ftBgApplyPopoverColor($ctrl, color, writeInput) {
        $ctrl.find('.ft-bg-hex').val(ftColors.hex(color));
        $ctrl.find('.ft-bg-r').val(color.r);
        $ctrl.find('.ft-bg-g').val(color.g);
        $ctrl.find('.ft-bg-b').val(color.b);
        $ctrl.find('.ft-bg-a').val(color.a);
        var aVal = Math.round(color.a * 100);
        $ctrl.find('.ft-bg-alpha').val(aVal);
        $ctrl.find('.ft-bg-alpha-value').text(aVal + '%');
        var $marker = $ctrl.data('ft-bg-active-stop');
        if ($marker && $marker.length) {
            $marker.css('background-color', ftColors.css(color));
            $ctrl.data('ft-bg-grad-dirty', true);
            ftBgPreviewGrad($ctrl);
            return;
        }
        if (writeInput !== false) {
            $ctrl.find('.ft-bg-color-input').val(ftColors.hexCss(color)).trigger('change');
        } else {
            ftBgRefreshClassic($ctrl);
        }
        ftBgPreviewGrad($ctrl);
    }

    // Источник текущего цвета в палитре: активный маркер градиента или классический input
    function ftBgPopoverSourceColor($ctrl) {
        var $m = $ctrl.data('ft-bg-active-stop');
        if ($m && $m.length) {
            return ftColors.parse($m.css('background-color')) || { r: 0, g: 0, b: 0, a: 1 };
        }
        return ftBgCurrentColor($ctrl) || { r: 0, g: 0, b: 0, a: 1 };
    }

    function ftBgPreviewGrad($ctrl) {
        var stored = $ctrl.find('.ft-bg-grad-input').val();
        if (!$ctrl.data('ft-bg-grad-dirty') && stored) {
            $ctrl.find('.ft-bg-grad-bar').css('background', stored);
            return;
        }
        var base = ftBgBuildGradCss($ctrl);
        $ctrl.find('.ft-bg-grad-bar').css('background', base);
        $ctrl.find('.ft-bg-grad-input').val(base);
    }

    // Сбор маркеров градиента: [{ color, pos }], отсортировано по pos
    function ftBgGradMarkers($ctrl) {
        var out = [];
        $ctrl.find('.ft-bg-grad-marker').each(function () {
            var $m = $(this);
            var color = ftColors.parse($m.css('background-color')) || { r: 255, g: 255, b: 255, a: 1 };
            var pos = parseFloat($m.attr('data-pos'));
            if (!isFinite(pos)) { pos = 0; }
            out.push({ color: color, pos: pos });
        });
        out.sort(function (a, b) { return a.pos - b.pos; });
        return out;
    }

    function ftBgBuildGradCss($ctrl) {
        var type = $ctrl.find('.ft-bg-grad-type').val() === 'radial' ? 'radial' : 'linear';
        var angle = ftBgAngleValue($ctrl);
        var marks = ftBgGradMarkers($ctrl);
        if (!marks.length) {
            marks = [
                { color: { r: 255, g: 255, b: 255, a: 1 }, pos: 0 },
                { color: { r: 6, g: 147, b: 227, a: 1 }, pos: 100 }
            ];
        }
        var parts = marks.map(function (m) {
            return ftColors.css(m.color) + ' ' + m.pos + '%';
        });
        if (type === 'radial') {
            return 'radial-gradient(circle at 50% 50%, ' + parts.join(', ') + ')';
        }
        return 'linear-gradient(' + angle + 'deg, ' + parts.join(', ') + ')';
    }

    $(document).on('click', '.ft-bg-control .ft-bg-type-tab', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        var type = $(this).data('ft-bg-type') === 'gradient' ? 'gradient' : 'classic';
        ftBgSetType($ctrl, type);
    });

    $(document).on('click', '.ft-bg-control .ft-bg-swatch', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        $ctrl.removeData('ft-bg-active-stop');
        if ($ctrl.find('.ft-bg-popover').hasClass('is-open')) {
            $ctrl.find('.ft-bg-popover').removeClass('is-open');
        } else {
            ftBgOpenPopover($ctrl, null, $(this));
        }
    });

    $(document).on('keydown', '.ft-bg-control .ft-bg-swatch', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    $(document).on('input change', '.ft-bg-control .ft-bg-color-input', function () {
        var $ctrl = ftBgGetCtrl($(this));
        var color = ftColors.parse($(this).val());
        if (color) {
            ftBgApplyPopoverColor($ctrl, color, false);
            ftBgRefreshClassic($ctrl);
        } else {
            ftBgRefreshClassic($ctrl);
        }
    });

    $(document).on('click', '.ft-bg-control .ft-bg-reset', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        var def = $ctrl.find('.ft-bg-color-input').data('default-color') || '';
        $ctrl.find('.ft-bg-color-input').val(def).trigger('change');
        ftBgRefreshClassic($ctrl);
        ftBgPreviewGrad($ctrl);
    });

    // Сброс градиента к значениям по умолчанию
    $(document).on('click', '.ft-bg-control .ft-bg-grad-reset', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        var def = $ctrl.find('.ft-bg-grad-input').data('ft-bg-def');
        var defType = $ctrl.find('.ft-bg-grad-type').data('ft-bg-def') || 'linear';
        var defAngle = $ctrl.find('.ft-bg-grad-angle').data('ft-bg-def');
        defAngle = (defAngle === undefined || defAngle === null || '' === defAngle) ? 180 : parseInt(defAngle, 10);
        if (isNaN(defAngle)) { defAngle = 180; }
        $ctrl.find('.ft-bg-grad-input').val(def === undefined ? '' : def);
        $ctrl.find('.ft-bg-grad-type').val(defType);
        $ctrl.find('.ft-bg-grad-angle').val(defAngle);
        $ctrl.find('.ft-bg-grad-angle-circle-indicator-wrapper').css('transform', 'rotate(' + defAngle + 'deg)');
        $ctrl.find('.ft-bg-grad-angle-circle').attr('aria-valuenow', defAngle);
        $ctrl.data('ft-bg-grad-dirty', false);
        $ctrl.removeData('ft-bg-active-stop');
        $ctrl.find('.ft-bg-popover').removeClass('is-open');
        $ctrl.find('.ft-bg-grad-bar').css('background', '');
        ftBgInitGradMarkers($ctrl);
        ftBgPreviewGrad($ctrl);
    });

    // SV-поле
    $(document).on('mousedown', '.ft-bg-control .ft-bg-sv', function (e) {
        e.preventDefault();
        var $sv = $(this);
        var $ctrl = ftBgGetCtrl($sv);
        var handle = function (ev) {
            var rect = $sv[0].getBoundingClientRect();
            var x = Math.max(0, Math.min(1, (ev.clientX - rect.left) / rect.width));
            var y = Math.max(0, Math.min(1, (ev.clientY - rect.top) / rect.height));
            ftBgPickFromSVPointer($ctrl, x, y);
        };
        handle(e);
        $(document).on('mousemove.ftbg-sv', handle);
        $(document).one('mouseup.ftbg-sv', function () {
            $(document).off('mousemove.ftbg-sv');
        });
    });

    // Hue-полоса
    $(document).on('mousedown', '.ft-bg-control .ft-bg-hue', function (e) {
        e.preventDefault();
        var $hue = $(this);
        var $ctrl = ftBgGetCtrl($hue);
        var handle = function (ev) {
            var rect = $hue[0].getBoundingClientRect();
            var x = Math.max(0, Math.min(1, (ev.clientX - rect.left) / rect.width));
            var h = Math.round(x * 360);
            ftBgPosHue($ctrl, h);
            var base = ftColors.parse($ctrl.find('.ft-bg-sv').css('background-color')) || { r: 255, g: 0, b: 0, a: 1 };
            var hsl = ftColors.rgb2hsl({ r: base.r, g: base.g, b: base.b, a: 1 });
            var s = Math.max(0, Math.min(1, parseFloat($ctrl.find('.ft-bg-sv-thumb').css('left')) / 100)) || 1;
            var l = Math.max(0, Math.min(1, 1 - parseFloat($ctrl.find('.ft-bg-sv-thumb').css('top')) / 100)) || 0.5;
            var newColor = ftColors.hsl2rgb(h, s, l);
            newColor.a = (parseFloat($ctrl.find('.ft-bg-a').val()) || 1);
            ftBgPosSv($ctrl, h);
            ftBgPosThumb($ctrl, newColor);
            ftBgApplyPopoverColor($ctrl, newColor);
        };
        handle(e);
        $(document).on('mousemove.ftbg-hue', handle);
        $(document).one('mouseup.ftbg-hue', function () {
            $(document).off('mousemove.ftbg-hue');
        });
    });

    // Alpha
    $(document).on('input change', '.ft-bg-control .ft-bg-alpha', function () {
        var $ctrl = ftBgGetCtrl($(this));
        var a = Math.max(0, Math.min(1, parseInt($(this).val(), 10) / 100));
        var color = ftBgPopoverSourceColor($ctrl);
        color.a = a;
        $(this).parent().find('.ft-bg-alpha-value').text(Math.round(a * 100) + '%');
        $ctrl.find('.ft-bg-a').val(a);
        ftBgApplyPopoverColor($ctrl, color);
    });

    // Каналы
    $(document).on('input change', '.ft-bg-control .ft-bg-hex', function () {
        var $ctrl = ftBgGetCtrl($(this));
        var c = ftColors.parse($(this).val());
        if (c) {
            c.a = ftBgPopoverSourceColor($ctrl).a;
            ftBgApplyPopoverColor($ctrl, c);
        }
    });

    $(document).on('input change', '.ft-bg-control .ft-bg-r, .ft-bg-control .ft-bg-g, .ft-bg-control .ft-bg-b, .ft-bg-control .ft-bg-a', function () {
        var $ctrl = ftBgGetCtrl($(this));
        var color = {
            r: Math.max(0, Math.min(255, parseInt($ctrl.find('.ft-bg-r').val(), 10) || 0)),
            g: Math.max(0, Math.min(255, parseInt($ctrl.find('.ft-bg-g').val(), 10) || 0)),
            b: Math.max(0, Math.min(255, parseInt($ctrl.find('.ft-bg-b').val(), 10) || 0)),
            a: Math.max(0, Math.min(1, parseFloat($ctrl.find('.ft-bg-a').val()) || 1))
        };
        ftBgApplyPopoverColor($ctrl, color);
    });

    // Быстрые цвета
    $(document).on('click', '.ft-bg-control .ft-bg-quick-swatch', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        var c = ftColors.parse($(this).attr('data-color'));
        if (c) {
            c.a = parseFloat($ctrl.find('.ft-bg-a').val()) || 1;
            ftBgApplyPopoverColor($ctrl, c);
        }
    });

    // === Изображение (медиатека / URL) ===
    $(document).on('click', '.ft-bg-control .ft-bg-image-open', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        if (typeof wp === 'undefined' || !wp.media) {
            alert('Медиабиблиотека не загружена. Обновите страницу.');
            return;
        }
        var frame = wp.media({
            title: 'Выберите изображение',
            button: { text: 'Выбрать' },
            library: { type: 'image' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            if (att && att.url) {
                var url = (att.sizes && att.sizes.large && att.sizes.large.url) ? att.sizes.large.url : att.url;
                $ctrl.find('.ft-bg-image-url').val(url).trigger('change');
                var $prev = $ctrl.find('.ft-bg-image-preview');
                $prev.css('display', 'inline-flex').find('img').attr('src', url);
                $ctrl.find('.ft-bg-image-remove').css('display', '');
                $ctrl.find('.ft-bg-image-urlrow').css('display', 'flex');
            }
        });
        frame.open();
    });

    $(document).on('click', '.ft-bg-control .ft-bg-image-urlmode', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        $ctrl.find('.ft-bg-image-urlrow').toggle();
    });

    $(document).on('click', '.ft-bg-control .ft-bg-image-urlok', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        var url = $ctrl.find('.ft-bg-image-urlinput').val().trim();
        if (!url) { return; }
        $ctrl.find('.ft-bg-image-url').val(url).trigger('change');
        var $prev = $ctrl.find('.ft-bg-image-preview');
        $prev.css('display', 'inline-flex').find('img').attr('src', url);
        $ctrl.find('.ft-bg-image-remove').css('display', '');
        $ctrl.find('.ft-bg-image-urlrow').css('display', 'flex');
    });

    $(document).on('click', '.ft-bg-control .ft-bg-image-remove', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        $ctrl.find('.ft-bg-image-url').val('').trigger('change');
        $ctrl.find('.ft-bg-image-preview').css('display', 'none');
        $ctrl.find('.ft-bg-image-remove').css('display', 'none');
        $ctrl.find('.ft-bg-image-urlrow').css('display', 'none');
    });

    // === Градиент: маркеры (стопы) ===
    // Перетаскивание маркера
    $(document).on('mousedown', '.ft-bg-control .ft-bg-grad-marker', function (e) {
        e.preventDefault();
        var $marker = $(this);
        var $ctrl = ftBgGetCtrl($marker);
        var $bar = $ctrl.find('.ft-bg-grad-bar');
        var moved = false;
        var startPx = e.clientX;
        var handle = function (ev) {
            var rect = $bar[0].getBoundingClientRect();
            var pct = Math.max(0, Math.min(100, (ev.clientX - rect.left) / rect.width * 100));
            if (Math.abs(ev.clientX - startPx) > 3) { moved = true; }
            $marker.css('left', pct + '%').attr('data-pos', pct.toFixed(1));
            $ctrl.data('ft-bg-grad-dirty', true);
            ftBgPreviewGrad($ctrl);
        };
        $(document).on('mousemove.ftbg-marker', handle);
        $(document).one('mouseup.ftbg-marker', function () {
            $(document).off('mousemove.ftbg-marker');
            if (!moved) { ftBgSelectMarker($ctrl, $marker, true); }
        });
    });

    // Клик по свободной части шкалы — добавить новый маркер
    $(document).on('click', '.ft-bg-control .ft-bg-grad-bar', function (e) {
        if ($(e.target).closest('.ft-bg-grad-marker').length) { return; }
        var $ctrl = ftBgGetCtrl($(this));
        if ($ctrl.find('.ft-bg-grad-marker').length >= 6) { return; }
        var rect = this.getBoundingClientRect();
        var pct = Math.max(0, Math.min(100, (e.clientX - rect.left) / rect.width * 100));
        var c = ftBgInterpGradColor($ctrl, pct);
        var $marker = ftBgAddMarker($ctrl, pct, c);
        if ($marker) { ftBgSelectMarker($ctrl, $marker, true); }
    });

    // Удаление активной точки
    $(document).on('click', '.ft-bg-control .ft-bg-remove-stop', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        var $markers = $ctrl.find('.ft-bg-grad-marker');
        if ($markers.length <= 2) { return; }
        var $active = $ctrl.find('.ft-bg-grad-marker.selected');
        if ($active.length) {
            $active.remove();
            $ctrl.data('ft-bg-grad-dirty', true);
            ftBgPreviewGrad($ctrl);
            $ctrl.find('.ft-bg-popover').removeClass('is-open');
            $ctrl.removeData('ft-bg-active-stop');
        }
    });

    // Добавить маркер в шкалу контрола
    function ftBgAddMarker($ctrl, pos, color) {
        var $bar = $ctrl.find('.ft-bg-grad-bar');
        var $m = $('<span class="ft-bg-grad-marker" data-pos="' + pos.toFixed(1) + '"></span>');
        var c = color || { r: 255, g: 255, b: 255, a: 1 };
        $m.css({ 'left': pos.toFixed(1) + '%', 'background-color': ftColors.css(c) });
        $bar.append($m);
        return $m;
    }

    // Выбрать маркер и (если надо) открыть палитру его цвета
    function ftBgSelectMarker($ctrl, $marker, open) {
        $ctrl.find('.ft-bg-grad-marker').removeClass('selected');
        $marker.addClass('selected');
        $ctrl.data('ft-bg-active-stop', $marker);
        if (open) {
            var color = ftColors.parse($marker.css('background-color')) || { r: 255, g: 255, b: 255, a: 1 };
            ftBgOpenPopover($ctrl, color, $marker);
        }
    }

    // Инициализация маркеров из сохранённого CSS-градиента
    function ftBgInitGradMarkers($ctrl) {
        var $bar = $ctrl.find('.ft-bg-grad-bar');
        if (!$bar.length) { return; }
        $bar.find('.ft-bg-grad-marker').remove();
        var css = $ctrl.find('.ft-bg-grad-input').val() || '';
        var stops = ftBgParseGradStops(css);
        if (stops && stops.length >= 2) {
            stops.forEach(function (s) {
                ftBgAddMarker($ctrl, s.pos, s.color);
            });
        } else {
            ftBgAddMarker($ctrl, 0, { r: 255, g: 255, b: 255, a: 1 });
            ftBgAddMarker($ctrl, 100, { r: 6, g: 147, b: 227, a: 1 });
        }
    }

    // Разбор стопов из CSS-строки градиента в [{color,pos}]
    function ftBgParseGradStops(css) {
        if (!css) { return null; }
        var stops = [];
        var re = /((?:rgba?\([^)]*\)|#[0-9a-fA-F]{3,8}))\s+(\d+(?:\.\d+)?)%/g;
        var m;
        while ((m = re.exec(css)) !== null) {
            var color = ftColors.parse(m[1]);
            var pos = parseFloat(m[2]);
            if (color) { stops.push({ color: color, pos: isFinite(pos) ? pos : 0 }); }
        }
        if (!stops.length) { return null; }
        stops.sort(function (a, b) { return a.pos - b.pos; });
        return stops;
    }

    // Цвет градиента в произвольной точке (линейная интерполяция между маркерами)
    function ftBgInterpGradColor($ctrl, pos) {
        var marks = ftBgGradMarkers($ctrl);
        if (!marks.length) { return null; }
        if (marks.length === 1) { return marks[0].color; }
        if (pos <= marks[0].pos) { return marks[0].color; }
        if (pos >= marks[marks.length - 1].pos) { return marks[marks.length - 1].color; }
        for (var i = 0; i < marks.length - 1; i++) {
            var a = marks[i], b = marks[i + 1];
            if (pos >= a.pos && pos <= b.pos) {
                var t = (pos - a.pos) / (b.pos - a.pos);
                return {
                    r: Math.round(a.color.r + (b.color.r - a.color.r) * t),
                    g: Math.round(a.color.g + (b.color.g - a.color.g) * t),
                    b: Math.round(a.color.b + (b.color.b - a.color.b) * t),
                    a: 1
                };
            }
        }
        return marks[marks.length - 1].color;
    }

    // Прогресс при вводе цвета в swatch (двойная прокрутка не нужна — это и есть палитра)
    $(document).on('change', '.ft-bg-control .ft-bg-grad-type', function () {
        var $ctrl = ftBgGetCtrl($(this));
        $ctrl.data('ft-bg-grad-dirty', true);
        ftBgPreviewGrad($ctrl);
    });

    // Круговой индикатор угла (как в WordPress components): 0° — верх, по часовой стрелке.
    // Поддержка отрицательных углов: левая половина круга даёт значения −180…0.
    function ftBgAngleValue($ctrl) {
        var v = parseInt($ctrl.find('.ft-bg-grad-angle').val(), 10);
        return isFinite(v) ? v : 180;
    }

    function ftBgSetAngle($ctrl, angle) {
        angle = Math.max(-360, Math.min(360, Math.round(angle)));
        $ctrl.find('.ft-bg-grad-angle').val(angle);
        $ctrl.find('.ft-bg-grad-angle-circle-indicator-wrapper').css('transform', 'rotate(' + angle + 'deg)');
        $ctrl.find('.ft-bg-grad-angle-circle').attr('aria-valuenow', angle);
        $ctrl.data('ft-bg-grad-dirty', true);
        ftBgPreviewGrad($ctrl);
    }

    $(document).on('mousedown', '.ft-bg-control .ft-bg-grad-angle-circle', function (e) {
        e.preventDefault();
        var $circle = $(this);
        var $ctrl = ftBgGetCtrl($circle);
        var handle = function (ev) {
            var rect = $circle[0].getBoundingClientRect();
            var cx = rect.left + rect.width / 2;
            var cy = rect.top + rect.height / 2;
            var deg = Math.round(Math.atan2(ev.clientY - cy, ev.clientX - cx) * 180 / Math.PI) + 90;
            if (deg > 180) { deg -= 360; }
            ftBgSetAngle($ctrl, deg);
        };
        handle(e);
        $(document).on('mousemove.ftbg-angle', handle);
        $(document).one('mouseup.ftbg-angle', function () {
            $(document).off('mousemove.ftbg-angle');
        });
    });

    // Колесо мыши над кругом — поворот на 1° за шаг
    $(document).on('wheel', '.ft-bg-control .ft-bg-grad-angle-circle', function (e) {
        e.preventDefault();
        var $ctrl = ftBgGetCtrl($(this));
        ftBgSetAngle($ctrl, ftBgAngleValue($ctrl) + Math.round(e.originalEvent.deltaY / 10));
    });

    // Стрелки на круге (role=slider)
    $(document).on('keydown', '.ft-bg-control .ft-bg-grad-angle-circle', function (e) {
        var $ctrl = ftBgGetCtrl($(this));
        var step = e.shiftKey ? 10 : 1;
        var angle = ftBgAngleValue($ctrl);
        if (e.key === 'ArrowUp' || e.key === 'ArrowRight') { e.preventDefault(); ftBgSetAngle($ctrl, angle + step); }
        else if (e.key === 'ArrowDown' || e.key === 'ArrowLeft') { e.preventDefault(); ftBgSetAngle($ctrl, angle - step); }
    });

    $(document).on('input change', '.ft-bg-control .ft-bg-grad-angle', function () {
        var $ctrl = ftBgGetCtrl($(this));
        ftBgSetAngle($ctrl, ftBgAngleValue($ctrl));
    });

    // Инициализация после загрузки
    $(document).ready(function () {
        $('.ft-bg-control').each(function () {
            var $ctrl = $(this);
            var $col = $ctrl.find('.ft-bg-color-input');
            var init = ftColors.parse($col.val());
            if (init) {
                $col.val(ftColors.hexCss(init));
            }
            ftBgRefreshClassic($ctrl);
            var type = ftBgGetType($ctrl);
            ftBgSetType($ctrl, type);
            ftBgInitGradMarkers($ctrl);
            var ang = $ctrl.find('.ft-bg-grad-angle').val() || 180;
            $ctrl.find('.ft-bg-grad-angle-circle-indicator-wrapper').css('transform', 'rotate(' + ang + 'deg)');
            $ctrl.find('.ft-bg-grad-angle-circle').attr('aria-valuenow', ang);
            ftBgPreviewGrad($ctrl);
        });
    });
})(jQuery);

// === Measure-контрол (ft-measure): скругление углов и размеры с единицами ===
(function ($) {
    function ftMeasValNum(v) {
        var n = parseFloat(String(v).replace(',', '.'));
        return isFinite(n) ? n : 0;
    }
    function ftMeasUnitFromCss(css, fallback) {
        var m = String(css || '').match(/(px|%|vh|vw|em|rem)$/);
        return m ? m[1] : fallback;
    }
    function ftMeasToString(parts, unit) {
        if (!parts || !parts.length) return '';
        var list = parts.map(function (v) {
            var n = Math.round(ftMeasValNum(v) * 1000) / 1000;
            return n + unit;
        });
        while (list.length > 1 && list[list.length - 1] === list[0]) {
            list.pop();
        }
        return list.join(' ');
    }
    function ftMeasCornersFromCss(css) {
        var parts = String(css || '').split(/\s+/).filter(function (p) { return p !== ''; });
        if (!parts.length) return [0, 0, 0, 0];
        if (parts.length === 1) return [parts[0], parts[0], parts[0], parts[0]];
        if (parts.length === 2) return [parts[0], parts[1], parts[0], parts[1]];
        if (parts.length === 3) return [parts[0], parts[1], parts[2], parts[1]];
        return parts.slice(0, 4);
    }
    function ftMeasIsEmpty($root) {
        return String($root.find('.ft-measure-value').val() || '').trim() === '';
    }
    function ftMeasRefresh($root) {
        // Пока значение не задано пользователем, скрытое поле не трогаем.
        // Иначе пустой контрол при загрузке страницы записал бы в форму «0px»
        // (числовые поля при пустом значении показывают 0), это значение ушло бы
        // в опцию устройства как явное и сломало бы наследование от Десктопа.
        if ($root.attr('data-ft-measure-empty') === '1' && ftMeasIsEmpty($root)) {
            return;
        }
        var $value = $root.find('.ft-measure-value');
        var unit = $root.find('.ft-measure__unit').val();
        var isRadius = $root.hasClass('ft-measure--radius');
        var isLinked = $root.find('.ft-measure__link').attr('aria-pressed') === 'true';
        if (isRadius) {
            var $cornerIn = $root.find('.ft-measure__corner-input');
            var parts = [];
            $cornerIn.each(function () { parts.push($(this).val()); });
            if (isLinked) {
                var single = $root.find('.ft-measure__single-input').val();
                $cornerIn.val(single);
                parts = [single, single, single, single];
            }
            $value.val(ftMeasToString(parts, unit));
        } else {
            var n = $root.find('.ft-measure__single-input').val();
            $value.val(String(Math.round(ftMeasValNum(n) * 1000) / 1000) + unit);
        }
    }
    function ftMeasSetLinked($root, linked) {
        var $link = $root.find('.ft-measure__link');
        $link.attr('aria-pressed', linked ? 'true' : 'false');
        var unit = $root.find('.ft-measure__unit').val();
        var $cornerIn = $root.find('.ft-measure__corner-input');
        var single = ftMeasValNum($link.closest('.ft-measure').find('.ft-measure__single-input').val());
        $root.find('.ft-measure__range').val(single);
        if (linked) {
            $cornerIn.val(single);
        }
        $root.find('.ft-measure__corners').toggle(!linked);
        $root.find('.ft-measure__single').toggle(linked);
        ftMeasRefresh($root);
    }
    function ftMeasInit($root) {
        var unitDef = ftMeasUnitFromCss($root.find('.ft-measure-value').val(), $root.find('.ft-measure__unit').val());
        if (ftMeasIsEmpty($root)) {
            $root.attr('data-ft-measure-empty', '1');
        } else {
            $root.removeAttr('data-ft-measure-empty');
        }
        var isRadius = $root.hasClass('ft-measure--radius');
        if (!isRadius) {
            var v = ftMeasValNum($root.find('.ft-measure-value').val());
            if ($root.hasClass('ft-measure--range-value')) {
                var hasValue = !ftMeasIsEmpty($root);
                $root.find('.ft-measure__range-wrap').toggle(hasValue);
                $root.find('.ft-measure__range').val(v);
                $root.find('.ft-measure__single-input').val(hasValue ? v : '');
            } else {
                $root.find('.ft-measure__range').val(v);
                $root.find('.ft-measure__single-input').val(v);
            }
        } else {
            var css = $root.find('.ft-measure-value').val();
            var parts = ftMeasCornersFromCss(css);
            var linked = parts.every(function (p) { return ftMeasValNum(p) === ftMeasValNum(parts[0]); });
            $root.find('.ft-measure__link').attr('aria-pressed', linked ? 'true' : 'false');
            $root.find('.ft-measure__single-input').val(ftMeasValNum(parts[0]));
            $root.find('.ft-measure__range').val(ftMeasValNum(parts[0]));
            $root.find('.ft-measure__corner-input').each(function (i) {
                $(this).val(ftMeasValNum(parts[i]));
            });
            $root.find('.ft-measure__corners').toggle(!linked);
            $root.find('.ft-measure__single').toggle(linked);
        }
        $root.find('.ft-measure__unit').val(unitDef);
        ftMeasRefresh($root);
    }
    $(document).on('click', '.ft-measure .ft-measure__link', function () {
        var $link = $(this);
        var linked = $link.attr('aria-pressed') !== 'true';
        ftMeasSetLinked($link.closest('.ft-measure'), linked);
    });
    $(document).on('input change', '.ft-measure .ft-measure__range', function () {
        var $root = $(this).closest('.ft-measure');
        $root.removeAttr('data-ft-measure-empty');
        var v = $(this).val();
        $root.find('.ft-measure__single-input').val(v);
        if ($root.find('.ft-measure__link').attr('aria-pressed') === 'true') {
            $root.find('.ft-measure__corner-input').val(v);
        }
        ftMeasRefresh($root);
    });
    $(document).on('input change', '.ft-measure .ft-measure__single-input', function () {
        var $root = $(this).closest('.ft-measure');
        $root.removeAttr('data-ft-measure-empty');
        var v = $(this).val();
        $root.find('.ft-measure__range').val(v);
        if ($root.find('.ft-measure__link').attr('aria-pressed') === 'true') {
            $root.find('.ft-measure__corner-input').val(v);
        }
        ftMeasRefresh($root);
    });
    $(document).on('input change', '.ft-measure .ft-measure__corner-input', function () {
        var $root = $(this).closest('.ft-measure');
        $root.removeAttr('data-ft-measure-empty');
        ftMeasRefresh($root);
    });
    $(document).on('change', '.ft-measure .ft-measure__unit', function () {
        var $root = $(this).closest('.ft-measure');
        $root.removeAttr('data-ft-measure-empty');
        ftMeasRefresh($root);
    });
    $(document).on('click', '.ft-measure .ft-measure__reset', function () {
        var $root = $(this).closest('.ft-measure');
        var resetMode = $root.attr('data-ft-reset-mode');
        if (resetMode === 'defaults' || resetMode === 'inherit') {
            var resetValue = resetMode === 'defaults' ? ($root.attr('data-default') || '') : '';
            $root.find('.ft-measure-value').val(resetValue);
            $root.find('.ft-measure__style').each(function () {
                var $select = $(this);
                var styleValue = resetMode === 'defaults' ? ($select.attr('data-default') || '') : '';
                $select.val(styleValue);
            });
            $root.find('.ft-bg-color-input').each(function () {
                var $input = $(this);
                var colorValue = resetMode === 'defaults' ? ($input.attr('data-default-color') || '') : '';
                $input.val(colorValue).trigger('input').trigger('change');
            });
            if (resetMode === 'inherit') {
                $root.attr('data-ft-measure-empty', '1');
            } else {
                $root.removeAttr('data-ft-measure-empty');
            }
            ftMeasInit($root);
            return;
        }
        if ($root.attr('data-ft-reset-to-defaults') === '1') {
            var defaultValue = $root.attr('data-default') || '';
            $root.find('.ft-measure-value').val(defaultValue);
            $root.removeAttr('data-ft-measure-empty');
            $root.find('.ft-measure__single-input').val(ftMeasValNum(defaultValue));
            $root.find('.ft-measure__range').val(ftMeasValNum(defaultValue));
            $root.find('.ft-measure__style').each(function () {
                var $select = $(this);
                $select.val($select.attr('data-default') || '');
            });
            $root.find('.ft-bg-color-input').each(function () {
                var $input = $(this);
                $input.val($input.attr('data-default-color') || '').trigger('change');
            });
            ftMeasInit($root);
            return;
        }
        // Сброс = «не задано», то есть наследовать (например, Десктоп).
        // Раньше сюда писался data-default, и поле становилось явным 0px/1px.
        $root.find('.ft-measure-value').val('');
        $root.attr('data-ft-measure-empty', '1');
        $root.find('.ft-measure__style').each(function () {
            var $sel = $(this);
            if ($sel.find('option[value=""]').length) {
                $sel.val('');
                return;
            }
            var sdef = $sel.attr('data-default');
            if (sdef && $sel.find('option[value="' + sdef + '"]').length) {
                $sel.val(sdef);
            } else {
                $sel.prop('selectedIndex', 0);
            }
        });
        $root.find('.ft-bg-reset').trigger('click');
        ftMeasInit($root);
    });
    $(document).ready(function () {
        $('.ft-measure').each(function () {
            ftMeasInit($(this));
        });
    });
})(jQuery);

jQuery(document).ready(function($) {
    // familyTreeAdmin нужен только для сетевых вызовов. Раньше здесь стоял
    // `return`, который молча отключал ВЕСЬ блок ниже (~1200 строк): сворачивание
    // разделов, табы устройств, табы «Обычный / При наведении» и палитры.
    // Любая потеря localize молча ломала сразу всё это — поэтому не выходим,
    // а подставляем заглушку.
    if (typeof familyTreeAdmin === 'undefined') {
        console.error('familyTreeAdmin object is not defined — используется заглушка');
        window.familyTreeAdmin = { ajaxurl: '', nonce: '' };
    }
    console.log('familyTreeAdmin object:', familyTreeAdmin); // Для отладки
    console.log('Nonce from familyTreeAdmin:', familyTreeAdmin.nonce); // Для отладки

    // === Сворачивание разделов страниц настроек ===
    // Регистрируется ПЕРВЫМ, чтобы любая ошибка в коде ниже не отключила
    // сворачивание: обработчик ниже в этом файле не сработал бы вообще.
    $(document).on('click', '.ft-settings-page .components-panel__body-title .components-panel__body-toggle', function(e) {
        e.preventDefault();
        var $body = $(this).closest('.components-panel__body');
        var isOpen = $body.toggleClass('is-opened').hasClass('is-opened');
        $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen && typeof window.ftFlushVisiblePickers === 'function') {
            window.ftFlushVisiblePickers($body);
        }
    });

    // По умолчанию разделы свёрнуты, открыт только первый в каждом табе.
    $('.ft-settings-page .ft-tab-panel').each(function () {
        $(this).find('.components-panel__body').each(function (i) {
            var $p = $(this);
            $p.toggleClass('is-opened', i === 0);
            $p.find('.components-panel__body-toggle')
              .attr('aria-expanded', i === 0 ? 'true' : 'false');
        });
    });

    // === Переключение вкладок страниц настроек (делегированная страховка) ===
    // Дублирует inline-скрипты обеих страниц: если те не успели привязаться из-за
    // ошибки в своём ready-блоке, вкладки всё равно переключаются.
    function ftActivateSettingsTab($page, id) {
        $page.find('.ft-tab-btn').removeClass('active');
        $page.find('.ft-tab-btn[data-tab="' + id + '"]').addClass('active');
        $page.find('.ft-tab-panel').removeClass('active');
        var $panel = $page.find('#' + id);
        if ($panel.length) { $panel.addClass('active'); }
        $page.find('.ft-settings-aside .ft-hint').removeClass('active');
        $page.find('.ft-settings-aside .ft-hint[data-hint="' + id + '"]').addClass('active');
        var storageKey = ($page.find('#ft-tab-tree').length) ? 'ft_tree_settings_active_tab' : 'ft_settings_active_tab';
        try { sessionStorage.setItem(storageKey, id); } catch (err) {}
        if (typeof window.ftFlushVisiblePickers === 'function' && $panel.length) {
            window.ftFlushVisiblePickers($panel);
        }
    }

    $(document).on('click', '.ft-settings-page .ft-tab-btn', function (e) {
        e.preventDefault();
        var id = $(this).data('tab');
        if (!id) { return; }
        ftActivateSettingsTab($(this).closest('.ft-settings-page'), id);
    });

    $('.ft-settings-page').each(function () {
        var $page = $(this);
        var storageKey = ($page.find('#ft-tab-tree').length) ? 'ft_tree_settings_active_tab' : 'ft_settings_active_tab';
        var saved = null;
        try { saved = sessionStorage.getItem(storageKey); } catch (err) {}
        var first = $page.find('.ft-tab-btn[data-tab]').first().data('tab');
        var target = (saved && $page.find('#' + saved).length) ? saved : (first || 'ft-tab-home');
        var $btn = $page.find('.ft-tab-btn[data-tab="' + target + '"]');
        if ($btn.length && !$btn.hasClass('active')) {
            ftActivateSettingsTab($page, target);
        }
    });

    // Сворачивание подразделов и стартовое состояние зарегистрированы выше,
    // в самом начале ready-блока.

    // Текущий раздел семейного архива (страницы разделов выводят скрытый инпут #ft-admin-section)
    var ftAdminSection = ($('#ft-admin-section').length ? $('#ft-admin-section').val() : 'photoalbums');

    // Лайтбокс GLightbox для «Открыть в полном размере» (тот же, что и во фронтенде)
    function ftInitAlbumLightbox() {
        if (typeof window.GLightbox === 'undefined') {
            return;
        }
        if (window.__ftAdminLightbox) {
            window.__ftAdminLightbox.destroy();
        }
        window.__ftAdminLightbox = window.GLightbox({
            selector: '.ft-admin-lightbox',
            touchNavigation: true,
            loop: true,
            keyboardNavigation: true,
            closeButton: true,
            openEffect: 'fade',
            closeEffect: 'fade',
            slideEffect: 'slide'
        });
    }
ftInitAlbumLightbox();

    // Лайтбокс для превью-картинок в подсказках страниц настроек (семейное древо / архив)
    function ftInitHintLightbox() {
        if (typeof window.GLightbox === 'undefined') {
            return;
        }
        window.__ftHintLightbox = window.GLightbox({
            selector: '.ft-hint-preview',
            touchNavigation: true,
            loop: false,
            keyboardNavigation: true,
            closeButton: true,
            openEffect: 'fade',
            closeEffect: 'fade'
        });
    }
    ftInitHintLightbox();

    // === Перестановка фотографий в альбомах (drag & drop с placeholder и FLIP-анимацией) ===
    var ftSortState = { dragEl: null, gridEl: null, lastTarget: null, dropRef: null, placeholderActive: false };

    function ftSnapshotPositions($items) {
        var map = {};
        $items.each(function () {
            var r = this.getBoundingClientRect();
            map[this] = { left: r.left, top: r.top };
        });
        return map;
    }

    function ftAnimateFrom(map, $grid) {
        var $items = $grid.find('.ft-album-editor-item');
        $items.each(function () {
            var from = map[this];
            if (!from) { return; }
            var r = this.getBoundingClientRect();
            this.style.transition = 'none';
            this.style.transform = 'translate(' + (from.left - r.left) + 'px, ' + (from.top - r.top) + 'px)';
        });
        void $grid[0].offsetWidth;
        $items.each(function () {
            this.style.transition = 'transform 0.3s cubic-bezier(0.22, 1, 0.36, 1)';
            this.style.transform = '';
        });
    }

    function ftShouldInsertBefore(ev, $tile) {
        var rect = $tile[0].getBoundingClientRect();
        var tileCy = rect.top + rect.height / 2;
        if (ftSortState.dragEl) {
            var dragRect = ftSortState.dragEl.getBoundingClientRect();
            if (Math.abs(tileCy - (dragRect.top + dragRect.height / 2)) > rect.height * 0.6) {
                return ev.clientY < tileCy;
            }
        }
        return ev.clientX < (rect.left + rect.width / 2);
    }

    var ftPlaceholder = (function () {
        var el = null;
        return {
            ensure: function (gridEl) {
                if (!el) {
                    el = document.createElement('div');
                    el.className = 'ft-album-editor-placeholder';
                    el.draggable = false;
                }
                if (el.parentNode !== gridEl) { gridEl.appendChild(el); }
                return el;
            },
            moveBefore: function (target) {
                if (el) { target.parentNode.insertBefore(el, target); }
            },
            moveAfter: function (target) {
                if (el && target.nextSibling) { target.parentNode.insertBefore(el, target.nextSibling); }
                else if (el && target.parentNode) { target.parentNode.appendChild(el); }
            },
            remove: function () {
                if (el && el.parentNode) { el.parentNode.removeChild(el); }
            }
        };
    })();

    function ftClearDragState() {
        if (ftSortState.dragEl) {
            $(ftSortState.dragEl).removeClass('ft-dragging');
            var $grid = $(ftSortState.gridEl);
            setTimeout(function () { $grid.find('.ft-album-editor-item').css({ transition: '', transform: '' }); }, 320);
        }
        ftPlaceholder.remove();
        ftSortState.dragEl = null;
        ftSortState.gridEl = null;
        ftSortState.lastTarget = null;
        ftSortState.dropRef = null;
        ftSortState.placeholderActive = false;
    }

    function ftSaveAlbumOrder($grid) {
        var photoIds = [];
        $grid.find('.ft-album-editor-item').each(function () {
            var id = $(this).data('photo-id');
            if (id !== undefined && id !== null && id !== '') { photoIds.push(String(id)); }
        });
        var postId = $grid.data('post-id');
        var albumIndex = $grid.data('album-index');
        if (!postId || !photoIds.length) { return; }
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_reorder_album_photos',
            post_id: postId,
            album_index: albumIndex,
            photo_ids: photoIds,
            section: ftAdminSection,
            nonce: familyTreeAdmin.nonce
        }, function (response) {
            if (response && response.success) {
                ftShowToast('Порядок фотографий сохранён');
                ftInitAlbumLightbox();

            } else {
                var msg = response && response.data && response.data.message ? response.data.message : 'Ошибка сохранения порядка';
                alert(msg);
            }
        });
    }

    function ftFinalizeReorder($grid) {
        var map = ftSnapshotPositions($grid.find('.ft-album-editor-item'));
        ftPlaceholder.remove();
        var ref = ftSortState.dropRef;
        if (ref && ref.tile && ref.tile.parentNode === $grid[0]) {
            if (ref.before) { $grid[0].insertBefore(ftSortState.dragEl, ref.tile); }
            else { $grid[0].insertBefore(ftSortState.dragEl, ref.tile.nextSibling); }
        }
        ftAnimateFrom(map, $grid);
        ftSaveAlbumOrder($grid);
        ftClearDragState();
    }

    function ftShowToast(msg) {
        var $t = $('#ft-sort-toast');
        if (!$t.length) { $t = $('<div id="ft-sort-toast"></div>').appendTo('body'); }
        $t.text(msg).addClass('ft-sort-toast-show');
        clearTimeout(ftShowToast._timer);
        ftShowToast._timer = setTimeout(function () { $t.removeClass('ft-sort-toast-show'); }, 1800);
    }

    $(document)
        .on('dragstart', '.ft-reorder-album-photos .ft-album-editor-item', function (e) {
            var $tile = $(this);
            var $grid = $tile.closest('.ft-reorder-album-photos');
            if (!$grid.length || $(e.target).closest('a, button').length) { return; }
            ftSortState.dragEl = this;
            ftSortState.gridEl = $grid[0];
            ftSortState.lastTarget = null;
            ftSortState.dropRef = null;
            ftSortState.placeholderActive = false;
            $tile.addClass('ft-dragging');
            var dt = e.originalEvent.dataTransfer;
            dt.effectAllowed = 'move';
            dt.setData('text/plain', String($tile.data('photo-id')));
        })
        .on('dragover', '.ft-reorder-album-photos', function (e) {
            if (!ftSortState.dragEl) { return; }
            e.preventDefault();
            var $grid = $(ftSortState.gridEl);
            var $tile = $(e.target).closest('.ft-album-editor-item');

            // Пустое место или сама перетаскиваемая плитка — оставляем плейсхолдер как есть
            if (!$tile.length || $tile[0] === ftSortState.dragEl) { return; }

            // «Магнит» на первое и последнее место, чтобы было легко встать в начало/конец
            var $items = $grid.find('.ft-album-editor-item');
            var isFirst = ($items[0] === $tile[0]);
            var isLast = ($items.length > 1 && $items[$items.length - 1] === $tile[0]);

            var before = isFirst ? true : (isLast ? false : ftShouldInsertBefore(e.originalEvent, $tile));

            var ref = ftSortState.dropRef;
            if (ref && ref.tile === $tile[0] && ref.before === before) { return; }
            ftSortState.dropRef = { tile: $tile[0], before: before };
            ftPlaceholder.ensure($grid[0]);
            if (before) { ftPlaceholder.moveBefore($tile[0]); }
            else { ftPlaceholder.moveAfter($tile[0]); }
            ftSortState.placeholderActive = true;
        })
        .on('drop', '.ft-reorder-album-photos', function (e) {
            if (!ftSortState.dragEl) { return; }
            e.preventDefault();
            var $tile = $(e.target).closest('.ft-album-editor-item');
            // Отпустили прямо на перетаскиваемой плитке — отменяем
            if ($tile.length && $tile[0] === ftSortState.dragEl) {
                ftClearDragState();
                return;
            }
            if (ftSortState.placeholderActive && ftSortState.dropRef) {
                ftFinalizeReorder($(ftSortState.gridEl));
            } else {
                ftClearDragState();
            }
        });

    $(document)
        .on('dragover.drop', function (e) { if (ftSortState.dragEl) { e.preventDefault(); } })
        .on('drop.drop', function (e) { if (ftSortState.dragEl) { e.preventDefault(); ftClearDragState(); } })
        .on('dragend', function () { if (ftSortState.dragEl) { ftClearDragState(); } });

    // === Перестановка альбомов в таблице (drag & drop строк) ===
    var ftRowSortState = { dragEl: null, tbodyEl: null, dropRef: null, placeholder: null };

    function ftRowGetPlaceholder() {
        if (!ftRowSortState.placeholder) {
            var ph = document.createElement('tr');
            ph.className = 'ft-album-row-placeholder';
            ph.innerHTML = '<td colspan="100"></td>';
            ph.draggable = false;
            ftRowSortState.placeholder = ph;
        }
        return ftRowSortState.placeholder;
    }

    function ftRowEnsurePlaceholder(tbody) {
        var ph = ftRowGetPlaceholder();
        if (ph.parentNode !== tbody) { tbody.appendChild(ph); }
        return ph;
    }

    function ftRowMovePlaceholder(tr, before) {
        var ph = ftRowGetPlaceholder();
        if (!ph.parentNode) { return; }
        if (before) { tr.parentNode.insertBefore(ph, tr); }
        else { tr.parentNode.insertBefore(ph, tr.nextSibling); }
    }

    function ftRowClearState() {
        var ph = ftRowSortState.placeholder;
        if (ph && ph.parentNode) { ph.parentNode.removeChild(ph); }
        if (ftRowSortState.dragEl) { $(ftRowSortState.dragEl).removeClass('ft-row-dragging'); }
        ftRowSortState.dragEl = null;
        ftRowSortState.tbodyEl = null;
        ftRowSortState.dropRef = null;
        ftRowSortState.placeholder = null;
    }

    function ftRowSaveOrder($tbody) {
        var order = [];
        $tbody.find('tr.ft-album-row').each(function () {
            order.push(String($(this).data('album-index')));
        });
        var postId = $tbody.data('post-id');
        if (!postId || !order.length) { return; }
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_reorder_albums',
            post_id: postId,
            album_order: order,
            section: ftAdminSection,
            nonce: familyTreeAdmin.nonce
        }, function (response) {
            if (response && response.success) {
                location.reload();
            } else {
                alert((response && response.data && response.data.message) ? response.data.message : 'Ошибка сохранения порядка альбомов');
                location.reload();
            }
        });
    }

    // Элементы внутри строк (формы переименования, кнопки, ссылки) не должны сами быть перетаскиваемыми
    $('.ft-reorder-albums tr.ft-album-row').find('input, button, select, textarea, a').attr('draggable', 'false');

    $(document)
        .on('dragstart', '.ft-reorder-albums tr.ft-album-row', function (e) {
            if ($(e.target).closest('input, button, select, textarea, a').length) { return; }
            var $tr = $(this);
            var $tbody = $tr.closest('tbody.ft-reorder-albums');
            if (!$tbody.length) { return; }
            ftRowSortState.dragEl = this;
            ftRowSortState.tbodyEl = $tbody[0];
            ftRowSortState.dropRef = null;
            $tr.addClass('ft-row-dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/plain', String($tr.data('album-index')));
        })
        .on('dragover', '.ft-reorder-albums', function (e) {
            if (!ftRowSortState.dragEl) { return; }
            e.preventDefault();
            var $tbody = $(ftRowSortState.tbodyEl);
            var $tr = $(e.target).closest('tr.ft-album-row');
            if (!$tr.length || $tr[0] === ftRowSortState.dragEl) { return; }
            var rows = $tbody.find('tr.ft-album-row');
            var isFirst = (rows[0] === $tr[0]);
            var isLast = (rows.length > 1 && rows[rows.length - 1] === $tr[0]);
            var before = isFirst ? true : (isLast ? false : (e.originalEvent.clientY < $tr[0].getBoundingClientRect().top + $tr[0].getBoundingClientRect().height / 2));
            var ref = ftRowSortState.dropRef;
            if (ref && ref.tr === $tr[0] && ref.before === before) { return; }
            ftRowSortState.dropRef = { tr: $tr[0], before: before };
            ftRowEnsurePlaceholder($tbody[0]);
            ftRowMovePlaceholder($tr[0], before);
        })
        .on('drop', '.ft-reorder-albums', function (e) {
            if (!ftRowSortState.dragEl) { return; }
            e.preventDefault();
            var $tbody = $(ftRowSortState.tbodyEl);
            var dragEl = ftRowSortState.dragEl;
            var ph = ftRowGetPlaceholder();
            var saved = ftRowSortState.dropRef;
            if (saved && ph && ph.parentNode) {
                ph.parentNode.insertBefore(dragEl, ph);
                ph.parentNode.removeChild(ph);
                ftRowClearState();
                ftRowSaveOrder($tbody);
            } else {
                ftRowClearState();
            }
        });

    $(document)
        .on('dragover.rowdrop', function (e) { if (ftRowSortState.dragEl) { e.preventDefault(); } })
        .on('drop.rowdrop', function (e) { if (ftRowSortState.dragEl) { e.preventDefault(); ftRowClearState(); } })
        .on('dragend', function () { if (ftRowSortState.dragEl) { ftRowClearState(); } });

    // === Модалка «Обложка и описание альбома» ===
    var ftAlbumMeta = { postId: 0, albumIndex: 0, coverId: 0, coverUrls: {} };

    function ftRenderModalCover() {
        var $preview = $('#ft-cover-preview');
        if (ftAlbumMeta.coverId && ftAlbumMeta.coverUrls[ftAlbumMeta.coverId]) {
            $preview.html('<img src="' + ftAlbumMeta.coverUrls[ftAlbumMeta.coverId] + '" alt="">');
        } else {
            $preview.html('<span class="dashicons dashicons-format-image"></span>');
        }
    }

    function ftOpenAlbumMetaModal($btn) {
        ftAlbumMeta.postId = $btn.data('post-id');
        ftAlbumMeta.albumIndex = $btn.data('album-index');
        ftAlbumMeta.coverId = parseInt($btn.data('cover') || '0', 10) || 0;
        var coverUrl = $btn.data('cover-url') || '';
        ftAlbumMeta.coverUrls = {};
        if (ftAlbumMeta.coverId && coverUrl) { ftAlbumMeta.coverUrls[ftAlbumMeta.coverId] = coverUrl; }
        $('#ft-album-description').val($btn.data('description') || '');
        ftRenderModalCover();
        $('#ft-album-meta-modal').addClass('ft-modal-open').attr('aria-hidden', 'false');
    }

    function ftCloseAlbumMetaModal() {
        $('#ft-album-meta-modal').removeClass('ft-modal-open').attr('aria-hidden', 'true');
    }

    function ftOpenCoverPicker(callback) {
        if (typeof wp === 'undefined' || !wp.media) {
            alert('Медиабиблиотека не загружена. Обновите страницу.');
            return;
        }
        var frame = wp.media({
            title: 'Выберите обложку альбома',
            button: { text: 'Выбрать обложку' },
            library: { type: 'image' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            if (att && att.id) {
                callback(att);
            }
        });
        frame.open();
    }

    $(document)
        .on('click', '.ft-album-meta-btn', function (e) {
            e.preventDefault();
            ftOpenAlbumMetaModal($(this));
        })
        .on('click', '#ft-cover-pick', function () {
            ftOpenCoverPicker(function (att) {
                var url = (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ? att.sizes.thumbnail.url : att.url;
                ftAlbumMeta.coverId = parseInt(att.id, 10) || 0;
                if (ftAlbumMeta.coverId) { ftAlbumMeta.coverUrls[ftAlbumMeta.coverId] = url; }
                ftRenderModalCover();
            });
        })
        .on('click', '#ft-cover-clear', function () {
            ftAlbumMeta.coverId = 0;
            ftRenderModalCover();
        })
        .on('click', '#ft-album-meta-save', function () {
            if (!ftAlbumMeta.postId) { return; }
            $.post(familyTreeAdmin.ajaxurl, {
                action: 'family_tree_update_album_meta',
                post_id: ftAlbumMeta.postId,
                album_index: ftAlbumMeta.albumIndex,
                cover: ftAlbumMeta.coverId,
                description: $('#ft-album-description').val(),
                section: ftAdminSection,
                nonce: familyTreeAdmin.nonce
            }, function (response) {
                if (response && response.success) {
                    location.reload();
                } else {
                    alert((response && response.data && response.data.message) ? response.data.message : 'Ошибка сохранения');
                }
            });
        })
        .on('click', '[data-ft-modal-close]', function () { ftCloseAlbumMetaModal(); });

    $(document)
        .on('keydown', function (e) {
            if (e.key === 'Escape' && $('#ft-album-meta-modal').hasClass('ft-modal-open')) {
                ftCloseAlbumMetaModal();
            }
        });

// Функция для настройки Select2
    function configureSelect2($element) {
        if (typeof $.fn.select2 !== 'function') { return; }
        var isMultiple = $element.prop('multiple');
        var placeholder = $element.data('placeholder') || 'Поиск...';
        $element.select2({
            ajax: {
                url: familyTreeAdmin.ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    var requestData = {
                        action: 'search_family_members',
                        nonce: familyTreeAdmin.nonce,
                        search: params.term,
                        exclude: $('#post_ID').val() || ''
                    };
                    console.log('Sending AJAX request ', requestData);
                    return requestData;
                },
                processResults: function (data) {
                    console.log('Received AJAX response:', data);
                    if (data && typeof data === 'object') {
                        if (data.success === true && Array.isArray(data.data)) {
                            return {
                                results: data.data
                            };
                        } else if (data.success === false) {
                            console.error('Server returned an error:', data.data);
                            return {
                                results: []
                            };
                        }
                    }
                    console.error('Unexpected data format from AJAX:', data);
                    return {
                        results: []
                    };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: placeholder,
            allowClear: true,
            multiple: isMultiple,
            language: {
                inputTooShort: function(args) {
                    var remainingChars = args.minimum - args.input.length;
                    return "Введите ещё " + remainingChars + " символ" + (remainingChars % 10 == 1 && remainingChars != 11 ? "" : (remainingChars % 10 >= 2 && remainingChars % 10 <= 4 && (remainingChars < 10 || remainingChars > 20) ? "а" : "ов")) + "...";
                },
                searching: function() {
                    return "Поиск...";
                },
                noResults: function() {
                    return "Ничего не найдено";
                },
                loadingMore: function() {
                    return "Загрузка данных...";
                },
                errorLoading: function() {
                    return "Ошибка загрузки результатов";
                }
            }
        });
    }
    
    // Инициализация Select2 для всех существующих селектов
    $('.family-member-select').each(function() {
        configureSelect2($(this));
    });
    
    // Показ/скрытие поля девичьей фамилии
    $('#family_member_gender').change(function() {
        if ($(this).val() === 'female') {
            $('#maiden_name_row').show();
        } else {
            $('#maiden_name_row').hide();
        }
        // Обновляем видимость полей смерти
        updateDeathFieldsVisibility();
    }).trigger('change');

    // --- Логика радиокнопок "Человек жив/умер" ---
    function updateDeathFieldsVisibility() {
        if ($('input[name="family_member_is_deceased"]:checked').val() === '1') {
            $('.death-fields').show();
        } else {
            $('.death-fields').hide();
        }
    }

    $(document).on('change', 'input[name="family_member_is_deceased"]', updateDeathFieldsVisibility);
    updateDeathFieldsVisibility();

    // --- Логика для чекбоксов "по старому стилю" ---
    // Для даты рождения
    $('#family_member_birth_old_style').change(function() {
        if ($(this).is(':checked')) {
            $('#birth_new_style_fields').show();
        } else {
            $('#birth_new_style_fields').hide();
        }
    });

    // Для даты смерти
    $('#family_member_death_old_style').change(function() {
        if ($(this).is(':checked')) {
            $('#death_new_style_fields').show();
        } else {
            $('#death_new_style_fields').hide();
        }
    });

    // Добавление супруга
    let spouseIndex = $('.spouse-entry').length;
    $('#add-spouse').click(function() {
        const spouseHtml = `
            <div class="spouse-entry" data-index="${spouseIndex}" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #fafafa;">
                <select name="family_member_spouses[${spouseIndex}][id]" class="family-member-select" data-placeholder="Поиск супруга(и)..." style="width: 100%; margin-bottom: 15px;">
                    <option value="">Выберите супруга(у)</option>
                </select>
                
                <div class="marriage-date" style="margin-bottom: 15px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Дата брака:</label>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="number" name="family_member_spouses[${spouseIndex}][married_day]" min="1" max="31" placeholder="ДД" class="small-text" style="width: 60px;">
                        <input type="number" name="family_member_spouses[${spouseIndex}][married_month]" min="1" max="12" placeholder="ММ" class="small-text" style="width: 60px;">
                        <input type="number" name="family_member_spouses[${spouseIndex}][married_year]" min="1000" max="2100" placeholder="ГГГГ" class="small-text" style="width: 80px;">
                    </div>
                </div>
                
                <div class="divorce-date" style="margin-bottom: 15px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Дата развода:</label>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="number" name="family_member_spouses[${spouseIndex}][divorced_day]" min="1" max="31" placeholder="ДД" class="small-text" style="width: 60px;">
                        <input type="number" name="family_member_spouses[${spouseIndex}][divorced_month]" min="1" max="12" placeholder="ММ" class="small-text" style="width: 60px;">
                        <input type="number" name="family_member_spouses[${spouseIndex}][divorced_year]" min="1000" max="2100" placeholder="ГГГГ" class="small-text" style="width: 80px;">
                    </div>
                    <label style="display: flex; align-items: center; margin-top: 10px; font-weight: normal;">
                        <input type="checkbox" name="family_member_spouses[${spouseIndex}][divorced_unknown]" value="1" id="divorced_unknown_${spouseIndex}" style="margin-right: 8px;">
                        Развод (дата неизвестна)
                    </label>
                </div>
                
                <button type="button" class="remove-spouse button">Удалить супруга(у)</button>
            </div>
        `;
        $('#spouses-container').append(spouseHtml);
        // Инициализация Select2 для нового селекта
        var $newSelect = $('#spouses-container').find('.spouse-entry:last .family-member-select');
        configureSelect2($newSelect);
        spouseIndex++;
    });

    // Удаление супруга
    $(document).on('click', '.remove-spouse', function() {
        if (confirm('Вы уверены, что хотите удалить этого супруга?')) {
            $(this).closest('.spouse-entry').remove();
        }
    });
    
    // Автоматическое обновление дат при изменении супругов
    $(document).on('change', 'input[name*="married_day"], input[name*="divorced_day"], input[name*="divorced_unknown"]', function() {
        const $input = $(this);
        const fieldName = $input.attr('name');
        const value = $input.val();
        const checked = $input.is(':checked');
        // Показываем уведомление о синхронизации
        if (!$input.hasClass('sync-notification-shown')) {
            $input.addClass('sync-notification-shown');
            const $notification = $('<div class="sync-notification" style="color: #00a0d2; font-size: 12px; margin-top: 5px;">Данные синхронизированы с записью супруга</div>');
            // Вставляем уведомление после родительского контейнера дат
            if ($input.closest('.marriage-date').length) {
                $input.closest('.marriage-date').after($notification);
            } else if ($input.closest('.divorce-date').length) {
                $input.closest('.divorce-date').after($notification);
            } else {
                $input.closest('.spouse-entry').append($notification);
            }
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
                $input.removeClass('sync-notification-shown');
            }, 3000);
        }
    });
    
    // Проверка лимита персоналий
    if ($('#post_type').val() === 'family_member' && typeof familyTreeAdmin !== 'undefined') {
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_check_limit',
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (response.success === false && response.data === 'limit_reached') {
                $('#publish').prop('disabled', true);
                $('.misc-pub-section').after('<div class="notice notice-error"><p>Достигнут лимит в 50 персоналий. Приобретите Pro-версию для снятия ограничений.</p></div>');
            }
        });
    }
    
    // Обработка кнопок на странице настроек
    $('#activate-license').click(function() {
        var licenseKey = $('input[name="family_tree_license_key"]').val();
        if (!licenseKey) {
            alert('Пожалуйста, введите лицензионный ключ');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'family_tree_activate_license',
            license_key: licenseKey,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (response.success) {
                alert('Лицензия успешно активирована!');
                location.reload();
            } else {
                alert('Ошибка активации: ' + response.data);
            }
        });
    });
    
    $('#deactivate-license').click(function() {
        if (!confirm('Вы уверены, что хотите деактивировать лицензию?')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'family_tree_deactivate_license',
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (response.success) {
                alert('Лицензия успешно деактивирована!');
                location.reload();
            } else {
                alert('Ошибка деактивации: ' + response.data);
            }
        });
    });
    
    $('#export-gedcom').click(function() {
        window.location.href = ajaxurl + '?action=family_tree_export_gedcom&nonce=' + familyTreeAdmin.nonce;
    });

    // === Фотоальбомы ===

    // Открывает медиабиблиотеку с мультивыбором изображений
    function ftOpenMediaUploader(callback) {
        if (typeof wp === 'undefined' || !wp.media) {
            alert('Медиабиблиотека не загружена');
            return;
        }
        var frame = wp.media({
            title: 'Выберите фотографии для альбома',
            button: { text: 'Добавить в альбом' },
            multiple: true,
            library: { type: 'image' }
        });
        frame.on('select', function() {
            var selection = frame.state().get('selection').toJSON();
            var ids = [];
            $.each(selection, function(i, attachment) {
                if (attachment && attachment.id) {
                    ids.push(attachment.id);
                }
            });
            callback(ids);
        });
        frame.open();
    }

// Перерисовывает метабокс раздела семейного архива персоны
    function ftReloadAlbumMetabox(postId, section) {
        section = section || 'photoalbums';
        var $old = $('.ft-album-metabox[data-post-id="' + postId + '"][data-section="' + section + '"]');
        if (!$old.length) {
            return;
        }
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_get_album_metabox',
            post_id: postId,
            section: section,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (response.success) {
                $old.replaceWith(response.data.html);
                ftInitAlbumLightbox();
            } else {
                alert(response.data && response.data.message ? response.data.message : 'Ошибка обновления раздела');
            }
        });
    }

    // Кнопка «Добавить фотоальбом» в колонке списка членов семьи
    $(document).on('click', '.ft-add-album-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var postId = $btn.data('post-id');
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_create_album',
            post_id: postId,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (response.success) {
                ftReloadAlbumMetabox(postId);
            } else {
                alert(response.data && response.data.message ? response.data.message : 'Ошибка создания альбома');
            }
        });
    });

    // === Режим отображения фотоальбомов персоны (метабокс) ===
    function ftAlbumDisplayValues($box) {
        var mode = $box.find('.ft-album-display-mode:checked').val() || 'all';
        var album = $box.find('.ft-album-display-album').val() || '';
        var albums = $box.find('.ft-album-display-albums').val() || [];
        if (!$.isArray(albums)) {
            albums = albums ? [albums] : [];
        }
        return { mode: mode, album: album, albums: albums };
    }

    function ftAlbumDisplaySave($box) {
        var postId = $box.data('post-id');
        var section = $box.data('section') || 'photoalbums';
        var v = ftAlbumDisplayValues($box);
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_update_album_display',
            post_id: postId,
            section: section,
            mode: v.mode,
            album: v.album,
            albums: v.albums,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (!response.success) {
                alert(response.data && response.data.message ? response.data.message : 'Ошибка сохранения режима');
            }
        });
    }

    function ftAlbumDisplayPreview($box) {
        var postId = $box.data('post-id');
        var section = $box.data('section') || 'photoalbums';
        var v = ftAlbumDisplayValues($box);
        var $preview = $box.find('.ft-album-display-preview');
        if (!$preview.length) {
            return;
        }
        $preview.addClass('ft-loading').html('<p style="color:#646970;">' + 'Загрузка предпросмотра...' + '</p>');
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_get_album_preview',
            post_id: postId,
            section: section,
            mode: v.mode,
            album: v.album,
            albums: v.albums,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            $preview.removeClass('ft-loading');
            if (response.success) {
                $preview.html(response.data.html);
            } else {
                $preview.html('<p>' + (response.data && response.data.message ? response.data.message : 'Ошибка предпросмотра') + '</p>');
            }
        });
    }

    function ftAlbumDisplaySync($box) {
        var v = ftAlbumDisplayValues($box);
        var $single = $box.find('.ft-album-display-album');
        var $multi = $box.find('.ft-album-display-albums');
        var $hint = $box.find('.ft-album-display-album-hint');
        $box.find('.ft-album-display-album-row').toggle(v.mode === 'album_photos' || v.mode === 'select_albums');
        $single.toggle(v.mode === 'album_photos');
        $multi.toggle(v.mode === 'select_albums');
        $hint.toggle(v.mode === 'select_albums');
        ftAlbumDisplayPreview($box);
        ftAlbumDisplaySave($box);
    }

    $(document).on('change', '.ft-album-display-mode', function() {
        ftAlbumDisplaySync($(this).closest('.ft-album-metabox'));
    });

    $(document).on('change', '.ft-album-display-album', function() {
        ftAlbumDisplaySync($(this).closest('.ft-album-metabox'));
    });

    $(document).on('change', '.ft-album-display-albums', function() {
        ftAlbumDisplaySync($(this).closest('.ft-album-metabox'));
    });

    // === Тумблер «Включено/Выключено» раздела для персоны (метабокс) ===
    $(document).on('change', '.ft-section-toggle-input', function() {
        var $input = $(this);
        var $toggle = $input.closest('.ft-section-toggle');
        var postId = $toggle.data('post-id');
        var section = $toggle.data('section');
        var enabled = $input.is(':checked');
        var previous = !enabled;
        var $state = $toggle.find('.ft-section-toggle-state');
        var applyState = function(isOn) {
            $toggle.toggleClass('ft-off', !isOn);
            $state.text(isOn ? 'Включено' : 'Выключено');
        };
        applyState(enabled);
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_toggle_section',
            post_id: postId,
            section: section,
            enabled: enabled ? '1' : '0',
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (!response.success) {
                $input.prop('checked', previous);
                applyState(previous);
                alert(response.data && response.data.message ? response.data.message : 'Ошибка переключения раздела');
            }
        }).fail(function() {
            $input.prop('checked', previous);
            applyState(previous);
            alert('Ошибка переключения раздела');
        });
    });

    // Кнопка «Создать фотоальбом» / «Добавить альбом» в метабоксе
    $(document).on('click', '.ft-create-album-btn', function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_create_album',
            post_id: postId,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (response.success) {
                ftReloadAlbumMetabox(postId);
            } else {
                alert(response.data && response.data.message ? response.data.message : 'Ошибка создания альбома');
            }
        });
    });

    // Кнопка «Создать папку/источник/адресата» на странице раздела семейного архива
    $(document).on('click', '.ft-create-section-folder-btn', function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        var name = $.trim($('#new_album_name').val() || '');
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_create_album',
            post_id: postId,
            album_name: name,
            section: ftAdminSection,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data && response.data.message ? response.data.message : 'Ошибка создания папки');
            }
        });
    });

    // Переименование альбома (по blur на инпуте)
    $(document).on('blur', '.ft-album-name-input', function() {
        var $input = $(this);
        var postId = $input.data('post-id');
        var albumIndex = $input.data('album-index');
        var newName = $.trim($input.val());
        if (!newName) {
            return;
        }
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_rename_album',
            post_id: postId,
            album_index: albumIndex,
            album_name: newName,
            section: ftAdminSection,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (!response.success) {
                alert(response.data && response.data.message ? response.data.message : 'Ошибка переименования');
            }
        });
    });

    // Удаление альбома
    $(document).on('click', '.ft-delete-album-btn', function(e) {
        e.preventDefault();
        if (!confirm('Удалить альбом со всеми фотографиями?')) {
            return;
        }
        var postId = $(this).data('post-id');
        var albumIndex = $(this).data('album-index');
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_delete_album',
            post_id: postId,
            album_index: albumIndex,
            section: ftAdminSection,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (response.success) {
                ftReloadAlbumMetabox(postId);
            } else {
                alert(response.data && response.data.message ? response.data.message : 'Ошибка удаления альбома');
            }
        });
    });

    // Кнопка «Добавить фотографии» (метабокс и список)
    $(document).on('click', '.ft-add-album-photos-btn', function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        var albumIndex = $(this).data('album-index') !== undefined ? $(this).data('album-index') : 0;
        ftOpenMediaUploader(function(ids) {
            if (!ids.length) {
                return;
            }
            $.post(familyTreeAdmin.ajaxurl, {
                action: 'family_tree_add_album_photos',
                post_id: postId,
                album_index: albumIndex,
                photo_ids: ids,
                section: ftAdminSection,
                nonce: familyTreeAdmin.nonce
            }, function(response) {
                if (response.success) {
                    var isEditor = $('.ft-album-metabox[data-post-id="' + postId + '"]').length > 0;
                    if (isEditor) {
                        ftReloadAlbumMetabox(postId);
                    } else if ($('.ft-reorder-album-photos[data-post-id="' + postId + '"]').length) {
                        location.reload();
                    }
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Ошибка добавления фотографий');
                }
            });
        });
    });

    // Кнопка удаления фото из альбома (метабокс)
    $(document).on('click', '.ft-remove-album-photo-btn', function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        var albumIndex = $(this).data('album-index') !== undefined ? $(this).data('album-index') : 0;
        var photoId = $(this).data('photo-id');
        if (!confirm('Удалить фотографию из альбома?')) {
            return;
        }
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_remove_album_photo',
            post_id: postId,
            album_index: albumIndex,
            photo_id: photoId,
            section: ftAdminSection,
            nonce: familyTreeAdmin.nonce
        }, function(response) {
            if (response.success) {
                ftReloadAlbumMetabox(postId);
            } else {
                alert(response.data && response.data.message ? response.data.message : 'Ошибка удаления фотографии');
            }
        });
    });

    // === Настройки: показ кастомизации «Альбом …» и описания по галочкам активности ===
    function ftSyncAlbumContentCustom() {
        if ($('#ft-subheader-custom').length) {
            $('#ft-subheader-custom').toggle($('#ft-album-show-subheader').is(':checked'));
        }
        if ($('#ft-description-custom').length) {
            $('#ft-description-custom').toggle($('#ft-album-show-description').is(':checked'));
        }
    }
    $(document).on('change', '#ft-album-show-subheader, #ft-album-show-description', ftSyncAlbumContentCustom);
    ftSyncAlbumContentCustom();

    // === Подсказка про метабоксы на странице редактирования персоналии ===
    (function() {
        var $tooltip = $('#ft-metabox-tooltip');
        if (!$tooltip.length) {
            return;
        }

        function ftHideMetaboxTooltip() {
            $tooltip.removeClass('ft-metabox-tooltip--show');
            setTimeout(function() {
                $tooltip.remove();
            }, 300);
        }

        $tooltip.on('click', '.ft-metabox-tooltip-close', function(e) {
            e.preventDefault();
            ftHideMetaboxTooltip();
        });

        $tooltip.on('change', '#ft_metabox_tooltip_dont_show', function() {
            var dismiss = this.checked ? 1 : 0;
            $.post(familyTreeAdmin.ajaxurl, {
                action: 'ft_dismiss_metabox_tooltip',
                nonce: familyTreeAdmin.nonce,
                dismiss: dismiss
            }, function(response) {
                if (dismiss) {
                    ftHideMetaboxTooltip();
                }
            });
        });

        $tooltip.addClass('ft-metabox-tooltip--show');
    })();

    // Сворачивание подразделов зарегистрировано в начале ready-блока.

    // Ленивая инициализация цветов: при переключении «Обычный / При наведении»
    $(document).on('change', '.ft-settings-page .ft-state-tabs input[type="radio"]', function() {
        if (typeof window.ftFlushVisiblePickers === 'function') {
            window.ftFlushVisiblePickers($(this).closest('.ft-state-cell'));
        }
    });

    // === Тумблер «Приватность» в списке персоналий ===
    $(document).on('click', '.ft-privacy-toggle[data-post-id]', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var postId = $btn.data('post-id');
        if ($btn.hasClass('is-busy')) {
            return;
        }
        $btn.addClass('is-busy');
        var prevPrivate = $btn.hasClass('is-private');
        $.post(familyTreeAdmin.ajaxurl, {
            action: 'family_tree_toggle_member_privacy',
            nonce: familyTreeAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response && response.success) {
                var isPrivate = response.data.private;
                $btn.toggleClass('is-private', isPrivate);
                $btn.attr('aria-pressed', isPrivate ? 'true' : 'false');
                $btn.find('.ft-privacy-toggle-label').text(isPrivate ? 'Скрыта' : 'Видна');
                $btn.attr('title', isPrivate ? 'Скрыта администратором' : 'Видна посетителям');
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : 'Ошибка переключения приватности';
                alert(msg);
                $btn.toggleClass('is-private', prevPrivate);
            }
        }).always(function() {
            $btn.removeClass('is-busy');
        });
    });

    // === Общие переключатели: устройства и состояния ===
    // Раньше эта логика жила инлайном в archive_settings_page(), поэтому на
    // «Странице персоны» не выполнялась вовсе: табы не нажимались, а палитра
    // цвета не инициализировалась. admin.js подключён на обоих экранах.

    // Табы устройств. Рамка с панелями — соседняя сестра контейнера кнопок
    // (.ft-device-options), а не самих кнопок, поэтому ищем от контейнера
    // вверх: у $btn.siblings() рамки нет, и панели не переключались вовсе.
    $(document).on('click', '.ft-device-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.hasClass('is-active')) {
            return;
        }
        var dev   = $btn.attr('data-ft-device');
        var group = $btn.attr('data-ft-device-group');
        var $grp  = $btn.closest('.ft-device-options')
                        .siblings('.ft-device-frame[data-ft-device-frame="' + group + '"]');
        if (!$grp.length) {
            $grp = $btn.closest('.ft-device-frame');
        }
        if (!$grp.length) {
            $grp = $btn.parent().siblings('.ft-device-frame[data-ft-device-frame="' + group + '"]');
        }
        $btn.siblings('.ft-device-btn').removeClass('is-active').attr('aria-pressed', 'false');
        $btn.addClass('is-active').attr('aria-pressed', 'true');
        if ($grp.length) {
            $grp.find('.ft-device-panel').hide();
            $grp.find('.ft-device-panel[data-ft-device-panel="' + dev + '"]').show();
        }
        // Открытые палитры/поповеры внутри скрытых панелей нужно закрыть.
        if (typeof window.ftFlushVisiblePickers === 'function') {
            window.ftFlushVisiblePickers($grp.length ? $grp : $btn.parent());
        }
    });

    // Табы «Обычный / При наведении».
    $(document).on('change', '.ft-state-tabs input[type="radio"]', function() {
        $(this).closest('.ft-state-cell').attr('data-state', $(this).val());
    });
    $(document).on('change', '.ft-album-shadow-toggle', function() {
        $(this).closest('.ft-album-shadow-control').find('.ft-album-shadow-options').toggle(this.checked);
    });
    function ftToggleAlbumCaptionSettings() {
        var visible = $('#ft-album-captions').is(':checked');
        var $captionSettings = $('.ft-album-caption-position-settings');
        $captionSettings.toggle(visible);
        $captionSettings.prev('.ft-genius_plugin-separator').toggle(visible);
    }
    if ($('#ft-album-captions').length) {
        ftToggleAlbumCaptionSettings();
        $(document).on('change', '#ft-album-captions', ftToggleAlbumCaptionSettings);
    }

    // Выбор цвета в этих рядах идёт через общий контрол .ft-bg-control
    // (свотч + HEX + поповер-палитра) — он же используется во всех остальных
    // местах настроек. Собственной инициализации палитры здесь нет намеренно,
    // иначе появился бы второй, отличающийся инструмент.

    // «Сбросить» в конце ряда настроек: чистит всю строку.
    // В data-ft-phs-reset — список CSS-селекторов через запятую.
    $(document).on('click', '.ft-phs-reset', function(e) {
        e.preventDefault();
        var resetColorPlaceholder = $(this).attr('data-ft-phs-reset-color-placeholder');
        var sels = String($(this).attr('data-ft-phs-reset') || '').split(',');
        $.each(sels, function(i, sel) {
            sel = $.trim(sel);
            if (!sel) { return; }
            var $t = $(sel);
            if (!$t.length) { return; }
            // Контрол фона (Классический / Градиент) — целиком.
            if ($t.hasClass('ft-bg-control')) {
                $t.find('.ft-bg-type-tab[data-ft-bg-type="classic"]').first().trigger('click');
                $t.find('.ft-bg-color-input').val('').trigger('change');
                $t.find('.ft-bg-grad-input').val('');
                return;
            }
            // Поле цвета внутри общего контрола: чистим значение и перерисовываем
            // свотч. change обязателен, иначе круг останется прежнего цвета.
            if ($t.hasClass('ft-bg-color-input')) {
                if (typeof resetColorPlaceholder !== 'undefined') {
                    $t.attr('placeholder', resetColorPlaceholder);
                }
                $t.val('').trigger('input').trigger('change');
                return;
            }
            if ($t.is('select')) {
                var $emptyOption = $t.find('option[value=""]').first();
                var $zeroOption = $t.find('option[value="0"]').first();
                var resetSelectValue = $emptyOption.length
                    ? ''
                    : ($zeroOption.length ? '0' : $t.find('option').first().val());
                $t.val(resetSelectValue).trigger('change');
                return;
            }
            var resetValue = $t.attr('data-ft-reset-value');
            $t.val(typeof resetValue === 'undefined' ? '' : resetValue);
            $t.trigger('input').trigger('change');
        });
    });

    // Чекбоксы «Добавить подчеркивание» / «Добавить разделители»: показывают
    // или прячут свой ряд с цветом и толщиной линии. При снятии галочки
    // значения чистятся, чтобы выключенная опция ничего не переносила с собой.
    $(document).on('change', '[data-ft-phs-underline-toggle]', function() {
        var $box  = $(this);
        var $wrap = $box.closest('.ft-phs-checkbox-block');
        if (!$wrap.length) { return; }
        var $set = $wrap.find('[data-ft-phs-underline-settings]').first();
        if (!$set.length) { return; }
        var on = !!$box.prop('checked');
        $set.toggleClass('is-on', on);
        if (on) {
            $set.show();
            return;
        }
        $set.hide();
        $set.find('input[type="text"], input[type="number"]').each(function() {
            var $in = $(this);
            $in.val('');
            if (!$in.hasClass('ft-bg-color-input') && typeof $in.wpColorPicker === 'function') {
                try { $in.wpColorPicker('color', ''); } catch (err) {}
            }
            $in.trigger('change');
        });
    });

    // Тулбар «Выравнивание по горизонтали»: значение лежит в скрытом поле,
    // кнопки только переключают нажатое состояние. Повторный клик по
    // активной иконке снимает выбор — возврат к наследованию Десктопа.
    $(document).on('click', '.ft-align__btn', function(e) {
        e.preventDefault();
        var $btn  = $(this);
        var $wrap = $btn.closest('.ft-align');
        if (!$wrap.length) { return; }
        var $input  = $wrap.find('.ft-align__value').first();
        var $btns   = $wrap.find('.ft-align__btn');
        var $pressed = $btns.filter('[aria-pressed="true"]');
        var is_same = $pressed.length === 1 && $pressed[0] === this;
        var val = is_same ? '' : String($btn.attr('data-ft-align-btn') || '');
        $btns.attr('aria-pressed', 'false').removeClass('is-pressed');
        if (val) {
            $btn.attr('aria-pressed', 'true').addClass('is-pressed');
        }
        $input.val(val).trigger('change');
        // Подсказка про наследование должна отражать новое состояние.
        var $help = $wrap.find('.ft-align__help');
        if ($help.length) {
            $help.text(val
                ? ($help.data('ftOwnText') || '')
                : ($help.data('ftInheritText') || ''));
        }
    });
});