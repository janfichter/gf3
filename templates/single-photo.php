<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Единый шаблон отдельной страницы фото в семейном архиве.
 * Работает для ВСЕХ разделов: фотоальбомы, документы, пресса, письма и кастомные.
 * URL: /{archive-slug}/{section}/{member-slug}/{album-slug}/{photo-slug}/
 */
$ft_section   = sanitize_key(get_query_var('ft_section'));
$ft_sections  = ft_get_archive_sections();
$is_photoalbums = ($ft_section === 'photoalbums');
$ft_valid     = (!empty($ft_section) && isset($ft_sections[$ft_section]) && !empty($ft_sections[$ft_section]['enabled']));

$member_slug = get_query_var('ft_member');
$album_slug  = get_query_var('ft_album');
$photo_slug  = get_query_var('ft_photo');

$member = $member_slug ? get_page_by_path($member_slug, OBJECT, 'family_member') : null;
$photo  = null;
$album_name = '';
$photos = array();

if ($member && $album_slug && $ft_valid) {
	$ft_albums = ft_get_albums($member->ID, $ft_section);
	foreach ($ft_albums as $ft_a) {
		if (sanitize_title($ft_a['name']) === $album_slug) {
			$album_name = $ft_a['name'];
			$photos = array_values(array_filter(array_map('intval', (array) $ft_a['photos']), 'wp_attachment_is_image'));
			break;
		}
	}
	if ($photo_slug && !empty($photos)) {
		foreach ($photos as $pid) {
			if (get_post_field('post_name', $pid) === $photo_slug || (string) $photo_slug === (string) $pid) {
				$photo = get_post($pid);
				break;
			}
		}
	}
}

// GLightbox: подключаем лайтбокс на странице фото (до get_header, чтобы скрипты попали в wp_head).
if ($photo) {
	wp_enqueue_style('glightbox', FAMILY_TREE_PLUGIN_URL . 'assets/vendor/glightbox.min.css', array(), '3.3.0');
	wp_enqueue_script('glightbox', FAMILY_TREE_PLUGIN_URL . 'assets/vendor/glightbox.min.js', array(), '3.3.0', true);
	wp_enqueue_script('family-tree-album', FAMILY_TREE_PLUGIN_URL . 'assets/js/family-album.js', array('glightbox'), (string) filemtime(FAMILY_TREE_PLUGIN_DIR . 'assets/js/family-album.js'), true);
}

Family_Tree_Template_Compat::header();
Family_Tree_Template_Compat::open_content();
// При полностью закрытом древе — заглушка вместо страницы фото
if (ft_privacy_visibility() === 'blocked') {
    echo '<div class="content inter"><section class="content__inter _container">' . ft_privacy_render_blocked() . '</section></div>';
    Family_Tree_Template_Compat::close_content();
    Family_Tree_Template_Compat::footer();
    return;
}
// Скрытая администратором персона — только заглушка
if ($member && ft_person_is_private($member->ID)) {
    echo '<div class="content inter"><section class="content__inter _container">' . ft_privacy_render_person_hidden() . '</section></div>';
    Family_Tree_Template_Compat::close_content();
    Family_Tree_Template_Compat::footer();
    return;
}
?>
<style>
.ft-photo-page__body{display:flex;flex-wrap:nowrap;gap:30px;align-items:stretch;margin-bottom:36px;}
.ft-photo-page__media,.ft-photo-page__content{flex:1 1 calc(50% - 30px);min-width:0;background:#fff;padding:20px;border-radius:10px;box-sizing:border-box;}
.ft-photo-page__img-wrap{position:relative;display:inline-block;max-width:100%;line-height:0;}

/* Фото внутри лайтбокс-обёртки */
.ft-photo-page__img-link{display:block;border-radius:10px;overflow:hidden;position:relative;}
.ft-photo-page__img-link .ft-photo-page__img{display:block;width:100%;height:auto;border-radius:10px;box-shadow:0 4px 18px rgba(0,0,0,.12);}

/* Лупа — появляется при наведении на фото */
.ft-photo-page__magnifier{position:absolute;top:12px;right:12px;width:38px;height:38px;background:rgba(255,255,255,.88) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233c4938' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cline x1='16.5' y1='16.5' x2='21' y2='21'/%3E%3C/svg%3E") no-repeat center/20px;border-radius:50%;opacity:0;transition:opacity .25s;pointer-events:none;z-index:3;box-shadow:0 1px 4px rgba(0,0,0,.25);}
.ft-photo-page__img-link:hover .ft-photo-page__magnifier{opacity:1;}

/* Метки «Кто на фото»: скрыты по умолчанию, видны при наведении на фото ИЛИ на список */
.ft-photo-tag{position:absolute;width:28px;height:28px;margin:-14px 0 0 -14px;border-radius:50%;background:#5b7745;color:#fff;font-size:13px;font-weight:700;line-height:24px;text-align:center;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35);text-decoration:none;z-index:2;opacity:0;visibility:hidden;transition:opacity .25s, visibility .25s;}
.ft-photo-tag:hover{transform:scale(1.15);color:#fff;}
.ft-photo-page.is-showing-tags .ft-photo-tag{opacity:1;visibility:visible;}

/* На тач-устройствах метки видны всегда (hover недоступен) */
@media (hover:none) and (pointer:coarse){
	.ft-photo-tag{opacity:1!important;visibility:visible!important;}
}

.ft-photo-page__desc{font-family:'Montserrat',sans-serif;font-size:16px;line-height:24px;color:#3c4938;}
.ft-photo-page__desc h1,.ft-photo-page__desc h2,.ft-photo-page__desc h3,.ft-photo-page__desc h4,.ft-photo-page__desc h5,.ft-photo-page__desc h6{font-family:'Cormorant',serif;color:#3c4938;}
.ft-photo-page__desc p:not(:last-child){padding-bottom:15px;}
.ft-photo-page__meta{margin-top:24px;}
.ft-photo-page__meta-row{display:flex;gap:8px;align-items:baseline;line-height:24px;}
.ft-photo-page__meta-label{font-family:'Montserrat',sans-serif;font-size:16px;font-weight:600;color:#3c4938;white-space:nowrap;}
.ft-photo-page__meta-value{font-family:'Montserrat',sans-serif;font-size:16px;line-height:24px;color:#3c4938;}
.ft-photo-page__people{margin-top:24px;}
.ft-photo-page__people-title{font-family:'Montserrat',sans-serif;font-size:16px;font-weight:600;color:#3c4938;margin:0 0 10px;}
.ft-photo-page__people-list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:8px;}
.ft-photo-page__people-list li{margin:0;}
.ft-photo-page__person{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;background:rgba(250,245,232,.7);border:1px solid #e5dcc3;border-radius:20px;text-decoration:none;color:#3c4938;font-size:14px;font-family:'Montserrat',sans-serif;}
.ft-photo-page__person:hover{background:rgba(250,245,232,1);color:#3c4938;text-decoration:none;}
.ft-photo-page__person-num{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#5b7745;color:#fff;font-size:11px;font-weight:700;flex-shrink:0;}
.ft-photo-page__others{margin:0 0 30px;background:#fff;padding:20px;border-radius:10px;}
.ft-photo-page__others-title{font-family:'Cormorant',serif;font-size:26px;font-weight:600;color:#3c4938;margin:0 0 16px;}
.ft-photo-page__thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;}
.ft-photo-page__thumbs a{position:relative;display:block;border-radius:8px;overflow:hidden;border:2px solid transparent;box-shadow:0 1px 4px rgba(0,0,0,.1);}
.ft-photo-page__thumbs a.is-current{border-color:#5b7745;}
.ft-photo-page__thumbs img{display:block;width:100%;height:85px;object-fit:cover;}
.ft-photo-page__thumb-title{position:absolute;left:0;right:0;bottom:0;padding:4px 6px;font-family:'Montserrat',sans-serif;font-size:11px;line-height:14px;color:#fff;background:rgba(60,73,56,.85);opacity:0;transition:opacity .2s;pointer-events:none;}
.ft-photo-page__thumbs a:hover .ft-photo-page__thumb-title{opacity:1;}
.ft-photo-page__content:empty{display:none;}
.ft-photo-page__nav{display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid #e8e2d2;padding-top:22px;margin-top:22px;}
.ft-photo-page__nav-link{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:rgba(250,245,232,.7);border:1px solid #5b7745;border-radius:24px;color:#3c4938;text-decoration:none;font-size:14px;font-family:'Montserrat',sans-serif;transition:background .2s;}
.ft-photo-page__nav-link:hover{background:#5b7745;color:#fff;text-decoration:none;}
.ft-photo-page__counter{font-family:'Montserrat',sans-serif;font-size:14px;color:#3c4938;white-space:nowrap;}
.ft-photo-page__edit-link{margin:8px 0 22px;font-family:'Montserrat',sans-serif;font-size:14px;}
.ft-photo-page__edit-link a{color:#3c4938;text-decoration:none;border-bottom:1px solid #c9c4b2;}
.ft-photo-page__back{display:block;margin-top:24px;text-align:left;}
.ft-photo-page__back-link{display:inline-block;font-family:'Montserrat',sans-serif;font-size:14px;color:#3c4938;text-decoration:none;border-bottom:1px solid #c9c4b2;}
.ft-photo-page__back-link:hover{color:#5b7745;border-bottom-color:#5b7745;text-decoration:none;}
@media (max-width:820px){
	.ft-photo-page__body{flex-direction:column;}
	.ft-photo-page__media,.ft-photo-page__content{flex:1 1 100%;}
	.ft-photo-page__nav{flex-wrap:wrap;}
}
<?php echo ft_photo_page_css(); ?>
</style>
<div class="content inter">
<section class="content__inter _container">
	<?php if ( function_exists( 'yoast_breadcrumb' ) ) { yoast_breadcrumb( '<div class="breadcrumbs">','</div>' ); } ?>
	<div class="ft-photo-page container">
		<?php if (!$member || !$photo || !$album_name) : ?>
			<p><?php echo esc_html__('Фотография не найдена.', 'genius-family-tree'); ?></p>
			<?php
			// Обратная ссылка: для photoalbums → альбомы; для остальных → папка или каталог раздела
			if ($member && $ft_valid) {
				$ft_section_terms  = ft_archive_section_terms($ft_section);
				$ft_back_url = $is_photoalbums
					? Family_Tree_Plugin::get_instance()->get_member_albums_url($member)
					: Family_Tree_Plugin::get_instance()->get_member_section_folder_url($member, $ft_section, $album_name);
				$ft_back_label = $is_photoalbums
					? __('Вернуться к альбомам', 'genius-family-tree')
					: ($album_name ? $album_name : $ft_section_terms['folder']);
			} elseif ($ft_valid) {
				$ft_section_terms = ft_archive_section_terms($ft_section);
				$ft_back_url = Family_Tree_Plugin::get_instance()->get_archive_page_url($ft_section);
				$ft_back_label = $ft_section_terms['section'];
			} else {
				$ft_back_url = '';
				$ft_back_label = '';
			}
			?>
			<?php if ($ft_back_url) : ?>
				<p><a href="<?php echo esc_url($ft_back_url); ?>">&larr; <?php echo esc_html($ft_back_label); ?></a></p>
			<?php endif; ?>
		<?php else :
			// Хелперы ссылок в зависимости от раздела
			if ($is_photoalbums) {
				$ft_item_url_fn   = function($m, $an, $p) { return Family_Tree_Plugin::get_instance()->get_member_photo_url($m, $an, $p); };
				$ft_folder_back   = Family_Tree_Plugin::get_instance()->get_member_album_url($member, $album_name);
				$ft_folder_back_label = $album_name;
			} else {
				$ft_item_url_fn   = function($m, $an, $p) { return Family_Tree_Plugin::get_instance()->get_member_section_item_url($m, sanitize_key(get_query_var('ft_section')), $an, $p); };
				$ft_folder_back   = Family_Tree_Plugin::get_instance()->get_member_section_folder_url($member, $ft_section, $album_name);
				$ft_folder_back_label = $album_name;
			}

			$ft_photo_full  = wp_get_attachment_image_src($photo->ID, 'full');
			$ft_photo_large = wp_get_attachment_image_src($photo->ID, 'large');
			$ft_photo_url   = $ft_photo_large ? $ft_photo_large[0] : ($ft_photo_full ? $ft_photo_full[0] : '');
			$ft_caption     = get_the_title($photo->ID);
			$ft_description = $photo->post_content;
			// Если описание не заполнено — показываем подпись (Caption) из медиатеки
			if (trim(wp_strip_all_tags((string) $ft_description)) === '') {
				$ft_description = trim((string) get_the_excerpt($photo->ID));
			}

			$ft_photo_index = array_search($photo->ID, $photos, true);
			$ft_prev_photo  = ($ft_photo_index !== false && $ft_photo_index > 0) ? $photos[$ft_photo_index - 1] : 0;
			$ft_next_photo  = ($ft_photo_index !== false && $ft_photo_index < count($photos) - 1) ? $photos[$ft_photo_index + 1] : 0;
			$ft_prev_url    = $ft_prev_photo ? $ft_item_url_fn($member, $album_name, $ft_prev_photo) : '';
			$ft_next_url    = $ft_next_photo ? $ft_item_url_fn($member, $album_name, $ft_next_photo) : '';

			// Отметки «Кто на фото» — только для фотоальбомов (метабокс заполняется для фотоальбомов)
			$ft_people_raw = $is_photoalbums ? get_post_meta($photo->ID, '_ft_photo_people', true) : array();
			$ft_people = array();
			if (is_array($ft_people_raw)) {
				foreach ($ft_people_raw as $ft_t) {
					if (!isset($ft_t['x'], $ft_t['y'], $ft_t['member'])) continue;
					$ft_person = get_post((int) $ft_t['member']);
					if (!$ft_person || 'family_member' !== $ft_person->post_type || 'publish' !== $ft_person->post_status) continue;
					$x = (float) $ft_t['x'];
					$y = (float) $ft_t['y'];
					if ($x < 0 || $x > 100 || $y < 0 || $y > 100) continue;
					$ft_people[] = array(
						'x'    => $x,
						'y'    => $y,
						'id'   => $ft_person->ID,
						'name' => ft_person_is_private($ft_person->ID) ? ft_privacy_hidden_label() : ft_person_display_name($ft_person->ID, $ft_person->post_title),
						'url'  => get_permalink($ft_person->ID),
						'hidden' => ft_person_is_private($ft_person->ID),
					);
				}
			}

			// Дата и место съемки
			$ft_taken_date = trim((string) get_post_meta($photo->ID, '_ft_photo_taken_date', true));
			if ($ft_taken_date !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ft_taken_date, $m)) {
				$ft_taken_date = $m[3] . '.' . $m[2] . '.' . $m[1];
			}
			$ft_taken_location = trim((string) get_post_meta($photo->ID, '_ft_photo_location', true));
			$ft_has_photo_meta = ($ft_taken_date !== '' || $ft_taken_location !== '');
			?>
			<header class="entry-header">
				<h1 class="entry-title"><?php echo esc_html($ft_caption); ?></h1>
				<?php
				$ft_edit_url = get_edit_post_link($photo->ID, '');
				if (is_user_logged_in() && $ft_edit_url && current_user_can('edit_post', $photo->ID)) : ?>
					<div class="ft-photo-page__edit-link">
						<a href="<?php echo esc_url($ft_edit_url); ?>" target="_blank">✎ <?php echo esc_html__('Редактировать фото', 'genius-family-tree'); ?></a>
					</div>
				<?php endif; ?>
			</header>

			<div class="ft-photo-page__body">
				<div class="ft-photo-page__media">
					<?php if ($ft_photo_url) : ?>
						<div class="ft-photo-page__img-wrap">
							<a href="<?php echo esc_url($ft_photo_full ? $ft_photo_full[0] : $ft_photo_url); ?>" class="ft-photo-page__img-link ft-album-photo glightbox" data-gallery="ft-photo-page" data-caption="<?php echo esc_attr($ft_caption); ?>" title="<?php echo esc_attr($ft_caption); ?>">
								<img class="ft-photo-page__img" src="<?php echo esc_url($ft_photo_url); ?>" alt="<?php echo esc_attr($ft_caption); ?>" draggable="false" />
								<span class="ft-photo-page__magnifier" aria-hidden="true"></span>
							</a>
							<?php foreach ($ft_people as $ft_n => $ft_p) : ?>
								<a class="ft-photo-tag" style="left:<?php echo esc_attr($ft_p['x']); ?>%;top:<?php echo esc_attr($ft_p['y']); ?>%;" href="<?php echo esc_url($ft_p['url']); ?>" title="<?php echo esc_attr($ft_p['name']); ?>" aria-label="<?php echo esc_attr($ft_p['name']); ?>"><?php echo intval($ft_n + 1); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="ft-photo-page__content">
					<?php if ($ft_description !== '') : ?>
						<div class="ft-photo-page__desc">
							<?php echo apply_filters('the_content', $ft_description); ?>
						</div>
					<?php endif; ?>
					<?php if ($ft_has_photo_meta) : ?>
						<div class="ft-photo-page__meta">
							<?php if ($ft_taken_date !== '') : ?>
								<div class="ft-photo-page__meta-row">
									<span class="ft-photo-page__meta-label"><?php echo esc_html__('Дата съемки:', 'genius-family-tree'); ?></span>
									<span class="ft-photo-page__meta-value"><?php echo esc_html($ft_taken_date); ?></span>
								</div>
							<?php endif; ?>
							<?php if ($ft_taken_location !== '') : ?>
								<div class="ft-photo-page__meta-row">
									<span class="ft-photo-page__meta-label"><?php echo esc_html__('Место съемки:', 'genius-family-tree'); ?></span>
									<span class="ft-photo-page__meta-value"><?php echo esc_html($ft_taken_location); ?></span>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if (!empty($ft_people)) : ?>
						<div class="ft-photo-page__people">
							<h3 class="ft-photo-page__people-title"><?php echo esc_html__('Кто на фото:', 'genius-family-tree'); ?></h3>
							<ul class="ft-photo-page__people-list">
								<?php foreach ($ft_people as $ft_n => $ft_p) : ?>
									<li>
										<?php if (!empty($ft_p['hidden'])) : ?>
											<span class="ft-photo-page__person ft-photo-page__person--hidden">
												<span class="ft-photo-page__person-num"><?php echo intval($ft_n + 1); ?></span>
												<span><?php echo esc_html($ft_p['name']); ?></span>
											</span>
										<?php else : ?>
											<a class="ft-photo-page__person" href="<?php echo esc_url($ft_p['url']); ?>">
												<span class="ft-photo-page__person-num"><?php echo intval($ft_n + 1); ?></span>
												<span><?php echo esc_html($ft_p['name']); ?></span>
											</a>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					<div class="ft-photo-page__back"><a class="ft-photo-page__back-link" href="<?php echo esc_url($ft_folder_back); ?>">&larr; <?php echo esc_html(sprintf(__('К папке «%s»', 'genius-family-tree'), $ft_folder_back_label)); ?></a></div>
				</div>
			</div>

			<?php if (count($photos) > 1) : ?>
				<div class="ft-photo-page__others">
					<h2 class="ft-photo-page__others-title"><?php echo esc_html($is_photoalbums ? __('Другие фотографии альбома', 'genius-family-tree') : __('Другие фотографии папки', 'genius-family-tree')); ?></h2>
					<div class="ft-photo-page__thumbs">
						<?php foreach ($photos as $thumb_id) :
							$th = wp_get_attachment_image_src($thumb_id, 'medium');
							if (!$th) continue;
							$th_url = $ft_item_url_fn($member, $album_name, $thumb_id);
							$is_cur = $thumb_id === $photo->ID;
							?>
							<a href="<?php echo esc_url($th_url); ?>" class="ft-photo-page__thumb<?php echo $is_cur ? ' is-current' : ''; ?>" aria-label="<?php echo esc_attr(get_the_title($thumb_id)); ?>">
								<img src="<?php echo esc_url($th[0]); ?>" alt="<?php echo esc_attr(get_the_title($thumb_id)); ?>" loading="lazy" />
								<span class="ft-photo-page__thumb-title"><?php echo esc_html(get_the_title($thumb_id)); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
					<nav class="ft-photo-page__nav">
						<?php if ($ft_prev_url) : ?>
							<a class="ft-photo-page__nav-link" href="<?php echo esc_url($ft_prev_url); ?>">&larr; <?php echo esc_html__('Предыдущее фото', 'genius-family-tree'); ?></a>
						<?php else : ?><span></span><?php endif; ?>
						<span class="ft-photo-page__counter"><?php echo esc_html(($ft_photo_index !== false) ? ($ft_photo_index + 1) . ' / ' . count($photos) : ''); ?></span>
						<?php if ($ft_next_url) : ?>
							<a class="ft-photo-page__nav-link" href="<?php echo esc_url($ft_next_url); ?>"><?php echo esc_html__('Следующее фото', 'genius-family-tree'); ?> &rarr;</a>
						<?php else : ?><span></span><?php endif; ?>
					</nav>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
</div>

<?php if ($photo) : ?>
<script>
(function(){
	var page = document.querySelector('.ft-photo-page');
	if(!page) return;
	var media = page.querySelector('.ft-photo-page__media');
	var people = page.querySelector('.ft-photo-page__people');
	function show(){ page.classList.add('is-showing-tags'); }
	function hide(){
		if((media && media.matches(':hover')) || (people && people.matches(':hover'))) return;
		page.classList.remove('is-showing-tags');
	}
	if(media){ media.addEventListener('mouseenter', show); media.addEventListener('mouseleave', hide); }
	if(people){ people.addEventListener('mouseenter', show); people.addEventListener('mouseleave', hide); }
})();
</script>
<?php endif; ?>

<?php
Family_Tree_Template_Compat::close_content();
Family_Tree_Template_Compat::footer();
?>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound