<?php
class Family_Tree_Ajax {
    public static function init() {
        add_action('wp_ajax_get_family_tree_data', array(__CLASS__, 'get_family_tree_data'));
        add_action('wp_ajax_nopriv_get_family_tree_data', array(__CLASS__, 'get_family_tree_data'));
        add_action('wp_ajax_search_family_members', array(__CLASS__, 'search_family_members'));
        add_action('wp_ajax_family_tree_check_limit', array(__CLASS__, 'check_limit'));
    }
    public static function get_family_tree_data() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'family_tree_nonce')) {
            wp_die('Security check failed');
        }
        $root_id = intval($_POST['root_id']);
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        if (!$root_id) wp_die('Invalid root ID');
        $family_data = self::build_family_tree($root_id, $group_id);
        wp_send_json($family_data);
    }
    public static function search_family_members() {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'family_tree_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        $search = isset($_REQUEST['search']) ? sanitize_text_field(wp_unslash($_REQUEST['search'])) : '';
        $exclude = isset($_REQUEST['exclude']) ? intval($_REQUEST['exclude']) : 0;
        $group_id = isset($_REQUEST['group_id']) ? intval($_REQUEST['group_id']) : 0;
        if (strlen($search) < 2) wp_send_json(array());
        $args = array(
            'post_type' => 'family_member',
            'posts_per_page' => 30,
            's' => $search,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        );
        // Если указана группа, фильтруем по ней
        if ($group_id) {
            $args['meta_query'] = array(
                array(
                    'key' => '_family_member_group_id',
                    'value' => $group_id,
                    'compare' => '='
                )
            );
        }
        $query = new WP_Query($args);
        $results = array();
        if ($query->have_posts()) {
            $count = 0;
            while ($query->have_posts() && $count < 20) {
                $query->the_post();
                $post_id = get_the_ID();
                if ($exclude && $post_id == $exclude) continue;
                $results[] = array('id' => $post_id, 'text' => get_the_title());
                $count++;
            }
            wp_reset_postdata();
        }
        wp_send_json_success($results);
    }
    public static function check_limit() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'family_tree_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        $is_pro = get_option('family_tree_is_pro', false);
        $member_count = wp_count_posts('family_member')->publish;
        if (!$is_pro && $member_count >= 50) wp_send_json_error('limit_reached');
        wp_send_json_success();
    }

    private static function extract_year_only($date_str, $year_fallback = '') {
        if (preg_match('/^\d{4}$/', $date_str)) {
            return $date_str;
        }
        if (preg_match('/^(\d{4})/', $date_str, $m)) {
            return $m[1];
        }
        if (preg_match('/^\d{4}$/', $year_fallback)) {
            return $year_fallback;
        }
        return '';
    }

    private static function build_family_tree($root_id, $group_id = 0) {
        $nodes = array();
        $links = array();
        $visited = array();
        $queue = array($root_id);
        
        // Проверяем, принадлежит ли корневой элемент к указанной группе (если группа задана)
        if ($group_id) {
            $root_group_id = get_post_meta($root_id, '_family_member_group_id', true);
            // Если корневой элемент не принадлежит этой группе, возвращаем пустые данные
            if ($root_group_id != $group_id) {
                return array('nodes' => array(), 'links' => array());
            }
        }
        
        while (!empty($queue)) {
            $person_id = array_shift($queue);
            if (in_array($person_id, $visited)) continue;
            $visited[] = $person_id;
            $person = get_post($person_id);
            if (!$person || $person->post_type !== 'family_member') continue;

            // Проверяем принадлежность к группе
            if ($group_id) {
                $person_group_id = get_post_meta($person_id, '_family_member_group_id', true);
                if ($person_group_id != $group_id) {
                    // Пропускаем персоны из других групп
                    continue;
                }
            }

            $first_name = get_post_meta($person_id, '_family_member_first_name', true);
            $middle_name = get_post_meta($person_id, '_family_member_middle_name', true);
            $last_name = get_post_meta($person_id, '_family_member_last_name', true);
            $gender = get_post_meta($person_id, '_family_member_gender', true);
            $maiden_name = get_post_meta($person_id, '_family_member_maiden_name', true);

            $birth_date = get_post_meta($person_id, '_family_member_birth_date', true);
            $death_date = get_post_meta($person_id, '_family_member_death_date', true);
            $birth_year_fb = get_post_meta($person_id, '_family_member_birth_year', true);
            $death_year_fb = get_post_meta($person_id, '_family_member_death_year', true);
            $is_deceased = (bool) get_post_meta($person_id, '_family_member_is_deceased', true);

            $birth_year = self::extract_year_only($birth_date, $birth_year_fb);
            $death_year = self::extract_year_only($death_date, $death_year_fb);

            // === ФОРМИРОВАНИЕ ОТОБРАЖЕНИЯ ДЛЯ ДРЕВА ===
            $birth_display = $birth_year; // если пусто — пусто
            $death_display = '';

            if ($death_year) {
                $death_display = $death_year;
            } elseif ($is_deceased) {
                $death_display = ($gender === 'female') ? 'Умерла' : 'Умер';
            }
            // Если не умер и года нет — остаётся пустой строкой

            $thumbnail_id = get_post_thumbnail_id($person_id);
            $image_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'medium') : FAMILY_TREE_PLUGIN_URL . 'assets/images/silhouette-' . ($gender === 'female' ? 'woman' : 'man') . '.svg';

            if (!in_array($person_id, array_column($nodes, 'id'))) {
                $nodes[] = array(
                    'id' => (string)$person_id,
                    'name' => $person->post_title,
                    'firstName' => $first_name,
                    'middleName' => $middle_name,
                    'lastName' => $last_name,
                    'maidenName' => $maiden_name,
                    'gender' => $gender,
                    'birthDate' => $birth_display,
                    'deathDate' => $death_display,
                    'img' => $image_url,
                    'permalink' => get_permalink($person_id)
                );
            }

            // === Связи (без изменений) ===
            $father_id = get_post_meta($person_id, '_family_member_father', true);
            $mother_id = get_post_meta($person_id, '_family_member_mother', true);
            $spouses = get_post_meta($person_id, '_family_member_spouses', true);
            $children = get_post_meta($person_id, '_family_member_children', true);
            if (!is_array($spouses)) $spouses = array();
            if (!is_array($children)) $children = array();

            foreach (array_filter([$father_id, $mother_id]) as $parent_id) {
                if (is_numeric($parent_id)) {
                    $pid = (int)$parent_id;
                    // Проверяем принадлежность родителя к группе
                    if ($group_id) {
                        $parent_group_id = get_post_meta($pid, '_family_member_group_id', true);
                        if ($parent_group_id != $group_id) {
                            continue; // Пропускаем родителей из других групп
                        }
                    }
                    if (!in_array($pid, $visited)) $queue[] = $pid;
                    if ($pid != $person_id) $links[] = array('source' => (string)$pid, 'target' => (string)$person_id, 'type' => 'child');
                }
            }

            foreach ($spouses as $spouse_data) {
                if (isset($spouse_data['id']) && is_numeric($spouse_data['id'])) {
                    $spouse_id = (int)$spouse_data['id'];
                    // Проверяем принадлежность супруга к группе
                    if ($group_id) {
                        $spouse_group_id = get_post_meta($spouse_id, '_family_member_group_id', true);
                        if ($spouse_group_id != $group_id) {
                            continue; // Пропускаем супругов из других групп
                        }
                    }
                    if (!in_array($spouse_id, $visited)) $queue[] = $spouse_id;
                    if ($spouse_id != $person_id) {
                        $marriage_date = !empty($spouse_data['married_year']) ? (string)$spouse_data['married_year'] : '';
                        $divorce_date = '';
                        if (!empty($spouse_data['divorced_unknown'])) {
                            $divorce_date = 'дата неизвестна';
                        } elseif (!empty($spouse_data['divorced_year'])) {
                            $divorce_date = (string)$spouse_data['divorced_year'];
                        }
                        $links[] = array(
                            'source' => (string)$person_id,
                            'target' => (string)$spouse_id,
                            'type' => 'spouse',
                            'marriageDate' => $marriage_date,
                            'divorceDate' => $divorce_date
                        );
                    }
                }
            }

            foreach ($children as $child_id) {
                if (is_numeric($child_id)) {
                    $cid = (int)$child_id;
                    // Проверяем принадлежность ребёнка к группе
                    if ($group_id) {
                        $child_group_id = get_post_meta($cid, '_family_member_group_id', true);
                        if ($child_group_id != $group_id) {
                            continue; // Пропускаем детей из других групп
                        }
                    }
                    if (!in_array($cid, $visited)) $queue[] = $cid;
                    if ($cid != $person_id) $links[] = array('source' => (string)$person_id, 'target' => (string)$cid, 'type' => 'child');
                }
            }
        }
        return array('nodes' => array_values($nodes), 'links' => array_values($links));
    }
}