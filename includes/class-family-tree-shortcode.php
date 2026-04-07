<?php
class Family_Tree_Shortcode {
    
    // Счётчик для генерации уникальных ID
    private static $shortcode_counter = 0;
    
    public static function init() {
        add_shortcode('family_tree', array(__CLASS__, 'render_shortcode'));
    }
    
    public static function render_shortcode($atts) {
        self::$shortcode_counter++;
        
        $center_on_from_url = isset($_GET['center_on']) ? intval($_GET['center_on']) : 0;
        $person_id_from_url = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;
        
        $atts = shortcode_atts(array(
            'root' => 0,
            'center_on' => 0,
            'group' => 0,
        ), $atts, 'family_tree');
        
        // Приоритет: URL параметры > параметры шорткода > глобальный корень > первая запись
        $root_id = 0;
        $center_on_id = 0;
        $group_id = intval($atts['group']);
        
        // 1. Сначала проверяем URL параметры (наивысший приоритет)
        if ($person_id_from_url) {
            $root_id = $person_id_from_url;
            $center_on_id = $person_id_from_url;
        } elseif ($center_on_from_url) {
            $root_id = $center_on_from_url;
            $center_on_id = $center_on_from_url;
        } 
        // 2. Затем параметры шорткода
        else {
            $root_id = intval($atts['root']);
            $center_on_id = intval($atts['center_on']);
            
            // Если указан center_on, но не указан root, root = center_on
            if ($center_on_id && !$root_id) {
                $root_id = $center_on_id;
            }
            
            // Если указан root, но не указан center_on, center_on = root
            if ($root_id && !$center_on_id) {
                $center_on_id = $root_id;
            }
        }
        
        // 3. Если все еще нет root_id, используем глобальный корень из настроек или первую запись
        if (!$root_id) {
            // Если указана группа, ищем корень в этой группе
            if ($group_id) {
                $root_members = get_posts(array(
                    'post_type' => 'family_member',
                    'posts_per_page' => 1,
                    'meta_key' => '_family_member_is_root',
                    'meta_value' => '1',
                    'meta_query' => array(
                        array(
                            'key' => '_family_member_group_id',
                            'value' => $group_id,
                            'compare' => '='
                        )
                    ),
                    'fields' => 'ids',
                    'post_status' => 'any'
                ));
                
                if (!empty($root_members)) {
                    $root_id = $root_members[0];
                    $center_on_id = $root_id;
                } else {
                    // Если нет корневого элемента в группе, берём первого члена группы
                    $first_member = get_posts(array(
                        'post_type' => 'family_member',
                        'numberposts' => 1,
                        'orderby' => 'ID',
                        'order' => 'ASC',
                        'meta_query' => array(
                            array(
                                'key' => '_family_member_group_id',
                                'value' => $group_id,
                                'compare' => '='
                            )
                        )
                    ));
                    if (!empty($first_member)) {
                        $root_id = $first_member[0]->ID;
                        $center_on_id = $root_id;
                    } else {
                        return '<p>' . __('В этой семейной группе пока нет участников.', 'genius-family-tree') . '</p>';
                    }
                }
            } else {
                // Для общего древа (без группы) - ищем ВСЕХ персон независимо от группы
                // Получаем глобальный корень из настроек плагина
                $global_root = get_option('family_tree_root_member', 0);
                
                // Проверяем, есть ли персона с меткой корня в базе (любые персоны)
                $root_members = get_posts(array(
                    'post_type' => 'family_member',
                    'posts_per_page' => 1,
                    'meta_key' => '_family_member_is_root',
                    'meta_value' => '1',
                    'fields' => 'ids',
                    'post_status' => 'any'
                ));
                
                if (!empty($root_members)) {
                    $root_id = $root_members[0];
                    $center_on_id = $root_id;
                } elseif ($global_root) {
                    $root_id = $global_root;
                    $center_on_id = $global_root;
                } else {
                    // Если ничего нет, берем первую запись (любую персону)
                    $first_member = get_posts(array(
                        'post_type' => 'family_member',
                        'numberposts' => 1,
                        'orderby' => 'ID',
                        'order' => 'ASC',
                        'post_status' => 'any'
                    ));
                    if (!empty($first_member)) {
                        $root_id = $first_member[0]->ID;
                        $center_on_id = $root_id;
                    } else {
                        return '<p>' . __('Пожалуйста, добавьте членов семьи для отображения древа.', 'genius-family-tree') . '</p>';
                    }
                }
            }
        }
        
        // Логируем для отладки
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Family Tree Shortcode - root_id: $root_id, center_on_id: $center_on_id, group_id: $group_id");
        }
        
        // Генерируем уникальный ID для контейнера
        $unique_id = 'FamilyChart-' . self::$shortcode_counter;
        if ($group_id) {
            $unique_id .= '-group' . $group_id;
        }
        
        // Передаем раздельные значения для root и center_on, а также group_id
        return '<div id="' . esc_attr($unique_id) . '" class="f3" 
                    data-root-id="' . esc_attr($root_id) . '" 
                    data-center-on="' . esc_attr($center_on_id) . '"
                    data-group-id="' . esc_attr($group_id) . '"></div>';
    }
}