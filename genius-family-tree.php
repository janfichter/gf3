<?php
/**
* Plugin Name:       Genius Family Tree
* Description:       Плагин для создания и отображения семейных деревьев
* Version:           1.3.4
* Author:            Jan Fichter
* Text Domain:       genius-family-tree
* License:           GPLv2 or later
* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
*/
// Защита от прямого доступа
if (!defined('ABSPATH')) {
exit;
}
// Определение констант
define('FAMILY_TREE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FAMILY_TREE_PLUGIN_URL', plugin_dir_url(__FILE__));
// Подключение необходимых файлов
require_once FAMILY_TREE_PLUGIN_DIR . 'includes/class-family-tree-post-type.php';
require_once FAMILY_TREE_PLUGIN_DIR . 'includes/class-family-tree-meta-boxes.php';
require_once FAMILY_TREE_PLUGIN_DIR . 'includes/class-family-tree-ajax.php';
require_once FAMILY_TREE_PLUGIN_DIR . 'includes/class-family-tree-shortcode.php';
// Инициализация плагина
class Family_Tree_Plugin {
private static $instance = null;
public static function get_instance() {
if (null === self::$instance) {
self::$instance = new self();
}
return self::$instance;
}
private function __construct() {
add_action('init', array($this, 'init'));
add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
add_action('after_setup_theme', array($this, 'add_thumbnail_support'));
add_action('template_include', array($this, 'load_family_member_template'));
// Добавляем основной шорткод для каталога фамилий
add_shortcode('family_surname_catalog', array($this, 'render_surname_catalog'));
// Добавляем отладочный шорткод
add_shortcode('family_surname_catalog_debug', array($this, 'render_surname_catalog_debug'));
// Активация и деактивация плагина
register_activation_hook(__FILE__, array($this, 'activate'));
register_deactivation_hook(__FILE__, array($this, 'deactivate'));
// Проверка ограничения на количество персоналий
add_action('admin_notices', array($this, 'check_member_limit'));
add_action('save_post_family_member', array($this, 'check_member_limit_on_save'), 10, 3);
// Инициализация админ-меню
add_action('admin_menu', array($this, 'init_admin'));
// Регистрация настроек
add_action('admin_init', array($this, 'register_settings'));
// AJAX обработчики для лицензий
add_action('wp_ajax_family_tree_activate_license', array($this, 'ajax_activate_license'));
add_action('wp_ajax_family_tree_deactivate_license', array($this, 'ajax_deactivate_license'));
add_action('wp_ajax_family_tree_export_gedcom', array($this, 'ajax_export_gedcom'));
add_action('wp_ajax_family_tree_import_gedcom', array($this, 'ajax_import_gedcom'));
// ========== ИСПРАВЛЕНИЕ ПРОБЛЕМЫ С КОЛИЧЕСТВОМ ЗАПИСЕЙ В АДМИНКЕ ==========
$this->fix_admin_per_page_issue();
// ========== ДОБАВЛЕНИЕ НОВЫХ СТОЛБЦОВ В АДМИНКЕ ==========
$this->add_custom_admin_columns();
// ========== ДОБАВЛЕНИЕ МЕТАБОКСА ДЛЯ КОРНЕВОГО ЭЛЕМЕНТА ==========
add_action('add_meta_boxes', array($this, 'add_root_member_meta_box'));
add_action('save_post_family_member', array($this, 'save_root_member_meta'), 10, 3);
// Удаляем стандартный метабокс членов группы из class-family-tree-meta-boxes.php, если он существует
add_action('add_meta_boxes', function() {
remove_meta_box('family-group-members', 'family_group', 'normal');
}, 5); // Приоритет 5, чтобы удалить до добавления нашего
// Добавляем наш улучшенный метабокс для членов семейной группы
add_action('add_meta_boxes', array($this, 'add_family_group_members_meta_box'), 10);
// Принудительно загружаем метабокс на странице редактирования family_group
add_action('load-post.php', function() {
$screen = get_current_screen();
if ($screen && $screen->post_type === 'family_group') {
// Убеждаемся, что метабокс зарегистрирован
add_meta_box(
'family_group_members',
__('Члены семьи в этой группе', 'genius-family-tree'),
array($this, 'render_family_group_members_meta_box'),
'family_group',
'normal',
'high'
);
}
});
// ========== ДОБАВЛЕНИЕ ДЕЙСТВИЯ ДЛЯ БЫСТРОГО РЕДАКТИРОВАНИЯ ==========
add_action('quick_edit_custom_box', array($this, 'display_root_member_quick_edit'), 10, 2);
add_action('save_post_family_member', array($this, 'save_root_member_quick_edit'), 10, 2);
}
/**
* Добавляем метабокс для выбора корневого элемента
*/
public function add_root_member_meta_box() {
add_meta_box(
'family_member_root',
__('Корневой элемент древа', 'genius-family-tree'),
array($this, 'render_root_member_meta_box'),
'family_member',
'side',
'high'
);
}
/**
* Отображаем метабокс
*/
public function render_root_member_meta_box($post) {
wp_nonce_field('family_member_root_nonce', 'family_member_root_nonce');
$is_root = get_post_meta($post->ID, '_family_member_is_root', true);
// Получаем группу текущей персоны
$group_id = get_post_meta($post->ID, '_family_member_group_id', true);
// Получаем корневой элемент для этой группы
$current_root = $this->get_root_member_id($group_id);
?>
<div class="family-tree-root-metabox">
<p>
<label>
<input type="checkbox" name="family_member_is_root" value="1" <?php checked($is_root, '1'); ?> />
<?php _e('Сделать корневым элементом древа', 'genius-family-tree'); ?>
</label>
</p>
<?php if ($current_root && $current_root != $post->ID): ?>
<p class="description" style="color: #666;">
<?php
$root_title = get_the_title($current_root);
printf(
__('Текущий корневой элемент: %s', 'genius-family-tree'),
'<strong>' . esc_html($root_title) . '</strong>'
);
?>
</p>
<p class="description" style="color: #999; font-style: italic;">
<?php _e('При установке этой опции корневой элемент будет изменен.', 'genius-family-tree'); ?>
</p>
<?php endif; ?>
</div>
<style>
.family-tree-root-metabox p {
margin: 8px 0;
}
</style>
<?php
}
/**
* Сохраняет мета-поле для корневого элемента древа
*/
public function save_root_member_meta($post_id, $post, $update) {
// Проверяем автосохранение
if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
return;
}
// Проверяем nonce
if (!isset($_POST['family_member_root_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['family_member_root_nonce'])), 'family_member_root_nonce')) {
return;
}
// Проверяем права
if (!current_user_can('edit_post', $post_id)) {
return;
}
// Получаем группу, к которой принадлежит персона
$group_id = get_post_meta($post_id, '_family_member_group_id', true);
// Проверяем, установлен ли флаг корневого элемента
$is_root = isset($_POST['family_member_is_root']) && $_POST['family_member_is_root'] == '1';
if ($is_root) {
// Сначала сбрасываем флаг у всех других записей в этой группе
$this->clear_root_member($group_id);
// Устанавливаем флаг для текущей записи
update_post_meta($post_id, '_family_member_is_root', '1');
} else {
// Если это был корневой элемент, снимаем флаг
$current_root = $this->get_root_member_id($group_id);
if ($current_root == $post_id) {
delete_post_meta($post_id, '_family_member_is_root');
}
}
}
/**
* Добавляем поле для быстрого редактирования
*/
public function display_root_member_quick_edit($column_name, $post_type) {
if ($post_type !== 'family_member' || $column_name !== 'is_root') {
return;
}
?>
<fieldset class="inline-edit-col-right">
<div class="inline-edit-col">
<label class="alignleft">
<input type="checkbox" name="family_member_is_root" value="1">
<span class="checkbox-title"><?php _e('Сделать корневым элементом древа', 'genius-family-tree'); ?></span>
</label>
</div>
</fieldset>
<?php
}
/**
* Сохраняем при быстром редактировании
*/
public function save_root_member_quick_edit($post_id, $post) {
if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
return;
}
if (!current_user_can('edit_post', $post_id)) {
return;
}
if ($post->post_type !== 'family_member') {
return;
}
// Получаем группу, к которой принадлежит персона
$group_id = get_post_meta($post_id, '_family_member_group_id', true);
$is_root = isset($_POST['family_member_is_root']) && $_POST['family_member_is_root'] == '1';
if ($is_root) {
$this->clear_root_member($group_id);
update_post_meta($post_id, '_family_member_is_root', '1');
}
}
/**
* Получает ID корневого элемента (опционально для указанной группы)
*/
private function get_root_member_id($group_id = 0) {
$args = array(
'post_type' => 'family_member',
'posts_per_page' => 1,
'meta_key' => '_family_member_is_root',
'meta_value' => '1',
'fields' => 'ids',
'post_status' => 'any'
);

// Если указана группа, ищем корень только в этой группе
if ($group_id) {
$args['meta_query'] = array(
array(
'key' => '_family_member_group_id',
'value' => $group_id,
'compare' => '='
)
);
}

$root_members = get_posts($args);
return !empty($root_members) ? $root_members[0] : 0;
}
/**
* Очищает флаг корневого элемента у всех записей (или у записей указанной группы)
*/
private function clear_root_member($group_id = 0) {
$args = array(
'post_type' => 'family_member',
'posts_per_page' => -1,
'meta_key' => '_family_member_is_root',
'meta_value' => '1',
'fields' => 'ids',
'post_status' => 'any'
);

// Если указана группа, очищаем только в этой группе
if ($group_id) {
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
/**
* Добавление пользовательских столбцов в админ-панели
*/
private function add_custom_admin_columns() {
// Добавляем фильтры для столбцов
add_filter('manage_family_member_posts_columns', array($this, 'add_family_member_columns'));
add_action('manage_family_member_posts_custom_column', array($this, 'display_family_member_columns'), 10, 2);
// Добавляем возможность сортировки по новым столбцам
add_filter('manage_edit-family_member_sortable_columns', array($this, 'make_family_member_columns_sortable'));
// Добавляем стили для столбцов
add_action('admin_head', array($this, 'custom_admin_column_styles'));
// Добавляем скрипт для копирования шорткода
add_action('admin_footer', array($this, 'output_copy_shortcode_script'));
// Добавляем поддержку быстрого редактирования
add_action('quick_edit_custom_box', array($this, 'display_root_member_quick_edit'), 10, 2);
// Добавляем фильтры для списка персоналий
add_action('restrict_manage_posts', array($this, 'add_family_member_filters'), 10, 2);
add_filter('parse_query', array($this, 'filter_family_member_query'));
// Убираем стандартный фильтр дат WordPress для family_member
add_filter('months_dropdown_results', array($this, 'remove_months_dropdown_for_family_member'), 10, 2);
// ========== ДОБАВЛЕНИЕ КОЛОНОК ДЛЯ СЕМЕЙНЫХ ГРУПП ==========
add_filter('manage_family_group_posts_columns', array($this, 'add_family_group_columns'));
add_action('manage_family_group_posts_custom_column', array($this, 'display_family_group_columns'), 10, 2);
}
/**
* Добавляем новые столбцы в список персоналий
*/
public function add_family_member_columns($columns) {
$new_columns = array();
// Перестраиваем массив столбцов, добавляя новые после заголовка
foreach ($columns as $key => $value) {
$new_columns[$key] = $value;
// После столбца с заголовком добавляем новые столбцы
if ($key === 'title') {
$new_columns['portrait'] = __('Портрет', 'genius-family-tree');
$new_columns['lifespan'] = __('Годы жизни', 'genius-family-tree');
$new_columns['family_group'] = __('Семейная группа', 'genius-family-tree');
$new_columns['is_root'] = __('Корень древа', 'genius-family-tree');
}
}
return $new_columns;
}
/**
* Отображаем содержимое новых столбцов
*/
public function display_family_member_columns($column, $post_id) {
switch ($column) {
case 'portrait':
$this->display_portrait_column($post_id);
break;
case 'lifespan':
$this->display_lifespan_column($post_id);
break;
case 'family_group':
$this->display_family_group_column($post_id);
break;
case 'is_root':
$is_root = get_post_meta($post_id, '_family_member_is_root', true);
if ($is_root) {
echo '<span class="dashicons dashicons-star-filled" style="color: #ffb900;" title="' . esc_attr__('Корневой элемент древа', 'genius-family-tree') . '"></span>';
} else {
echo '<span class="dashicons dashicons-star-empty" style="color: #ccc;" title="' . esc_attr__('Не корневой элемент', 'genius-family-tree') . '"></span>';
}
break;
}
}
/**
* Отображение столбца с портретом
*/
private function display_portrait_column($post_id) {
$thumbnail_id = get_post_thumbnail_id($post_id);
$gender = get_post_meta($post_id, '_family_member_gender', true);
if ($thumbnail_id) {
// Показываем миниатюру, если она есть
$image = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
if ($image) {
echo '<img src="' . esc_url($image[0]) . '" width="50" height="50" style="object-fit: cover; border-radius: 4px;" alt="' . esc_attr__('Портрет', 'genius-family-tree') . '" />';
} else {
$this->show_silhouette($gender);
}
} else {
// Показываем силуэт по умолчанию
$this->show_silhouette($gender);
}
}
/**
* Показывает силуэт в зависимости от пола
*/
private function show_silhouette($gender) {
$silhouette_url = FAMILY_TREE_PLUGIN_URL . 'assets/images/silhouette-' . ($gender === 'female' ? 'woman' : 'man') . '.svg';
echo '<img src="' . esc_url($silhouette_url) . '" width="50" height="50" style="object-fit: cover; border-radius: 4px; opacity: 0.6;" alt="' . esc_attr__('Силуэт', 'genius-family-tree') . '" />';
}
/**
* Отображение столбца с годами жизни
*/
private function display_lifespan_column($post_id) {
$birth_date = get_post_meta($post_id, '_family_member_birth_date', true);
$death_date = get_post_meta($post_id, '_family_member_death_date', true);
$is_deceased = get_post_meta($post_id, '_family_member_is_deceased', true);
$gender = get_post_meta($post_id, '_family_member_gender', true);
$birth_year = $this->extract_year($birth_date);
$death_year = $this->extract_year($death_date);
$output = '';
if (!empty($birth_year)) {
$output .= $birth_year;
} else {
$output .= '?';
}
$output .= ' — ';
if (!empty($death_year)) {
$output .= $death_year;
} elseif ($is_deceased) {
$output .= ($gender === 'female') ? __('Умерла', 'genius-family-tree') : __('Умер', 'genius-family-tree');
} else {
$output .= __('наст. время', 'genius-family-tree');
}
// Добавляем полные даты во всплывающую подсказку, если они есть
$tooltip = array();
if (!empty($birth_date) && $birth_date !== $birth_year) {
$tooltip[] = sprintf(__('Родился(ась): %s', 'genius-family-tree'), $this->format_date_for_display($birth_date));
}
if (!empty($death_date) && $death_date !== $death_year) {
$tooltip[] = sprintf(__('Умер(ла): %s', 'genius-family-tree'), $this->format_date_for_display($death_date));
}
if (!empty($tooltip)) {
echo '<span title="' . esc_attr(implode(' | ', $tooltip)) . '">' . esc_html($output) . '</span>';
} else {
echo esc_html($output);
}
}
/**
* Извлекает год из даты
*/
private function extract_year($date_string) {
if (empty($date_string)) {
return '';
}
if (preg_match('/^(\d{4})/', $date_string, $matches)) {
return $matches[1];
}
return '';
}
/**
* Отображение столбца с семейной группой
*/
private function display_family_group_column($post_id) {
$group_id = get_post_meta($post_id, '_family_member_group_id', true);
if ($group_id) {
$group = get_post($group_id);
if ($group) {
echo esc_html($group->post_title);
} else {
echo '<em style="color: #999;">' . __('Группа не найдена', 'genius-family-tree') . '</em>';
}
} else {
echo '<em style="color: #999;">' . __('Без группы', 'genius-family-tree') . '</em>';
}
}
/**
* Добавляет колонки для списка семейных групп
*/
public function add_family_group_columns($columns) {
$new_columns = array();
foreach ($columns as $key => $value) {
$new_columns[$key] = $value;
if ($key === 'title') {
$new_columns['member_count'] = __('Количество персоналий', 'genius-family-tree');
$new_columns['shortcode'] = __('Шорткод', 'genius-family-tree');
}
}
return $new_columns;
}
/**
* Отображает содержимое колонок для семейных групп
*/
public function display_family_group_columns($column, $post_id) {
switch ($column) {
case 'member_count':
$count = $this->get_group_member_count($post_id);
echo '<strong>' . esc_html($count) . '</strong>';
break;
case 'shortcode':
$shortcode = '[family_tree group=\'' . $post_id . '\']';
?>
<div style="display: flex; align-items: center; gap: 8px;">
<code style="background: #f0f0f1; padding: 4px 8px; border-radius: 3px; font-size: 12px;"><?php echo esc_html($shortcode); ?></code>
<button type="button" class="button button-small copy-shortcode-btn" data-shortcode="<?php echo esc_attr($shortcode); ?>" title="<?php esc_attr_e('Копировать шорткод', 'genius-family-tree'); ?>">
<span class="dashicons dashicons-clipboard"></span>
</button>
</div>
<?php
break;
}
}
/**
* Выводит скрипт для копирования шорткода
*/
public function output_copy_shortcode_script() {
global $pagenow, $typenow;
?>
<script>
jQuery(document).ready(function($) {
$(document).on('click', '.copy-shortcode-btn', function(e) {
e.preventDefault();
var $btn = $(this);
var shortcode = $btn.data('shortcode');
// Используем современный API буфера обмена
if (navigator.clipboard && navigator.clipboard.writeText) {
navigator.clipboard.writeText(shortcode).then(function() {
showCopySuccess($btn);
}).catch(function(err) {
fallbackCopy(shortcode, $btn);
});
} else {
fallbackCopy(shortcode, $btn);
}
function showCopySuccess($button) {
var $icon = $button.find('.dashicons');
$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes-alt');
$button.attr('title', '<?php esc_attr_e('Скопировано!', 'genius-family-tree'); ?>');
// Показываем уведомление
if (typeof wp !== 'undefined' && wp.notice) {
wp.notice.add({
text: '<?php esc_html_e('Шорткод скопирован в буфер обмена', 'genius-family-tree'); ?>',
type: 'success',
dismissible: true
});
} else {
// Fallback уведомление
var $notice = $('<div class="notice notice-success is-dismissible" style="position: fixed; bottom: 20px; right: 20px; z-index: 999999;"><p><?php esc_html_e('Шорткод скопирован в буфер обмена', 'genius-family-tree'); ?></p></div>');
$('body').append($notice);
setTimeout(function() {
$notice.fadeOut(function() { $(this).remove(); });
}, 3000);
}
setTimeout(function() {
$icon.removeClass('dashicons-yes-alt').addClass('dashicons-clipboard');
$button.attr('title', '<?php esc_attr_e('Копировать шорткод', 'genius-family-tree'); ?>');
}, 2000);
}
function fallbackCopy(text, $button) {
var $tempInput = $('<textarea>');
$('body').append($tempInput);
$tempInput.val(text).select();
document.execCommand('copy');
$tempInput.remove();
showCopySuccess($button);
}
});
// Исправление для поиска в семейной группе - удаляем старый код, так как теперь используется стандартный поиск WordPress
if ($('body').hasClass('post-type-family_group') && $('#family_group_members').length) {
// Больше не нужно специальное обработчик, стандартный поиск WordPress работает автоматически
}
});
</script>
<?php
}
/**
* Получает количество персоналий в группе
*/
private function get_group_member_count($group_id) {
$members = get_posts(array(
'post_type' => 'family_member',
'posts_per_page' => -1,
'post_status' => 'any',
'meta_query' => array(
array(
'key' => '_family_member_group_id',
'value' => $group_id,
'compare' => '='
)
),
'fields' => 'ids'
));
return count($members);
}
/**
* Добавляет метабокс со списком персоналий группы
*/
public function add_family_group_members_meta_box() {
add_meta_box(
'family_group_members',
__('Члены семьи в этой группе', 'genius-family-tree'),
array($this, 'render_family_group_members_meta_box'),
'family_group',
'normal',
'high'
);
}
/**
* Отображает метабокс со списком персоналий группы
*/
public function render_family_group_members_meta_box($post, $metabox) {
global $pagenow;
$group_id = $post->ID;
// Получаем параметры из GET запроса напрямую (используем стандартный 's' для поиска)
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$sort_order = isset($_GET['ft_group_sort']) ? sanitize_text_field($_GET['ft_group_sort']) : 'date_asc';
// Строим запрос
$args = array(
'post_type' => 'family_member',
'posts_per_page' => -1,
'post_status' => 'any',
'meta_query' => array(
array(
'key' => '_family_member_group_id',
'value' => (string) $group_id,
'compare' => '='
)
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
$members = get_posts($args);
// Вывод панели управления (только сортировка, поиск через стандартный WordPress search-box)
?>
<style>
.ft-group-members-container {
margin-top: 10px;
}
.ft-controls-bar {
background: #f9f9f9;
border: 1px solid #ddd;
padding: 12px;
margin-bottom: 20px;
border-radius: 4px;
display: flex;
flex-wrap: wrap;
gap: 10px;
align-items: center;
}
.ft-sort-form {
display: inline-flex;
gap: 8px;
align-items: center;
}
.ft-sort-form select {
min-width: 200px;
padding: 6px 10px;
border: 1px solid #ccc;
border-radius: 4px;
}
.ft-sort-form button.button {
padding: 6px 12px;
}
.ft-members-list {
display: flex;
flex-direction: column;
gap: 10px;
}
.ft-member-item {
display: flex;
align-items: center;
gap: 15px;
padding: 10px;
background: #fff;
border: 1px solid #c3c4c7;
border-radius: 4px;
}
.ft-member-item:hover {
box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.ft-member-avatar {
width: 50px;
height: 50px;
border-radius: 4px;
object-fit: cover;
flex-shrink: 0;
}
.ft-member-info {
flex-grow: 1;
}
.ft-member-name {
font-size: 14px;
font-weight: 600;
margin: 0 0 4px 0;
}
.ft-member-name a {
text-decoration: none;
color: inherit;
}
.ft-member-name a:hover {
color: #2271b1;
}
.ft-member-dates {
font-size: 12px;
color: #646970;
font-style: italic;
}
.ft-root-badge {
background: #d63638;
color: #fff;
padding: 2px 8px;
border-radius: 12px;
font-size: 11px;
font-weight: 600;
text-transform: uppercase;
}
.ft-no-members {
text-align: center;
padding: 40px;
color: #646970;
background: #f9f9f9;
border-radius: 4px;
border: 1px dashed #c3c4c7;
}
/* Исправление проблемы с перекрытием wpfooter */
#family_group_members {
margin-bottom: 120px !important;
padding-bottom: 60px !important;
}
.ft-group-members-container {
margin-bottom: 140px !important;
padding-bottom: 80px !important;
}
.ft-controls-bar {
position: relative;
z-index: 1;
}
#family_group_members .inside {
padding-bottom: 100px !important;
margin-bottom: 50px !important;
}
</style>
<div class="ft-group-members-container">
<!-- Панель управления: Поиск и Сортировка -->
<div class="ft-controls-bar" id="ft-controls-bar">
<!-- Поле поиска -->
<div style="display: flex; gap: 5px; align-items: center;">
<label class="screen-reader-text" for="family-group-search-input"><?php esc_html_e('Поиск по имени или фамилии...', 'genius-family-tree'); ?></label>
<input type="search"
id="family-group-search-input"
name="s"
value="<?php echo esc_attr($search_query); ?>"
placeholder="<?php esc_attr_e('Поиск...', 'genius-family-tree'); ?>"
style="padding: 6px 10px; min-width: 200px; border: 1px solid #ccc; border-radius: 4px;" />
<button type="button" class="button ft-btn-search"><?php esc_html_e('Найти', 'genius-family-tree'); ?></button>
<?php if (!empty($search_query)): ?>
<a href="<?php echo esc_url(remove_query_arg(array('s', 'ft_action'))); ?>" class="button" style="color: #646970;">
<?php esc_html_e('Сброс', 'genius-family-tree'); ?>
</a>
<?php endif; ?>
</div>
<!-- Селект сортировки -->
<div style="display: flex; gap: 5px; align-items: center;">
<select name="ft_group_sort" id="ft-group-sort" style="min-width: 200px; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;">
<option value="date_asc" <?php selected($sort_order, 'date_asc'); ?>><?php esc_html_e('Дата рождения (старшие сначала)', 'genius-family-tree'); ?></option>
<option value="date_desc" <?php selected($sort_order, 'date_desc'); ?>><?php esc_html_e('Дата рождения (младшие сначала)', 'genius-family-tree'); ?></option>
<option value="alpha_asc" <?php selected($sort_order, 'alpha_asc'); ?>><?php esc_html_e('А-Я (А-Я)', 'genius-family-tree'); ?></option>
<option value="alpha_desc" <?php selected($sort_order, 'alpha_desc'); ?>><?php esc_html_e('Я-А (Я-А)', 'genius-family-tree'); ?></option>
</select>
<button type="button" class="button ft-btn-apply"><?php esc_html_e('Применить', 'genius-family-tree'); ?></button>
</div>
</div>

<script>
jQuery(document).ready(function($) {
var groupId = '<?php echo esc_js($group_id); ?>';
var baseUrl = '<?php echo esc_url(admin_url('post.php')); ?>';
var $container = $('#ft-controls-bar');

function reloadWithParams() {
var params = new URLSearchParams();
params.append('post_type', 'family_group');
params.append('post', groupId);
params.append('action', 'edit');

var searchVal = $container.find('#family-group-search-input').val();
if (searchVal) params.append('s', searchVal);

var sortVal = $container.find('#ft-group-sort').val();
if (sortVal) params.append('ft_group_sort', sortVal);

window.location.href = baseUrl + '?' + params.toString();
}

// Обработчики кнопок
$container.find('.ft-btn-search, .ft-btn-apply').on('click', function(e) {
e.preventDefault();
reloadWithParams();
});
});
</script>

<div class="ft-members-list">
<?php
if (empty($members)) {
echo '<div class="ft-no-members">' . __('В этой группе пока нет персоналий', 'genius-family-tree') . '</div>';
echo '</div></div></div>';
return;
}
foreach ($members as $member) {
$thumbnail_id = get_post_thumbnail_id($member->ID);
$gender = get_post_meta($member->ID, '_family_member_gender', true);
$birth_date = get_post_meta($member->ID, '_family_member_birth_date', true);
$death_date = get_post_meta($member->ID, '_family_member_death_date', true);
$is_root = get_post_meta($member->ID, '_family_member_is_root', true);
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
$avatar_url = '';
if ($thumbnail_id) {
$avatar_image = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
if ($avatar_image) {
$avatar_url = $avatar_image[0];
}
}
if (empty($avatar_url)) {
$avatar_url = FAMILY_TREE_PLUGIN_URL . 'assets/images/silhouette-' . ($gender === 'female' ? 'woman' : 'man') . '.svg';
}
?>
<div class="ft-member-item">
<img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr(get_the_title($member->ID)); ?>" class="ft-member-avatar">
<div class="ft-member-info">
<h4 class="ft-member-name">
<a href="<?php echo esc_url(get_edit_post_link($member->ID)); ?>">
<?php echo esc_html(get_the_title($member->ID)); ?>
</a>
</h4>
<div class="ft-member-dates">
<?php echo esc_html($dates_display); ?>
</div>
</div>
<?php if ($is_root == '1' || $is_root == 'yes'): ?>
<span class="ft-root-badge"><?php esc_html_e('Корень', 'genius-family-tree'); ?></span>
<?php endif; ?>
</div>
<?php
}
?>
</div>
</div>
</div>
<?php
}
/**
* Добавляет фильтры для списка персоналий (поиск и сортировка)
*/
public function add_family_member_filters($post_type) {
if ($post_type !== 'family_member') {
return;
}
// Получаем текущие параметры
$sort_order = isset($_GET['ft_member_sort']) ? sanitize_text_field($_GET['ft_member_sort']) : 'date_asc';
?>
<style>
.ft-member-sort-form {
display: inline-flex;
gap: 8px;
margin-right: 10px;
align-items: center;
}
.ft-member-sort-form select {
padding: 6px 10px;
border: 1px solid #ccc;
border-radius: 4px;
min-width: 200px;
}
.ft-member-sort-form .button {
padding: 6px 12px;
}
</style>
<!-- Форма сортировки -->
<form method="get" class="ft-member-sort-form">
<input type="hidden" name="post_type" value="family_member">
<select name="ft_member_sort">
<option value="date_asc" <?php selected($sort_order, 'date_asc'); ?>><?php esc_html_e('Дата рождения (старшие сначала)', 'genius-family-tree'); ?></option>
<option value="date_desc" <?php selected($sort_order, 'date_desc'); ?>><?php esc_html_e('Дата рождения (младшие сначала)', 'genius-family-tree'); ?></option>
<option value="alpha_asc" <?php selected($sort_order, 'alpha_asc'); ?>><?php esc_html_e('А-Я (А-Я)', 'genius-family-tree'); ?></option>
<option value="alpha_desc" <?php selected($sort_order, 'alpha_desc'); ?>><?php esc_html_e('Я-А (Я-А)', 'genius-family-tree'); ?></option>
</select>
<button type="submit" class="button"><?php esc_html_e('Фильтр', 'genius-family-tree'); ?></button>
<?php if ($sort_order !== 'date_asc'): ?>
<a href="<?php echo esc_url(remove_query_arg(array('ft_member_sort'))); ?>" class="button" style="color: #646970;">
<?php esc_html_e('Сброс', 'genius-family-tree'); ?>
</a>
<?php endif; ?>
</form>
<?php
// Убираем стандартную кнопку "Фильтр" WordPress
add_action('admin_footer', array($this, 'remove_standard_filter_button'), 100);
}
/**
* Удаляем стандартную кнопку "Фильтр" WordPress для family_member
*/
public function remove_standard_filter_button() {
global $pagenow, $typenow;
if ($pagenow === 'edit.php' && $typenow === 'family_member') {
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
var filterButton = document.getElementById('post-query-submit');
if (filterButton) {
filterButton.style.display = 'none';
}
var filterByDate = document.getElementById('filter-by-date');
if (filterByDate && filterByDate.parentNode) {
filterByDate.parentNode.style.display = 'none';
}
});
</script>
<?php
}
}
/**
* Убирает стандартный фильтр дат WordPress для персоналий
*/
public function remove_months_dropdown_for_family_member($months, $post_type) {
if ($post_type === 'family_member') {
return array();
}
return $months;
}
/**
* Получает доступные десятилетия рождения
*/
private function get_available_birth_decades() {
global $wpdb;
$decades = $wpdb->get_col("
SELECT DISTINCT FLOOR(SUBSTRING(meta_value, 1, 4) / 10) * 10 AS decade
FROM {$wpdb->postmeta} pm
INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
WHERE pm.meta_key = '_birth_date'
AND p.post_type = 'family_member'
AND p.post_status != 'trash'
AND meta_value REGEXP '^[0-9]{4}'
ORDER BY decade DESC
");
return array_map('intval', array_filter($decades));
}
/**
* Обрабатывает фильтры для запроса персоналий (сортировка и поиск)
*/
public function filter_family_member_query($query) {
global $pagenow;
if (!is_admin() || $pagenow !== 'edit.php' ||
!isset($query->query_vars['post_type']) ||
$query->query_vars['post_type'] !== 'family_member') {
return;
}
// Сортировка
if (isset($_GET['ft_member_sort'])) {
$sort_order = sanitize_text_field($_GET['ft_member_sort']);
switch ($sort_order) {
case 'date_asc':
$query->set('meta_key', '_family_member_birth_date');
$query->set('orderby', 'meta_value_num');
$query->set('order', 'ASC');
break;
case 'date_desc':
$query->set('meta_key', '_family_member_birth_date');
$query->set('orderby', 'meta_value_num');
$query->set('order', 'DESC');
break;
case 'alpha_asc':
$query->set('orderby', 'title');
$query->set('order', 'ASC');
break;
case 'alpha_desc':
$query->set('orderby', 'title');
$query->set('order', 'DESC');
break;
}
}
}
/**
* Форматирует дату для отображения
*/
private function format_date_for_display($date_string) {
if (empty($date_string)) {
return '';
}
// Проверяем различные форматы дат
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_string, $matches)) {
return sprintf('%02d.%02d.%d', $matches[3], $matches[2], $matches[1]);
}
if (preg_match('/^(\d{4})-(\d{2})$/', $date_string, $matches)) {
$months = array(
'01' => 'января', '02' => 'февраля', '03' => 'марта',
'04' => 'апреля', '05' => 'мая', '06' => 'июня',
'07' => 'июля', '08' => 'августа', '09' => 'сентября',
'10' => 'октября', '11' => 'ноября', '12' => 'декабря'
);
$month_num = $matches[2];
$month_name = isset($months[$month_num]) ? $months[$month_num] : $month_num;
return $month_name . ' ' . $matches[1];
}
return $date_string;
}
/**
* Делаем новые столбцы сортируемыми
*/
public function make_family_member_columns_sortable($columns) {
$columns['lifespan'] = 'lifespan';
$columns['is_root'] = 'is_root';
$columns['family_group'] = 'family_group';
return $columns;
}
/**
* Добавляем стили для новых столбцов
*/
public function custom_admin_column_styles() {
$screen = get_current_screen();
if ($screen && $screen->post_type === 'family_member') {
?>
<style>
.column-portrait {
width: 70px;
text-align: center;
}
.column-lifespan {
width: 150px;
}
.column-family_group {
width: 200px;
}
.column-is_root {
width: 50px;
text-align: center;
}
.column-portrait img {
display: block;
margin: 0 auto;
}
.family-tree-root-metabox .description {
margin-top: 5px;
font-size: 12px;
}
</style>
<script>
jQuery(document).ready(function($) {
// Добавляем обработчик для быстрого редактирования
$('#the-list').on('click', '.editinline', function() {
var post_id = $(this).closest('tr').attr('id').replace('post-', '');
var is_root = $('td.is_root .dashicons-star-filled', $(this).closest('tr')).length > 0;
$('#inline-edit').find('input[name="family_member_is_root"]').prop('checked', is_root);
});
});
</script>
<?php
}
// Стили для страницы редактирования семейной группы
if ($screen && $screen->post_type === 'family_group') {
?>
<style>
.family-group-members-list {
display: grid;
grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
gap: 15px;
margin-top: 15px;
}
.family-member-card {
background: #fff;
border: 1px solid #ccd0d4;
border-radius: 4px;
padding: 15px;
box-shadow: 0 1px 2px rgba(0,0,0,0.05);
transition: box-shadow 0.2s ease;
}
.family-member-card:hover {
box-shadow: 0 3px 6px rgba(0,0,0,0.1);
}
.family-member-card .member-avatar {
width: 60px;
height: 60px;
flex-shrink: 0;
border-radius: 4px;
overflow: hidden;
}
.family-member-card .member-avatar img {
width: 100%;
height: 100%;
object-fit: cover;
}
.family-member-card .member-info {
flex-grow: 1;
min-width: 0;
}
.family-member-card .member-name {
font-weight: 600;
color: #2271b1;
margin-bottom: 4px;
font-size: 14px;
}
.family-member-card .member-name a {
color: inherit;
text-decoration: none;
}
.family-member-card .member-name a:hover {
color: #135e96;
}
.family-member-card .member-lifespan {
font-size: 12px;
color: #666;
}
.family-member-card .member-root-icon {
color: #ffb900;
font-size: 18px;
}
.family-member-card .member-group-label {
font-size: 11px;
color: #999;
border-top: 1px solid #f0f0f1;
padding-top: 8px;
margin-top: 10px;
}
</style>
<?php
}
}
/**
* Исправление проблемы с количеством записей в админ-панели
*/
private function fix_admin_per_page_issue() {
// Регистрируем опцию экрана для типа записей family_member
add_action('load-edit.php', function() {
$screen = get_current_screen();
if ($screen && $screen->post_type === 'family_member') {
// Добавляем опцию экрана, если её ещё нет
$option = $screen->get_option('per_page', 'option');
if (empty($option)) {
add_screen_option('per_page', array(
'label' => __('Персоналий на странице', 'genius-family-tree'),
'default' => 20,
'option' => 'edit_family_member_per_page'
));
}
}
});
// Фильтр для правильного сохранения опции
add_filter('set-screen-option', function($status, $option, $value) {
if (in_array($option, ['edit_family_member_per_page', 'family_member_per_page'])) {
return (int)$value;
}
return $status;
}, 10, 3);
// Принудительно устанавливаем количество записей из настроек пользователя
add_filter('pre_get_posts', function($query) {
// Только в админке и только для нашего типа записей
if (is_admin() && $query->get('post_type') === 'family_member' && $query->is_main_query()) {
// Получаем ID текущего пользователя
$user_id = get_current_user_id();
// Пробуем получить настройку из разных мест
$per_page = get_user_meta($user_id, 'edit_family_member_per_page', true);
if (empty($per_page) || !is_numeric($per_page)) {
$per_page = get_user_meta($user_id, 'family_member_per_page', true);
}
if (empty($per_page) || !is_numeric($per_page)) {
// Если нет пользовательской настройки, пробуем получить из опций экрана
$screen = get_current_screen();
if ($screen && $screen->post_type === 'family_member') {
$option = $screen->get_option('per_page', 'option');
if ($option) {
$per_page = get_user_meta($user_id, $option, true);
}
}
}
// Если нашли значение и оно числовое - применяем
if (!empty($per_page) && is_numeric($per_page) && $per_page > 0) {
$query->set('posts_per_page', (int)$per_page);
}
}
return $query;
}, PHP_INT_MAX); // Максимальный приоритет
// Добавляем фильтр для edit_posts_per_page
add_filter('edit_posts_per_page', function($per_page, $post_type) {
if ($post_type === 'family_member') {
$user_id = get_current_user_id();
$user_option = get_user_meta($user_id, 'edit_family_member_per_page', true);
if (!empty($user_option) && is_numeric($user_option)) {
return (int)$user_option;
}
}
return $per_page;
}, 10, 2);
}
public function init_admin() {
// Добавляем главное меню "Семейное древо"
add_menu_page(
'Семейное древо',
'Семейное древо',
'manage_options',
'family-tree-main',
array($this, 'main_page'),
'dashicons-groups',
5
);
// Добавляем подменю "Все члены семьи"
add_submenu_page(
'family-tree-main',
'Все члены семьи',
'Все члены семьи',
'manage_options',
'edit.php?post_type=family_member'
);
// Добавляем подменю "Добавить нового члена семьи"
add_submenu_page(
'family-tree-main',
'Добавить нового члена семьи',
'Добавить нового',
'manage_options',
'post-new.php?post_type=family_member'
);
// Добавляем подменю "Семейные группы"
add_submenu_page(
'family-tree-main',
'Семейные группы',
'Семейные группы',
'manage_options',
'edit.php?post_type=family_group'
);
// Добавляем подменю "Добавить новую семейную группу"
add_submenu_page(
'family-tree-main',
'Добавить новую семейную группу',
'Добавить группу',
'manage_options',
'post-new.php?post_type=family_group'
);
// Добавляем подменю "Настройки"
add_submenu_page(
'family-tree-main',
'Настройки семейного древа',
'Настройки',
'manage_options',
'family-tree-settings',
array($this, 'settings_page')
);
// Добавляем страницу для миграции персоналий в группу по умолчанию
add_submenu_page(
null,
'Миграция персоналий',
'Миграция персоналий',
'manage_options',
'family-tree-migrate',
array($this, 'migrate_members_page')
);
}
public function main_page() {
?>
<div class="wrap">
<h1><?php echo esc_html__('Семейное древо', 'genius-family-tree'); ?></h1>
<p><?php echo esc_html__('Добро пожаловать в плагин управления семейным древом. Выберите раздел в меню слева.', 'genius-family-tree'); ?></p>
<?php
// Проверяем наличие персоналий без группы и предлагаем миграцию
$default_group_id = get_option('family_tree_default_group_id', 0);
if ($default_group_id) {
$ungrouped_count = count(get_posts(array(
'post_type' => 'family_member',
'posts_per_page' => -1,
'post_status' => 'any',
'meta_query' => array(
array(
'key' => '_family_member_group_id',
'compare' => 'NOT EXISTS'
)
),
'fields' => 'ids'
)));
if ($ungrouped_count > 0) {
?>
<div class="notice notice-warning" style="margin-top: 20px;">
<p>
<strong><?php _e('Внимание!', 'genius-family-tree'); ?></strong><br>
<?php printf(
_n(
'Найдена %d персоналия без семейной группы.',
'Найдено %d персоналий без семейной группы.',
$ungrouped_count,
'genius-family-tree'
),
$ungrouped_count
); ?>
<a href="<?php echo esc_url(admin_url('admin.php?page=family-tree-migrate')); ?>" class="button button-primary" style="margin-left: 10px;">
<?php _e('Перенести в группу «Без названия»', 'genius-family-tree'); ?>
</a>
</p>
</div>
<?php
}
}
?>
</div>
<?php
}
/**
* Страница миграции персоналий в группу по умолчанию
*/
public function migrate_members_page() {
$default_group_id = get_option('family_tree_default_group_id', 0);
if (!$default_group_id || !get_post($default_group_id)) {
?>
<div class="wrap">
<h1><?php _e('Миграция персоналий', 'genius-family-tree'); ?></h1>
<div class="notice notice-error">
<p><?php _e('Группа по умолчанию не найдена. Пожалуйста, активируйте плагин заново или создайте группу вручную.', 'genius-family-tree'); ?></p>
</div>
</div>
<?php
return;
}
$default_group = get_post($default_group_id);
// Обработка миграции
if (isset($_POST['migrate_members']) && check_admin_referer('family_tree_migrate_nonce')) {
$this->migrate_ungrouped_members_to_default($default_group_id);
?>
<div class="wrap">
<div class="notice notice-success">
<p><?php _e('Все персоналии успешно перенесены в группу «Без названия».', 'genius-family-tree'); ?></p>
</div>
<p><a href="<?php echo esc_url(admin_url('edit.php?post_type=family_member')); ?>" class="button"><?php _e('Вернуться к списку персоналий', 'genius-family-tree'); ?></a></p>
</div>
<?php
return;
}
// Получаем количество персоналий без группы
$ungrouped_members = get_posts(array(
'post_type' => 'family_member',
'posts_per_page' => -1,
'post_status' => 'any',
'meta_query' => array(
array(
'key' => '_family_member_group_id',
'compare' => 'NOT EXISTS'
)
),
'fields' => 'ids'
));
$ungrouped_count = count($ungrouped_members);
?>
<div class="wrap">
<h1><?php _e('Миграция персоналий', 'genius-family-tree'); ?></h1>
<?php if ($ungrouped_count > 0): ?>
<p><?php printf(
_n(
'Найдена %d персоналия без семейной группы.',
'Найдено %d персоналий без семейной группы.',
$ungrouped_count,
'genius-family-tree'
),
$ungrouped_count
); ?></p>
<p><?php printf(
__('Эти персоналии будут перенесены в группу «%s». Вы сможете изменить название группы или перераспределить персоналий позже.', 'genius-family-tree'),
'<strong>' . esc_html($default_group->post_title) . '</strong>'
); ?></p>
<form method="post" style="margin-top: 20px;">
<?php wp_nonce_field('family_tree_migrate_nonce'); ?>
<input type="submit" name="migrate_members" value="<?php esc_attr_e('Выполнить миграцию', 'genius-family-tree'); ?>" class="button button-primary button-hero">
<a href="<?php echo esc_url(admin_url('edit.php?post_type=family_member')); ?>" class="button button-secondary" style="margin-left: 10px;"><?php _e('Отмена', 'genius-family-tree'); ?></a>
</form>
<h2 style="margin-top: 30px;"><?php _e('Персоналии для миграции:', 'genius-family-tree'); ?></h2>
<ul style="list-style: disc; margin-left: 20px; max-height: 400px; overflow-y: auto;">
<?php foreach ($ungrouped_members as $member_id): ?>
<li style="padding: 5px 0;">
<a href="<?php echo esc_url(get_edit_post_link($member_id)); ?>" target="_blank">
<?php echo esc_html(get_the_title($member_id)); ?>
</a>
</li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<div class="notice notice-success">
<p><?php _e('Все персоналии уже распределены по группам. Миграция не требуется.', 'genius-family-tree'); ?></p>
</div>
<p><a href="<?php echo esc_url(admin_url('edit.php?post_type=family_member')); ?>" class="button"><?php _e('Вернуться к списку персоналий', 'genius-family-tree'); ?></a></p>
<?php endif; ?>
</div>
<?php
}
public function register_settings() {
register_setting('family_tree_settings', 'family_tree_license_key', array('sanitize_callback' => 'sanitize_text_field'));
register_setting('family_tree_settings', 'family_tree_is_pro', array('sanitize_callback' => 'intval'));
register_setting('family_tree_settings', 'family_tree_license_data', array('sanitize_callback' => array($this, 'sanitize_license_data')));
register_setting('family_tree_settings', 'family_tree_license_last_check', array('sanitize_callback' => 'intval'));
register_setting('family_tree_settings', 'family_tree_license_tier', array('sanitize_callback' => 'sanitize_text_field'));
register_setting('family_tree_settings', 'family_tree_has_gedcom', array('sanitize_callback' => 'intval'));
register_setting('family_tree_settings', 'family_tree_is_lifetime', array('sanitize_callback' => 'intval'));
}
// Функция санитизации для лицензионных данных
public function sanitize_license_data($data) {
if (!is_array($data)) {
return array();
}
$sanitized = array();
foreach ($data as $key => $value) {
$sanitized[sanitize_text_field($key)] = sanitize_text_field($value);
}
return $sanitized;
}
public function init() {
// Регистрация типа записи
Family_Tree_Post_Type::register();
// Регистрация метабоксов
Family_Tree_Meta_Boxes::init();
// Регистрация AJAX обработчиков
Family_Tree_Ajax::init();
// Регистрация шорткода
Family_Tree_Shortcode::init();
}
public function activate() {
// Регистрируем тип записи и пересохраняем правила
Family_Tree_Post_Type::register();
flush_rewrite_rules();
// Создаем семейную группу "Без названия" по умолчанию
$this->create_default_family_group();
}
/**
* Создает семейную группу по умолчанию и переносит в неё все персоналии без группы
*/
private function create_default_family_group() {
// Проверяем, существует ли уже группа по умолчанию
$default_group_id = get_option('family_tree_default_group_id', 0);
if ($default_group_id && get_post($default_group_id)) {
// Группа существует, проверяем нужно ли перенести персоналии без группы
$this->migrate_ungrouped_members_to_default($default_group_id);
return;
}
// Создаем новую группу
$group_id = wp_insert_post(array(
'post_type' => 'family_group',
'post_title' => __('Без названия', 'genius-family-tree'),
'post_status' => 'publish'
));
if ($group_id && !is_wp_error($group_id)) {
update_option('family_tree_default_group_id', $group_id);
// Переносим все персоналии без группы в новую группу по умолчанию
$this->migrate_ungrouped_members_to_default($group_id);
} else {
// Если создание группы не удалось, всё равно пытаемся мигрировать персоналии
// на случай если группа была создана вручную
$existing_groups = get_posts(array(
'post_type' => 'family_group',
'posts_per_page' => 1,
'post_status' => 'any',
'fields' => 'ids'
));
if (!empty($existing_groups)) {
update_option('family_tree_default_group_id', $existing_groups[0]);
$this->migrate_ungrouped_members_to_default($existing_groups[0]);
}
}
}
/**
* Переносит все персоналии без группы в группу по умолчанию
*/
private function migrate_ungrouped_members_to_default($default_group_id) {
// Получаем все персоналии, у которых нет группы
$ungrouped_members = get_posts(array(
'post_type' => 'family_member',
'posts_per_page' => -1,
'post_status' => 'any',
'meta_query' => array(
array(
'key' => '_family_member_group_id',
'compare' => 'NOT EXISTS'
)
),
'fields' => 'ids'
));
if (!empty($ungrouped_members)) {
foreach ($ungrouped_members as $member_id) {
update_post_meta($member_id, '_family_member_group_id', $default_group_id);
}
}
}
public function deactivate() {
// Очищаем правила пермалинков
flush_rewrite_rules();
// При деактивации плагина пытаемся деактивировать лицензию на сервере
$license_key = get_option('family_tree_license_key', '');
if (!empty($license_key)) {
$this->deactivate_license_on_server($license_key);
// Отправляем данные о деактивации
$this->send_activation_data($license_key, false);
}
}
/**
* Отправляет запрос на сервер для деактивации лицензии при деактивации плагина
*/
private function deactivate_license_on_server($license_key) {
$api_url = 'https://xn----8sbbdpda1c7cwf.xn--p1ai/wp-json/family-tree/v1/deactivate-license';
$response = wp_remote_post($api_url, array(
'timeout' => 15,
'body' => array(
'license_key' => $license_key,
'site_url' => home_url(),
)
));
}
public function enqueue_scripts() {
// Для страниц персон не загружаем скрипты древа вообще
if (is_singular('family_member')) {
// Загружаем только базовые стили
wp_enqueue_style('family-tree-style', FAMILY_TREE_PLUGIN_URL . 'assets/css/family-tree.css', array(), '1.3.4');
return;
}
// Не загружаем скрипты в админке
if (is_admin()) {
return;
}
// Проверяем, есть ли шорткод на странице
$should_load_scripts = $this->should_load_family_tree_scripts();
// Загружаем скрипты только если они действительно нужны
if ($should_load_scripts) {
// Загружаем стили
wp_enqueue_style('family-tree-style', FAMILY_TREE_PLUGIN_URL . 'assets/css/family-tree.css', array(), '1.3.4');
// Загружаем D3.js
wp_enqueue_script('d3', FAMILY_TREE_PLUGIN_URL . 'assets/vendor/d3.min.js', array(), '1.0.0', true);
// Загружаем FamilyChart библиотеку (f3)
wp_enqueue_script('family-chart', FAMILY_TREE_PLUGIN_URL . 'assets/vendor/family-chart.min.js', array('d3'), '1.0.0', true);
// Загружаем наш скрипт
wp_enqueue_script('family-tree-script', FAMILY_TREE_PLUGIN_URL . 'assets/js/family-tree.js', array('family-chart'), '1.3.4', true);
// Локализация скрипта с plugin_url
wp_localize_script('family-tree-script', 'familyTreeAjax', array(
'ajaxurl' => admin_url('admin-ajax.php'),
'nonce' => wp_create_nonce('family_tree_nonce'),
'plugin_url' => FAMILY_TREE_PLUGIN_URL,
'silhouette_man' => FAMILY_TREE_PLUGIN_URL . 'assets/images/silhouette-man.svg',
'silhouette_woman' => FAMILY_TREE_PLUGIN_URL . 'assets/images/silhouette-woman.svg',
'root_member_id' => $this->get_root_member_id()
));
} else {
// Загружаем только стили
wp_enqueue_style('family-tree-style', FAMILY_TREE_PLUGIN_URL . 'assets/css/family-tree.css', array(), '1.3.4');
}
}
private function should_load_family_tree_scripts() {
// Проверяем только на страницах, где может быть древо
if (is_page() || is_single() || is_home() || is_archive()) {
global $post;
if ($post) {
// Проверяем наличие шорткода
if (has_shortcode($post->post_content, 'family_tree')) {
return true;
}
// Проверяем по содержанию
if (strpos($post->post_content, '[family_tree') !== false) {
return true;
}
}
}
return false;
}
public function admin_enqueue_scripts($hook) {
global $post_type, $pagenow;
// Отключаем heartbeat на странице настроек плагина
if ($hook === 'family_member_page_family-tree-settings') {
wp_deregister_script('heartbeat');
}
if (($hook == 'post-new.php' || $hook == 'post.php') && ($post_type == 'family_member' || $post_type == 'family_group')) {
wp_enqueue_style('family-tree-admin', FAMILY_TREE_PLUGIN_URL . 'assets/css/admin.css', array(), '1.3.4');
wp_enqueue_script('family-tree-admin', FAMILY_TREE_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), '1.3.4', true);
// Передача данных в скрипт
wp_localize_script('family-tree-admin', 'familyTreeAdmin', array(
'ajaxurl' => admin_url('admin-ajax.php'),
'nonce' => wp_create_nonce('family_tree_admin_nonce'),
'silhouette_man' => FAMILY_TREE_PLUGIN_URL . 'assets/images/silhouette-man.svg',
'silhouette_woman' => FAMILY_TREE_PLUGIN_URL . 'assets/images/silhouette-woman.svg',
'root_member_id' => $this->get_root_member_id()
));
}
// Загружаем стили и скрипты для страницы настроек
if ($hook == 'family_member_page_family-tree-settings') {
wp_enqueue_style('family-tree-admin', FAMILY_TREE_PLUGIN_URL . 'assets/css/admin.css', array(), '1.3.4');
wp_enqueue_script('family-tree-settings', FAMILY_TREE_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), '1.3.4', true);
wp_localize_script('family-tree-settings', 'familyTreeAdmin', array(
'ajaxurl' => admin_url('admin-ajax.php'),
'nonce' => wp_create_nonce('family_tree_license_nonce')
));
}
}
public function add_thumbnail_support() {
add_theme_support('post-thumbnails');
}
public function load_family_member_template($template) {
if (is_singular('family_member')) {
$plugin_template = FAMILY_TREE_PLUGIN_DIR . 'templates/single-family_member.php';
if (file_exists($plugin_template)) {
return $plugin_template;
}
}
return $template;
}
/**
* Находит URL первой страницы, содержащей шорткод [family_tree]
* @return string|false URL страницы или false, если не найдена
*/
public static function find_family_tree_page_url() {
$all_pages = get_posts(array(
'post_type' => 'page',
'post_status' => 'publish',
'posts_per_page' => -1,
'orderby' => 'menu_order',
'order' => 'ASC'
));
foreach ($all_pages as $page) {
if (has_shortcode($page->post_content, 'family_tree') || strpos($page->post_content, '[family_tree') !== false) {
return get_permalink($page->ID);
}
}
return false;
}
/**
* Вспомогательная функция для создания безопасного ID
*/
private function make_safe_id($str) {
// Транслитерация кириллицы
$translit_table = array(
'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo','Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'J','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'C','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch','Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'
);
$str = strtr($str, $translit_table);
// Удаление всех символов, кроме букв, цифр и дефисов/подчеркиваний
$str = preg_replace("/[^A-Za-z0-9_-]/", '', $str);
// Приведение к нижнему регистру
$str = strtolower($str);
return $str;
}
/**
* Вспомогательная функция для нормализации фамилии
* Убирает окончания -ов, -ова, -ев, -ева, -ин, -ина и т.д.
*/
private function normalize_surname($surname) {
// Приводим к нижнему регистру для сравнения
$surname_lower = mb_strtolower($surname, 'UTF-8');
// Список окончаний для нормализации
$endings = array(
// Мужские окончания
'ов' => '',
'ев' => '',
'ин' => '',
'ын' => '',
'ский' => '',
'цкий' => '',
'ской' => '',
'цкой' => '',
// Женские окончания
'ова' => '',
'ева' => '',
'ина' => '',
'ына' => '',
'ская' => '',
'цкая' => '',
'ская' => '',
'цкая' => '',
);
// Пробуем удалить каждое окончание
foreach ($endings as $ending => $replacement) {
if (mb_substr($surname_lower, -mb_strlen($ending, 'UTF-8')) === $ending) {
// Получаем основу фамилии (без окончания)
$base = mb_substr($surname, 0, mb_strlen($surname, 'UTF-8') - mb_strlen($ending, 'UTF-8'), 'UTF-8');
// Возвращаем нормализованную фамилию с большой буквы
return mb_strtoupper(mb_substr($base, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($base, 1, null, 'UTF-8');
}
}
// Окончание не найдено, возвращаем оригинальную фамилию
return $surname;
}
/**
* Вспомогательная функция для определения типа фамилии (мужская/женская)
*/
private function get_surname_type($surname) {
$surname_lower = mb_strtolower($surname, 'UTF-8');
// Женские окончания
$feminine_endings = array('ova', 'eva', 'ina', 'yna', 'skaya', 'tskaya');
foreach ($feminine_endings as $ending) {
if (mb_substr($surname_lower, -mb_strlen($ending, 'UTF-8')) === $ending) {
return 'feminine';
}
}
// Мужские окончания
$masculine_endings = array('ov', 'ev', 'in', 'yn', 'skiy', 'tskiy', 'skoy', 'tskoy');
foreach ($masculine_endings as $ending) {
if (mb_substr($surname_lower, -mb_strlen($ending, 'UTF-8')) === $ending) {
return 'masculine';
}
}
// Если не определено, считаем нейтральной
return 'neutral';
}
/**
* Функция для формирования заголовка фамилии
*/
private function format_surname_title($variants) {
if (empty($variants)) {
return '';
}
// Если только одна фамилия
if (count($variants) == 1) {
return $variants[0];
}
// Разделяем на мужские и женские варианты
$masculine_variants = array();
$feminine_variants = array();
foreach ($variants as $variant) {
$type = $this->get_surname_type($variant);
if ($type === 'masculine' || $type === 'neutral') {
$masculine_variants[] = $variant;
} elseif ($type === 'feminine') {
$feminine_variants[] = $variant;
}
}
// Убираем дубликаты
$masculine_variants = array_unique($masculine_variants);
$feminine_variants = array_unique($feminine_variants);
// Формируем заголовок
if (!empty($masculine_variants) && !empty($feminine_variants)) {
// Есть и мужские, и женские варианты
return implode(', ', array_merge($masculine_variants, $feminine_variants));
} elseif (!empty($masculine_variants)) {
// Только мужские варианты
return implode(', ', $masculine_variants);
} elseif (!empty($feminine_variants)) {
// Только женские варианты
return implode(', ', $feminine_variants);
} else {
// Все остальные случаи
return implode(', ', $variants);
}
}
/**
* ОСНОВНОЙ callback-функция для шорткода [family_surname_catalog]
*/
public function render_surname_catalog($atts) {
// Проверяем, что мы на странице сайта, а не в админке
if (is_admin()) {
return '';
}
// Получаем ВСЕ записи типа family_member
$args = array(
'post_type'      => 'family_member',
'post_status'    => 'publish',
'posts_per_page' => -1, // Получаем все записи
'nopaging'       => true, // Отключаем пагинацию
'no_found_rows'  => true, // Не считаем общее количество
'orderby'        => 'title', // Сортируем по заголовку
'order'          => 'ASC',
);
$members_query = new WP_Query($args);
if (!$members_query->have_posts()) {
return '<p>' . esc_html__('Фамилии не найдены.', 'genius-family-tree') . '</p>';
}
// --- СОБИРАЕМ ДАННЫЕ ---
$all_persons = array(); // Все записи с фамилией
$surname_groups = array(); // Группы фамилий по нормализованным формам
$cyrillic_surnames = array(); // Кириллические фамилии
$latin_surnames = array();    // Латинские фамилии
while ($members_query->have_posts()) {
$members_query->the_post();
$post_id = get_the_ID();
// --- Логика получения фамилии ТОЛЬКО из метаполя ---
$raw_last_name_meta = get_post_meta($post_id, '_family_member_last_name', true);
$last_name = trim($raw_last_name_meta);
// --- Если фамилия пустая, пропускаем персону ---
if (empty($last_name)) {
continue; // Пропустить эту персону
}
// --- Получаем девичью фамилию ---
$maiden_name = get_post_meta($post_id, '_family_member_maiden_name', true);
$maiden_name = trim($maiden_name);
// --- Сохраняем запись ---
$person_data = array(
'id' => $post_id,
'title' => get_the_title(),
'url' => get_permalink(),
'surname' => $last_name,
'maiden_name' => $maiden_name
);
$all_persons[] = $person_data;
// --- Добавляем основную фамилию в группы ---
$normalized_surname = $this->normalize_surname($last_name);
// Определяем алфавит по первой букве фамилии
$first_char_raw = mb_substr($last_name, 0, 1, 'UTF-8');
$first_char = mb_strtoupper($first_char_raw, 'UTF-8');
// Проверка на латиницу
if (preg_match('/^[A-Za-z]$/', $first_char)) {
$alphabet_type = 'latin';
if (!isset($latin_surnames[$first_char])) {
$latin_surnames[$first_char] = array();
}
$latin_surnames[$first_char][] = $last_name;
} else {
$alphabet_type = 'cyrillic';
if (!isset($cyrillic_surnames[$first_char])) {
$cyrillic_surnames[$first_char] = array();
}
$cyrillic_surnames[$first_char][] = $last_name;
}
if (!isset($surname_groups[$alphabet_type])) {
$surname_groups[$alphabet_type] = array();
}
if (!isset($surname_groups[$alphabet_type][$first_char])) {
$surname_groups[$alphabet_type][$first_char] = array(
'letter' => $first_char,
'members' => array()
);
}
// Инициализируем массив для фамилии, если он не существует
if (!isset($surname_groups[$alphabet_type][$first_char]['members'][$normalized_surname])) {
$surname_groups[$alphabet_type][$first_char]['members'][$normalized_surname] = array(
'surname' => $normalized_surname,
'variants' => array(),
'posts' => array()
);
}
// Добавляем вариант фамилии, если он еще не добавлен
if (!in_array($last_name, $surname_groups[$alphabet_type][$first_char]['members'][$normalized_surname]['variants'])) {
$surname_groups[$alphabet_type][$first_char]['members'][$normalized_surname]['variants'][] = $last_name;
}
// Добавляем персону в массив фамилии
$surname_groups[$alphabet_type][$first_char]['members'][$normalized_surname]['posts'][] = array(
'id' => $post_id,
'title' => get_the_title(),
'url' => get_permalink(),
'surname' => $last_name,
'type' => 'основная'
);
// --- Добавляем девичью фамилию в группы (если она есть) ---
if (!empty($maiden_name)) {
$normalized_maiden_name = $this->normalize_surname($maiden_name);
// Определяем алфавит по первой букве девичьей фамилии
$maiden_first_char_raw = mb_substr($maiden_name, 0, 1, 'UTF-8');
$maiden_first_char = mb_strtoupper($maiden_first_char_raw, 'UTF-8');
// Проверка на латиницу
if (preg_match('/^[A-Za-z]$/', $maiden_first_char)) {
$maiden_alphabet_type = 'latin';
if (!isset($latin_surnames[$maiden_first_char])) {
$latin_surnames[$maiden_first_char] = array();
}
$latin_surnames[$maiden_first_char][] = $maiden_name;
} else {
$maiden_alphabet_type = 'cyrillic';
if (!isset($cyrillic_surnames[$maiden_first_char])) {
$cyrillic_surnames[$maiden_first_char] = array();
}
$cyrillic_surnames[$maiden_first_char][] = $maiden_name;
}
if (!isset($surname_groups[$maiden_alphabet_type])) {
$surname_groups[$maiden_alphabet_type] = array();
}
if (!isset($surname_groups[$maiden_alphabet_type][$maiden_first_char])) {
$surname_groups[$maiden_alphabet_type][$maiden_first_char] = array(
'letter' => $maiden_first_char,
'members' => array()
);
}
// Инициализируем массив для девичьей фамилии, если он не существует
if (!isset($surname_groups[$maiden_alphabet_type][$maiden_first_char]['members'][$normalized_maiden_name])) {
$surname_groups[$maiden_alphabet_type][$maiden_first_char]['members'][$normalized_maiden_name] = array(
'surname' => $normalized_maiden_name,
'variants' => array(),
'posts' => array()
);
}
// Добавляем вариант девичьей фамилии, если он еще не добавлен
if (!in_array($maiden_name, $surname_groups[$maiden_alphabet_type][$maiden_first_char]['members'][$normalized_maiden_name]['variants'])) {
$surname_groups[$maiden_alphabet_type][$maiden_first_char]['members'][$normalized_maiden_name]['variants'][] = $maiden_name;
}
// Добавляем персону в массив девичьей фамилии
$surname_groups[$maiden_alphabet_type][$maiden_first_char]['members'][$normalized_maiden_name]['posts'][] = array(
'id' => $post_id,
'title' => get_the_title(),
'url' => get_permalink(),
'surname' => $maiden_name,
'type' => 'девичья'
);
}
}
wp_reset_postdata();
// Если данных нет (например, все персоны без фамилий)
if (empty($all_persons)) {
return '<p>' . esc_html__('Фамилии не найдены.', 'genius-family-tree') . '</p>';
}
// --- Сортируем данные по алфавиту ---
ksort($cyrillic_surnames, SORT_STRING);
ksort($latin_surnames, SORT_STRING);
foreach (array('cyrillic', 'latin') as $atype) {
if (isset($surname_groups[$atype])) {
ksort($surname_groups[$atype], SORT_STRING);
foreach ($surname_groups[$atype] as &$letter_data) {
ksort($letter_data['members'], SORT_STRING);
foreach ($letter_data['members'] as &$surname_group) {
sort($surname_group['variants'], SORT_STRING);
usort($surname_group['posts'], function($a, $b) {
return strcasecmp($a['title'], $b['title']);
});
}
unset($surname_group);
}
unset($letter_data);
}
}
// --- Начинаем формировать HTML ---
ob_start();
?>
<!-- Заглушка для предотвращения ошибок других скриптов -->
<script>
if (typeof window.f3 === 'undefined') {
window.f3 = {
handlers: {},
elements: {},
CalculateTree: function() {},
createStore: function() {},
d3AnimationView: function() {}
};
}
</script>
<div class="family-tree-surname-catalog">
<!-- Алфавиты -->
<?php if (isset($surname_groups['cyrillic'])): ?>
<div class="catalog-alphabet cyrillic-alphabet" id="cyrillic-alphabet">
<ul class="alphabet-list">
<?php
$russian_alphabet = array('А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я');
foreach ($russian_alphabet as $letter) {
$has_entries = isset($surname_groups['cyrillic'][$letter]);
$class = $has_entries ? 'active' : 'inactive';
$safe_letter_id = $this->make_safe_id('cy-' . $letter);
$href = $has_entries ? "#section-{$safe_letter_id}" : '';
echo '<li class="alphabet-letter ' . esc_attr($class) . '">';
if ($has_entries) {
echo '<a href="' . esc_url($href) . '">' . esc_html($letter) . '</a>';
} else {
echo '<span>' . esc_html($letter) . '</span>';
}
echo '</li>';
}
?>
</ul>
</div>
<?php endif; ?>
<?php if (isset($surname_groups['latin'])): ?>
<div class="catalog-alphabet latin-alphabet" id="latin-alphabet">
<ul class="alphabet-list">
<?php
$latin_alphabet = range('A', 'Z');
foreach ($latin_alphabet as $letter) {
$has_entries = isset($surname_groups['latin'][$letter]);
$class = $has_entries ? 'active' : 'inactive';
$safe_letter_id = $this->make_safe_id('lt-' . $letter);
$href = $has_entries ? "#section-{$safe_letter_id}" : '';
echo '<li class="alphabet-letter ' . esc_attr($class) . '">';
if ($has_entries) {
echo '<a href="' . esc_url($href) . '">' . esc_html($letter) . '</a>';
} else {
echo '<span>' . esc_html($letter) . '</span>';
}
echo '</li>';
}
?>
</ul>
</div>
<?php endif; ?>
<!-- Список фамилий по буквам кириллицы -->
<?php if (isset($surname_groups['cyrillic'])): ?>
<div class="catalog-surnames cyrillic-surnames">
<?php foreach ($surname_groups['cyrillic'] as $letter => $letter_data): ?>
<div class="surname-section" id="section-<?php echo esc_attr($this->make_safe_id('cy-' . $letter)); ?>">
<h2><?php echo esc_html($letter); ?></h2>
<div class="surname-list">
<?php
$members_by_surname = $letter_data['members'];
$surname_count = count($members_by_surname);
$current_index = 0;
foreach ($members_by_surname as $surname_data):
$current_index++;
$surname = $surname_data['surname'];
$variants = $surname_data['variants'];
$posts = $surname_data['posts'];
usort($posts, function($a, $b) {
return strcasecmp($a['title'], $b['title']);
});
$formatted_surname_title = $this->format_surname_title($variants);
?>
<div class="surname-group<?php echo ($current_index < $surname_count) ? ' surname-group-with-margin' : ''; ?>">
<h3 class="surname-title">
<?php echo esc_html($formatted_surname_title); ?>
<span class="surname-count">(<?php echo count($posts); ?>)</span>
</h3>
<ul class="surname-members">
<?php foreach ($posts as $member): ?>
<li class="member-item">
<a href="<?php echo esc_url($member['url']); ?>"><?php echo esc_html($member['title']); ?></a>
</li>
<?php endforeach; ?>
</ul>
</div>
<?php endforeach; ?>
</div>
<div class="back-to-top">
<a href="#cyrillic-alphabet">&#8593; <?php echo esc_html__('Наверх', 'genius-family-tree'); ?></a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<!-- Список фамилий по буквам латиницы -->
<?php if (isset($surname_groups['latin'])): ?>
<div class="catalog-surnames latin-surnames">
<?php foreach ($surname_groups['latin'] as $letter => $letter_data): ?>
<div class="surname-section" id="section-<?php echo esc_attr($this->make_safe_id('lt-' . $letter)); ?>">
<h2><?php echo esc_html($letter); ?></h2>
<div class="surname-list">
<?php
$members_by_surname = $letter_data['members'];
$surname_count = count($members_by_surname);
$current_index = 0;
foreach ($members_by_surname as $surname_data):
$current_index++;
$surname = $surname_data['surname'];
$variants = $surname_data['variants'];
$posts = $surname_data['posts'];
usort($posts, function($a, $b) {
return strcasecmp($a['title'], $b['title']);
});
$formatted_surname_title = $this->format_surname_title($variants);
?>
<div class="surname-group<?php echo ($current_index < $surname_count) ? ' surname-group-with-margin' : ''; ?>">
<h3 class="surname-title">
<?php echo esc_html($formatted_surname_title); ?>
<span class="surname-count">(<?php echo count($posts); ?>)</span>
</h3>
<ul class="surname-members">
<?php foreach ($posts as $member): ?>
<li class="member-item">
<a href="<?php echo esc_url($member['url']); ?>"><?php echo esc_html($member['title']); ?></a>
</li>
<?php endforeach; ?>
</ul>
</div>
<?php endforeach; ?>
</div>
<div class="back-to-top">
<a href="#latin-alphabet">&#8593; <?php echo esc_html__('Наверх', 'genius-family-tree'); ?></a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php
$output = ob_get_clean();
return $output;
}
/**
* DEBUG callback-функция для шорткода [family_surname_catalog_debug]
* Просто выводит список всех записей и их фамилий.
*/
public function render_surname_catalog_debug($atts) {
if (is_admin()) return '';
// Получаем ВСЕ записи типа family_member
$args = array(
'post_type'      => 'family_member',
'post_status'    => 'publish',
'posts_per_page' => 500,
'nopaging'       => true,
'no_found_rows'  => true,
'orderby'        => 'title',
'order'          => 'ASC',
);
$members_query = new WP_Query($args);
if (!$members_query->have_posts()) {
return '<p>' . esc_html__('Нет записей типа family_member.', 'genius-family-tree') . '</p>';
}
$total_count = 0;
$with_surname_count = 0;
$surname_list = array();
while ($members_query->have_posts()) {
$members_query->the_post();
$post_id = get_the_ID();
$total_count++;
$raw_last_name_meta = get_post_meta($post_id, '_family_member_last_name', true);
$last_name = trim($raw_last_name_meta);
if (!empty($last_name)) {
$with_surname_count++;
if (!isset($surname_list[$last_name])) {
$surname_list[$last_name] = array(
'count' => 0,
'members' => array()
);
}
$surname_list[$last_name]['count']++;
$surname_list[$last_name]['members'][] = array(
'id' => $post_id,
'title' => get_the_title(),
'url' => get_permalink()
);
}
}
wp_reset_postdata();
uksort($surname_list, 'strnatcasecmp');
ob_start();
?>
<div style="padding: 20px; font-family: monospace;">
<h2><?php echo esc_html__('DEBUG: Каталог Фамилий (Простой список)', 'genius-family-tree'); ?></h2>
<p><strong><?php echo esc_html__('Всего записей:', 'genius-family-tree'); ?></strong> <?php echo esc_html($total_count); ?></p>
<p><strong><?php echo esc_html__('Записей с фамилией:', 'genius-family-tree'); ?></strong> <?php echo esc_html($with_surname_count); ?></p>
<p><strong><?php echo esc_html__('Записей без фамилии:', 'genius-family-tree'); ?></strong> <?php echo esc_html($total_count - $with_surname_count); ?></p>
<hr>
<h3><?php echo esc_html__('Список фамилий:', 'genius-family-tree'); ?></h3>
<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">
<thead>
<tr>
<th><?php echo esc_html__('Фамилия', 'genius-family-tree'); ?></th>
<th><?php echo esc_html__('Количество', 'genius-family-tree'); ?></th>
<th><?php echo esc_html__('Персоны', 'genius-family-tree'); ?></th>
</tr>
</thead>
<tbody>
<?php foreach($surname_list as $surname => $data): ?>
<tr>
<td><strong><?php echo esc_html($surname); ?></strong></td>
<td><?php echo esc_html($data['count']); ?></td>
<td>
<ul style="margin: 0; padding-left: 20px;">
<?php foreach($data['members'] as $member): ?>
<li>
<a href="<?php echo esc_url($member['url']); ?>" target="_blank"><?php echo esc_html($member['title']); ?></a>
(ID: <?php echo esc_html($member['id']); ?>)
</li>
<?php endforeach; ?>
</ul>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php
return ob_get_clean();
}
// Добавляем страницу настроек
public function settings_page() {
// Обработка формы
$message = '';
$message_type = '';
if (isset($_POST['family_tree_save_settings']) && isset($_POST['family_tree_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['family_tree_settings_nonce'])), 'family_tree_save_settings')) {
if (isset($_POST['family_tree_license_key'])) {
$new_license_key = sanitize_text_field(wp_unslash($_POST['family_tree_license_key']));
$old_license_key = get_option('family_tree_license_key', '');
if (empty($new_license_key)) {
// Деактивация
update_option('family_tree_license_key', '');
update_option('family_tree_is_pro', false);
delete_option('family_tree_license_data');
delete_option('family_tree_license_tier');
delete_option('family_tree_has_gedcom');
delete_option('family_tree_is_lifetime');
if (!empty($old_license_key)) {
$this->send_activation_data($old_license_key, false);
}
$message = esc_html__('Лицензия деактивирована', 'genius-family-tree');
$message_type = 'updated';
} else {
// Активация/проверка лицензии
$result = $this->validate_license($new_license_key);
if ($result['success']) {
update_option('family_tree_license_key', $new_license_key);
$message = esc_html($result['message']);
$message_type = 'updated';
$this->send_activation_data($new_license_key, true);
} else {
$message = esc_html($result['message']);
$message_type = 'error';
$this->send_activation_data($new_license_key, false);
}
}
}
}
// Получаем текущие
$license_key = get_option('family_tree_license_key', '');
$is_pro = get_option('family_tree_is_pro', false);
$license_data = get_option('family_tree_license_data', array());
$last_check = get_option('family_tree_license_last_check', 0);
$license_tier = get_option('family_tree_license_tier', 'free');
$has_gedcom = get_option('family_tree_has_gedcom', false);
$is_lifetime = get_option('family_tree_is_lifetime', false);
$license_status = $is_pro ? esc_html__('Активна', 'genius-family-tree') : esc_html__('Не активна', 'genius-family-tree');
$license_status_class = $is_pro ? 'pro-active' : 'pro-inactive';
?>
<div class="wrap">
<h1><?php echo esc_html__('Настройки семейного древа', 'genius-family-tree'); ?></h1>
<?php if (!empty($message)): ?>
<div class="notice notice-<?php echo esc_attr($message_type); ?>"><p><?php echo wp_kses_post($message); ?></p></div>
<?php endif; ?>
<div class="family-tree-settings-container">
<form method="post" action="">
<?php wp_nonce_field('family_tree_save_settings', 'family_tree_settings_nonce'); ?>
<table class="form-table">
<tr>
<th scope="row"><?php echo esc_html__('Лицензионный ключ', 'genius-family-tree'); ?></th>
<td>
<input type="text" name="family_tree_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" />
<p class="description"><?php echo esc_html__('Введите ваш лицензионный ключ для активации Pro-версии.', 'genius-family-tree'); ?></p>
<?php if (!empty($license_data)): ?>
<div class="license-info" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
<strong><?php echo esc_html__('Информация о лицензии:', 'genius-family-tree'); ?></strong><br>
<?php if (isset($license_data['customer_name'])): ?>
<?php echo esc_html__('Владелец:', 'genius-family-tree'); ?> <?php echo esc_html($license_data['customer_name']); ?><br>
<?php endif; ?>
<?php
if (isset($license_data['license_tier']) && $license_data['license_tier'] === 'basic' && isset($license_data['expires'])) {
$expires_timestamp = strtotime($license_data['expires']);
if ($expires_timestamp && $expires_timestamp > 0) {
echo esc_html__('Действует до:', 'genius-family-tree') . ' ' . esc_html(gmdate('d.m.Y', $expires_timestamp)) . '<br>';
}
}
?>
<?php if (isset($license_data['license_tier'])): ?>
<?php echo esc_html__('Тип:', 'genius-family-tree'); ?> <?php
switch($license_data['license_tier']) {
case 'basic': echo esc_html__('Базовая (подписка)', 'genius-family-tree'); break;
case 'premium': echo esc_html__('Премиум (пожизненно)', 'genius-family-tree'); break;
default: echo esc_html__('Бесплатная', 'genius-family-tree'); break;
}
?><br>
<?php endif; ?>
<?php echo esc_html__('Последняя проверка:', 'genius-family-tree'); ?> <?php echo esc_html(gmdate('d.m.Y H:i', $last_check)); ?>
</div>
<?php endif; ?>
</td>
</tr>
<tr>
<th scope="row"><?php echo esc_html__('Статус Pro-версии', 'genius-family-tree'); ?></th>
<td>
<span class="license-status <?php echo esc_attr($license_status_class); ?>">
<?php echo esc_html($license_status); ?>
</span>
<?php if ($is_pro): ?>
<button type="button" class="button" id="deactivate-license-button" style="margin-left: 10px;"><?php echo esc_html__('Деактивировать лицензию', 'genius-family-tree'); ?></button>
<?php endif; ?>
<p class="description"><?php echo esc_html__('Pro-версия снимает ограничения на количество персоналий и добавляет экспорт в GEDCOM.', 'genius-family-tree'); ?></p>
</td>
</tr>
</table>
<?php submit_button(esc_html__('Сохранить настройки', 'genius-family-tree'), 'primary', 'family_tree_save_settings'); ?>
</form>
<?php if ($is_pro): ?>
<div class="pro-features-section">
<h3><?php
switch($license_tier) {
case 'basic': echo esc_html__('Функции Базовой версии', 'genius-family-tree'); break;
case 'premium': echo esc_html__('Функции Премиум-версии', 'genius-family-tree'); break;
default: echo esc_html__('Функции Pro-версии', 'genius-family-tree'); break;
}
?></h3>
<ul>
<li>✓ <?php echo esc_html__('Неограниченное количество персоналий', 'genius-family-tree'); ?></li>
<?php if ($has_gedcom): ?>
<li>✓ <?php echo esc_html__('Экспорт в формат GEDCOM с фотографиями', 'genius-family-tree'); ?></li>
<li>✓ <?php echo esc_html__('Импорт из формата GEDCOM с фотографиями', 'genius-family-tree'); ?></li>
<li>✓ <?php echo esc_html__('Возможность указать корневой элемент древа', 'genius-family-tree'); ?></li>
<li>✓ <?php echo esc_html__('Расширенные возможности настройки', 'genius-family-tree'); ?></li>
<?php endif; ?>
</ul>
<?php if ($has_gedcom): ?>
<div class="gedcom-buttons-section" style="margin-top: 24px; max-width: 400px;">
<!-- Экспорт -->
<div class="gedcom-button-wrapper" style="margin-bottom: 12px;">
<a href="<?php echo esc_url(admin_url('admin-ajax.php?action=family_tree_export_gedcom&_wpnonce=' . wp_create_nonce('family_tree_gedcom'))); ?>" class="button button-primary button-hero" style="width: 100%; text-align: center;">
<?php echo esc_html__('Экспорт в GEDCOM', 'genius-family-tree'); ?>
</a>
</div>
<!-- Импорт -->
<div class="gedcom-button-wrapper">
<form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 8px;">
<input type="hidden" name="action" value="family_tree_import_gedcom" />
<?php wp_nonce_field('family_tree_import_gedcom', 'family_tree_import_gedcom_nonce'); ?>
<input type="file" name="gedcom_file" accept=".ged,.GED" required style="width: 100%; padding: 6px 10px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; box-sizing: border-box;" />
<input type="submit" class="button button-secondary button-hero" value="<?php echo esc_attr__('Импорт из GEDCOM', 'genius-family-tree'); ?>" style="width: 100%; text-align: center;" />
<p class="description"><?php echo esc_html__('Поддерживается импорт фотографий и корневого элемента из файлов GEDCOM', 'genius-family-tree'); ?></p>
</form>
</div>
</div>
<?php endif; ?>
</div>
<?php else: ?>
<div class="pro-features-section">
<h3><?php echo esc_html__('Доступные функции в Pro-версии', 'genius-family-tree'); ?></h3>
<ul>
<li>✓ <?php echo esc_html__('Неограниченное количество персоналий (в стандартной версии максимум 50)', 'genius-family-tree'); ?></li>
<li>✓ <?php echo esc_html__('Экспорт в формат GEDCOM с фотографиями', 'genius-family-tree'); ?></li>
<li>✓ <?php echo esc_html__('Импорт из формата GEDCOM с фотографиями', 'genius-family-tree'); ?></li>
<li>✓ <?php echo esc_html__('Возможность указать корневой элемент древа', 'genius-family-tree'); ?></li>
<li>✓ <?php echo esc_html__('Расширенные возможности настройки', 'genius-family-tree'); ?></li>
</ul>
<p><a href="https://xn----8sbbdpda1c7cwf.xn--p1ai/product-category/genius-family-tree/" class="button button-primary" target="_blank"><?php echo esc_html__('Приобрести Pro-версию', 'genius-family-tree'); ?></a></p>
</div>
<?php endif; ?>
</div>
</div>
<style>
.family-tree-settings-container {
background: #fff;
padding: 20px;
border: 1px solid #ccd0d4;
box-shadow: 0 1px 1px rgba(0,0,0,.04);
margin-top: 15px;
}
.license-status.pro-active {
color: #00a32a;
font-weight: bold;
}
.license-status.pro-inactive {
color: #d63638;
font-weight: bold;
}
.pro-features-section {
margin-top: 30px;
padding-top: 20px;
border-top: 1px solid #eee;
}
.pro-features-section ul {
list-style: none;
}
.pro-features-section ul li:before {
content: "✓";
color: #00a32a;
margin-right: 8px;
}
</style>
<script type="text/javascript">
jQuery(document).ready(function($) {
$('#deactivate-license-button').click(function() {
if (!confirm('<?php echo esc_js(__('Вы уверены, что хотите деактивировать лицензию?', 'genius-family-tree')); ?>')) {
return;
}
$.post(ajaxurl, {
action: 'family_tree_deactivate_license',
nonce: '<?php echo esc_js(wp_create_nonce('family_tree_license_nonce')); ?>'
}, function(response) {
if (response.success) {
alert('<?php echo esc_js(__('Лицензия успешно деактивирована!', 'genius-family-tree')); ?>');
location.reload();
} else {
alert('<?php echo esc_js(__('Ошибка деактивации: ', 'genius-family-tree')); ?>' + response.data);
}
});
});
});
</script>
<?php
}
// AJAX обработчик для активации лицензии
public function ajax_activate_license() {
if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'family_tree_license_nonce')) {
wp_send_json_error(esc_html__('Ошибка безопасности', 'genius-family-tree'));
}
if (!current_user_can('manage_options')) {
wp_send_json_error(esc_html__('Недостаточно прав', 'genius-family-tree'));
}
if (!isset($_POST['license_key'])) {
wp_send_json_error(esc_html__('Не указан лицензионный ключ', 'genius-family-tree'));
}
$license_key = sanitize_text_field(wp_unslash($_POST['license_key']));
if (empty($license_key)) {
wp_send_json_error(esc_html__('Не указан лицензионный ключ', 'genius-family-tree'));
}
$result = $this->validate_license($license_key);
if ($result['success']) {
update_option('family_tree_license_key', $license_key);
$this->send_activation_data($license_key, true);
wp_send_json_success(esc_html($result['message']));
} else {
$this->send_activation_data($license_key, false);
wp_send_json_error(esc_html($result['message']));
}
}
// AJAX обработчик для деактивации лицензии
public function ajax_deactivate_license() {
if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'family_tree_license_nonce')) {
wp_send_json_error(esc_html__('Ошибка безопасности', 'genius-family-tree'));
}
if (!current_user_can('manage_options')) {
wp_send_json_error(esc_html__('Недостаточно прав', 'genius-family-tree'));
}
$license_key = get_option('family_tree_license_key', '');
if (empty($license_key)) {
wp_send_json_error(esc_html__('Лицензия не найдена', 'genius-family-tree'));
}
$api_url = 'https://xn----8sbbdpda1c7cwf.xn--p1ai/wp-json/family-tree/v1/deactivate-license';
$response = wp_remote_post($api_url, array(
'timeout' => 15,
'body' => array(
'license_key' => $license_key,
'site_url' => home_url(),
)
));
delete_option('family_tree_license_key');
update_option('family_tree_is_pro', false);
delete_option('family_tree_license_data');
delete_option('family_tree_license_last_check');
delete_option('family_tree_license_tier');
delete_option('family_tree_has_gedcom');
delete_option('family_tree_is_lifetime');
$this->send_activation_data($license_key, false);
wp_send_json_success(esc_html__('Лицензия успешно деактивирована', 'genius-family-tree'));
}
// AJAX обработчик для экспорта GEDCOM
public function ajax_export_gedcom() {
// Проверка прав доступа
if (!current_user_can('manage_options')) {
wp_die(esc_html__('Недостаточно прав', 'genius-family-tree'));
}
// Проверка nonce
if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'family_tree_gedcom')) {
wp_die(esc_html__('Ошибка безопасности', 'genius-family-tree'));
}
// Проверка доступа к GEDCOM (только у Premium)
if (!get_option('family_tree_has_gedcom', false) || !get_option('family_tree_is_pro', false)) {
wp_die(esc_html__('Экспорт доступен только в Premium-версии', 'genius-family-tree'));
}
// Получаем все записи типа family_member
$args = array(
'post_type' => 'family_member',
'post_status' => 'publish',
'posts_per_page' => -1,
'nopaging' => true
);
$query = new WP_Query($args);
if (!$query->have_posts()) {
wp_die(esc_html__('Нет данных для экспорта', 'genius-family-tree'));
}
$gedcom_content = "0 HEAD
";
$gedcom_content .= "1 GEDC
";
$gedcom_content .= "2 VERS 5.5.1
";
$gedcom_content .= "2 FORM LINEAGE-LINKED
";
$gedcom_content .= "1 CHAR UTF-8
";
$gedcom_content .= "1 LANG Russian
";
$gedcom_content .= "1 SOUR Genius Family Tree
";
$gedcom_content .= "2 NAME Genius Family Tree
";
$gedcom_content .= "2 VERS 1.3.4
";
$gedcom_content .= "1 DEST GEDCOM
";
$gedcom_content .= "1 DATE " . gmdate('d M Y') . "
";
$gedcom_content .= "1 FILE " . get_bloginfo('name') . "
";
$gedcom_content .= "1 COPR Copyright (c) " . gmdate('Y') . " " . get_bloginfo('name') . "
";
$individuals = array();
$used_families = array();
$family_counter = 1;
// Находим корневой элемент (если есть)
$root_member_id = $this->get_root_member_id();
// Сначала собираем всех персон
foreach ($query->posts as $post) {
$id = $post->ID;
$ref = "@I{$id}@";
$individuals[$id] = $ref;
$first_name = get_post_meta($id, '_family_member_first_name', true);
$last_name = get_post_meta($id, '_family_member_last_name', true);
$middle_name = get_post_meta($id, '_family_member_middle_name', true);
$gender = get_post_meta($id, '_family_member_gender', true);
$birth_date = get_post_meta($id, '_family_member_birth_date', true);
$birth_place = get_post_meta($id, '_family_member_birth_place', true);
$death_date = get_post_meta($id, '_family_member_death_date', true);
$death_cause = get_post_meta($id, '_family_member_death_cause', true);
$death_place = get_post_meta($id, '_family_member_death_place', true);
$is_deceased = get_post_meta($id, '_family_member_is_deceased', true);
$maiden_name = get_post_meta($id, '_family_member_maiden_name', true);
$is_root = ($root_member_id == $id);
// Получаем изображение персоны
$thumbnail_id = get_post_thumbnail_id($id);
$image_url = '';
$image_path = '';
if ($thumbnail_id) {
$image_url = wp_get_attachment_url($id);
$image_path = get_attached_file($thumbnail_id);
}
$full_first_name = trim($first_name . ' ' . $middle_name);
if (empty($full_first_name)) $full_first_name = $first_name;
$gedcom_content .= "0 {$ref} INDI
";
$gedcom_content .= "1 NAME {$full_first_name} /{$last_name}/
";
$gedcom_content .= "2 GIVN {$full_first_name}
";
$gedcom_content .= "2 SURN {$last_name}
";
if ($gender) {
$gedcom_content .= "1 SEX " . strtoupper(substr($gender, 0, 1)) . "
";
}
// Добавляем информацию о корневом элементе
if ($is_root) {
$gedcom_content .= "1 _ROOT Y
";
}
// Добавляем изображение в GEDCOM
if (!empty($image_url)) {
$filename = basename($image_url);
$file_extension = pathinfo($filename, PATHINFO_EXTENSION);
$gedcom_content .= "1 OBJE
";
$gedcom_content .= "2 FORM " . strtoupper($file_extension) . "
";
$gedcom_content .= "2 TITL Фотография " . $post->post_title . "
";
$gedcom_content .= "2 FILE " . $image_url . "
";
if (!empty($image_path)) {
$gedcom_content .= "2 _PATH " . $image_path . "
";
}
}
if (!empty($birth_date) || !empty($birth_place)) {
$gedcom_content .= "1 BIRT
";
if (!empty($birth_date)) {
$gedcom_content .= "2 DATE " . $this->format_gedcom_date($birth_date) . "
";
}
if (!empty($birth_place)) {
$gedcom_content .= "2 PLAC " . sanitize_text_field($birth_place) . "
";
}
}
if ($is_deceased || !empty($death_date) || !empty($death_cause) || !empty($death_place)) {
$gedcom_content .= "1 DEAT
";
if (!empty($death_date)) {
$gedcom_content .= "2 DATE " . $this->format_gedcom_date($death_date) . "
";
}
if (!empty($death_cause)) {
$gedcom_content .= "2 CAUS " . sanitize_text_field($death_cause) . "
";
}
if (!empty($death_place)) {
$gedcom_content .= "2 PLAC " . sanitize_text_field($death_place) . "
";
}
}
if ($maiden_name) {
$gedcom_content .= "1 NAME {$full_first_name} /{$maiden_name}/
";
$gedcom_content .= "2 TYPE married
";
}
$gedcom_content .= "1 RIN WP:{$id}
";
$gedcom_content .= "1 NOTE Запись создана в Genius Family Tree
";
}
// === СОБИРАЕМ СЕМЬИ ИЗ ВСЕХ ВОЗМОЖНЫХ ИСТОЧНИКОВ ===
// 1. Семьи по отцу → детям
foreach ($query->posts as $father_post) {
$father_id = $father_post->ID;
$children = get_post_meta($father_id, '_family_member_children', true);
if (!is_array($children)) $children = array();
$children = array_filter(array_map('intval', $children));
if (!empty($children)) {
$family_key = "father_{$father_id}";
if (!isset($used_families[$family_key])) {
$family_id = "F" . $family_counter++;
$used_families[$family_key] = $family_id;
$gedcom_content .= "0 @{$family_id}@ FAM
";
$gedcom_content .= "1 HUSB {$individuals[$father_id]}
";
foreach ($children as $child_id) {
if (isset($individuals[$child_id])) {
$gedcom_content .= "1 CHIL {$individuals[$child_id]}
";
}
}
}
}
}
// 2. Семьи по матери → детям
foreach ($query->posts as $mother_post) {
$mother_id = $mother_post->ID;
$children = get_post_meta($mother_id, '_family_member_children', true);
if (!is_array($children)) $children = array();
$children = array_filter(array_map('intval', $children));
if (!empty($children)) {
$family_key = "mother_{$mother_id}";
if (!isset($used_families[$family_key])) {
$family_id = "F" . $family_counter++;
$used_families[$family_key] = $family_id;
$gedcom_content .= "0 @{$family_id}@ FAM
";
$gedcom_content .= "1 WIFE {$individuals[$mother_id]}
";
foreach ($children as $child_id) {
if (isset($individuals[$child_id])) {
$gedcom_content .= "1 CHIL {$individuals[$child_id]}
";
}
}
}
}
}
// 3. Семьи по детям → родителям (обратная связь)
foreach ($query->posts as $child_post) {
$child_id = $child_post->ID;
$father_id = get_post_meta($child_id, '_family_member_father', true);
$mother_id = get_post_meta($child_id, '_family_member_mother', true);
if ($father_id || $mother_id) {
$family_key = "child_{$child_id}";
if (!isset($used_families[$family_key])) {
$family_id = "F" . $family_counter++;
$used_families[$family_key] = $family_id;
$gedcom_content .= "0 @{$family_id}@ FAM
";
if ($father_id && isset($individuals[$father_id])) {
$gedcom_content .= "1 HUSB {$individuals[$father_id]}
";
}
if ($mother_id && isset($individuals[$mother_id])) {
$gedcom_content .= "1 WIFE {$individuals[$mother_id]}
";
}
$gedcom_content .= "1 CHIL {$individuals[$child_id]}
";
}
}
}
// 4. Брачные семьи по супругам
foreach ($query->posts as $person_post) {
$person_id = $person_post->ID;
$spouses = get_post_meta($person_id, '_family_member_spouses', true);
if (!is_array($spouses)) continue;
foreach ($spouses as $spouse_data) {
$spouse_id = isset($spouse_data['id']) ? intval($spouse_data['id']) : 0;
if (!$spouse_id || $spouse_id <= $person_id) continue;
$family_key = "spouse_{$person_id}_{$spouse_id}";
if (!isset($used_families[$family_key])) {
$family_id = "F" . $family_counter++;
$used_families[$family_key] = $family_id;
$gedcom_content .= "0 @{$family_id}@ FAM
";
$gedcom_content .= "1 HUSB {$individuals[$person_id]}
";
$gedcom_content .= "1 WIFE {$individuals[$spouse_id]}
";
// Добавляем общих детей
$person_children = get_post_meta($person_id, '_family_member_children', true);
$spouse_children = get_post_meta($spouse_id, '_family_member_children', true);
if (!is_array($person_children)) $person_children = array();
if (!is_array($spouse_children)) $spouse_children = array();
$common_children = array_intersect(
array_filter(array_map('intval', $person_children)),
array_filter(array_map('intval', $spouse_children))
);
foreach ($common_children as $child_id) {
if (isset($individuals[$child_id])) {
$gedcom_content .= "1 CHIL {$individuals[$child_id]}
";
}
}
}
}
}
$gedcom_content .= "0 TRLR
";
// Отправляем файл
$filename = 'family-tree-' . gmdate('Y-m-d-His') . '.ged';
header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($gedcom_content));
echo $gedcom_content;
exit;
}
/**
* Форматирует дату в формат GEDCOM: "01 JAN 1950", "JAN 1950", "1950"
*/
private function format_gedcom_date($date_string) {
if (empty($date_string) || !is_string($date_string)) {
return '';
}
$date_string = trim($date_string);
// Полный формат: ГГГГ-ММ-ДД
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_string, $m)) {
$year = (int)$m[1];
$month = (int)$m[2];
$day = (int)$m[3];
if ($year >= 1 && $year <= 9999 && checkdate($month, $day, $year)) {
return $this->format_timestamp_for_gedcom(mktime(0, 0, 0, $month, $day, $year));
}
}
// Формат: ГГГГ-ММ
if (preg_match('/^(\d{4})-(\d{2})$/', $date_string, $m)) {
$year = (int)$m[1];
$month = (int)$m[2];
if ($year >= 1 && $year <= 9999 && $month >= 1 && $month <= 12) {
return ' ' . $this->month_number_to_gedcom($month) . ' ' . $year;
}
}
// Формат: ГГГГ
if (preg_match('/^(\d{4})$/', $date_string, $m)) {
$year = (int)$m[1];
if ($year >= 1 && $year <= 9999) {
return $date_string;
}
}
return '';
}
/**
* Преобразует timestamp в строку GEDCOM: "01 JAN 1950"
*/
private function format_timestamp_for_gedcom($timestamp) {
$months = array(
1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR',
5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AUG',
9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC'
);
$day = gmdate('d', $timestamp);
$month_num = (int)gmdate('m', $timestamp);
$year = gmdate('Y', $timestamp);
$month = $months[$month_num] ?? 'JAN';
return "{$day} {$month} {$year}";
}
/**
* Преобразует номер месяца в строку GEDCOM (например, 5 → 'MAY')
*/
private function month_number_to_gedcom($month) {
$months = array(
1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR',
5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AUG',
9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC'
);
return $months[$month] ?? 'JAN';
}
/**
* Парсит дату в формате GEDCOM (например, "01 JUL 1851" или "1851") и возвращает массив [year, month, day]
* @return array|null
*/
private function parse_gedcom_date_to_parts($date_str) {
if (empty($date_str)) return null;
// Месяцы GEDCOM → цифры
$months = array(
'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4,
'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8,
'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12
);
// Формат: "DD MMM YYYY" или "MMM YYYY" или "YYYY"
if (preg_match('/^(\d{1,2})\s+([A-Z]{3})\s+(\d{4})$/', $date_str, $m)) {
return array(
'year' => (int)$m[3],
'month' => $months[$m[2]] ?? 0,
'day' => (int)$m[1]
);
}
if (preg_match('/^([A-Z]{3})\s+(\d{4})$/', $date_str, $m)) {
return array(
'year' => (int)$m[2],
'month' => $months[$m[1]] ?? 0,
'day' => 0
);
}
if (preg_match('/^(\d{4})$/', $date_str, $m)) {
return array(
'year' => (int)$m[1],
'month' => 0,
'day' => 0
);
}
return null;
}
/**
* Обработчик импорта GEDCOM через AJAX (POST-запрос)
*/
public function ajax_import_gedcom() {
// Проверка прав доступа
if (!current_user_can('manage_options')) {
wp_die(esc_html__('Недостаточно прав', 'genius-family-tree'));
}
// Проверка nonce
$nonce = isset($_POST['family_tree_import_gedcom_nonce'])
? sanitize_text_field(wp_unslash($_POST['family_tree_import_gedcom_nonce']))
: '';
if (!wp_verify_nonce($nonce, 'family_tree_import_gedcom')) {
wp_die(esc_html__('Ошибка безопасности', 'genius-family-tree'));
}
// Проверка доступа к GEDCOM (только у Premium)
if (!get_option('family_tree_has_gedcom', false) || !get_option('family_tree_is_pro', false)) {
wp_die(esc_html__('Импорт доступен только в Premium-версии', 'genius-family-tree'));
}
// Проверка наличия файла
if (!isset($_FILES['gedcom_file']) || empty($_FILES['gedcom_file']['tmp_name'])) {
wp_die(esc_html__('Файл не был загружен', 'genius-family-tree'));
}
if ($_FILES['gedcom_file']['error'] !== UPLOAD_ERR_OK) {
wp_die(esc_html__('Ошибка загрузки файла', 'genius-family-tree'));
}
$file_extension = strtolower(pathinfo($_FILES['gedcom_file']['name'], PATHINFO_EXTENSION));
if ($file_extension !== 'ged') {
wp_die(esc_html__('Разрешены только .ged файлы', 'genius-family-tree'));
}
if (!is_uploaded_file($_FILES['gedcom_file']['tmp_name'])) {
wp_die(esc_html__('Недопустимый источник файла', 'genius-family-tree'));
}
$file_content = file_get_contents($_FILES['gedcom_file']['tmp_name']);
if ($file_content === false) {
wp_die(esc_html__('Не удалось прочитать файл', 'genius-family-tree'));
}
// Парсинг GEDCOM файла
$parsed_data = $this->parse_gedcom_file($file_content);
if (empty($parsed_data['individuals'])) {
wp_die(esc_html__('Не удалось распарсить GEDCOM файл или файл пуст', 'genius-family-tree'));
}
// Импорт данных
$import_result = $this->import_gedcom_data($parsed_data);
// Выводим результат
?>
<div class="wrap">
<h1 class="wp-heading-inline">
<span class="dashicons dashicons-upload" style="font-size: 32px; vertical-align: middle; margin-right: 12px; color: #82878c;"></span>
<?php echo esc_html__('Результат импорта GEDCOM', 'genius-family-tree'); ?>
</h1>
<hr class="wp-header-end">
<div class="notice notice-success notice-alt is-dismissible" style="padding: 20px; border-left: 4px solid #46b450;">
<div style="display: flex; align-items: flex-start; gap: 16px;">
<span class="dashicons dashicons-yes" style="color: #46b450; font-size: 24px; margin-top: 2px;"></span>
<div>
<h3 style="margin: 0 0 12px; font-size: 18px; font-weight: 600;">
<?php echo esc_html__('Импорт завершён успешно!', 'genius-family-tree'); ?>
</h3>
<p style="margin: 0; font-size: 15px;">
<?php
echo sprintf(
_n(
'Импортирована %s персона.',
'Импортировано %s персон.',
$import_result['persons_imported'],
'genius-family-tree'
),
'<strong>' . number_format_i18n($import_result['persons_imported']) . '</strong>'
);
?>
</p>
<?php if (!empty($import_result['images_imported'])): ?>
<p style="margin: 8px 0 0; font-size: 14px;">
<?php echo sprintf(
_n(
'Импортировано %s фотография.',
'Импортировано %s фотографий.',
$import_result['images_imported'],
'genius-family-tree'
),
'<strong>' . number_format_i18n($import_result['images_imported']) . '</strong>'
); ?>
</p>
<?php endif; ?>
<?php if (!empty($import_result['root_member'])): ?>
<p style="margin: 8px 0 0; font-size: 14px; color: #46b450;">
<span class="dashicons dashicons-star-filled" style="font-size: 16px; width: 16px; height: 16px;"></span>
<?php echo sprintf(
__('Корневой элемент древа: %s', 'genius-family-tree'),
'<strong>' . esc_html($import_result['root_member']) . '</strong>'
); ?>
</p>
<?php endif; ?>
</div>
</div>
</div>
<?php if ($import_result['errors_count'] > 0): ?>
<div class="notice notice-warning notice-alt is-dismissible" style="padding: 20px; margin-top: 20px; border-left: 4px solid #dba617;">
<div style="display: flex; align-items: flex-start; gap: 16px;">
<span class="dashicons dashicons-warning" style="color: #dba617; font-size: 24px; margin-top: 2px;"></span>
<div>
<h3 style="margin: 0 0 12px; font-size: 18px; font-weight: 600;">
<?php echo esc_html__('Обнаружены ошибки', 'genius-family-tree'); ?>
</h3>
<p style="margin: 0; font-size: 15px;">
<?php echo esc_html__('Количество ошибок:', 'genius-family-tree'); ?> <strong><?php echo number_format_i18n($import_result['errors_count']); ?></strong>
</p>
<?php if (!empty($import_result['errors'])): ?>
<details style="margin-top: 16px; cursor: pointer;">
<summary style="list-style: none; margin: 0; padding: 0; outline: none; font-weight: 600; text-decoration: underline;">
<?php echo esc_html__('Показать список ошибок', 'genius-family-tree'); ?>
</summary>
<ul style="margin-top: 12px; padding-left: 20px; max-height: 200px; overflow-y: auto; background: #f8f9f9; padding: 12px; border-radius: 4px;">
<?php foreach ($import_result['errors'] as $error): ?>
<li style="margin-bottom: 6px; font-family: monospace; font-size: 13px; color: #d63638;">
<?php echo esc_html($error); ?>
</li>
<?php endforeach; ?>
</ul>
</details>
<?php endif; ?>
</div>
</div>
</div>
<?php endif; ?>
<div class="postbox" style="margin-top: 32px; border: 1px solid #c8d7e1; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
<div class="postbox-header" style="padding: 16px 20px; border-bottom: 1px solid #f0f0f0;">
<h2 class="hndle" style="font-size: 18px; margin: 0; font-weight: 600;">
<?php echo esc_html__('Что дальше?', 'genius-family-tree'); ?>
</h2>
</div>
<div class="inside" style="padding: 20px;">
<ul style="padding-left: 24px; margin: 0;">
<li style="margin-bottom: 12px;">
<a href="<?php echo esc_url(admin_url('edit.php?post_type=family_member')); ?>" style="font-weight: 500;">
<?php echo esc_html__('Просмотреть импортированные записи', 'genius-family-tree'); ?>
</a>
</li>
<li style="margin-bottom: 12px;">
<a href="<?php echo esc_url(admin_url('edit.php?post_type=family_member&page=family-tree-settings')); ?>" style="font-weight: 500;">
<?php echo esc_html__('Настроить отображение древа', 'genius-family-tree'); ?>
</a>
</li>
<li>
<?php echo sprintf(
esc_html__('Добавьте шорткод %s на любую страницу, чтобы отобразить древо.', 'genius-family-tree'),
'<code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">[family_tree]</code>'
); ?>
</li>
</ul>
</div>
</div>
<div style="margin-top: 32px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
<a href="<?php echo esc_url(admin_url('edit.php?post_type=family_member')); ?>" class="button button-primary button-hero">
<span class="dashicons dashicons-admin-users" style="margin-right: 6px;"></span>
<?php echo esc_html__('Перейти к списку персон', 'genius-family-tree'); ?>
</a>
<a href="<?php echo esc_url(admin_url('edit.php?post_type=family_member&page=family-tree-settings')); ?>" class="button button-secondary">
<?php echo esc_html__('Вернуться в настройки', 'genius-family-tree'); ?>
</a>
</div>
</div>
<?php
exit;
}
/**
* Парсит содержимое GEDCOM файла
*/
private function parse_gedcom_file($content) {
// Удаляем BOM
if (substr($content, 0, 3) === "\xef\xbb\xbf") {
$content = substr($content, 3);
}
// Нормализуем переводы строк
$content = str_replace("\r
", "
", $content);
$content = str_replace("\r", "
", $content);
$lines = explode("
", $content);
$individuals = array();
$families = array();
$current_individual = null;
$current_family = null;
$current_event = null;
$current_obje = null;
$root_member_id = null;
foreach ($lines as $line) {
$line = trim($line);
if (empty($line)) continue;
if (preg_match('/^(\d+)\s+([^\s]+)(?:\s+(.*))?$/', $line, $matches)) {
$level = (int)$matches[1];
$tag = strtoupper($matches[2]);
$value = isset($matches[3]) ? trim($matches[3]) : '';
// Обработка INDI: 0 @I123@ INDI
if ($level == 0 && $value == 'INDI') {
if ($current_individual) {
$individuals[$current_individual['id']] = $current_individual;
}
$current_individual = array(
'id' => $tag,
'name' => '',
'first_name' => '',
'last_name' => '',
'middle_name' => '',
'gender' => '',
'birth_date' => '',
'birth_place' => '',
'death_date' => '',
'death_cause' => '',
'death_place' => '',
'is_deceased' => 0,
'maiden_name' => '',
'image_url' => '',
'image_file' => '',
'is_root' => false
);
$current_event = null;
$current_obje = null;
}
// Обработка FAM: 0 @F1@ FAM
if ($level == 0 && $value == 'FAM') {
if ($current_family) {
$families[] = $current_family;
}
$current_family = array(
'id' => $tag,
'husb' => '',
'wife' => '',
'chil' => array()
);
}
// Обработка содержимого INDI
if ($current_individual) {
if ($level == 1) {
switch ($tag) {
case 'NAME':
if (preg_match('/^(.*?)\/(.*?)\/$/', $value, $name_matches)) {
$first_name_part = trim($name_matches[1]);
$last_name = trim($name_matches[2]);
$name_parts = explode(' ', $first_name_part, 2);
$first_name = $name_parts[0];
$middle_name = isset($name_parts[1]) ? $name_parts[1] : '';
$current_individual['name'] = trim($first_name_part . ' ' . $last_name);
$current_individual['first_name'] = $first_name;
$current_individual['last_name'] = $last_name;
$current_individual['middle_name'] = $middle_name;
}
break;
case 'SEX':
if (strtoupper($value) === 'M') {
$current_individual['gender'] = 'male';
} elseif (strtoupper($value) === 'F') {
$current_individual['gender'] = 'female';
} else {
$current_individual['gender'] = $value;
}
break;
case 'BIRT':
$current_event = 'BIRT';
break;
case 'DEAT':
$current_event = 'DEAT';
$current_individual['is_deceased'] = 1;
break;
case 'OBJE':
$current_obje = array('level' => 1, 'file' => '', 'form' => '', 'title' => '');
break;
case '_ROOT':
if ($value === 'Y') {
$current_individual['is_root'] = true;
$root_member_id = $current_individual['id'];
}
break;
}
} elseif ($level == 2) {
if ($current_event === 'BIRT') {
if ($tag === 'DATE') {
$current_individual['birth_date'] = $value;
} elseif ($tag === 'PLAC') {
$current_individual['birth_place'] = $value;
}
} elseif ($current_event === 'DEAT') {
if ($tag === 'DATE') {
$current_individual['death_date'] = $value;
} elseif ($tag === 'CAUS') {
$current_individual['death_cause'] = $value;
} elseif ($tag === 'PLAC') {
$current_individual['death_place'] = $value;
}
} elseif ($current_obje && $current_obje['level'] === 1) {
if ($tag === 'FILE') {
$current_obje['file'] = $value;
$current_individual['image_url'] = $value;
} elseif ($tag === 'FORM') {
$current_obje['form'] = $value;
} elseif ($tag === 'TITL') {
$current_obje['title'] = $value;
} elseif ($tag === '_PATH') {
$current_individual['image_file'] = $value;
}
}
}
}
// Обработка содержимого FAM
if ($current_family) {
if ($level == 1) {
switch ($tag) {
case 'HUSB':
$current_family['husb'] = $value;
break;
case 'WIFE':
$current_family['wife'] = $value;
break;
case 'CHIL':
$current_family['chil'][] = $value;
break;
}
}
}
}
}
// Добавляем последнюю запись
if ($current_individual) {
$individuals[$current_individual['id']] = $current_individual;
}
if ($current_family) {
$families[] = $current_family;
}
// Применяем связи
foreach ($families as $family) {
// Отец → дети
if ($family['husb'] && isset($individuals[$family['husb']])) {
foreach ($family['chil'] as $child_id) {
if (isset($individuals[$child_id])) {
$individuals[$child_id]['father'] = $family['husb'];
}
}
if (!isset($individuals[$family['husb']]['children'])) {
$individuals[$family['husb']]['children'] = array();
}
$individuals[$family['husb']]['children'] = array_unique(array_merge($individuals[$family['husb']]['children'], $family['chil']));
}
// Мать → дети
if ($family['wife'] && isset($individuals[$family['wife']])) {
foreach ($family['chil'] as $child_id) {
if (isset($individuals[$child_id])) {
$individuals[$child_id]['mother'] = $family['wife'];
}
}
if (!isset($individuals[$family['wife']]['children'])) {
$individuals[$family['wife']]['children'] = array();
}
$individuals[$family['wife']]['children'] = array_unique(array_merge($individuals[$family['wife']]['children'], $family['chil']));
}
// Супруги
if ($family['husb'] && $family['wife']) {
if (!isset($individuals[$family['husb']]['spouses'])) {
$individuals[$family['husb']]['spouses'] = array();
}
if (!in_array($family['wife'], $individuals[$family['husb']]['spouses'])) {
$individuals[$family['husb']]['spouses'][] = $family['wife'];
}
if (!isset($individuals[$family['wife']]['spouses'])) {
$individuals[$family['wife']]['spouses'] = array();
}
if (!in_array($family['husb'], $individuals[$family['wife']]['spouses'])) {
$individuals[$family['wife']]['spouses'][] = $family['husb'];
}
}
}
return array(
'individuals' => array_values($individuals),
'root_member' => $root_member_id
);
}
/**
* Импортирует распарсенные данные GEDCOM в WordPress
*/
private function import_gedcom_data($parsed_data) {
$individuals = $parsed_data['individuals'];
$root_member_id = $parsed_data['root_member'];
$result = array(
'persons_imported' => 0,
'images_imported' => 0,
'errors_count' => 0,
'errors' => array(),
'root_member' => ''
);
// Сначала создадим все записи, чтобы получить их ID
$post_ids = array();
$root_post_id = null;
foreach ($individuals as $individual) {
// Парсим дату рождения из GEDCOM-формата
$birth_year = $birth_month = $birth_day = '';
if (!empty($individual['birth_date'])) {
$parsed = $this->parse_gedcom_date_to_parts($individual['birth_date']);
if ($parsed) {
$birth_year = $parsed['year'];
$birth_month = $parsed['month'];
$birth_day = $parsed['day'];
}
}
// Парсим дату смерти из GEDCOM-формата
$death_year = $death_month = $death_day = '';
if (!empty($individual['death_date'])) {
$parsed = $this->parse_gedcom_date_to_parts($individual['death_date']);
if ($parsed) {
$death_year = $parsed['year'];
$death_month = $parsed['month'];
$death_day = $parsed['day'];
}
}
// Формируем составные даты в формате ГГГГ-ММ-ДД
$birth_date_composite = $this->build_composite_date($birth_year, $birth_month, $birth_day);
$death_date_composite = $this->build_composite_date($death_year, $death_month, $death_day);

// Формируем post_title без девичьей фамилии (только имя + отчество + основная фамилия)
$post_title_parts = array();
if (!empty($individual['first_name'])) {
    $post_title_parts[] = $individual['first_name'];
}
if (!empty($individual['middle_name'])) {
    $post_title_parts[] = $individual['middle_name'];
}
if (!empty($individual['last_name'])) {
    $post_title_parts[] = $individual['last_name'];
}
$post_title = !empty($post_title_parts) ? implode(' ', $post_title_parts) : esc_html__('Без имени', 'genius-family-tree');

$post_data = array(
'post_title' => $post_title,
'post_type' => 'family_member',
'post_status' => 'publish',
'meta_input' => array(
'_family_member_first_name' => sanitize_text_field($individual['first_name']),
'_family_member_last_name' => sanitize_text_field($individual['last_name']),
'_family_member_middle_name' => sanitize_text_field($individual['middle_name']),
'_family_member_gender' => sanitize_text_field($individual['gender']),
'_family_member_is_deceased' => (int)$individual['is_deceased'],
'_family_member_birth_date' => $birth_date_composite,
'_family_member_birth_year' => sanitize_text_field($birth_year),
'_family_member_birth_month' => sanitize_text_field($birth_month),
'_family_member_birth_day' => sanitize_text_field($birth_day),
'_family_member_death_date' => $death_date_composite,
'_family_member_death_year' => sanitize_text_field($death_year),
'_family_member_death_month' => sanitize_text_field($death_month),
'_family_member_death_day' => sanitize_text_field($death_day),
'_family_member_maiden_name' => sanitize_text_field($individual['maiden_name'])
)
);
$post_id = wp_insert_post($post_data, true);
if (is_wp_error($post_id)) {
$result['errors'][] = sprintf(__('Ошибка создания персоны "%s": %s', 'genius-family-tree'), esc_html($individual['name']), $post_id->get_error_message());
$result['errors_count']++;
} else {
$post_ids[$individual['id']] = $post_id;
$result['persons_imported']++;
// Если это корневой элемент, запоминаем его ID
if ($individual['is_root'] || ($root_member_id && $individual['id'] === $root_member_id)) {
$root_post_id = $post_id;
}
// Обработка изображения
if (!empty($individual['image_url'])) {
$image_imported = $this->import_gedcom_image($post_id, $individual['image_url'], $individual['name']);
if ($image_imported) {
$result['images_imported']++;
}
} elseif (!empty($individual['image_file']) && file_exists($individual['image_file'])) {
$image_imported = $this->create_attachment_from_file($post_id, $individual['image_file'], $individual['name']);
if ($image_imported) {
$result['images_imported']++;
}
}
}
}
// Если нашли корневой элемент, устанавливаем его
if ($root_post_id) {
// Получаем группу корневого элемента
$root_group_id = get_post_meta($root_post_id, '_family_member_group_id', true);
// Сначала сбрасываем флаг у всех записей в этой группе
$this->clear_root_member($root_group_id);
// Устанавливаем флаг для корневого элемента
update_post_meta($root_post_id, '_family_member_is_root', '1');
$result['root_member'] = get_the_title($root_post_id);
}
// Теперь обновим связи
foreach ($individuals as $individual) {
if (!isset($post_ids[$individual['id']])) continue;
$post_id = $post_ids[$individual['id']];
// Отец/мать
if (!empty($individual['father']) && isset($post_ids[$individual['father']])) {
update_post_meta($post_id, '_family_member_father', $post_ids[$individual['father']]);
}
if (!empty($individual['mother']) && isset($post_ids[$individual['mother']])) {
update_post_meta($post_id, '_family_member_mother', $post_ids[$individual['mother']]);
}
// Дети
if (!empty($individual['children'])) {
$children = array();
foreach ($individual['children'] as $child_ref) {
if (isset($post_ids[$child_ref])) {
$children[] = $post_ids[$child_ref];
}
}
if (!empty($children)) {
update_post_meta($post_id, '_family_member_children', $children);
}
}
// Супруги
if (!empty($individual['spouses'])) {
$spouses = array();
foreach ($individual['spouses'] as $spouse_ref) {
if (isset($post_ids[$spouse_ref])) {
$spouses[] = array('id' => $post_ids[$spouse_ref]);
}
}
if (!empty($spouses)) {
update_post_meta($post_id, '_family_member_spouses', $spouses);
}
}
}
return $result;
}
/**
* Импортирует изображение из GEDCOM в медиабиблиотеку WordPress
* @param int $post_id ID записи персоны
* @param string $image_url URL изображения из GEDCOM
* @param string $person_name Имя персоны для названия файла
* @return bool Успешность импорта
*/
private function import_gedcom_image($post_id, $image_url, $person_name = '') {
// Проверяем, что URL не пустой
if (empty($image_url)) {
return false;
}
// Проверяем, не является ли URL локальным путем
if (strpos($image_url, 'http') !== 0 && strpos($image_url, 'https') !== 0) {
// Это локальный путь, возможно файл уже есть на сервере
// Пробуем преобразовать в URL
$upload_dir = wp_upload_dir();
$base_dir = $upload_dir['basedir'];
// Если путь начинается с /, пытаемся найти файл
if (file_exists($image_url)) {
// Прямой путь к файлу
$file_path = $image_url;
} elseif (file_exists($base_dir . '/' . ltrim($image_url, '/'))) {
// Относительный путь от папки uploads
$file_path = $base_dir . '/' . ltrim($image_url, '/');
} else {
return false;
}
// Создаем вложение из локального файла
return $this->create_attachment_from_file($post_id, $file_path, $person_name);
}
// Скачиваем изображение по URL
$response = wp_remote_get($image_url, array(
'timeout' => 30,
'stream' => false,
'sslverify' => false
));
if (is_wp_error($response)) {
$this->log_error('Ошибка скачивания изображения: ' . $response->get_error_message());
return false;
}
$download_code = wp_remote_retrieve_response_code($response);
if ($download_code !== 200) {
$this->log_error('Ошибка HTTP при скачивании изображения: ' . $download_code);
return false;
}
$image_data = wp_remote_retrieve_body($response);
if (empty($image_data)) {
return false;
}
// Определяем расширение файла по MIME-типу или URL
$mime_type = wp_remote_retrieve_header($response, 'content-type');
$extension = $this->get_extension_from_mime($mime_type);
// Если не удалось определить по MIME, пробуем из URL
if (empty($extension)) {
$path_info = pathinfo(parse_url($image_url, PHP_URL_PATH));
$extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : 'jpg';
}
// Формируем имя файла
$filename = sanitize_title($person_name ?: 'person') . '-' . uniqid() . '.' . $extension;
// Загружаем файл в медиабиблиотеку WordPress
$upload = wp_upload_bits($filename, null, $image_data);
if ($upload['error']) {
$this->log_error('Ошибка загрузки файла: ' . $upload['error']);
return false;
}
return $this->create_attachment_from_file($post_id, $upload['file'], $person_name, $filename);
}
/**
* Создает вложение из локального файла
*/
private function create_attachment_from_file($post_id, $file_path, $person_name, $filename = '') {
if (!file_exists($file_path)) {
return false;
}
$filetype = wp_check_filetype(basename($file_path), null);
$attachment = array(
'post_mime_type' => $filetype['type'],
'post_title' => $person_name ?: 'Фото персоны',
'post_content' => '',
'post_status' => 'inherit'
);
$attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
if (is_wp_error($attach_id)) {
$this->log_error('Ошибка создания вложения: ' . $attach_id->get_error_message());
return false;
}
// Генерируем метаданные для изображения
require_once(ABSPATH . 'wp-admin/includes/image.php');
$attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
wp_update_attachment_metadata($attach_id, $attach_data);
// Устанавливаем как миниатюру записи
set_post_thumbnail($post_id, $attach_id);
return true;
}
/**
* Определяет расширение файла по MIME-типу
*/
private function get_extension_from_mime($mime_type) {
$mime_map = array(
'image/jpeg' => 'jpg',
'image/jpg' => 'jpg',
'image/png' => 'png',
'image/gif' => 'gif',
'image/webp' => 'webp',
'image/bmp' => 'bmp',
'image/tiff' => 'tiff'
);
return isset($mime_map[$mime_type]) ? $mime_map[$mime_type] : '';
}
/**
* Логирует ошибки
*/
private function log_error($message) {
if (defined('WP_DEBUG') && WP_DEBUG) {
error_log('Genius Family Tree: ' . $message);
}
}
/**
* Собирает составную дату в формате YYYY-MM-DD из отдельных частей
*/
private function build_composite_date($year, $month, $day) {
$year = trim($year);
$month = trim($month);
$day = trim($day);
if (!$year) {
return '';
}
if ($month && $day) {
return sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day);
} elseif ($month) {
return sprintf('%04d-%02d', (int)$year, (int)$month);
} else {
return (string)(int)$year;
}
}
// Проверка лицензии
public function validate_license($license_key, $background_check = false) {
$api_url = 'https://xn----8sbbdpda1c7cwf.xn--p1ai/wp-json/family-tree/v1/validate-license';
$response = wp_remote_post($api_url, array(
'timeout' => 15,
'body' => array(
'license_key' => $license_key,
'site_url' => home_url(),
'plugin_version' => '1.3.4',
'wp_version' => get_bloginfo('version'),
'php_version' => phpversion()
)
));
if (is_wp_error($response)) {
if (!$background_check) {
return array('success' => false, 'message' => esc_html__('Ошибка соединения с сервером лицензий', 'genius-family-tree'));
}
return false;
}
$response_code = wp_remote_retrieve_response_code($response);
$response_body = wp_remote_retrieve_body($response);
if ($response_code !== 200) {
if (!$background_check) {
return array('success' => false, 'message' => esc_html__('Ошибка проверки лицензии', 'genius-family-tree'));
}
return false;
}
$data = json_decode($response_body, true);
if (!$data || !isset($data['success'])) {
if (!$background_check) {
return array('success' => false, 'message' => esc_html__('Неверный формат ответа от сервера', 'genius-family-tree'));
}
return false;
}
if ($data['success']) {
update_option('family_tree_is_pro', true);
update_option('family_tree_license_data', $data);
update_option('family_tree_license_last_check', time());
if (isset($data['license_tier'])) {
update_option('family_tree_license_tier', $data['license_tier']);
}
if (isset($data['has_gedcom'])) {
update_option('family_tree_has_gedcom', $data['has_gedcom']);
}
if (isset($data['is_lifetime'])) {
update_option('family_tree_is_lifetime', $data['is_lifetime']);
}
$this->send_activation_data($license_key, true);
if (!$background_check) {
return array('success' => true, 'message' => esc_html__('Лицензия успешно активирована', 'genius-family-tree'));
}
return true;
} else {
update_option('family_tree_is_pro', false);
delete_option('family_tree_license_data');
delete_option('family_tree_license_tier');
delete_option('family_tree_has_gedcom');
delete_option('family_tree_is_lifetime');
$this->send_activation_data($license_key, false);
if (!$background_check) {
$message = isset($data['message']) ? esc_html($data['message']) : esc_html__('Неверный лицензионный ключ', 'genius-family-tree');
return array('success' => false, 'message' => $message);
}
return false;
}
}
private function send_activation_data($license_key, $is_active) {
$api_url = 'https://xn----8sbbdpda1c7cwf.xn--p1ai/wp-json/family-tree/v1/update-license-status';
$response = wp_remote_post($api_url, array(
'timeout' => 30,
'body' => array(
'license_key' => $license_key,
'site_url' => home_url(),
'is_active' => $is_active ? 1 : 0
),
'headers' => array(
'Content-Type' => 'application/json'
)
));
}
public function check_member_limit() {
$screen = get_current_screen();
if (!$screen || $screen->post_type !== 'family_member') {
return;
}
if (get_option('family_tree_is_pro', false)) {
return;
}
$count = wp_count_posts('family_member');
$member_count = $count->publish;
if ($member_count >= 50) {
$message = sprintf(
esc_html__('Достигнут лимит в 50 персоналий. Приобретите %1$sPro-версию%2$s для снятия ограничений.', 'genius-family-tree'),
'<a href="https://xn----8sbbdpda1c7cwf.xn--p1ai/product-category/genius-family-tree/" target="_blank">',
'</a>'
);
echo '<div class="notice notice-warning"><p>' . wp_kses_post($message) . '</p></div>';
}
}
public function check_member_limit_on_save($post_id, $post, $update) {
if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
return;
}
if (!current_user_can('edit_post', $post_id)) {
return;
}
if ($update) {
return;
}
if (get_option('family_tree_is_pro', false)) {
return;
}
$count = wp_count_posts('family_member');
$member_count = $count->publish;
if ($member_count >= 50) {
$message = sprintf(
esc_html__('Достигнут лимит в 50 персоналий. Приобретите %1$sPro-версию%2$s для снятия ограничений.', 'genius-family-tree'),
'<a href="https://xn----8sbbdpda1c7cwf.xn--p1ai/product-category/genius-family-tree/" target="_blank">',
'</a>'
);
wp_die(wp_kses_post($message), esc_html__('Лимит превышен', 'genius-family-tree'), array('back_link' => true));
}
}
}
Family_Tree_Plugin::get_instance();
?>