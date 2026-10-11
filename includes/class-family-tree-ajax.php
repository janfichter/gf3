<?php
class Family_Tree_Ajax {
    public static function init() {
        add_action('wp_ajax_get_family_tree_data', array(__CLASS__, 'get_family_tree_data'));
        add_action('wp_ajax_nopriv_get_family_tree_data', array(__CLASS__, 'get_family_tree_data'));
        add_action('wp_ajax_search_family_members', array(__CLASS__, 'search_family_members'));
        add_action('wp_ajax_family_tree_check_limit', array(__CLASS__, 'check_limit'));
        add_action('wp_ajax_family_tree_create_album', array(__CLASS__, 'create_album'));
        add_action('wp_ajax_family_tree_add_album_photos', array(__CLASS__, 'add_album_photos'));
        add_action('wp_ajax_family_tree_remove_album_photo', array(__CLASS__, 'remove_album_photo'));
        add_action('wp_ajax_family_tree_reorder_album_photos', array(__CLASS__, 'reorder_album_photos'));
        add_action('wp_ajax_family_tree_rename_album', array(__CLASS__, 'rename_album'));
        add_action('wp_ajax_family_tree_delete_album', array(__CLASS__, 'delete_album'));
        add_action('wp_ajax_family_tree_reorder_albums', array(__CLASS__, 'reorder_albums'));
        add_action('wp_ajax_family_tree_update_album_meta', array(__CLASS__, 'update_album_meta'));
        add_action('wp_ajax_family_tree_get_album_metabox', array('Family_Tree_Meta_Boxes', 'ajax_get_album_metabox'));
        add_action('wp_ajax_family_tree_get_album_preview', array('Family_Tree_Meta_Boxes', 'ajax_get_album_preview'));
        add_action('wp_ajax_family_tree_update_album_display', array(__CLASS__, 'update_album_display'));
        add_action('wp_ajax_family_tree_toggle_section', array(__CLASS__, 'toggle_section'));
        add_action('wp_ajax_ft_dismiss_metabox_tooltip', array(__CLASS__, 'dismiss_metabox_tooltip'));
    }

    /**
     * Проверка nonce и прав для AJAX-запросов фотоальбома.
     *
     * @param int $post_id ID персоналии.
     * @return bool
     */
    private static function album_security_check($post_id) {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'family_tree_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        return true;
    }

    /**
     * AJAX: создание нового именованного фотоальбома для персоналии.
     * POST: post_id, album_name (опционально), section
     */
    public static function create_album() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        $name = isset($_POST['album_name']) ? sanitize_text_field(wp_unslash($_POST['album_name'])) : '';
        $index = ft_add_album($post_id, $name, $section);
        $albums = ft_get_albums($post_id, $section);
        wp_send_json_success(array(
            'created' => true,
            'index'   => $index,
            'albums'  => $albums,
        ));
    }

    /**
     * AJAX: добавление фотографий в конкретный альбом персоналии.
     * POST: post_id, album_index, photo_ids[], section
     */
    public static function add_album_photos() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $album_index = isset($_POST['album_index']) ? intval(wp_unslash($_POST['album_index'])) : 0;
        $photo_ids = isset($_POST['photo_ids']) ? wp_unslash($_POST['photo_ids']) : array();
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        if (!is_array($photo_ids)) {
            wp_send_json_error(array('message' => 'Invalid photo IDs'));
        }
        $added = ft_add_photos_to_album($post_id, $album_index, $photo_ids, $section);
        $albums = ft_get_albums($post_id, $section);
        wp_send_json_success(array('added' => $added, 'albums' => $albums));
    }

    /**
     * AJAX: удаление фотографии из конкретного альбома персоналии.
     * POST: post_id, album_index, photo_id, section
     */
    public static function remove_album_photo() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $album_index = isset($_POST['album_index']) ? intval(wp_unslash($_POST['album_index'])) : 0;
        $photo_id = isset($_POST['photo_id']) ? intval(wp_unslash($_POST['photo_id'])) : 0;
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        ft_remove_photo_from_album($post_id, $album_index, $photo_id, $section);
        $albums = ft_get_albums($post_id, $section);
        wp_send_json_success(array('albums' => $albums));
    }

    /**
     * AJAX: сохранение порядка фотографий в альбоме (drag & drop).
     * POST: post_id, album_index, photo_ids[], section
     */
    public static function reorder_album_photos() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $album_index = isset($_POST['album_index']) ? intval(wp_unslash($_POST['album_index'])) : 0;
        $photo_ids = isset($_POST['photo_ids']) ? wp_unslash($_POST['photo_ids']) : array();
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        if (!is_array($photo_ids)) {
            wp_send_json_error(array('message' => 'Invalid photo IDs'));
        }
        $ok = ft_reorder_album_photos($post_id, $album_index, $photo_ids, $section);
        if (!$ok) {
            wp_send_json_error(array('message' => 'Album not found'));
        }
        $albums = ft_get_albums($post_id, $section);
        wp_send_json_success(array('albums' => $albums));
    }

    /**
     * AJAX: переименование альбома.
     * POST: post_id, album_index, album_name, section
     */
    public static function rename_album() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $album_index = isset($_POST['album_index']) ? intval(wp_unslash($_POST['album_index'])) : 0;
        $album_name = isset($_POST['album_name']) ? sanitize_text_field(wp_unslash($_POST['album_name'])) : '';
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        $ok = ft_rename_album($post_id, $album_index, $album_name, $section);
        if (!$ok) {
            wp_send_json_error(array('message' => 'Album not found'));
        }
        $albums = ft_get_albums($post_id, $section);
        wp_send_json_success(array('albums' => $albums));
    }

    /**
     * AJAX: удаление альбома целиком.
     * POST: post_id, album_index, section
     */
    public static function delete_album() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $album_index = isset($_POST['album_index']) ? intval(wp_unslash($_POST['album_index'])) : 0;
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        $ok = ft_delete_album($post_id, $album_index, $section);
        if (!$ok) {
            wp_send_json_error(array('message' => 'Album not found'));
        }
        $albums = ft_get_albums($post_id, $section);
        wp_send_json_success(array('albums' => $albums));
    }

    /**
     * AJAX: сохранение порядка альбомов (перетаскивание строк в таблице админки).
     * POST: post_id, album_order[], section
     */
    public static function reorder_albums() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $album_order = isset($_POST['album_order']) ? wp_unslash($_POST['album_order']) : array();
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        if (!is_array($album_order)) {
            wp_send_json_error(array('message' => 'Invalid album order'));
        }
        ft_reorder_albums($post_id, $album_order, $section);
        $albums = ft_get_albums($post_id, $section);
        wp_send_json_success(array('albums' => $albums));
    }

    /**
     * AJAX: сохранение обложки и описания альбома.
     * POST: post_id, album_index, cover, description, section
     */
    public static function update_album_meta() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $album_index = isset($_POST['album_index']) ? intval(wp_unslash($_POST['album_index'])) : 0;
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        $cover = isset($_POST['cover']) ? intval($_POST['cover']) : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $ok = ft_update_album_meta($post_id, $album_index, $cover, $description, $section);
        if (!$ok) {
            wp_send_json_error(array('message' => 'Album not found'));
        }
        $albums = ft_get_albums($post_id, $section);
        wp_send_json_success(array('albums' => $albums));
    }
    /**
     * AJAX: сохранение режима отображения фотоальбомов персоны.
     * POST: post_id, mode, album
     */
    public static function update_album_display() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'all';
        if ($mode === 'one_album') {
            $mode = 'select_albums';
        }
        if (!in_array($mode, array('all', 'album_photos', 'album_covers', 'select_albums'), true)) {
            $mode = 'all';
        }
        $album = isset($_POST['album']) ? sanitize_title(wp_unslash($_POST['album'])) : '';
        $albums = isset($_POST['albums']) ? (array) wp_unslash($_POST['albums']) : array();
        $albums = array_values(array_unique(array_filter(array_map('sanitize_title', $albums))));
        if ($mode === 'select_albums' && empty($albums) && $album !== '') {
            $albums = array($album);
        }
        update_post_meta($post_id, ft_album_display_meta_key($section), array('mode' => $mode, 'album' => $album, 'albums' => $albums));
        wp_send_json_success();
    }
    /**
     * AJAX: включение/выключение раздела семейного архива для конкретной персоны.
     * POST: post_id, section, enabled (1|0)
     */
    public static function toggle_section() {
        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id || get_post_type($post_id) !== 'family_member') {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
        self::album_security_check($post_id);
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'photoalbums';
        if ('' === $section) {
            $section = 'photoalbums';
        }
        $enabled = !empty($_POST['enabled']);
        $disabled = (array) get_post_meta($post_id, '_family_member_disabled_sections', true);
        $disabled = array_values(array_unique(array_filter(array_map('sanitize_key', $disabled))));
        if ($enabled) {
            $disabled = array_values(array_diff($disabled, array($section)));
        } elseif (!in_array($section, $disabled, true)) {
            $disabled[] = $section;
        }
        if (empty($disabled)) {
            delete_post_meta($post_id, '_family_member_disabled_sections');
        } else {
            update_post_meta($post_id, '_family_member_disabled_sections', $disabled);
        }
        wp_send_json_success(array('enabled' => $enabled));
    }

    /**
     * AJAX: сохранение настройки показа подсказки про метабоксы (user meta).
     * POST: dismiss (1 — не показывать больше, 0 — показывать снова)
     */
    public static function dismiss_metabox_tooltip() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'family_tree_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        $user_id = get_current_user_id();
        $dismiss = !empty($_POST['dismiss']);
        if ($dismiss) {
            update_user_meta($user_id, 'ft_metabox_tooltip_dismissed', '1');
        } else {
            delete_user_meta($user_id, 'ft_metabox_tooltip_dismissed');
        }
        wp_send_json_success(array('dismissed' => $dismiss));
    }
    public static function get_family_tree_data() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'family_tree_nonce')) {
            wp_die('Security check failed');
        }
        $root_id = isset($_POST['root_id']) ? intval(wp_unslash($_POST['root_id'])) : 0;
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        if (!$root_id) wp_die('Invalid root ID');
        $family_data = self::build_family_tree($root_id, $group_id);
        wp_send_json($family_data);
    }
    public static function search_family_members() {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'family_tree_admin_nonce')) {
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
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'family_tree_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        $is_pro = Family_Tree_Plugin::get_instance()->is_pro();
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

    /**
     * Пакетная загрузка всех персоналий для построения древа.
     * Одним запросом забираем все посты family_member (прогрев кэша постов),
     * затем одним SQL прогреваем кэш их меты через update_meta_cache().
     * Благодаря прогреву get_post() и get_post_meta() внутри обхода BFS
     * не выполняют запросов к БД: для древа из ~3500 персон число запросов
     * сокращается с ~35 000 до нескольких единиц.
     */
    /**
     * Запись члена семьи не опубликована: черновик, ожидает проверки, отложенная
     * публикация, корзина. Такие персоны остаются в древе, потому что связи
     * родства строятся по метаданным, но их персональная страница недоступна.
     *
     * Статус private оставлен как видимый: запись открыта пользователям с
     * правом read_private_posts, и для них страница работает штатно.
     *
     * @param WP_Post|object $person Запись члена семьи.
     * @return bool
     */
    private static function is_member_unpublished($person) {
        if (!$person instanceof WP_Post) {
            return true;
        }
        return !in_array($person->post_status, array('publish', 'private'), true);
    }
    private static function preload_members_resources() {
        $all_members = get_posts(array(
            'post_type' => 'family_member',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'nopaging' => true,
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));
        $posts_map = array();
        $ids = array();
        foreach ($all_members as $member_post) {
            $mid = (int) $member_post->ID;
            $ids[] = $mid;
            $posts_map[$mid] = $member_post;
        }
        if (!empty($ids)) {
            update_meta_cache('post', $ids);
            $thumb_ids = array();
            foreach ($ids as $mid) {
                $thumb_id = get_post_thumbnail_id($mid);
                if ($thumb_id) {
                    $thumb_ids[] = (int) $thumb_id;
                }
            }
            if (!empty($thumb_ids)) {
                $thumb_ids = array_values(array_unique($thumb_ids));
                // Прогреваем кэш постов и меты вложений (изображений), чтобы
                // wp_get_attachment_image_url() не ходил за фото в базу по отдельности.
                get_posts(array(
                    'post_type' => 'attachment',
                    'post_status' => 'inherit',
                    'post__in' => $thumb_ids,
                    'posts_per_page' => -1,
                    'nopaging' => true,
                    'no_found_rows' => true,
                    'orderby' => 'post__in',
                ));
                update_meta_cache('post', $thumb_ids);
            }
        }
        return $posts_map;
    }

    private static function build_family_tree($root_id, $group_id = 0) {
        $nodes = array();
        $links = array();
        // При полностью закрытом древе не отдаём данные вовсе.
        if (ft_privacy_visibility() === 'blocked') {
            return array('nodes' => array(), 'links' => array());
        }
        // ОПТИМИЗАЦИЯ: используем ассоциативные массивы (ID => true) вместо
        // in_array()/array_column() внутри цикла — поиск O(1) вместо O(n),
        // что критично для деревьев с сотнями персоналий.
        $visited = array();
        $node_ids = array();
        $queue = array($root_id);

        // Пакетная загрузка всех персоналий и метаданных (см. preload_members_resources).
        $posts_map = self::preload_members_resources();
        $ft_hides = ft_hidden_fields();

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
            if (isset($visited[$person_id])) continue;
            $visited[$person_id] = true;
            // get_post() отвечает из прогретого кэша (посты загружены пакетно).
            $person = isset($posts_map[$person_id]) ? $posts_map[$person_id] : get_post($person_id);
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
            $image_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'medium') : family_tree_get_placeholder($gender);

            if (!isset($node_ids[$person_id])) {
                $node_ids[$person_id] = true;
                // Персональная приватность / приватная группа: вместо карточки
                // персоны в древе отображается заглушка без ссылки и данных.
                if (self::is_member_unpublished($person)) {
                    // Запись не опубликована: персональной страницы нет,
                    // поэтому карточку персоны заменяем заглушкой.
                    $nodes[] = array(
                        'id' => (string)$person_id,
                        'name' => ft_unpublished_label(),
                        'firstName' => ft_unpublished_label(),
                        'middleName' => '',
                        'lastName' => '',
                        'maidenName' => '',
                        'gender' => $gender,
                        'birthDate' => '',
                        'deathDate' => '',
                        'img' => family_tree_get_placeholder($gender),
                        'permalink' => '',
                        'hidden' => true,
                        'unpublished' => true,
                    );
                } elseif (ft_person_is_private($person_id)) {
                    $nodes[] = array(
                        'id' => (string)$person_id,
                        'name' => ft_privacy_hidden_label(),
                        'firstName' => __('Скрыто', 'genius-family-tree'),
                        'middleName' => __('администратором', 'genius-family-tree'),
                        'lastName' => '',
                        'maidenName' => '',
                        'gender' => $gender,
                        'birthDate' => '',
                        'deathDate' => '',
                        'img' => family_tree_get_placeholder($gender),
                        'permalink' => '',
                        'hidden' => true,
                    );
                } else {
                    // Маскирование «чувствительных» полей на публике.
                    if (in_array('name_parts', $ft_hides, true)) {
                        $name_show = trim((string) $first_name);
                        $middle_name = '';
                        $last_name = '';
                        $maiden_name = '';
                    } else {
                        $name_show = $person->post_title;
                    }
                    if (in_array('dates', $ft_hides, true) || in_array('years', $ft_hides, true)) {
                        $birth_display = '';
                        $death_display = '';
                    }
                    if (in_array('photo', $ft_hides, true)) {
                        $image_url = family_tree_get_placeholder($gender);
                    }
                    $nodes[] = array(
                        'id' => (string)$person_id,
                        'name' => $name_show,
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
                    if (!isset($visited[$pid])) $queue[] = $pid;
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
                    if (!isset($visited[$spouse_id])) $queue[] = $spouse_id;
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
                    if (!isset($visited[$cid])) $queue[] = $cid;
                    if ($cid != $person_id) $links[] = array('source' => (string)$person_id, 'target' => (string)$cid, 'type' => 'child');
                }
            }
        }
        return array('nodes' => array_values($nodes), 'links' => array_values($links));
    }
}