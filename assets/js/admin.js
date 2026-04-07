jQuery(document).ready(function($) {
    // Проверка наличия familyTreeAdmin
    if (typeof familyTreeAdmin === 'undefined') {
        console.error('familyTreeAdmin object is not defined');
        return;
    }
    console.log('familyTreeAdmin object:', familyTreeAdmin); // Для отладки
    console.log('Nonce from familyTreeAdmin:', familyTreeAdmin.nonce); // Для отладки
    
    // Функция для настройки Select2
    function configureSelect2($element) {
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
        // Обновляем текст метки для чекбокса смерти
        updateDeathCheckboxLabel();
    }).trigger('change');

    // Функция обновления текста метки чекбокса смерти
    function updateDeathCheckboxLabel() {
        const $checkbox = $('#family_member_is_deceased');
        const isChecked = $checkbox.is(':checked');
        const gender = $('#family_member_gender').val() || 'male';
        const labelText = isChecked ? 'Человек умер' : 'Человек жив';
        $('#family_member_is_deceased_label').text(labelText);
    }

    // --- Логика для чекбокса "Человек жив/умер" ---
    $('#family_member_is_deceased').change(function() {
        const isChecked = $(this).is(':checked');
        updateDeathCheckboxLabel();
        
        if (isChecked) {
            $('.death-fields').show();
        } else {
            $('.death-fields').hide();
        }
    }).trigger('change');

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
});