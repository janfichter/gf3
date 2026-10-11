<?php
class Family_Tree_Post_Type {
    
    public static function register() {
        // Регистрация типа записи "Члены семьи"
        $labels = array(
            'name' => 'Члены семьи',
            'singular_name' => 'Член семьи',
            'menu_name' => 'Семейное древо',
            'name_admin_bar' => 'Члена семьи',
            'archives' => 'Архивы членов семьи',
            'attributes' => 'Атрибуты члена семьи',
            'parent_item_colon' => 'Родительский член семьи:',
            'all_items' => 'Все члены семьи',
            'add_new_item' => 'Добавить нового члена семьи',
            'add_new' => 'Добавить персону',
            'new_item' => 'Новый член семьи',
            'edit_item' => 'Редактировать члена семьи',
            'update_item' => 'Обновить члена семьи',
            'view_item' => 'Просмотреть члена семьи',
            'view_items' => 'Просмотреть членов семьи',
            'search_items' => 'Поиск членов семьи',
            'not_found' => 'Не найдено',
            'not_found_in_trash' => 'Не найдено в корзине',
        );

        $args = array(
            'label' => 'Член семьи',
            'description' => 'Члены семейного древа',
            'labels' => $labels,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false, // Скрываем из главного меню, будет в подменю
            'menu_position' => 5,
            'menu_icon' => 'dashicons-groups',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'rewrite' => array(
                'slug' => 'family-member',
                'with_front' => false
            ),
            'show_in_rest' => true,
            'capability_type' => 'post',
            'query_var' => true,
        );

        register_post_type('family_member', $args);

        // Шаблоны страницы темы доступны для типа записи family_member
        add_post_type_support('family_member', 'page-templates');
        
        // Регистрация типа записи "Семейные группы"
        $group_labels = array(
            'name' => 'Семейные группы',
            'singular_name' => 'Семейная группа',
            'menu_name' => 'Семейные группы',
            'name_admin_bar' => 'Семейную группу',
            'archives' => 'Архивы семейных групп',
            'attributes' => 'Атрибуты семейной группы',
            'parent_item_colon' => 'Родительская семейная группа:',
            'all_items' => 'Все члены семейной группы',
            'add_new_item' => 'Добавить новую семейную группу',
            'add_new' => 'Добавить новую семейную группу',
            'new_item' => 'Новая семейная группа',
            'edit_item' => 'Редактировать семейную группу',
            'update_item' => 'Обновить семейную группу',
            'view_item' => 'Просмотреть семейную группу',
            'view_items' => 'Просмотреть семейные группы',
            'search_items' => 'Поиск семейных групп',
            'not_found' => 'Не найдено',
            'not_found_in_trash' => 'Не найдено в корзине',
        );

        $group_args = array(
            'label' => 'Семейная группа',
            'description' => 'Семейные группы для разделения деревьев',
            'labels' => $group_labels,
            'supports' => array('title'),
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Скрываем из главного меню, будет в подменю
            'menu_position' => 6,
            'menu_icon' => 'dashicons-networking',
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'show_in_rest' => true,
            'capability_type' => 'post',
            'query_var' => true,
        );

        register_post_type('family_group', $group_args);

        // Закрытие REST API от утечки персональных данных (ФЗ-152):
        // анонимные и ограниченные посетители получают только публичные данные
        // с применением настроек приватности и маскировки полей.
        add_filter('rest_family_member_query', array(__CLASS__, 'rest_query_privacy'), 10, 2);
        add_filter('rest_prepare_family_member', array(__CLASS__, 'rest_prepare_privacy'), 10, 3);
    }

    /**
     * Исключает скрытые персоны из выборки REST API для посетителей
     * без полного доступа к древу.
     */
    public static function rest_query_privacy($args, $request) {
        if (!function_exists('ft_privacy_visibility') || ft_privacy_visibility() === 'full') {
            return $args;
        }
        if (current_user_can('edit_posts')) {
            return $args;
        }
        $private_ids = self::get_private_member_ids();
        if (empty($private_ids)) {
            return $args;
        }
        $exclude = isset($args['post__not_in']) && is_array($args['post__not_in']) ? $args['post__not_in'] : array();
        $args['post__not_in'] = array_values(array_unique(array_merge($exclude, $private_ids)));
        return $args;
    }

    /**
     * Применяет маскировку полей к записи из REST API согласно
     * настройкам приватности (стиль семейного древа: маскированное имя,
     * скрытое фото, заглушка для скрытых персон).
     */
    public static function rest_prepare_privacy($response, $post, $request) {
        if (!function_exists('ft_privacy_visibility') || ft_privacy_visibility() === 'full') {
            return $response;
        }
        if (current_user_can('edit_post', $post->ID)) {
            return $response;
        }
        if (!function_exists('ft_person_is_private')) {
            return $response;
        }
        $data = $response->get_data();

        if (ft_person_is_private($post->ID)) {
            $hidden = function_exists('ft_privacy_hidden_label') ? ft_privacy_hidden_label() : '';
            $data['title']['rendered'] = $hidden;
            $data['content']['rendered'] = '';
            $data['excerpt']['rendered'] = '';
            $data['featured_media'] = 0;
            $data['slug'] = '';
            $response->set_data($data);
            return $response;
        }

        if (function_exists('ft_hide_field')) {
            if (ft_hide_field('name_parts') && function_exists('ft_person_display_name')) {
                $data['title']['rendered'] = ft_person_display_name(
                    $post->ID,
                    isset($data['title']['rendered']) ? $data['title']['rendered'] : get_the_title($post->ID)
                );
            }
            if (ft_hide_field('photo')) {
                $data['featured_media'] = 0;
                if (isset($data['media_details'])) {
                    $data['media_details'] = array();
                }
                if (isset($data['featured_media_src'])) {
                    $data['featured_media_src'] = '';
                }
            }
        }
        $response->set_data($data);
        return $response;
    }

    /**
     * Возвращает ID всех скрытых персон: с персональным флагом приватности
     * либо входящих в приватные семейные группы.
     */
    private static function get_private_member_ids() {
        static $cached = null;
        if (null !== $cached) {
            return $cached;
        }
        $ids = array();

        $q1 = new WP_Query(array(
            'post_type' => 'family_member',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_key' => '_family_member_private',
            'meta_value' => '1',
            'no_found_rows' => true,
        ));
        if (!empty($q1->posts)) {
            $ids = array_merge($ids, $q1->posts);
        }

        $q2 = new WP_Query(array(
            'post_type' => 'family_group',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_key' => '_family_group_private',
            'meta_value' => '1',
            'no_found_rows' => true,
        ));
        if (!empty($q2->posts)) {
            $q3 = new WP_Query(array(
                'post_type' => 'family_member',
                'fields' => 'ids',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => array(array(
                    'key' => '_family_member_group_id',
                    'value' => $q2->posts,
                    'compare' => 'IN',
                )),
                'no_found_rows' => true,
            ));
            if (!empty($q3->posts)) {
                $ids = array_merge($ids, $q3->posts);
            }
        }

        $cached = array_values(array_unique(array_map('intval', $ids)));
        return $cached;
    }
    
    // Добавляем метод для пересохранения правил пермалинков
    public static function flush_rewrite_rules() {
        self::register();
        flush_rewrite_rules();
    }
}
?>