<?php
class Family_Tree_Meta_Boxes {
    public static function init() {
        // Блочный редактор для вложений принудительно отключён (у Гутенберга есть баги с контентом
        // вложений: «Этот блок содержит ошибку», потеря текста).
        // Editor support нужен, чтобы классический редактор показывал полноценный TinyMCE-редактор
        // описания (postdivrich). Встроенный блок wp_attachment_details (простой редактор «Описание»
        // + «Подпись к изображению» + alt) при этом удаляем — остаётся ОДИН описательный редактор.
        add_post_type_support('attachment', 'editor');
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_meta_boxes'));
        // Гарантируем положение метабокса «Подпись» сразу после «Родственные связи».
        // Если пользователь сохранял свой порядок метабоксов (usermeta meta-box-order),
        // ядро WP выносит сохранённые блоки в отдельную очередь 'sorted', а новый блок,
        // которого не было при сохранении, остаётся в группе 'high' и рендерится первой —
        // то есть улетает в самый верх. Фильтруем сохранённый порядок, вставляя «Подпись»
        // именно после «Родственные связи».
        add_filter('get_user_option_meta-box-order_family_member', array(__CLASS__, 'order_signature_after_relationships'));
        // Классический редактор вложения: в части версий WP путь сохранения идёт через
        // edit_attachment без гарантированного save_post. Дублируем — обработчик идемпотентен.
        add_action('edit_attachment', array(__CLASS__, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
        // Отключаем Гутенберг для вложений (override любых forced-фильтров темы).
        add_filter('use_block_editor_for_post', array(__CLASS__, 'disable_block_editor_for_attachment'), 10, 2);
        add_filter('use_block_editor_for_post_type', array(__CLASS__, 'disable_block_editor_for_attachment_type'), 10, 2);
        // AJAX-сохранение «Кто на фото» (мета персон сохраняется мгновенно, без ожидания save_post)
        add_action('wp_ajax_ft_save_photo_people', array(__CLASS__, 'ajax_save_photo_people'));
        // AJAX-сохранение «Дата и место съемки» (тот же мгновенный паттерн)
        add_action('wp_ajax_ft_save_photo_taken', array(__CLASS__, 'ajax_save_photo_taken'));
        // Авто-обновление slug при смене названия фото (wp_insert_post_data работает до записи в БД, включая REST)
        add_filter('wp_insert_post_data', array(__CLASS__, 'filter_attachment_slug'), 20, 2);
        // Заменяем нативный блок вложений (превью + alt + «Подпись» + простой редактор «Описание»)
        // собственной версией: превью и alt оставляем, подпись и дублирующий редактор убираем.
        add_action('add_meta_boxes_attachment', array(__CLASS__, 'replace_native_attachment_editor'));
    }

    /**
     * Вставляет метабокс «Подпись» в сохранённый пользователем порядок метабоксов
     * сразу после «Родственные связи». Вызывается фильтром get_user_option_meta-box-order_family_member.
     */
    public static function order_signature_after_relationships($order) {
        if (!is_array($order) || empty($order['normal'])) {
            return $order;
        }
        $normal = array_values(array_filter(array_map('trim', explode(',', (string) $order['normal'])), 'strlen'));
        if (in_array('family-member-signature', $normal, true)) {
            return $order;
        }
        $index = array_search('family-member-relationships', $normal, true);
        if (false === $index) {
            $index = count($normal);
        } else {
            $index++;
        }
        array_splice($normal, $index, 0, array('family-member-signature'));
        $order['normal'] = implode(',', $normal);
        return $order;
    }
    /**
     * Заменяет нативный блок редактора медиа (edit_form_image_editor) собственным:
     * оставляем превью изображения и поле «Атрибут alt» (нужно для доступности),
     * убираем «Подпись к изображению» (всегда = название фото) и простой редактор
     * «Описание» (attachment_content), дублирующий полноценный TinyMCE (postdivrich).
     */
    public static function replace_native_attachment_editor() {
        // remove_action работает, т.к. core регистрирует callback ДО do_action('add_meta_boxes_attachment').
        remove_action('edit_form_after_title', 'edit_form_image_editor');
        add_action('edit_form_after_title', array(__CLASS__, 'render_attachment_editor'), 11);
    }

    /**
     * Рендер превью фото и поля alt на экране редактирования вложения.
     */
    public static function render_attachment_editor($post) {
        if ('attachment' !== $post->post_type || !wp_attachment_is_image($post->ID)) {
            return;
        }
        $thumb_url = wp_get_attachment_image_src($post->ID, array(900, 450), true);
        $alt_text  = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        ?>
        <div class="wp_attachment_holder wp-clearfix">
            <?php if ($thumb_url) : ?>
                <div class="wp_attachment_image wp-clearfix" id="media-head-<?php echo (int) $post->ID; ?>">
                    <p id="thumbnail-head-<?php echo (int) $post->ID; ?>"><img class="thumbnail" src="<?php echo esc_url($thumb_url[0]); ?>" style="max-width:100%" alt="" /></p>
                    <?php if (wp_image_editor_supports(array('mime_type' => $post->post_mime_type))) : ?>
                        <?php $image_edit_nonce = wp_create_nonce('image_editor-' . $post->ID); ?>
                        <p><input type="button" id="imgedit-open-btn-<?php echo (int) $post->ID; ?>" onclick="imageEdit.open(<?php echo (int) $post->ID; ?>, '<?php echo esc_js($image_edit_nonce); ?>')" class="button" value="<?php esc_attr_e('Редактировать'); ?>" /> <span class="spinner"></span></p>
                    <?php endif; ?>
                </div>
                <div style="display:none" class="image-editor" id="image-editor-<?php echo (int) $post->ID; ?>"></div>
            <?php endif; ?>
        </div>
        <div class="wp_attachment_details edit-form-section">
            <p class="attachment-alt-text">
                <label for="attachment_alt"><strong><?php esc_html_e('Атрибут alt'); ?></strong></label><br />
                <textarea class="widefat" name="_wp_attachment_image_alt" id="attachment_alt" aria-describedby="alt-text-description"><?php echo esc_attr($alt_text); ?></textarea>
            </p>
            <p class="attachment-alt-text-description" id="alt-text-description">
                <?php
                printf(
                    '<a href="%1$s" target="_blank">%2$s<span class="screen-reader-text"> %3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>. %4$s',
                    esc_url(__('https://www.w3.org/WAI/tutorials/images/decision-tree/')),
                    esc_html__('Опишите назначение изображения'),
                    esc_html__('(откроется в новой вкладке)'),
                    esc_html__('Оставьте пустым, если изображение является только элементом декора.')
                );
                ?>
            </p>
        </div>
        <label for="content" class="attachment-content-description" style="display:block;margin:20px 0 6px;"><strong><?php esc_html_e('Описание'); ?></strong></label>
        <?php
    }
    /**
     * Запрещает блочный редактор для вложений-изображений (классический редактор медиа).
     */
    public static function disable_block_editor_for_attachment($can_edit, $post) {
        if ($post && 'attachment' === $post->post_type && wp_attachment_is_image($post->ID)) {
            return false;
        }
        return $can_edit;
    }
    /**
     * Запрещает блочный редактор для типа записи attachment в целом.
     */
    public static function disable_block_editor_for_attachment_type($can_edit, $post_type) {
        if ('attachment' === $post_type) {
            return false;
        }
        return $can_edit;
    }
    public static function enqueue_admin_scripts($hook) {
        if (($hook == 'post-new.php' || $hook == 'post.php') && (get_post_type() == 'family_member' || get_post_type() == 'family_group')) {
            wp_enqueue_script('family-tree-select2', FAMILY_TREE_PLUGIN_URL . 'assets/vendor/select2.min.js', array('jquery'), '4.0.13', true);
            wp_enqueue_style('family-tree-select2', FAMILY_TREE_PLUGIN_URL . 'assets/vendor/select2.min.css', array(), '4.0.13');
            wp_enqueue_script('family-tree-glightbox');
            wp_enqueue_style('family-tree-glightbox');
        }
        // Скрипты «Кто на фото» для страницы правки изображения (вложение)
        if (($hook == 'post-new.php' || $hook == 'post.php') && get_post_type() == 'attachment') {
            $ft_people_post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
            if ($ft_people_post_id && wp_attachment_is_image($ft_people_post_id)) {
                wp_enqueue_style('ft-photo-people', FAMILY_TREE_PLUGIN_URL . 'assets/css/photo-people.css', array(), '1.4.4');
                wp_enqueue_script('ft-photo-people', FAMILY_TREE_PLUGIN_URL . 'assets/js/photo-people.js', array('jquery'), '1.4.4', true);
                $ft_people_img = wp_get_attachment_image_src($ft_people_post_id, 'large');
                wp_localize_script('ft-photo-people', 'ftPhotoPeople', array(
                    'imageSrc' => $ft_people_img ? $ft_people_img[0] : '',
                    'members'  => self::get_family_members_list(),
                    'nonce'    => wp_create_nonce('ft_photo_people_nonce'),
                    'postId'   => $ft_people_post_id,
                    'ajaxUrl'  => admin_url('admin-ajax.php'),
                ));
                wp_localize_script('ft-photo-people', 'ftPhotoTaken', array(
                    'nonce'   => wp_create_nonce('ft_photo_taken_ajax'),
                    'postId'  => $ft_people_post_id,
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                ));
            }
        }
    }
    /**
     * Список персоналий для отметки на фото: id => имя (отсортировано по имени).
     */
    public static function get_family_members_list() {
        $list = array();
        $members = get_posts(array(
            'post_type'      => 'family_member',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'nopaging'       => true,
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
        foreach ($members as $m) {
            $list[] = array(
                'id'   => (int) $m->ID,
                'name' => $m->post_title,
            );
        }
        return $list;
    }
    public static function add_meta_boxes() {
        // Метабоксы для family_member
        add_meta_box('family-member-consent', esc_html__('Согласие на публикацию данных', 'genius-family-tree'), array(__CLASS__, 'render_consent_meta_box'), 'family_member', 'normal', 'high');
        add_meta_box('family-member-details', esc_html__('Детали члена семьи', 'genius-family-tree'), array(__CLASS__, 'render_details_meta_box'), 'family_member', 'normal', 'high');
        add_meta_box('family-member-relationships', esc_html__('Родственные связи', 'genius-family-tree'), array(__CLASS__, 'render_relationships_meta_box'), 'family_member', 'normal', 'high');
        add_meta_box('family-member-signature', esc_html__('Подпись', 'genius-family-tree'), array(__CLASS__, 'render_signature_meta_box'), 'family_member', 'normal', 'high');
        add_meta_box('family-member-album', esc_html__('Фотоальбом', 'genius-family-tree'), array(__CLASS__, 'render_album_meta_box'), 'family_member', 'normal', 'low');
        // Метабокс для выбора семейной группы
        add_meta_box('family-member-group', esc_html__('Семейная группа', 'genius-family-tree'), array(__CLASS__, 'render_group_meta_box'), 'family_member', 'side', 'high');
        // Метабокс приватности члена семьи
        add_meta_box('family-member-privacy', esc_html__('Приватность', 'genius-family-tree'), array(__CLASS__, 'render_privacy_meta_box'), 'family_member', 'side', 'high');
        // Метабоксы остальных разделов семейного архива (Документы, Пресса, Письма, кастомные)
        foreach (ft_get_archive_sections() as $ft_sec_id => $ft_sec_cfg) {
            if ('photoalbums' === $ft_sec_id || empty($ft_sec_cfg['enabled'])) {
                continue;
            }
            $ft_sec_title = (!empty($ft_sec_cfg['title'])) ? $ft_sec_cfg['title'] : $ft_sec_id;
            add_meta_box(
                'family-member-section-' . $ft_sec_id,
                esc_html($ft_sec_title),
                function($post) use ($ft_sec_id) {
                    echo self::album_metabox_content((int) $post->ID, $ft_sec_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML метабокса
                },
                'family_member',
                'normal',
                'low'
            );
        }

        // Метабокс «Дата и место съемки» для вложений-изображений (идёт перед «Кто на фото»)
        add_meta_box('ft-photo-taken', esc_html__('Дата и место съемки', 'genius-family-tree'), array(__CLASS__, 'render_photo_taken_meta_box'), 'attachment', 'normal', 'high');

        // Метабокс «Кто на фото» для вложений-изображений
        add_meta_box('ft-photo-people', esc_html__('Кто на фото', 'genius-family-tree'), array(__CLASS__, 'render_photo_people_meta_box'), 'attachment', 'normal', 'high');
        
        // Метабоксы для family_group - удалили, так как теперь используется метабокс из genius-family-tree.php
        // add_meta_box('family-group-members', esc_html__('Члены семейной группы', 'genius-family-tree'), array(__CLASS__, 'render_group_members_meta_box'), 'family_group', 'normal', 'high');
    }

    /**
     * Рендер метабокса «Подпись» — изображение подписи персоны для сайдбара на странице персоналии.
     */
    public static function render_signature_meta_box($post) {
        wp_nonce_field('family_member_signature_nonce', 'family_member_signature_nonce');
        $signature_id  = (int) get_post_meta($post->ID, '_family_member_signature_id', true);
        $signature_url = $signature_id ? wp_get_attachment_url($signature_id) : '';
        ?>
        <div class="ft-signature-metabox">
            <input type="hidden" name="family_member_signature" id="family-member-signature-input" value="<?php echo esc_attr($signature_id); ?>" />
            <div class="ft-signature-metabox__preview" id="family-member-signature-preview">
                <?php if ($signature_url) : ?>
                    <img src="<?php echo esc_url($signature_url); ?>" alt="" />
                <?php else : ?>
                    <span class="ft-signature-metabox__empty"><span class="dashicons dashicons-format-image"></span><?php echo esc_html__('Подпись не загружена', 'genius-family-tree'); ?></span>
                <?php endif; ?>
            </div>
            <p class="description" style="margin-top:8px;">
                <?php echo esc_html__('Загрузите изображение подписи персоны. Оно будет отображаться в самом низу сайдбара на странице персоналии.', 'genius-family-tree'); ?>
            </p>
            <p>
                <button type="button" class="button button-secondary" id="family-member-signature-upload"><?php echo esc_html__('Выбрать изображение', 'genius-family-tree'); ?></button>
                <button type="button" class="button button-link-delete" id="family-member-signature-remove" <?php disabled($signature_id, 0); ?>><?php echo esc_html__('Удалить', 'genius-family-tree'); ?></button>
            </p>
        </div>
        <script>
        (function($) {
            $(function() {
                var $signInput   = $('#family-member-signature-input'),
                    $signPreview = $('#family-member-signature-preview'),
                    $signRemove  = $('#family-member-signature-remove'),
                    $signEmpty   = $('<span class="ft-signature-metabox__empty"><span class="dashicons dashicons-format-image"></span><?php echo esc_html__('Подпись не загружена', 'genius-family-tree'); ?></span>');

                $('#family-member-signature-upload').on('click', function(e) {
                    e.preventDefault();
                    if (typeof wp === 'undefined' || !wp.media) {
                        alert('<?php echo esc_js(__('Медиабиблиотека не загружена. Обновите страницу.', 'genius-family-tree')); ?>');
                        return;
                    }
                    var $frame = wp.media({
                        title: '<?php echo esc_js(__('Выберите подпись', 'genius-family-tree')); ?>',
                        button: { text: '<?php echo esc_js(__('Выбрать', 'genius-family-tree')); ?>' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    $frame.on('select', function() {
                        var att = $frame.state().get('selection').first().toJSON();
                        if (!att || !att.id) {
                            return;
                        }
                        var url = (att.sizes && att.sizes.medium && att.sizes.medium.url) ? att.sizes.medium.url : att.url;
                        $signInput.val(att.id);
                        $signPreview.empty().append($('<img>').attr('src', url).attr('alt', ''));
                        $signRemove.prop('disabled', false);
                    });
                    $frame.open();
                });

                $signRemove.on('click', function(e) {
                    e.preventDefault();
                    $signInput.val('');
                    $signPreview.empty().append($signEmpty);
                    $(this).prop('disabled', true);
                });
            });
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Рендер метабокса «Приватность» для члена семьи.
     */
    public static function render_privacy_meta_box($post) {
        wp_nonce_field('family_member_privacy_nonce', 'family_member_privacy_nonce');
        $private = get_post_meta($post->ID, '_family_member_private', true);
        ?>
        <label>
            <input type="checkbox" name="family_member_private" value="1" <?php checked(!empty($private)); ?> />
            <?php echo esc_html__('Скрыть от посетителей', 'genius-family-tree'); ?>
        </label>
        <p class="description" style="margin-top:6px;">
            <?php echo esc_html__('Персона будет скрыта: в древе и алфавитном каталоге вместо неё появится пометка «Скрыто администратором», на странице персоналии — только заголовок.', 'genius-family-tree'); ?>
        </p>
        <p class="description">
            <?php echo esc_html__('Действует, когда в «Общих настройках» включена публичная маскировка информации.', 'genius-family-tree'); ?>
        </p>
        <?php
    }

    /**
     * Метабокс «Согласие на публикацию» — вверху страницы редактирования персоны.
     * Без подтверждения кнопки «Опубликовать»/«Сохранить» недоступны,
     * подтверждение сохраняется навсегда и не может быть снято.
     */
    public static function render_consent_meta_box($post) {
        $given = (bool) get_post_meta($post->ID, '_family_member_consent', true);
        ?>
        <div class="ft-consent-box<?php echo $given ? ' is-given' : ''; ?>"
            data-consent-nonce="<?php echo esc_attr(wp_create_nonce('family_tree_consent_nonce')); ?>"
            data-consent-given="<?php echo $given ? '1' : '0'; ?>"
            data-consent-saved-text="<?php echo esc_attr__('Согласие подтверждено. Отметка сохраняется навсегда.', 'genius-family-tree'); ?>"
            data-consent-lock-hint="<?php echo esc_attr__('Подтвердите согласие на публикацию данных, поставив галочку в блоке «Согласие на публикацию данных».', 'genius-family-tree'); ?>"
            data-consent-ajaxurl="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <label class="ft-consent-label">
                <input type="checkbox" id="ft-consent-checkbox" name="family_member_consent" value="1"
                    <?php echo $given ? 'checked="checked"' : ''; ?>
                    <?php echo $given ? 'disabled="disabled"' : ''; ?> />
                <span class="ft-consent-text">
                    <?php echo esc_html__('Публикуя информацию о данной персоне, я подтверждаю, что данные размещаются с согласия человека, которому посвящена страница, или его наследника, а также не являются охраняемой законом тайной.', 'genius-family-tree'); ?>
                </span>
            </label>
            <?php if ($given) : ?>
            <p class="ft-consent-status">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html__('Согласие подтверждено. Отметка сохраняется навсегда.', 'genius-family-tree'); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Отображение метабокса выбора семейной группы для члена семьи
     */
    public static function render_group_meta_box($post) {
        wp_nonce_field('family_member_group_nonce', 'family_member_group_nonce');
        $group_id = get_post_meta($post->ID, '_family_member_group_id', true);
        
        if (empty($group_id)) {
            $group_id = get_option('family_tree_default_group_id', 0);
        }
        $is_root = get_post_meta($post->ID, '_family_member_is_root', true);
        
        $group_roots = array();
        $groups = get_posts(array(
            'post_type' => 'family_group',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        foreach ($groups as $group) {
            $root_id = Family_Tree_Meta_Boxes::get_root_member_id($group->ID);
            if ($root_id) {
                $group_roots[$group->ID] = get_the_title($root_id);
            }
        }
        ?>
        <div class="family-tree-group-metabox">
            <p>
                <label for="family_member_group_id"><?php echo esc_html__('Семейная группа:', 'genius-family-tree'); ?></label>
                <select name="family_member_group_id" id="family_member_group_id" class="ft-group-select">
                    <option value=""><?php echo esc_html__('— Общая (без группы) —', 'genius-family-tree'); ?></option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo esc_attr($group->ID); ?>" <?php selected($group_id, $group->ID); ?>>
                            <?php echo esc_html($group->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p class="description">
                <?php echo esc_html__('Если не выбрать группу, персона будет отображаться в общем древе.', 'genius-family-tree'); ?>
            </p>
            <p id="ft-root-checkbox-wrap" style="margin-top: 12px; <?php echo $group_id ? '' : 'display:none;'; ?>">
                <label>
                    <input type="checkbox" name="family_member_is_root" value="1" <?php checked($is_root, '1'); ?> />
                    <?php echo esc_html__('Сделать корневым элементом', 'genius-family-tree'); ?>
                </label>
            </p>
            <p id="ft-root-info" style="margin-top: 8px; <?php echo ($group_id && !$is_root && isset($group_roots[$group_id])) ? '' : 'display:none;'; ?>">
                <span style="color: #666;">
                    <?php echo esc_html__('Текущий корневой элемент:', 'genius-family-tree'); ?>
                    <strong><?php echo esc_html(isset($group_roots[$group_id]) ? $group_roots[$group_id] : ''); ?></strong>
                </span>
                <br>
                <span style="color: #999; font-style: italic; font-size: 12px;">
                    <?php echo esc_html__('При установке этой опции корневой элемент будет изменен.', 'genius-family-tree'); ?>
                </span>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var groupRoots = <?php echo wp_json_encode($group_roots); ?>;
            var currentPostId = <?php echo (int) $post->ID; ?>;
            
            var updateRootInfo = function() {
                var val = $('#family_member_group_id').val();
                var $wrap = $('#ft-root-checkbox-wrap');
                var $info = $('#ft-root-info');
                
                if (val && val !== '0') {
                    $wrap.show();
                    var rootName = groupRoots[val] || '';
                    var $strong = $info.find('strong');
                    if (rootName) {
                        $info.find('span:first').show();
                        $strong.text(rootName);
                        $info.show();
                    } else {
                        $info.hide();
                    }
                } else {
                    $wrap.hide();
                    $info.hide();
                }
            };
            
            $('#family_member_group_id').on('change', updateRootInfo);
            updateRootInfo();
        });
        </script>
        <?php
    }
    
    /**
     * Отображение метабокса членов семейной группы
     */
    public static function render_group_members_meta_box($post) {
        wp_nonce_field('family_group_members_nonce', 'family_group_members_nonce');
        $group_id = $post->ID;
        
        // Получаем параметры поиска и сортировки
        $raw_search = filter_input(INPUT_GET, 'ft_group_search', FILTER_SANITIZE_STRING) ?: '';
        $search_query = sanitize_text_field($raw_search);
        $raw_sort = filter_input(INPUT_GET, 'ft_group_sort', FILTER_SANITIZE_STRING) ?: 'date_asc';
        $sort_order = sanitize_text_field($raw_sort);

        // Строим запрос
        $args = array(
            'post_type' => 'family_member',
            'posts_per_page' => -1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => array(
                array(
                    'key' => '_family_member_group_id',
                    'value' => (string) $group_id,
                    'compare' => '=',
                ),
            ),
        );

        // Добавляем поиск
        if (!empty($search_query)) {
            $args['s'] = $search_query;
        }

        // Добавляем сортировку
        switch ($sort_order) {
            case 'date_asc':
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                $args['meta_key'] = '_family_member_birth_date';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
                break;
            case 'date_desc':
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                $args['meta_key'] = '_family_member_birth_date';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            case 'alpha_asc':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
            case 'alpha_desc':
                $args['orderby'] = 'title';
                $args['order'] = 'DESC';
                break;
            default:
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                $args['meta_key'] = '_family_member_birth_date';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
        }

        $members_query = new WP_Query($args);
        $members = $members_query->posts;
        ?>
        <style>
            .ft-group-members-container {
                margin-top: 10px;
            }
            .ft-controls-bar {
                background: #f6f7f7;
                border: 1px solid #dcdcde;
                padding: 14px;
                margin-bottom: 16px;
                border-radius: 6px;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
            }
            .ft-controls-bar input[type="text"],
            .ft-controls-bar select {
                min-width: 200px;
                padding: 8px 10px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                background: #fff;
                font-size: 14px;
            }
            .ft-controls-bar button {
                padding: 8px 14px;
                background: #2271b1;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 500;
                line-height: 1.4;
                transition: background 0.2s ease;
            }
            .ft-controls-bar button:hover {
                background: #135e96;
            }
            .ft-controls-bar .ft-reset-link {
                padding: 8px 14px;
                color: #646970;
                text-decoration: none;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                font-size: 13px;
                transition: all 0.2s ease;
            }
            .ft-controls-bar .ft-reset-link:hover {
                color: #1d2327;
                border-color: #8c8f94;
            }
            .ft-members-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
            }
            .ft-member-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                display: flex;
                flex-direction: column;
                position: relative;
            }
            .ft-member-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.08);
                border-color: #8d96a0;
            }
            .ft-card-header {
                position: relative;
                height: 140px;
                background: #f0f0f1;
                overflow: hidden;
            }
            .ft-card-avatar {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s ease;
            }
            .ft-member-card:hover .ft-card-avatar {
                transform: scale(1.05);
            }
            .ft-root-indicator {
                position: absolute;
                top: 8px;
                right: 8px;
                background: #d63638;
                color: #fff;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                z-index: 2;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .ft-card-body {
                padding: 16px;
                flex-grow: 1;
                display: flex;
                flex-direction: column;
            }
            .ft-member-name {
                font-size: 16px;
                font-weight: 600;
                color: #1d2327;
                margin: 0 0 6px 0;
                line-height: 1.3;
            }
            .ft-member-name a {
                text-decoration: none;
                color: inherit;
                transition: color 0.2s ease;
            }
            .ft-member-name a:hover {
                color: #2271b1;
            }
            .ft-member-dates {
                font-size: 13px;
                color: #646970;
                margin-bottom: 10px;
            }
            .ft-member-group {
                font-size: 12px;
                color: #2271b1;
                background: #f0f6fc;
                padding: 4px 10px;
                border-radius: 4px;
                display: inline-block;
                margin-top: auto;
                font-weight: 500;
            }
            .ft-no-members {
                grid-column: 1 / -1;
                text-align: center;
                padding: 40px;
                color: #646970;
                font-style: italic;
                background: #f6f7f7;
                border-radius: 8px;
                border: 1px dashed #dcdcde;
            }
        </style>

        <div class="ft-group-members-container">
            <!-- Панель управления: Поиск и Сортировка -->
            <form method="get" class="ft-controls-bar">
                <input type="hidden" name="post_type" value="family_group">
                <input type="hidden" name="post" value="<?php echo esc_attr($group_id); ?>">
                <input type="hidden" name="action" value="edit">
                
                <input type="text" 
                       name="ft_group_search" 
                       placeholder="<?php esc_attr_e('Поиск по имени или фамилии...', 'genius-family-tree'); ?>" 
                       value="<?php echo esc_attr($search_query); ?>" />
                
                <select name="ft_group_sort">
                    <option value="date_asc" <?php selected($sort_order, 'date_asc'); ?>><?php esc_html_e('Дата рождения (старшие сначала)', 'genius-family-tree'); ?></option>
                    <option value="date_desc" <?php selected($sort_order, 'date_desc'); ?>><?php esc_html_e('Дата рождения (младшие сначала)', 'genius-family-tree'); ?></option>
                    <option value="alpha_asc" <?php selected($sort_order, 'alpha_asc'); ?>><?php esc_html_e('А-Я (А-Я)', 'genius-family-tree'); ?></option>
                    <option value="alpha_desc" <?php selected($sort_order, 'alpha_desc'); ?>><?php esc_html_e('Я-А (Я-А)', 'genius-family-tree'); ?></option>
                </select>

                <button type="submit"><?php esc_html_e('Применить', 'genius-family-tree'); ?></button>
                <?php if (!empty($search_query) || $sort_order !== 'date_asc'): ?>
                    <a href="<?php echo esc_url(remove_query_arg(array('ft_group_search', 'ft_group_sort'))); ?>" class="ft-reset-link">
                        <?php esc_html_e('Сброс', 'genius-family-tree'); ?>
                    </a>
                <?php endif; ?>
            </form>

            <div class="ft-members-grid">
                <?php if ($members && !empty($members)): ?>
                    <?php foreach ($members as $member): 
                        $birth_date = get_post_meta($member->ID, '_family_member_birth_date', true);
                        $death_date = get_post_meta($member->ID, '_family_member_death_date', true);
                        $is_root = get_post_meta($member->ID, '_family_member_is_root', true);
                        
                        // Форматируем даты
                        $dates_display = '';
                        if (!empty($birth_date)) {
                            $dates_display .= date_i18n('Y', strtotime($birth_date));
                        } else {
                            $dates_display .= '?';
                        }
                        $dates_display .= ' – ';
                        if (!empty($death_date)) {
                            $dates_display .= date_i18n('Y', strtotime($death_date));
                        } else {
                            $dates_display .= 'н.в.';
                        }

                        // Получаем аватар
                        $avatar_url = '';
                        if (has_post_thumbnail($member->ID)) {
                            $avatar_url = get_the_post_thumbnail_url($member->ID, 'medium');
                        } else {
                            $avatar_url = 'https://via.placeholder.com/300x200?text=' . urlencode(get_the_title($member->ID));
                        }

                        // Получаем название группы
                        $group_terms = get_the_terms($member->ID, 'family_group');
                        $group_name = '';
                        if ($group_terms && !is_wp_error($group_terms)) {
                            $group_name = $group_terms[0]->name;
                        }
                    ?>
                        <div class="ft-member-card">
                            <div class="ft-card-header">
                                <?php if ($is_root == 'yes'): ?>
                                    <span class="ft-root-indicator">
                                        <svg style="width:12px;height:12px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24"><path d="M12 2L1 21h22L12 2zm0 3.5l8.5 14.5h-17L12 5.5z" fill="#fff"/></svg>
                                        <?php esc_html_e('Корень', 'genius-family-tree'); ?>
                                    </span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(get_edit_post_link($member->ID)); ?>">
                                    <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr(get_the_title($member->ID)); ?>" class="ft-card-avatar">
                                </a>
                            </div>
                            <div class="ft-card-body">
                                <h3 class="ft-member-name">
                                    <a href="<?php echo esc_url(get_edit_post_link($member->ID)); ?>">
                                        <?php echo esc_html(get_the_title($member->ID)); ?>
                                    </a>
                                </h3>
                                <div class="ft-member-dates">
                                    <?php echo esc_html($dates_display); ?>
                                </div>
                                <?php if (!empty($group_name)): ?>
                                    <div class="ft-member-group">
                                        <?php echo esc_html($group_name); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="ft-no-members">
                        <?php if (!empty($search_query)): ?>
                            <?php esc_html_e('По вашему запросу ничего не найдено.', 'genius-family-tree'); ?>
                        <?php else: ?>
                            <?php esc_html_e('В этой группе пока нет участников.', 'genius-family-tree'); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <p class="ft-add-member-link">
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=family_member&new_group_id=' . $group_id)); ?>" class="button button-primary">
                    <?php echo esc_html__('Добавить нового члена семьи', 'genius-family-tree'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    public static function render_details_meta_box($post) {
        wp_nonce_field('family_member_details_nonce', 'family_member_details_nonce');
        $first_name = get_post_meta($post->ID, '_family_member_first_name', true);
        $middle_name = get_post_meta($post->ID, '_family_member_middle_name', true);
        $last_name = get_post_meta($post->ID, '_family_member_last_name', true);
        $maiden_name = get_post_meta($post->ID, '_family_member_maiden_name', true);
        $gender = get_post_meta($post->ID, '_family_member_gender', true);
        // Дата рождения
        $birth_day = get_post_meta($post->ID, '_family_member_birth_day', true);
        $birth_month = get_post_meta($post->ID, '_family_member_birth_month', true);
        $birth_year = get_post_meta($post->ID, '_family_member_birth_year', true);
        $birth_old_style = get_post_meta($post->ID, '_family_member_birth_old_style', true);
        $birth_new_day = get_post_meta($post->ID, '_family_member_birth_new_day', true);
        $birth_new_month = get_post_meta($post->ID, '_family_member_birth_new_month', true);
        $birth_new_year = get_post_meta($post->ID, '_family_member_birth_new_year', true);
        $birth_place = get_post_meta($post->ID, '_family_member_birth_place', true);
        // Дата смерти
        $death_day = get_post_meta($post->ID, '_family_member_death_day', true);
        $death_month = get_post_meta($post->ID, '_family_member_death_month', true);
        $death_year = get_post_meta($post->ID, '_family_member_death_year', true);
        $death_old_style = get_post_meta($post->ID, '_family_member_death_old_style', true);
        $death_new_day = get_post_meta($post->ID, '_family_member_death_new_day', true);
        $death_new_month = get_post_meta($post->ID, '_family_member_death_new_month', true);
        $death_new_year = get_post_meta($post->ID, '_family_member_death_new_year', true);
        $is_deceased = get_post_meta($post->ID, '_family_member_is_deceased', true);
        $death_cause = get_post_meta($post->ID, '_family_member_death_cause', true);
        $death_place = get_post_meta($post->ID, '_family_member_death_place', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="family_member_first_name"><?php echo esc_html__('Имя', 'genius-family-tree'); ?></label></th>
                <td><input type="text" name="family_member_first_name" id="family_member_first_name" value="<?php echo esc_attr($first_name); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="family_member_middle_name"><?php echo esc_html__('Отчество', 'genius-family-tree'); ?></label></th>
                <td><input type="text" name="family_member_middle_name" id="family_member_middle_name" value="<?php echo esc_attr($middle_name); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="family_member_last_name"><?php echo esc_html__('Фамилия', 'genius-family-tree'); ?></label></th>
                <td><input type="text" name="family_member_last_name" id="family_member_last_name" value="<?php echo esc_attr($last_name); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="family_member_gender"><?php echo esc_html__('Пол', 'genius-family-tree'); ?></label></th>
                <td>
                    <select name="family_member_gender" id="family_member_gender">
                        <option value="male" <?php selected($gender, 'male'); ?>><?php echo esc_html__('Мужской', 'genius-family-tree'); ?></option>
                        <option value="female" <?php selected($gender, 'female'); ?>><?php echo esc_html__('Женский', 'genius-family-tree'); ?></option>
                    </select>
                </td>
            </tr>
            <tr id="maiden_name_row" <?php if ($gender !== 'female') echo 'style="display:none;"'; ?>>
                <th><label for="family_member_maiden_name"><?php echo esc_html__('Девичья фамилия', 'genius-family-tree'); ?></label></th>
                <td><input type="text" name="family_member_maiden_name" id="family_member_maiden_name" value="<?php echo esc_attr($maiden_name); ?>" class="regular-text"></td>
            </tr>
            <!-- Дата рождения -->
            <tr>
                <th><label><?php echo esc_html__('Дата рождения', 'genius-family-tree'); ?></label></th>
                <td>
                    <div class="ft-date-row">
                        <input type="number" name="family_member_birth_day" id="family_member_birth_day" 
                               value="<?php echo !empty($birth_day) ? esc_attr($birth_day) : ''; ?>" 
                               min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                        <input type="number" name="family_member_birth_month" id="family_member_birth_month" 
                               value="<?php echo !empty($birth_month) ? esc_attr($birth_month) : ''; ?>" 
                               min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                        <input type="number" name="family_member_birth_year" id="family_member_birth_year" 
                               value="<?php echo !empty($birth_year) ? esc_attr($birth_year) : ''; ?>" 
                               min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                    </div>
                    <p class="description"><?php echo esc_html__('Оставьте пустым, если дата неизвестна', 'genius-family-tree'); ?></p>
                    <!-- Чекбокс "по старому стилю" -->
                    <div style="margin-top: 10px;">
                        <input type="checkbox" name="family_member_birth_old_style" id="family_member_birth_old_style" value="1" <?php checked($birth_old_style, 1); ?>>
                        <label for="family_member_birth_old_style"><?php echo esc_html__('по старому стилю', 'genius-family-tree'); ?></label>
                    </div>
                    <!-- Поля для новой даты (по новому стилю) -->
                    <div class="ft-old-style-section" id="birth_new_style_fields" <?php echo $birth_old_style ? '' : 'style="display: none;"'; ?>>
                        <label><?php echo esc_html__('Дата по новому стилю', 'genius-family-tree'); ?>:</label>
                        <div class="ft-date-row">
                            <input type="number" name="family_member_birth_new_day" id="family_member_birth_new_day" 
                                   value="<?php echo !empty($birth_new_day) ? esc_attr($birth_new_day) : ''; ?>" 
                                   min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                            <input type="number" name="family_member_birth_new_month" id="family_member_birth_new_month" 
                                   value="<?php echo !empty($birth_new_month) ? esc_attr($birth_new_month) : ''; ?>" 
                                   min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                            <input type="number" name="family_member_birth_new_year" id="family_member_birth_new_year" 
                                   value="<?php echo !empty($birth_new_year) ? esc_attr($birth_new_year) : ''; ?>" 
                                   min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="family_member_birth_place"><?php echo esc_html__('Место рождения', 'genius-family-tree'); ?></label></th>
                <td>
                    <input type="text" name="family_member_birth_place" id="family_member_birth_place" value="<?php echo esc_attr($birth_place); ?>" class="regular-text">
                    <p class="description"><?php echo esc_html__('Оставьте пустым, если место неизвестно', 'genius-family-tree'); ?></p>
                </td>
            </tr>
            <!-- Статус -->
            <tr>
                <th><label><?php echo esc_html__('Статус', 'genius-family-tree'); ?></label></th>
                <td>
                    <label style="margin-right: 20px;">
                        <input type="radio" name="family_member_is_deceased" id="family_member_is_deceased_no" value="0" <?php checked(!$is_deceased, true); ?>>
                        <?php echo esc_html__('Человек жив', 'genius-family-tree'); ?>
                    </label>
                    <label>
                        <input type="radio" name="family_member_is_deceased" id="family_member_is_deceased_yes" value="1" <?php checked($is_deceased, 1); ?>>
                        <?php echo esc_html__('Человек умер', 'genius-family-tree'); ?>
                    </label>
                </td>
            </tr>
            <tr class="death-fields" <?php if (!$is_deceased) echo 'style="display:none;"'; ?>>
                <th><label><?php echo esc_html__('Дата смерти', 'genius-family-tree'); ?></label></th>
                <td>
                    <div class="ft-date-row">
                        <input type="number" name="family_member_death_day" id="family_member_death_day" 
                               value="<?php echo !empty($death_day) ? esc_attr($death_day) : ''; ?>" 
                               min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                        <input type="number" name="family_member_death_month" id="family_member_death_month" 
                               value="<?php echo !empty($death_month) ? esc_attr($death_month) : ''; ?>" 
                               min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                        <input type="number" name="family_member_death_year" id="family_member_death_year" 
                               value="<?php echo !empty($death_year) ? esc_attr($death_year) : ''; ?>" 
                               min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                    </div>
                    <p class="description"><?php echo esc_html__('Оставьте пустым, если дата неизвестна', 'genius-family-tree'); ?></p>
                    <!-- Чекбокс "по старому стилю" для смерти -->
                    <div style="margin-top: 10px;">
                        <input type="checkbox" name="family_member_death_old_style" id="family_member_death_old_style" value="1" <?php checked($death_old_style, 1); ?>>
                        <label for="family_member_death_old_style"><?php echo esc_html__('по старому стилю', 'genius-family-tree'); ?></label>
                    </div>
                    <!-- Поля для новой даты (по новому стилю) -->
                    <div class="ft-old-style-section" id="death_new_style_fields" <?php echo $death_old_style ? '' : 'style="display: none;"'; ?>>
                        <label><?php echo esc_html__('Дата по новому стилю', 'genius-family-tree'); ?>:</label>
                        <div class="ft-date-row">
                            <input type="number" name="family_member_death_new_day" id="family_member_death_new_day" 
                                   value="<?php echo !empty($death_new_day) ? esc_attr($death_new_day) : ''; ?>" 
                                   min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                            <input type="number" name="family_member_death_new_month" id="family_member_death_new_month" 
                                   value="<?php echo !empty($death_new_month) ? esc_attr($death_new_month) : ''; ?>" 
                                   min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                            <input type="number" name="family_member_death_new_year" id="family_member_death_new_year" 
                                   value="<?php echo !empty($death_new_year) ? esc_attr($death_new_year) : ''; ?>" 
                                   min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                        </div>
                    </div>
                </td>
            </tr>
            <tr class="death-fields" <?php if (!$is_deceased) echo 'style="display:none;"'; ?>>
                <th><label for="family_member_death_cause"><?php echo esc_html__('Причина смерти', 'genius-family-tree'); ?></label></th>
                <td>
                    <input type="text" name="family_member_death_cause" id="family_member_death_cause" value="<?php echo esc_attr($death_cause); ?>" class="regular-text">
                    <p class="description"><?php echo esc_html__('Оставьте пустым, если причина неизвестна', 'genius-family-tree'); ?></p>
                </td>
            </tr>
            <tr class="death-fields" <?php if (!$is_deceased) echo 'style="display:none;"'; ?>>
                <th><label for="family_member_death_place"><?php echo esc_html__('Место смерти', 'genius-family-tree'); ?></label></th>
                <td>
                    <input type="text" name="family_member_death_place" id="family_member_death_place" value="<?php echo esc_attr($death_place); ?>" class="regular-text">
                    <p class="description"><?php echo esc_html__('Оставьте пустым, если место неизвестно', 'genius-family-tree'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    public static function render_relationships_meta_box($post) {
        wp_nonce_field('family_member_relationships_nonce', 'family_member_relationships_nonce');
        $father = get_post_meta($post->ID, '_family_member_father', true);
        $mother = get_post_meta($post->ID, '_family_member_mother', true);
        $spouses = get_post_meta($post->ID, '_family_member_spouses', true);
        if (!$spouses) $spouses = array();
        $children = get_post_meta($post->ID, '_family_member_children', true);
        if (!$children) $children = array();
        ?>
        <table class="form-table">
            <tr>
                <th><label for="family_member_father"><?php echo esc_html__('Отец', 'genius-family-tree'); ?></label></th>
                <td>
                    <select name="family_member_father" id="family_member_father" class="family-member-select" data-placeholder="<?php echo esc_attr__('Поиск отца...', 'genius-family-tree'); ?>">
                        <option value=""><?php echo esc_html__('Выберите отца', 'genius-family-tree'); ?></option>
                        <?php if ($father):
                            $father_post = get_post($father);
                            if ($father_post): ?>
                                <option value="<?php echo esc_attr($father); ?>" selected><?php echo esc_html($father_post->post_title); ?></option>
                            <?php endif;
                        endif; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="family_member_mother"><?php echo esc_html__('Мать', 'genius-family-tree'); ?></label></th>
                <td>
                    <select name="family_member_mother" id="family_member_mother" class="family-member-select" data-placeholder="<?php echo esc_attr__('Поиск матери...', 'genius-family-tree'); ?>">
                        <option value=""><?php echo esc_html__('Выберите мать', 'genius-family-tree'); ?></option>
                        <?php if ($mother):
                            $mother_post = get_post($mother);
                            if ($mother_post): ?>
                                <option value="<?php echo esc_attr($mother); ?>" selected><?php echo esc_html($mother_post->post_title); ?></option>
                            <?php endif;
                        endif; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('Супруг(а)', 'genius-family-tree'); ?></label></th>
                <td>
                    <div id="spouses-container">
                        <?php foreach ($spouses as $index => $spouse_data):
                            $spouse_id = isset($spouse_data['id']) ? $spouse_data['id'] : '';
                            $married_day = isset($spouse_data['married_day']) ? $spouse_data['married_day'] : '';
                            $married_month = isset($spouse_data['married_month']) ? $spouse_data['married_month'] : '';
                            $married_year = isset($spouse_data['married_year']) ? $spouse_data['married_year'] : '';
                            $divorced_day = isset($spouse_data['divorced_day']) ? $spouse_data['divorced_day'] : '';
                            $divorced_month = isset($spouse_data['divorced_month']) ? $spouse_data['divorced_month'] : '';
                            $divorced_year = isset($spouse_data['divorced_year']) ? $spouse_data['divorced_year'] : '';
                            $divorced_unknown = isset($spouse_data['divorced_unknown']) ? $spouse_data['divorced_unknown'] : '';
                            ?>
                            <div class="spouse-entry" data-index="<?php echo esc_attr($index); ?>">
                                <select name="family_member_spouses[<?php echo esc_attr($index); ?>][id]" class="family-member-select spouse-entry-select" data-placeholder="<?php echo esc_attr__('Поиск супруга(и)...', 'genius-family-tree'); ?>">
                                    <option value=""><?php echo esc_html__('Выберите супруга(у)', 'genius-family-tree'); ?></option>
                                    <?php if ($spouse_id):
                                        $spouse_post = get_post($spouse_id);
                                        if ($spouse_post): ?>
                                            <option value="<?php echo esc_attr($spouse_id); ?>" selected><?php echo esc_html($spouse_post->post_title); ?></option>
                                        <?php endif;
                                    endif; ?>
                                </select>
                                <div class="marriage-date">
                                    <label><?php echo esc_html__('Дата брака:', 'genius-family-tree'); ?></label>
                                    <div class="ft-date-row">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][married_day]" value="<?php echo !empty($married_day) ? esc_attr($married_day) : ''; ?>" min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][married_month]" value="<?php echo !empty($married_month) ? esc_attr($married_month) : ''; ?>" min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][married_year]" value="<?php echo !empty($married_year) ? esc_attr($married_year) : ''; ?>" min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                                    </div>
                                </div>
                                <div class="divorce-date">
                                    <label><?php echo esc_html__('Дата развода:', 'genius-family-tree'); ?></label>
                                    <div class="ft-date-row">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][divorced_day]" value="<?php echo !empty($divorced_day) ? esc_attr($divorced_day) : ''; ?>" min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][divorced_month]" value="<?php echo !empty($divorced_month) ? esc_attr($divorced_month) : ''; ?>" min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][divorced_year]" value="<?php echo !empty($divorced_year) ? esc_attr($divorced_year) : ''; ?>" min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text ft-date-input">
                                    </div>
                                    <label class="ft-checkbox-label">
                                        <input type="checkbox" name="family_member_spouses[<?php echo esc_attr($index); ?>][divorced_unknown]" id="divorced_unknown_<?php echo esc_attr($index); ?>" value="1" <?php checked($divorced_unknown, 1); ?>>
                                        <?php echo esc_html__('Развод (дата неизвестна)', 'genius-family-tree'); ?>
                                    </label>
                                </div>
                                <button type="button" class="remove-spouse button"><?php echo esc_html__('Удалить супруга(у)', 'genius-family-tree'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="add-spouse" class="button"><?php echo esc_html__('Добавить супруга(у)', 'genius-family-tree'); ?></button>
                    <p class="description"><?php echo esc_html__('Даты брака и развода автоматически синхронизируются с записью супруга(и).', 'genius-family-tree'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('Дети', 'genius-family-tree'); ?></label></th>
                <td>
                    <select name="family_member_children[]" id="family_member_children" class="family-member-select" multiple data-placeholder="<?php echo esc_attr__('Поиск детей...', 'genius-family-tree'); ?>">
                        <?php foreach ($children as $child_id):
                            $child = get_post($child_id);
                            if ($child): ?>
                                <option value="<?php echo esc_attr($child_id); ?>" selected><?php echo esc_html($child->post_title); ?></option>
                            <?php endif;
                        endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Начните вводить имя ребенка для поиска. Можно выбрать несколько детей.', 'genius-family-tree'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    /**
     * Отображение метабокса «Фотоальбом» для члена семьи
     */
    public static function render_album_meta_box($post) {
        echo self::album_metabox_content((int) $post->ID);
    }
    /**
     * Формирует HTML-контент метабокса раздела семейного архива персоны
     * («Фотоальбомы» и остальные разделы: Документы, Пресса, Письма, кастомные).
     *
     * @param int    $post_id ID персоны.
     * @param string $section ключ раздела.
     */
    public static function album_metabox_content($post_id, $section = 'photoalbums') {
        $post_id = (int) $post_id;
        $section = sanitize_key($section);
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $terms = ft_archive_section_terms($section);
        $sec_cfg = ft_get_archive_sections();
        $sec_title = (!empty($sec_cfg[$section]['title'])) ? $sec_cfg[$section]['title'] : $terms['name'];
        $albums = ft_get_albums($post_id, $section);
        $display = ft_get_album_display($post_id, $section);
        $manage_url = Family_Tree_Plugin::get_instance()->archive_person_url($section, $post_id);
        if ('photoalbums' === $section) {
            $modes = array(
                'all'          => __('Все фотографии (единая сетка)', 'genius-family-tree'),
                'album_photos' => __('Фотографии из конкретного альбома', 'genius-family-tree'),
                'select_albums'=> __('Выбрать альбом(ы) (обложки с названием и количеством фото)', 'genius-family-tree'),
                'album_covers' => __('Все альбомы', 'genius-family-tree'),
            );
            $no_post_save = __('Сохраните персоналию, чтобы управлять фотоальбомами.', 'genius-family-tree');
            $empty_msg = __('Фотоальбомы ещё не созданы. Фотографии и альбомы добавляются на отдельной странице управления.', 'genius-family-tree');
            $manage_btn = __('Открыть управление фотоальбомами', 'genius-family-tree');
            $intro = __('Выберите, что посетитель увидит на сайте в разделе «Фотоальбомы» этой персоны (на странице персоны и в шорткодах). Добавление и редактирование фотографий и альбомов выполняется на странице «Семейный архив → Фотоальбомы».', 'genius-family-tree');
            $folder_word = __('Альбом(ы):', 'genius-family-tree');
            $pick_ph = __('— Выберите альбом —', 'genius-family-tree');
            $edit_btn = __('Редактировать фотоальбомы (фотографии, порядок, обложки, названия)', 'genius-family-tree');
        } else {
            $modes = array(
                'all'          => __('Все материалы (единая сетка)', 'genius-family-tree'),
                'album_photos' => __('Материалы из конкретной папки/альбома (по выбору)', 'genius-family-tree'),
                'select_albums'=> __('Выбрать несколько папок/альбомов (обложки с названием и количеством материалов)', 'genius-family-tree'),
                'album_covers' => __('Все папки/альбомы (обложки с названием и количеством материалов)', 'genius-family-tree'),
            );
            $no_post_save = sprintf(__('Сохраните персоналию, чтобы управлять разделом «%1$s».', 'genius-family-tree'), $sec_title);
            $empty_msg = sprintf(__('Раздел «%1$s» пока пуст. Материалы раздела добавляются на отдельной странице управления.', 'genius-family-tree'), $sec_title);
            $manage_btn = sprintf(__('Открыть управление разделом «%1$s»', 'genius-family-tree'), $sec_title);
            $intro = sprintf(__('Выберите, что посетитель увидит на сайте в разделе «%1$s» этой персоны. Добавление и редактирование материалов выполняется на отдельной странице управления разделом.', 'genius-family-tree'), $sec_title);
            $folder_word = $terms['folder_single'] . ':';
            $pick_ph = __('— Выберите альбом/папку —', 'genius-family-tree');
            $edit_btn = sprintf(__('Редактировать материалы раздела «%1$s» (порядок, обложки, названия)', 'genius-family-tree'), $sec_title);
        }
        $count_lbl = function($photos) use ($section) {
            return ft_archive_item_name($section, count(array_filter(array_map('intval', (array) $photos), 'wp_attachment_is_image')));
        };
        $mode_name = 'ft_album_display_mode_' . $section;
        $single_id = 'ft-album-display-album-' . $section;
        $multi_id = 'ft-album-display-albums-' . $section;
        $sec_off = in_array($section, ft_get_person_disabled_sections($post_id), true);
        ob_start();
        ?>
        <div class="ft-album-metabox" data-post-id="<?php echo esc_attr($post_id); ?>" data-section="<?php echo esc_attr($section); ?>">
            <?php if (!$post_id) : ?>
                <p><?php echo esc_html($no_post_save); ?></p>
            <?php elseif (empty($albums)) : ?>
                <p><?php echo esc_html($empty_msg); ?></p>
                <p>
                    <a href="<?php echo esc_url($manage_url); ?>" class="button button-primary" target="_blank">
                        <?php echo esc_html($manage_btn); ?>
                    </a>
                </p>
            <?php else : ?>
                <p class="description" style="margin-top:0;">
                    <?php echo esc_html($intro); ?>
                </p>
                <div class="ft-section-toggle" data-post-id="<?php echo esc_attr($post_id); ?>" data-section="<?php echo esc_attr($section); ?>">
                    <label class="ft-section-toggle-wrap">
                        <input type="checkbox" class="ft-section-toggle-input" <?php checked(!$sec_off); ?> />
                        <span class="ft-section-toggle-switch"></span>
                        <span class="ft-section-toggle-state"><?php echo esc_html($sec_off ? __('Выключено', 'genius-family-tree') : __('Включено', 'genius-family-tree')); ?></span>
                    </label>
                    <span class="ft-section-toggle-note"><?php echo esc_html__('Раздел показывается посетителям на странице этой персоны.', 'genius-family-tree'); ?></span>
                </div>
                <fieldset>
                    <legend><strong><?php echo esc_html__('Отображение на сайте', 'genius-family-tree'); ?></strong></legend>
                    <?php foreach ($modes as $mk => $ml) : ?>
                        <label style="display:block;margin:4px 0;">
                            <input type="radio" name="<?php echo esc_attr($mode_name); ?>" value="<?php echo esc_attr($mk); ?>" class="ft-album-display-mode" <?php checked($display['mode'], $mk); ?> />
                            <?php echo esc_html($ml); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <p class="ft-album-display-album-row" style="margin-top:10px;<?php echo in_array($display['mode'], array('album_photos', 'select_albums'), true) ? '' : 'display:none;'; ?>">
                    <label for="<?php echo esc_attr($single_id); ?>" style="font-weight:600;"><?php echo esc_html($folder_word); ?></label><br />
                    <select name="ft_album_display_album" id="<?php echo esc_attr($single_id); ?>" class="widefat ft-album-display-album" style="margin-top:4px;<?php echo $display['mode'] === 'album_photos' ? '' : 'display:none;'; ?>">
                        <option value=""><?php echo esc_html($pick_ph); ?></option>
                        <?php foreach ($albums as $a) :
                            $a_slug = sanitize_title($a['name']);
                            ?>
                            <option value="<?php echo esc_attr($a_slug); ?>" <?php selected($display['album'], $a_slug); ?>><?php echo esc_html($a['name'] . ' (' . $count_lbl($a['photos']) . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="ft_album_display_albums[]" id="<?php echo esc_attr($multi_id); ?>" class="widefat ft-album-display-albums" multiple size="5" style="margin-top:4px;<?php echo $display['mode'] === 'select_albums' ? '' : 'display:none;'; ?>">
                        <?php foreach ($albums as $a) :
                            $a_slug = sanitize_title($a['name']);
                            ?>
                            <option value="<?php echo esc_attr($a_slug); ?>" <?php echo in_array($a_slug, $display['albums'], true) ? 'selected' : ''; ?>><?php echo esc_html($a['name'] . ' (' . $count_lbl($a['photos']) . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="ft-album-display-album-hint" style="display:<?php echo $display['mode'] === 'select_albums' ? 'block' : 'none'; ?>;font-size:12px;color:#646970;margin-top:4px;"><?php echo esc_html__('Удерживайте Ctrl (⌘ на Mac), чтобы выбрать несколько альбомов.', 'genius-family-tree'); ?></span>
                </p>
                <hr>
                <p style="margin-bottom:6px;"><strong><?php echo esc_html__('Предпросмотр', 'genius-family-tree'); ?></strong></p>
                <div class="ft-album-display-preview" data-post-id="<?php echo esc_attr($post_id); ?>" data-section="<?php echo esc_attr($section); ?>">
                    <?php echo self::render_album_display_preview($post_id, $display['mode'], $display['album'], $display['albums'], $section); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML рендера ?>
                </div>
                <hr>
                <p>
                    <a href="<?php echo esc_url($manage_url); ?>" class="button" target="_blank">
                        <?php echo esc_html($edit_btn); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    /**
     * Предпросмотр в метабоксе использует прежний простой рендер
     * (не стандартную разметку страницы персоны).
     *
     * @param int    $post_id
     * @param string $mode       режим отображения.
     * @param string $album_slug slug альбома (для album_photos).
     * @param array  $albums     массив выбранных альбомов (для select_albums).
     * @param string $section    ключ раздела.
     */
    public static function render_album_display_preview($post_id, $mode, $album_slug = '', $albums = array(), $section = 'photoalbums') {
        $post_id = (int) $post_id;
        $section = sanitize_key($section);
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $member = get_post($post_id);
        if (!$member || $member->post_type !== 'family_member') {
            return '<p>' . esc_html__('Персоналия не найдена.', 'genius-family-tree') . '</p>';
        }
        if (!in_array($mode, array('all', 'album_photos', 'album_covers', 'select_albums'), true)) {
            $mode = 'all';
        }
        $plugin = Family_Tree_Plugin::get_instance();
        $albums_all = ft_get_albums($post_id, $section);
        $albums_all = array_values(array_filter($albums_all, function($a) { return !empty($a['photos']); }));
        if (empty($albums_all)) {
            if ('photoalbums' === $section) {
                return '<p>' . esc_html__('У персоны пока нет фотографий.', 'genius-family-tree') . '</p>';
            }
            return '<p>' . esc_html__('У персоны пока нет материалов в этом разделе.', 'genius-family-tree') . '</p>';
        }
        $terms = ft_archive_section_terms($section);
        $sec_cfg = ft_get_archive_sections();
        $sec_title = (!empty($sec_cfg[$section]['title'])) ? $sec_cfg[$section]['title'] : $terms['name'];
        if ($mode === 'album_covers' || $mode === 'select_albums') {
            $selected = array();
            if ($mode === 'select_albums') {
                $selected_slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) $albums))));
                foreach ($albums_all as $a) {
                    if (in_array(sanitize_title($a['name']), $selected_slugs, true)) {
                        $selected[] = $a;
                    }
                }
                if (empty($selected)) {
                    return '<p>' . esc_html__('Папки/альбомы не выбраны.', 'genius-family-tree') . '</p>';
                }
            } else {
                $selected = $albums_all;
            }
            $html = '<div class="ft-albums-album"><h2 class="ft-albums-album-title">' . esc_html($sec_title) . '</h2>';
            $html .= '<div class="ft-albums-covers" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">';
            foreach ($selected as $a) {
                $html .= $plugin->render_album_cover_card($member, $a, $section);
            }
            $html .= '</div></div>';
            return $html;
        }
        if ($mode === 'album_photos') {
            $target = null;
            foreach ($albums_all as $a) {
                if (sanitize_title($a['name']) === sanitize_title($album_slug)) {
                    $target = $a;
                    break;
                }
            }
            if (!$target) {
                return '<p>' . esc_html__('Выбранная папка/альбом не найдены.', 'genius-family-tree') . '</p>';
            }
            $photos = array_values(array_filter(array_map('intval', (array) $target['photos']), 'wp_attachment_is_image'));
            $title = $target['name'];
            $desc = !empty($target['description']) ? $target['description'] : '';
        } else {
            $photos = array();
            foreach ($albums_all as $a) {
                $p = array_values(array_filter(array_map('intval', (array) $a['photos']), 'wp_attachment_is_image'));
                $photos = array_merge($photos, $p);
            }
            $title = '';
            $desc = '';
        }
        if (empty($photos)) {
            return '<p>' . esc_html__('Фотографии не найдены.', 'genius-family-tree') . '</p>';
        }
        $photos = array_slice($photos, 0, 30);
        $html = '<div class="ft-albums-album ft-album-view">';
        if ($title !== '') {
            $html .= '<h2 class="ft-albums-album-title">' . esc_html($title) . '</h2>';
            if ($desc !== '') {
                $html .= '<div class="ft-album-description" style="margin:-8px 0 16px;color:#50575e;font-size:14px;line-height:1.6;">' . nl2br(esc_html($desc)) . '</div>';
            }
        }
        $html .= $plugin->render_album_photos_grid($photos, 'ft-album-preview-' . (int) $post_id, $member, $title, 'lightbox');
        $html .= '</div>';
        return $html;
    }
    /**
     * AJAX: возвращает предпросмотр секции «Фотоальбомы» по выбранному режиму.
     */
    public static function ajax_get_album_preview() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'family_tree_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'all';
        if (!in_array($mode, array('all', 'album_photos', 'album_covers', 'select_albums'), true)) {
            $mode = 'all';
        }
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $album = isset($_POST['album']) ? sanitize_title(wp_unslash($_POST['album'])) : '';
        $albums = isset($_POST['albums']) ? (array) wp_unslash($_POST['albums']) : array();
        $albums = array_values(array_unique(array_filter(array_map('sanitize_title', $albums))));
        wp_send_json_success(array('html' => self::render_album_display_preview($post_id, $mode, $album, $albums, $section)));
    }
    /**
     * AJAX: возвращает HTML метабокса раздела для перерисовки
     */
    public static function ajax_get_album_metabox() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'family_tree_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        wp_send_json_success(array('html' => self::album_metabox_content($post_id, $section)));
    }
    /**
     * Выводит элементы фотографий альбома в админке
     */
    private static function render_album_photo_items($post_id, $album_index, $photos) {
        if (empty($photos)) {
            echo '<p class="description ft-album-no-photos">' . esc_html__('В альбоме пока нет фотографий.', 'genius-family-tree') . '</p>';
            return;
        }
        foreach ($photos as $photo_id) {
            $thumb = wp_get_attachment_image_src($photo_id, 'thumbnail');
            $full = wp_get_attachment_image_src($photo_id, 'full');
            if (!$thumb) {
                continue;
            }
            ?>
            <div class="ft-album-editor-item" data-photo-id="<?php echo esc_attr($photo_id); ?>" draggable="true">
                <img src="<?php echo esc_url($thumb[0]); ?>" alt="" width="100" height="100" draggable="false">
                <button type="button" class="ft-remove-album-photo-btn" data-post-id="<?php echo esc_attr($post_id); ?>" data-album-index="<?php echo esc_attr($album_index); ?>" data-photo-id="<?php echo esc_attr($photo_id); ?>" title="<?php echo esc_attr__('Удалить фото из альбома', 'genius-family-tree'); ?>" draggable="false"></button>
                <?php if ($full) : ?>
                <a href="<?php echo esc_url($full[0]); ?>" class="ft-album-editor-view ft-admin-lightbox" data-gallery="ft-admin-album-photos" title="<?php echo esc_attr(get_the_title($photo_id)); ?>" draggable="false">
                    <span class="dashicons dashicons-external"></span>
                </a>
                <?php endif; ?>
                <?php $ft_edit_link = get_edit_post_link($photo_id, ''); ?>
                <?php if ($ft_edit_link) : ?>
                    <a href="<?php echo esc_url($ft_edit_link); ?>" target="_blank" class="ft-album-editor-edit" title="<?php echo esc_attr__('Редактировать фото (название, описание, Кто на фото)', 'genius-family-tree'); ?>" draggable="false">
                        <span class="dashicons dashicons-edit"></span>
                    </a>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    // Метод для синхронизации данных брака между супругами
    private static function sync_spouse_data($person_id, $spouses_data) {
        // Получаем текущие данные о супругах для этой персоны
        $current_spouses = get_post_meta($person_id, '_family_member_spouses', true);
        if (!is_array($current_spouses)) {
            $current_spouses = array();
        }
        // Проходим по всем супругам
        foreach ($spouses_data as $spouse_key => $spouse_data) {
            if (!isset($spouse_data['id']) || empty($spouse_data['id'])) {
                continue;
            }
            $spouse_id = $spouse_data['id'];
            // Получаем данные о супругах супруга
            $spouse_spouses = get_post_meta($spouse_id, '_family_member_spouses', true);
            if (!is_array($spouse_spouses)) {
                $spouse_spouses = array();
            }
            // Ищем, есть ли текущая персона в списке супругов у супруга
            $found = false;
            $spouse_spouse_key = null;
            foreach ($spouse_spouses as $key => $spouse_spouse_data) {
                if (isset($spouse_spouse_data['id']) && $spouse_spouse_data['id'] == $person_id) {
                    $found = true;
                    $spouse_spouse_key = $key;
                    break;
                }
            }
            // Если текущая персона не найдена у супруга, добавляем её
            if (!$found) {
                $spouse_spouses[] = array(
                    'id' => $person_id,
                    'married_day' => isset($spouse_data['married_day']) ? $spouse_data['married_day'] : '',
                    'married_month' => isset($spouse_data['married_month']) ? $spouse_data['married_month'] : '',
                    'married_year' => isset($spouse_data['married_year']) ? $spouse_data['married_year'] : '',
                    'divorced_day' => isset($spouse_data['divorced_day']) ? $spouse_data['divorced_day'] : '',
                    'divorced_month' => isset($spouse_data['divorced_month']) ? $spouse_data['divorced_month'] : '',
                    'divorced_year' => isset($spouse_data['divorced_year']) ? $spouse_data['divorced_year'] : '',
                    'divorced_unknown' => isset($spouse_data['divorced_unknown']) ? $spouse_data['divorced_unknown'] : ''
                );
            } else {
                // Если найдена, обновляем данные
                $spouse_spouses[$spouse_spouse_key] = array(
                    'id' => $person_id,
                    'married_day' => isset($spouse_data['married_day']) ? $spouse_data['married_day'] : '',
                    'married_month' => isset($spouse_data['married_month']) ? $spouse_data['married_month'] : '',
                    'married_year' => isset($spouse_data['married_year']) ? $spouse_data['married_year'] : '',
                    'divorced_day' => isset($spouse_data['divorced_day']) ? $spouse_data['divorced_day'] : '',
                    'divorced_month' => isset($spouse_data['divorced_month']) ? $spouse_data['divorced_month'] : '',
                    'divorced_year' => isset($spouse_data['divorced_year']) ? $spouse_data['divorced_year'] : '',
                    'divorced_unknown' => isset($spouse_data['divorced_unknown']) ? $spouse_data['divorced_unknown'] : ''
                );
            }
            // Сохраняем обновленные данные у супруга
            update_post_meta($spouse_id, '_family_member_spouses', $spouse_spouses);
        }
    }
    // === НОВАЯ ФУНКЦИЯ: синхронизация детей и родителей ===
    private static function sync_children_data($parent_id, $children_ids) {
        $parent_gender = get_post_meta($parent_id, '_family_member_gender', true);
        $parent_is_male = ($parent_gender === 'male');
        foreach ($children_ids as $child_id) {
            if (!$child_id) continue;
            // Обновляем у ребёнка отца или мать
            if ($parent_is_male) {
                update_post_meta($child_id, '_family_member_father', $parent_id);
            } else {
                update_post_meta($child_id, '_family_member_mother', $parent_id);
            }
            // Также обновим _children у ребёнка? Нет, это не нужно — дети не имеют детей в этом контексте.
            // Но убедимся, что родитель есть в списке детей ребёнка? Нет, это избыточно.
        }
        // Теперь нужно убедиться, что у детей, которых убрали, родитель удалён
        // Получим старый список детей
        $old_children = get_post_meta($parent_id, '_family_member_children', true);
        if (!is_array($old_children)) $old_children = array();
        $old_children = array_filter(array_map('intval', $old_children));
        $removed_children = array_diff($old_children, $children_ids);
        foreach ($removed_children as $child_id) {
            if (!$child_id) continue;
            $current_father = get_post_meta($child_id, '_family_member_father', true);
            $current_mother = get_post_meta($child_id, '_family_member_mother', true);
            if ($parent_is_male && $current_father == $parent_id) {
                delete_post_meta($child_id, '_family_member_father');
            } elseif (!$parent_is_male && $current_mother == $parent_id) {
                delete_post_meta($child_id, '_family_member_mother');
            }
        }
    }
    public static function save_meta_boxes($post_id) {
        // Проверка автосохранения
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Обработка сохранения для family_member
        if ('family_member' == get_post_type($post_id)) {
            self::save_family_member_meta($post_id);
        }
        
        // Обработка сохранения для family_group
        if ('family_group' == get_post_type($post_id)) {
            self::save_family_group_meta($post_id);
        }
        
        // Обработка «Кто на фото» и «Дата и место съемки» для вложений-изображений
        if ('attachment' == get_post_type($post_id)) {
            self::save_photo_taken_meta($post_id);
            self::save_photo_people_meta($post_id);
        }
    }

    /**
     * Рендер метабокса «Кто на фото» в редакторе вложения.
     */
    public static function render_photo_people_meta_box($post) {
        if (!wp_attachment_is_image($post->ID)) {
            echo '<p>' . esc_html__('Отметить людей можно только на изображениях.', 'genius-family-tree') . '</p>';
            return;
        }
        wp_nonce_field('ft_photo_people_nonce', 'ft_photo_people_nonce');
        $saved = get_post_meta($post->ID, '_ft_photo_people', true);
        if (!is_array($saved)) {
            $saved = array();
        }
        $img = wp_get_attachment_image_src($post->ID, 'large');
        ?>
        <div class="ft-photo-people">
            <div class="ft-photo-people__stage" id="ft-people-stage">
                <?php if ($img) : ?>
                    <img class="ft-photo-people__img" id="ft-people-img" src="<?php echo esc_url($img[0]); ?>" alt="" />
                <?php endif; ?>
            </div>
            <input type="hidden" name="ft_photo_people" id="ft-photo-people-input" value="<?php echo esc_attr(wp_json_encode($saved, JSON_UNESCAPED_UNICODE)); ?>" />
            <p class="description" style="margin-top:8px;">
                <?php echo esc_html__('Кликните по фотографии в том месте, где изображён человек, и выберите его из списка персоналий.', 'genius-family-tree'); ?>
            </p>
            <ul class="ft-photo-people__list" id="ft-people-list"></ul>
        </div>
        <?php
    }

    /**
     * Рендер метабокса «Дата и место съемки» в редакторе вложения.
     */
    public static function render_photo_taken_meta_box($post) {
        if (!wp_attachment_is_image($post->ID)) {
            return;
        }
        wp_nonce_field('ft_photo_taken_nonce', 'ft_photo_taken_nonce');
        $taken_date     = get_post_meta($post->ID, '_ft_photo_taken_date', true);
        $taken_location = get_post_meta($post->ID, '_ft_photo_location', true);
        ?>
        <p>
            <label for="ft-photo-taken-date"><strong><?php esc_html_e('Дата съемки', 'genius-family-tree'); ?></strong></label><br />
            <input type="text" class="widefat" name="ft_photo_taken_date" id="ft-photo-taken-date" value="<?php echo esc_attr($taken_date); ?>" placeholder="2026-09-11" />
        </p>
        <p>
            <label for="ft-photo-taken-location"><strong><?php esc_html_e('Место съемки', 'genius-family-tree'); ?></strong></label><br />
            <input type="text" class="widefat" name="ft_photo_taken_location" id="ft-photo-taken-location" value="<?php echo esc_attr($taken_location); ?>" />
        </p>
        <?php
    }

    /**
     * Сохранение даты и места съемки фото.
     */
    public static function save_photo_taken_meta($post_id) {
        if (!wp_attachment_is_image($post_id)) {
            return;
        }
        if (!self::verify_photo_taken_nonce($post_id)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $taken_date     = isset($_POST['ft_photo_taken_date']) ? sanitize_text_field(wp_unslash($_POST['ft_photo_taken_date'])) : '';
        $taken_location = isset($_POST['ft_photo_taken_location']) ? sanitize_text_field(wp_unslash($_POST['ft_photo_taken_location'])) : '';
        update_post_meta($post_id, '_ft_photo_taken_date', $taken_date);
        update_post_meta($post_id, '_ft_photo_location', $taken_location);
    }

    /**
     * Проверка nonce для «Дата и место съемки».
     * Основной вариант — собственный nonce метабокса; если поля метабокса по какой-то
     * причине не попали в запрос, используется общий nonce классического редактора
     * (update-post_{id}), который WP присылает в том же POST.
     */
    private static function verify_photo_taken_nonce($post_id) {
        if (isset($_POST['ft_photo_taken_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ft_photo_taken_nonce'])), 'ft_photo_taken_nonce')) {
            return true;
        }
        if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-post_' . $post_id)) {
            return true;
        }
        return false;
    }

    /**
     * Сохранение отметок «Кто на фото».
     */
    public static function save_photo_people_meta($post_id) {
        if (!wp_attachment_is_image($post_id)) {
            return;
        }
        if (!isset($_POST['ft_photo_people_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ft_photo_people_nonce'])), 'ft_photo_people_nonce')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $raw = isset($_POST['ft_photo_people']) ? wp_unslash($_POST['ft_photo_people']) : '';
        $tags = json_decode($raw, true);
        $clean = array();
        if (is_array($tags)) {
            foreach ($tags as $t) {
                if (!is_array($t) || !isset($t['x'], $t['y'], $t['member'])) {
                    continue;
                }
                $x = (float) $t['x'];
                $y = (float) $t['y'];
                $member_id = (int) $t['member'];
                if ($x < 0 || $x > 100 || $y < 0 || $y > 100) {
                    continue;
                }
                if ($member_id && 'family_member' === get_post_type($member_id)) {
                    $clean[] = array(
                        'x'      => round($x, 2),
                        'y'      => round($y, 2),
                        'member' => $member_id,
                    );
                }
            }
        }
        update_post_meta($post_id, '_ft_photo_people', $clean);
    }

    /**
     * AJAX: мгновенное сохранение даты и места съемки (из редактора вложения).
     */
    public static function ajax_save_photo_taken() {
        check_ajax_referer('ft_photo_taken_ajax', 'nonce');
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id || !wp_attachment_is_image($post_id)) {
            wp_send_json_error(array('message' => 'Invalid post'));
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        $taken_date     = isset($_POST['taken_date']) ? sanitize_text_field(wp_unslash($_POST['taken_date'])) : '';
        $taken_location = isset($_POST['taken_location']) ? sanitize_text_field(wp_unslash($_POST['taken_location'])) : '';
        update_post_meta($post_id, '_ft_photo_taken_date', $taken_date);
        update_post_meta($post_id, '_ft_photo_location', $taken_location);
        wp_send_json_success();
    }

    /**
     * AJAX: мгновенное сохранение «Кто на фото» (из редактора, без ожидания meta-box-loader).
     */
    public static function ajax_save_photo_people() {
        check_ajax_referer('ft_photo_people_nonce', 'nonce');
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $raw = isset($_POST['tags']) ? wp_unslash($_POST['tags']) : '';
        if (!$post_id || !wp_attachment_is_image($post_id)) {
            wp_send_json_error(array('message' => 'Invalid post'));
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        $tags = json_decode($raw, true);
        $clean = array();
        if (is_array($tags)) {
            foreach ($tags as $t) {
                if (!is_array($t) || !isset($t['x'], $t['y'], $t['member'])) {
                    continue;
                }
                $x = (float) $t['x'];
                $y = (float) $t['y'];
                $member_id = (int) $t['member'];
                if ($x < 0 || $x > 100 || $y < 0 || $y > 100) {
                    continue;
                }
                if ($member_id && 'family_member' === get_post_type($member_id)) {
                    $clean[] = array(
                        'x'      => round($x, 2),
                        'y'      => round($y, 2),
                        'member' => $member_id,
                    );
                }
            }
        }
        update_post_meta($post_id, '_ft_photo_people', $clean);
        wp_send_json_success();
    }

    /**
     * Авто-обновление slug фотографии при смене названия:
     * wp_insert_post_data применяется ДО записи в БД и работает при любом способе
     * сохранения (REST, админка, programmatic) — в отличие от save_post.
     */
    public static function filter_attachment_slug($data, $postarr) {
        if ($data['post_type'] !== 'attachment') {
            return $data;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $data;
        }
        if (isset($data['post_status']) && $data['post_status'] === 'trash') {
            return $data;
        }
        $mime = isset($data['post_mime_type']) ? $data['post_mime_type'] : '';
        if (strpos($mime, 'image/') !== 0) {
            return $data;
        }
        $title = isset($data['post_title']) ? trim($data['post_title']) : '';
        if ($title === '') {
            return $data;
        }
        $expected = sanitize_title($title);
        if ($expected === '') {
            return $data;
        }
        // Если slug уже совпадает с sanitized title — ничего не меняем (避免 бесконечного unique).
        $current = isset($data['post_name']) ? $data['post_name'] : '';
        if ($current === $expected) {
            return $data;
        }
        $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        $parent = 0;
        if ($post_id) {
            $existing = get_post($post_id);
            $parent = $existing ? (int) $existing->post_parent : 0;
        } elseif (isset($postarr['post_parent'])) {
            $parent = (int) $postarr['post_parent'];
        }
        $status = isset($data['post_status']) ? $data['post_status'] : 'publish';
        $new_slug = wp_unique_post_slug($expected, $post_id, $status, 'attachment', $parent);
        $data['post_name'] = $new_slug;
        return $data;
    }

    /**
     * Сохранение мета-данных для family_member
     */
    public static function save_family_member_meta($post_id) {
        // Проверка nonce для деталей
        if (!isset($_POST['family_member_details_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['family_member_details_nonce'])), 'family_member_details_nonce')) {
            return;
        }
        if (!isset($_POST['family_member_relationships_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['family_member_relationships_nonce'])), 'family_member_relationships_nonce')) {
            return;
        }
        // Проверка nonce для группы
        if (!isset($_POST['family_member_group_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['family_member_group_nonce'])), 'family_member_group_nonce')) {
            return;
        }
        // Проверка прав доступа
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // === СОХРАНЕНИЕ ПРИВАТНОСТИ ===
        $private = !empty($_POST['family_member_private']) ? 1 : 0;
        if ($private) {
            update_post_meta($post_id, '_family_member_private', '1');
        } else {
            delete_post_meta($post_id, '_family_member_private');
        }
        
        // === СОХРАНЕНИЕ СОГЛАСИЯ НА ПУБЛИКАЦИЮ (ФЗ-152) ===
        // Отметка неотъемлема: если согласие уже дано, оно сохраняется навсегда.
        if (!empty($_POST['family_member_consent']) && !get_post_meta($post_id, '_family_member_consent', true)) {
            update_post_meta($post_id, '_family_member_consent', '1');
        }
        
        // === СОХРАНЕНИЕ СЕМЕЙНОЙ ГРУППЫ ===
        $group_id = !empty($_POST['family_member_group_id']) ? intval($_POST['family_member_group_id']) : 0;
        
        // Если группа не выбрана, автоматически назначаем группу по умолчанию
        if (!$group_id) {
            $default_group_id = get_option('family_tree_default_group_id', 0);
            if ($default_group_id && get_post($default_group_id)) {
                $group_id = $default_group_id;
            }
        }
        
        if ($group_id) {
            update_post_meta($post_id, '_family_member_group_id', $group_id);
        } else {
            delete_post_meta($post_id, '_family_member_group_id');
        }
        
        // === СОХРАНЕНИЕ КОРНЕВОГО ЭЛЕМЕНТА ===
        if ($group_id && isset($_POST['family_member_is_root'])) {
            if ($_POST['family_member_is_root'] == '1') {
                self::clear_root_member($group_id);
                update_post_meta($post_id, '_family_member_is_root', '1');
            } else {
                $current_root = self::get_root_member_id($group_id);
                if ($current_root == $post_id) {
                    delete_post_meta($post_id, '_family_member_is_root');
                }
            }
        }
        
        // === СОХРАНЕНИЕ ДЕТАЛЕЙ ===
        if (isset($_POST['family_member_first_name'])) {
            update_post_meta($post_id, '_family_member_first_name', sanitize_text_field(wp_unslash($_POST['family_member_first_name'])));
        }
        if (isset($_POST['family_member_middle_name'])) {
            update_post_meta($post_id, '_family_member_middle_name', sanitize_text_field(wp_unslash($_POST['family_member_middle_name'])));
        }
        if (isset($_POST['family_member_last_name'])) {
            update_post_meta($post_id, '_family_member_last_name', sanitize_text_field(wp_unslash($_POST['family_member_last_name'])));
        }
        // Девичья фамилия - обновляем всегда, даже если пустая
        $maiden_name = isset($_POST['family_member_maiden_name']) ? sanitize_text_field(wp_unslash($_POST['family_member_maiden_name'])) : '';
        update_post_meta($post_id, '_family_member_maiden_name', $maiden_name);
        if (isset($_POST['family_member_gender'])) {
            update_post_meta($post_id, '_family_member_gender', sanitize_text_field(wp_unslash($_POST['family_member_gender'])));
        }
        // Дата рождения
        $birth_day = !empty($_POST['family_member_birth_day']) ? intval($_POST['family_member_birth_day']) : 0;
        $birth_month = !empty($_POST['family_member_birth_month']) ? intval($_POST['family_member_birth_month']) : 0;
        $birth_year = !empty($_POST['family_member_birth_year']) ? intval($_POST['family_member_birth_year']) : 0;
        update_post_meta($post_id, '_family_member_birth_day', $birth_day);
        update_post_meta($post_id, '_family_member_birth_month', $birth_month);
        update_post_meta($post_id, '_family_member_birth_year', $birth_year);
        // --- СОСТАВНАЯ ДАТА РОЖДЕНИЯ ---
        if ($birth_year) {
            if ($birth_month && $birth_day) {
                $birth_date = sprintf('%04d-%02d-%02d', $birth_year, $birth_month, $birth_day);
            } elseif ($birth_month) {
                $birth_date = sprintf('%04d-%02d', $birth_year, $birth_month);
            } else {
                $birth_date = (string)$birth_year;
            }
            update_post_meta($post_id, '_family_member_birth_date', $birth_date);
        } else {
            delete_post_meta($post_id, '_family_member_birth_date');
        }
        update_post_meta($post_id, '_family_member_birth_old_style', !empty($_POST['family_member_birth_old_style']) ? 1 : 0);
        if (isset($_POST['family_member_birth_new_day'])) {
            update_post_meta($post_id, '_family_member_birth_new_day', intval($_POST['family_member_birth_new_day']));
        }
        if (isset($_POST['family_member_birth_new_month'])) {
            update_post_meta($post_id, '_family_member_birth_new_month', intval($_POST['family_member_birth_new_month']));
        }
        if (isset($_POST['family_member_birth_new_year'])) {
            update_post_meta($post_id, '_family_member_birth_new_year', intval($_POST['family_member_birth_new_year']));
        }
        if (isset($_POST['family_member_birth_place'])) {
            update_post_meta($post_id, '_family_member_birth_place', sanitize_text_field(wp_unslash($_POST['family_member_birth_place'])));
        }
        // Дата смерти
        $is_deceased = !empty($_POST['family_member_is_deceased']);
        update_post_meta($post_id, '_family_member_is_deceased', $is_deceased ? 1 : 0);
        $death_day = !empty($_POST['family_member_death_day']) ? intval($_POST['family_member_death_day']) : 0;
        $death_month = !empty($_POST['family_member_death_month']) ? intval($_POST['family_member_death_month']) : 0;
        $death_year = !empty($_POST['family_member_death_year']) ? intval($_POST['family_member_death_year']) : 0;
        update_post_meta($post_id, '_family_member_death_day', $death_day);
        update_post_meta($post_id, '_family_member_death_month', $death_month);
        update_post_meta($post_id, '_family_member_death_year', $death_year);
        // --- СОСТАВНАЯ ДАТА СМЕРТИ ---
        if ($death_year) {
            if ($death_month && $death_day) {
                $death_date = sprintf('%04d-%02d-%02d', $death_year, $death_month, $death_day);
            } elseif ($death_month) {
                $death_date = sprintf('%04d-%02d', $death_year, $death_month);
            } else {
                $death_date = (string)$death_year;
            }
            update_post_meta($post_id, '_family_member_death_date', $death_date);
        } else {
            delete_post_meta($post_id, '_family_member_death_date');
        }
        update_post_meta($post_id, '_family_member_death_old_style', !empty($_POST['family_member_death_old_style']) ? 1 : 0);
        if (isset($_POST['family_member_death_new_day'])) {
            update_post_meta($post_id, '_family_member_death_new_day', intval($_POST['family_member_death_new_day']));
        }
        if (isset($_POST['family_member_death_new_month'])) {
            update_post_meta($post_id, '_family_member_death_new_month', intval($_POST['family_member_death_new_month']));
        }
        if (isset($_POST['family_member_death_new_year'])) {
            update_post_meta($post_id, '_family_member_death_new_year', intval($_POST['family_member_death_new_year']));
        }
        if (isset($_POST['family_member_death_cause'])) {
            update_post_meta($post_id, '_family_member_death_cause', sanitize_text_field(wp_unslash($_POST['family_member_death_cause'])));
        }
        if (isset($_POST['family_member_death_place'])) {
            update_post_meta($post_id, '_family_member_death_place', sanitize_text_field(wp_unslash($_POST['family_member_death_place'])));
        }
        // === СОХРАНЕНИЕ СВЯЗЕЙ ===
        $new_father_id = !empty($_POST['family_member_father']) ? intval($_POST['family_member_father']) : 0;
        $new_mother_id = !empty($_POST['family_member_mother']) ? intval($_POST['family_member_mother']) : 0;
        // Получаем старые значения
        $old_father_id = get_post_meta($post_id, '_family_member_father', true);
        $old_mother_id = get_post_meta($post_id, '_family_member_mother', true);
        // Функция для обновления списка детей у родителя
        $update_children = function($parent_id, $child_id, $add = true) {
            if (!$parent_id || !$child_id) return;
            $children = get_post_meta($parent_id, '_family_member_children', true);
            if (!is_array($children)) $children = array();
            $children = array_map('intval', $children);
            $children = array_filter($children); // удаляем нули
            if ($add) {
                if (!in_array($child_id, $children)) {
                    $children[] = $child_id;
                }
            } else {
                $children = array_diff($children, array($child_id));
            }
            update_post_meta($parent_id, '_family_member_children', array_values($children));
        };
        // Удаляем ребёнка из старых родителей (если они изменились)
        if ($old_father_id && $old_father_id != $new_father_id) {
            $update_children($old_father_id, $post_id, false);
        }
        if ($old_mother_id && $old_mother_id != $new_mother_id) {
            $update_children($old_mother_id, $post_id, false);
        }
        // Добавляем ребёнка к новым родителям
        if ($new_father_id) {
            $update_children($new_father_id, $post_id, true);
        }
        if ($new_mother_id) {
            $update_children($new_mother_id, $post_id, true);
        }
        // Сохраняем новые значения отца/матери
        if ($new_father_id) {
            update_post_meta($post_id, '_family_member_father', $new_father_id);
        } else {
            delete_post_meta($post_id, '_family_member_father');
        }
        if ($new_mother_id) {
            update_post_meta($post_id, '_family_member_mother', $new_mother_id);
        } else {
            delete_post_meta($post_id, '_family_member_mother');
        }
        // === СУПРУГИ ===
        $sanitized_spouses = array();
        $raw_spouses = filter_input(INPUT_POST, 'family_member_spouses', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: array();
        if (is_array($raw_spouses)) {
            foreach ($raw_spouses as $key => $item) {
                if (!is_array($item)) continue;
                $sanitized_spouses[sanitize_text_field($key)] = array(
                    'id' => isset($item['id']) ? absint($item['id']) : 0,
                    'married_day' => isset($item['married_day']) ? absint($item['married_day']) : 0,
                    'married_month' => isset($item['married_month']) ? absint($item['married_month']) : 0,
                    'married_year' => isset($item['married_year']) ? absint($item['married_year']) : 0,
                    'divorced_day' => isset($item['divorced_day']) ? absint($item['divorced_day']) : 0,
                    'divorced_month' => isset($item['divorced_month']) ? absint($item['divorced_month']) : 0,
                    'divorced_year' => isset($item['divorced_year']) ? absint($item['divorced_year']) : 0,
                    'divorced_unknown' => !empty($item['divorced_unknown']) ? 1 : 0,
                );
            }
        }
        update_post_meta($post_id, '_family_member_spouses', $sanitized_spouses);
        self::sync_spouse_data($post_id, $sanitized_spouses);
        // === ДЕТИ (ручной выбор) ===
        $sanitized_children = array();
        $raw_children = filter_input(INPUT_POST, 'family_member_children', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: array();
        if (is_array($raw_children)) {
            foreach ($raw_children as $child_id) {
                $cid = absint($child_id);
                if ($cid) $sanitized_children[] = $cid;
            }
        }
        update_post_meta($post_id, '_family_member_children', $sanitized_children);
        // === СИНХРОНИЗАЦИЯ ДЕТЕЙ ↔ РОДИТЕЛЕЙ ===
        self::sync_children_data($post_id, $sanitized_children);
        // === ПОДПИСЬ ===
        if (isset($_POST['family_member_signature'])) {
            $signature_nonce_ok = isset($_POST['family_member_signature_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['family_member_signature_nonce'])), 'family_member_signature_nonce');
            if ($signature_nonce_ok) {
                $signature_id = absint($_POST['family_member_signature']);
                if ($signature_id) {
                    update_post_meta($post_id, '_family_member_signature_id', $signature_id);
                } else {
                    delete_post_meta($post_id, '_family_member_signature_id');
                }
            }
        }
    }
    
    /**
     * Сохранение мета-данных для family_group
     */
    public static function save_family_group_meta($post_id) {
        // Проверка прав доступа
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Проверка nonce для членов группы
        if (isset($_POST['family_group_members_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['family_group_members_nonce'])), 'family_group_members_nonce')) {
            // Здесь можно добавить дополнительную логику сохранения для группы
        }

        // === СОХРАНЕНИЕ ПРИВАТНОСТИ ГРУППЫ ===
        if (isset($_POST['family_group_privacy_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['family_group_privacy_nonce'])), 'family_group_privacy_nonce')) {
            $private = !empty($_POST['family_group_private']) ? 1 : 0;
            if ($private) {
                update_post_meta($post_id, '_family_group_private', '1');
            } else {
                delete_post_meta($post_id, '_family_group_private');
            }
        }
    }
    
    private static function clear_root_member($group_id = 0) {
        $args = array(
            'post_type' => 'family_member',
            'posts_per_page' => -1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_key' => '_family_member_is_root',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'meta_value' => '1',
            'fields' => 'ids',
            'post_status' => 'any'
        );
        if ($group_id) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $args['meta_query'] = array(
                array(
                    'key' => '_family_member_group_id',
                    'value' => $group_id,
                    'compare' => '='
                )
            );
        }
        $root_members = get_posts($args);
        foreach ($root_members as $member_id) {
            delete_post_meta($member_id, '_family_member_is_root');
        }
    }
    
    private static function get_root_member_id($group_id = 0) {
        $args = array(
            'post_type' => 'family_member',
            'posts_per_page' => 1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_key' => '_family_member_is_root',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'meta_value' => '1',
            'fields' => 'ids',
            'post_status' => 'any'
        );
        if ($group_id) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $args['meta_query'] = array(
                array(
                    'key' => '_family_member_group_id',
                    'value' => $group_id,
                    'compare' => '='
                )
            );
        }
        $root = get_posts($args);
        return !empty($root) ? $root[0] : 0;
    }
}