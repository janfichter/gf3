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
            'add_new' => 'Добавить нового',
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
            'add_new_item' => 'Добавить нового члена семейной группы',
            'add_new' => 'Добавить нового',
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
    }
    
    // Добавляем метод для пересохранения правил пермалинков
    public static function flush_rewrite_rules() {
        self::register();
        flush_rewrite_rules();
    }
}
?>