<?php
class Family_Tree_Meta_Boxes {
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
    }
    public static function enqueue_admin_scripts($hook) {
        if (($hook == 'post-new.php' || $hook == 'post.php') && (get_post_type() == 'family_member' || get_post_type() == 'family_group')) {
            wp_enqueue_script('family-tree-select2', FAMILY_TREE_PLUGIN_URL . 'assets/vendor/select2.min.js', array('jquery'), '4.0.13', true);
            wp_enqueue_style('family-tree-select2', FAMILY_TREE_PLUGIN_URL . 'assets/vendor/select2.min.css', array(), '4.0.13');
        }
    }
    public static function add_meta_boxes() {
        // Метабоксы для family_member
        add_meta_box('family-member-details', esc_html__('Детали члена семьи', 'genius-family-tree'), array(__CLASS__, 'render_details_meta_box'), 'family_member', 'normal', 'high');
        add_meta_box('family-member-relationships', esc_html__('Родственные связи', 'genius-family-tree'), array(__CLASS__, 'render_relationships_meta_box'), 'family_member', 'normal', 'high');
        // Метабокс для выбора семейной группы
        add_meta_box('family-member-group', esc_html__('Семейная группа', 'genius-family-tree'), array(__CLASS__, 'render_group_meta_box'), 'family_member', 'side', 'high');
        
        // Метабоксы для family_group - удалили, так как теперь используется метабокс из genius-family-tree.php
        // add_meta_box('family-group-members', esc_html__('Члены семейной группы', 'genius-family-tree'), array(__CLASS__, 'render_group_members_meta_box'), 'family_group', 'normal', 'high');
    }
    
    /**
     * Отображение метабокса выбора семейной группы для члена семьи
     */
    public static function render_group_meta_box($post) {
        wp_nonce_field('family_member_group_nonce', 'family_member_group_nonce');
        $group_id = get_post_meta($post->ID, '_family_member_group_id', true);
        
        // Если группа не установлена, получаем группу по умолчанию
        if (empty($group_id)) {
            $group_id = get_option('family_tree_default_group_id', 0);
        }
        ?>
        <div class="family-tree-group-metabox">
            <p>
                <label for="family_member_group_id"><?php echo esc_html__('Выберите семейную группу:', 'genius-family-tree'); ?></label>
                <select name="family_member_group_id" id="family_member_group_id" class="widefat">
                    <option value=""><?php echo esc_html__('— Общая (без группы) —', 'genius-family-tree'); ?></option>
                    <?php
                    $groups = get_posts(array(
                        'post_type' => 'family_group',
                        'posts_per_page' => -1,
                        'post_status' => 'any',
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ));
                    foreach ($groups as $group) {
                        ?>
                        <option value="<?php echo esc_attr($group->ID); ?>" <?php selected($group_id, $group->ID); ?>>
                            <?php echo esc_html($group->post_title); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </p>
            <p class="description">
                <?php echo esc_html__('Если не выбрать группу, персона будет отображаться в общем древе.', 'genius-family-tree'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Отображение метабокса членов семейной группы
     */
    public static function render_group_members_meta_box($post) {
        wp_nonce_field('family_group_members_nonce', 'family_group_members_nonce');
        $group_id = $post->ID;
        
        // Получаем параметры поиска и сортировки
        $search_query = isset($_GET['ft_group_search']) ? sanitize_text_field($_GET['ft_group_search']) : '';
        $sort_order = isset($_GET['ft_group_sort']) ? sanitize_text_field($_GET['ft_group_sort']) : 'date_asc';

        // Строим запрос
        $args = array(
            'post_type' => 'family_member',
            'posts_per_page' => -1,
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
                $args['meta_key'] = '_family_member_birth_date';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
                break;
            case 'date_desc':
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
                background: #f9f9f9;
                border: 1px solid #ddd;
                padding: 12px;
                margin-bottom: 15px;
                border-radius: 4px;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
            }
            .ft-controls-bar input[type="text"],
            .ft-controls-bar select {
                min-width: 200px;
                padding: 6px 10px;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .ft-controls-bar button {
                padding: 6px 12px;
                background: #2271b1;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
                font-size: 13px;
                line-height: 1.4;
            }
            .ft-controls-bar button:hover {
                background: #135e96;
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
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                transition: transform 0.2s, box-shadow 0.2s;
                display: flex;
                flex-direction: column;
                position: relative;
            }
            .ft-member-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
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
                transition: transform 0.3s;
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
                padding: 4px 8px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                z-index: 2;
            }
            .ft-card-body {
                padding: 15px;
                flex-grow: 1;
                display: flex;
                flex-direction: column;
            }
            .ft-member-name {
                font-size: 18px;
                font-weight: 600;
                color: #1d2327;
                margin: 0 0 8px 0;
                line-height: 1.3;
            }
            .ft-member-name a {
                text-decoration: none;
                color: inherit;
            }
            .ft-member-name a:hover {
                color: #2271b1;
            }
            .ft-member-dates {
                font-size: 13px;
                color: #646970;
                margin-bottom: 10px;
                font-style: italic;
            }
            .ft-member-group {
                font-size: 12px;
                color: #2271b1;
                background: #f0f6fc;
                padding: 4px 8px;
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
                background: #f9f9f9;
                border-radius: 8px;
                border: 1px dashed #c3c4c7;
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
                    <a href="<?php echo esc_url(remove_query_arg(array('ft_group_search', 'ft_group_sort'))); ?>" style="padding: 6px 12px; color: #646970; text-decoration: none; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
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
                                        <svg style="width:12px;height:12px;vertical-align:middle;margin-right:4px;fill:#fff;" viewBox="0 0 24 24"><path d="M12 2L1 21h22L12 2zm0 3.5l8.5 14.5h-17L12 5.5z"/></svg>
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
            
            <p style="margin-top: 20px;">
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
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="number" name="family_member_birth_day" id="family_member_birth_day" 
                               value="<?php echo !empty($birth_day) ? esc_attr($birth_day) : ''; ?>" 
                               min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                        <input type="number" name="family_member_birth_month" id="family_member_birth_month" 
                               value="<?php echo !empty($birth_month) ? esc_attr($birth_month) : ''; ?>" 
                               min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                        <input type="number" name="family_member_birth_year" id="family_member_birth_year" 
                               value="<?php echo !empty($birth_year) ? esc_attr($birth_year) : ''; ?>" 
                               min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text" style="width: 80px;">
                    </div>
                    <p class="description"><?php echo esc_html__('Оставьте пустым, если дата неизвестна', 'genius-family-tree'); ?></p>
                    <!-- Чекбокс "по старому стилю" -->
                    <div style="margin-top: 10px;">
                        <input type="checkbox" name="family_member_birth_old_style" id="family_member_birth_old_style" value="1" <?php checked($birth_old_style, 1); ?>>
                        <label for="family_member_birth_old_style"><?php echo esc_html__('по старому стилю', 'genius-family-tree'); ?></label>
                    </div>
                    <!-- Поля для новой даты (по новому стилю) -->
                    <div id="birth_new_style_fields" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; <?php echo $birth_old_style ? '' : 'display: none;'; ?>">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html__('Дата по новому стилю', 'genius-family-tree'); ?>:</label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="number" name="family_member_birth_new_day" id="family_member_birth_new_day" 
                                   value="<?php echo !empty($birth_new_day) ? esc_attr($birth_new_day) : ''; ?>" 
                                   min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                            <input type="number" name="family_member_birth_new_month" id="family_member_birth_new_month" 
                                   value="<?php echo !empty($birth_new_month) ? esc_attr($birth_new_month) : ''; ?>" 
                                   min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                            <input type="number" name="family_member_birth_new_year" id="family_member_birth_new_year" 
                                   value="<?php echo !empty($birth_new_year) ? esc_attr($birth_new_year) : ''; ?>" 
                                   min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text" style="width: 80px;">
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
            <!-- Дата смерти -->
            <tr>
                <th><label><?php echo esc_html__('Статус', 'genius-family-tree'); ?></label></th>
                <td>
                    <input type="checkbox" name="family_member_is_deceased" id="family_member_is_deceased" value="1" <?php checked($is_deceased, 1); ?>>
                    <label for="family_member_is_deceased" id="family_member_is_deceased_label">
                        <?php echo esc_html($is_deceased ? 'Человек умер' : 'Человек жив'); ?>
                    </label>
                </td>
            </tr>
            <tr class="death-fields" <?php if (!$is_deceased) echo 'style="display:none;"'; ?>>
                <th><label><?php echo esc_html__('Дата смерти', 'genius-family-tree'); ?></label></th>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="number" name="family_member_death_day" id="family_member_death_day" 
                               value="<?php echo !empty($death_day) ? esc_attr($death_day) : ''; ?>" 
                               min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                        <input type="number" name="family_member_death_month" id="family_member_death_month" 
                               value="<?php echo !empty($death_month) ? esc_attr($death_month) : ''; ?>" 
                               min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                        <input type="number" name="family_member_death_year" id="family_member_death_year" 
                               value="<?php echo !empty($death_year) ? esc_attr($death_year) : ''; ?>" 
                               min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text" style="width: 80px;">
                    </div>
                    <p class="description"><?php echo esc_html__('Оставьте пустым, если дата неизвестна', 'genius-family-tree'); ?></p>
                    <!-- Чекбокс "по старому стилю" для смерти -->
                    <div style="margin-top: 10px;">
                        <input type="checkbox" name="family_member_death_old_style" id="family_member_death_old_style" value="1" <?php checked($death_old_style, 1); ?>>
                        <label for="family_member_death_old_style"><?php echo esc_html__('по старому стилю', 'genius-family-tree'); ?></label>
                    </div>
                    <!-- Поля для новой даты (по новому стилю) -->
                    <div id="death_new_style_fields" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; <?php echo $death_old_style ? '' : 'display: none;'; ?>">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html__('Дата по новому стилю', 'genius-family-tree'); ?>:</label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="number" name="family_member_death_new_day" id="family_member_death_new_day" 
                                   value="<?php echo !empty($death_new_day) ? esc_attr($death_new_day) : ''; ?>" 
                                   min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                            <input type="number" name="family_member_death_new_month" id="family_member_death_new_month" 
                                   value="<?php echo !empty($death_new_month) ? esc_attr($death_new_month) : ''; ?>" 
                                   min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                            <input type="number" name="family_member_death_new_year" id="family_member_death_new_year" 
                                   value="<?php echo !empty($death_new_year) ? esc_attr($death_new_year) : ''; ?>" 
                                   min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text" style="width: 80px;">
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
                            <div class="spouse-entry" data-index="<?php echo esc_attr($index); ?>" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #fafafa;">
                                <select name="family_member_spouses[<?php echo esc_attr($index); ?>][id]" class="family-member-select" data-placeholder="<?php echo esc_attr__('Поиск супруга(и)...', 'genius-family-tree'); ?>" style="width: 100%; margin-bottom: 15px;">
                                    <option value=""><?php echo esc_html__('Выберите супруга(у)', 'genius-family-tree'); ?></option>
                                    <?php if ($spouse_id):
                                        $spouse_post = get_post($spouse_id);
                                        if ($spouse_post): ?>
                                            <option value="<?php echo esc_attr($spouse_id); ?>" selected><?php echo esc_html($spouse_post->post_title); ?></option>
                                        <?php endif;
                                    endif; ?>
                                </select>
                                <div class="marriage-date" style="margin-bottom: 15px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;"><?php echo esc_html__('Дата брака:', 'genius-family-tree'); ?></label>
                                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][married_day]" value="<?php echo !empty($married_day) ? esc_attr($married_day) : ''; ?>" min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][married_month]" value="<?php echo !empty($married_month) ? esc_attr($married_month) : ''; ?>" min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][married_year]" value="<?php echo !empty($married_year) ? esc_attr($married_year) : ''; ?>" min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text" style="width: 80px;">
                                    </div>
                                </div>
                                <div class="divorce-date" style="margin-bottom: 15px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;"><?php echo esc_html__('Дата развода:', 'genius-family-tree'); ?></label>
                                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][divorced_day]" value="<?php echo !empty($divorced_day) ? esc_attr($divorced_day) : ''; ?>" min="1" max="31" placeholder="<?php echo esc_attr__('ДД', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][divorced_month]" value="<?php echo !empty($divorced_month) ? esc_attr($divorced_month) : ''; ?>" min="1" max="12" placeholder="<?php echo esc_attr__('ММ', 'genius-family-tree'); ?>" class="small-text" style="width: 60px;">
                                        <input type="number" name="family_member_spouses[<?php echo esc_attr($index); ?>][divorced_year]" value="<?php echo !empty($divorced_year) ? esc_attr($divorced_year) : ''; ?>" min="1000" max="2100" placeholder="<?php echo esc_attr__('ГГГГ', 'genius-family-tree'); ?>" class="small-text" style="width: 80px;">
                                    </div>
                                    <label style="display: flex; align-items: center; margin-top: 10px; font-weight: normal;">
                                        <input type="checkbox" name="family_member_spouses[<?php echo esc_attr($index); ?>][divorced_unknown]" id="divorced_unknown_<?php echo esc_attr($index); ?>" value="1" <?php checked($divorced_unknown, 1); ?> style="margin-right: 8px;">
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
        if (isset($_POST['family_member_maiden_name'])) {
            update_post_meta($post_id, '_family_member_maiden_name', sanitize_text_field(wp_unslash($_POST['family_member_maiden_name'])));
        }
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
        if (isset($_POST['family_member_spouses']) && is_array($_POST['family_member_spouses'])) {
            foreach ($_POST['family_member_spouses'] as $key => $item) {
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
        if (isset($_POST['family_member_children']) && is_array($_POST['family_member_children'])) {
            foreach ($_POST['family_member_children'] as $child_id) {
                $cid = absint($child_id);
                if ($cid) $sanitized_children[] = $cid;
            }
        }
        update_post_meta($post_id, '_family_member_children', $sanitized_children);
        // === СИНХРОНИЗАЦИЯ ДЕТЕЙ ↔ РОДИТЕЛЕЙ ===
        self::sync_children_data($post_id, $sanitized_children);
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
    }
}