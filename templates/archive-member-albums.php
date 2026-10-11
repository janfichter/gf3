<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Шаблон фотоальбомов конкретной персоны в семейном архиве.
 * Использует переменную $member, установленную в archive_template_include().
 * Оформление управляется табом «Альбомы персоналий» (family_tree_person_albums_settings).
 */
$member = isset($member) && is_object($member) ? $member : null;
if (!$member) {
	// Fallback: пробуем найти персону по slug из query var
	$member_slug = get_query_var('ft_member');
	if ($member_slug) {
		$member = get_page_by_path($member_slug, OBJECT, 'family_member');
	}
}
if (!$member) {
	Family_Tree_Template_Compat::header();
	Family_Tree_Template_Compat::open_content();
	echo '<div class="content inter"><section class="content__inter _container"><p>' . esc_html__('Персоналия не найдена.', 'genius-family-tree') . '</p></section></div>';
	Family_Tree_Template_Compat::close_content();
	Family_Tree_Template_Compat::footer();
	return;
}
// При полностью закрытом древе — заглушка вместо страницы альбомов
if (ft_privacy_visibility() === 'blocked') {
	Family_Tree_Template_Compat::header();
	Family_Tree_Template_Compat::open_content();
	echo '<div class="content inter"><section class="content__inter _container">' . ft_privacy_render_blocked() . '</section></div>';
	Family_Tree_Template_Compat::close_content();
	Family_Tree_Template_Compat::footer();
	return;
}
// Скрытая администратором персона — только заглушка
if (ft_person_is_private($member->ID)) {
	Family_Tree_Template_Compat::header();
	Family_Tree_Template_Compat::open_content();
	echo '<div class="content inter"><section class="content__inter _container">' . ft_privacy_render_person_hidden() . '</section></div>';
	Family_Tree_Template_Compat::close_content();
	Family_Tree_Template_Compat::footer();
	return;
}
Family_Tree_Template_Compat::header();
Family_Tree_Template_Compat::open_content();
$album_name = isset($_GET['album']) ? sanitize_title(wp_unslash($_GET['album'])) : '';
$album_name = $album_name ?: get_query_var('ft_album');
$albums = ft_get_albums($member->ID);
$albums = array_values(array_filter($albums, function($a) { return !empty($a['photos']); }));
$archive_page_id = get_option('family_tree_archive_page_id', 0);
$archive_url = $archive_page_id ? get_permalink($archive_page_id) : '#';
$catalog_url = $archive_url . 'photoalbums/';
// Базовый URL альбомов персоны: /{archive-slug}/photoalbums/{member-slug}/
$member_albums_url = trailingslashit($archive_url . 'photoalbums/') . $member->post_name . '/';
$pa_settings = ft_get_person_albums_settings();
?>
<div class="content inter">
<section class="content__inter _container">
	<?php if ( function_exists( 'yoast_breadcrumb' ) ) { yoast_breadcrumb( '<div class="breadcrumbs">','</div>' ); } ?>
	<div class="family-archive-member">
		<div class="container">
			<style type="text/css"><?php echo ft_person_albums_css($pa_settings); ?></style>
			<?php
			$thumb_id = get_post_thumbnail_id($member->ID);
			$thumb_src = '';
			if ($thumb_id && !ft_hide_field('photo')) {
				$thumb_src_arr = wp_get_attachment_image_src($thumb_id, 'medium');
				$thumb_src = $thumb_src_arr ? $thumb_src_arr[0] : '';
			}
			$birth_year = get_post_meta($member->ID, '_family_member_birth_year', true);
			$death_year = get_post_meta($member->ID, '_family_member_death_year', true);
			$years = '';
			if ((!ft_hide_field('dates') && !ft_hide_field('years')) && $birth_year) { $years = $birth_year . ( $death_year ? ' — ' . $death_year : '' ); }
			?>
			<div class="ft-albums-header">
				<div class="ft-albums-header__inner">
				<?php if ($thumb_src) : ?>
					<img src="<?php echo esc_url($thumb_src); ?>" width="84" height="84" class="ft-albums-header__avatar" alt="<?php echo esc_attr(ft_person_display_name($member->ID, get_the_title($member->ID))); ?>" />
				<?php else : ?>
					<div class="ft-albums-header__avatar ft-albums-header__avatar--empty"><?php echo esc_html(mb_strtoupper(mb_substr(ft_person_display_name($member->ID, get_the_title($member->ID)), 0, 1, 'UTF-8'), 'UTF-8')); ?></div>
				<?php endif; ?>
				<div>
					<h1 class="ft-albums-header-title"><?php echo esc_html(ft_person_display_name($member->ID, get_the_title($member->ID))); ?></h1>
					<?php if ($years) : ?><div class="ft-albums-years"><?php echo esc_html($years); ?></div><?php endif; ?>
					<div class="ft-albums-header__links">
						<a href="<?php echo esc_url(get_permalink($member->ID)); ?>" class="ft-pa-header-btn"><?php echo esc_html__('Открыть страницу персоны', 'genius-family-tree'); ?></a>
						<a href="<?php echo esc_url($catalog_url); ?>" class="ft-pa-header-btn"><?php echo esc_html__('Каталог фотоальбомов', 'genius-family-tree'); ?></a>
					</div>
				</div>
				</div>
			</div>

			<?php if (empty($albums)) : ?>
				<p><?php echo esc_html__('Фотоальбомы ещё не добавлены.', 'genius-family-tree'); ?></p>
			<?php else : ?>
				<nav class="ft-album-switcher">
<?php foreach ($albums as $idx => $a) :
                        $a_slug = sanitize_title($a['name']);
                        $is_active = empty($album_name) ? ($idx === 0) : ($a_slug === $album_name);
                        $tab_cover_id = !empty($a['cover']) ? (int) $a['cover'] : (!empty($a['photos']) ? (int) reset($a['photos']) : 0);
                        $tab_cover_src = $tab_cover_id ? wp_get_attachment_image_src($tab_cover_id, 'thumbnail') : false;
                    ?>
                        <a href="<?php echo esc_url($member_albums_url . $a_slug . '/'); ?>" class="ft-album-tab<?php echo $is_active ? ' is-active' : ''; ?>">
                            <?php if ($tab_cover_src) : ?>
                                <span class="ft-album-tab-cover"><img src="<?php echo esc_url($tab_cover_src[0]); ?>" alt="" /></span>
                            <?php endif; ?>
                            <span class="ft-album-tab-name"><?php echo esc_html($a['name']); ?> (<?php echo count($a['photos']); ?>)</span>
                        </a>
                    <?php endforeach; ?>
				</nav>

				<?php
				// Определяем альбом для отображения
				$display_album = $albums[0];
				if (!empty($album_name)) {
					foreach ($albums as $idx => $a) {
						if (sanitize_title($a['name']) === $album_name) {
							$display_album = $a;
							break;
						}
					}
				}
				$photos = array_values(array_filter(array_map('intval', $display_album['photos']), function($pid) { return wp_attachment_is_image($pid); }));

				// Классы блока фотографий (раскладка/подписи), переиспользуем инфраструктуру family-member-photo-*
				$ft_pa_layout_class = 'ft-layout-' . $pa_settings['layout'];
				$ft_pa_caption_class = intval($pa_settings['captions']) ? 'ft-captions-' . $pa_settings['caption_position'] : '';
				$ft_pa_style_vars = '--ft-album-cols:' . intval($pa_settings['columns']) . ';--ft-album-cols-tablet:' . intval($pa_settings['columns_tablet']) . ';--ft-album-cols-mobile:' . intval($pa_settings['columns_mobile']) . ';--ft-album-gap:' . intval($pa_settings['gap']) . 'px;--ft-album-img-height:' . intval($pa_settings['image_height']) . 'px;--ft-album-image-radius:' . ft_radius_css($pa_settings['image_radius'], '8px') . ';--ft-caption-py:' . intval($pa_settings['caption_padding_y']) . 'px;--ft-caption-px:' . intval($pa_settings['caption_padding_x']) . 'px;';
				?>
				<div class="family-member-photo-album ft-album-view <?php echo esc_attr(trim($ft_pa_layout_class . ' ' . $ft_pa_caption_class)); ?>" style="<?php echo esc_attr($ft_pa_style_vars); ?>">
					<h2 class="ft-albums-album-title"><?php echo esc_html($display_album['name']); ?></h2>
                    <?php if (!empty($display_album['description'])) : ?>
                        <div class="ft-album-description"><?php echo nl2br(esc_html($display_album['description'])); ?></div>
                    <?php endif; ?>
					<?php if (empty($photos)) : ?>
						<p><?php echo esc_html__('В этом альбоме нет фотографий.', 'genius-family-tree'); ?></p>
					<?php else : ?>
						<div class="family-member-photo-grid">
							<?php foreach ($photos as $photo_id) :
								$thumb = wp_get_attachment_image_src($photo_id, 'medium');
								$caption = (string) get_the_title($photo_id);
								if ('' === $caption) { $caption = (string) wp_get_attachment_caption($photo_id); }
								if (!$thumb) continue;
								// Фото всегда открывается на отдельной странице фотографии.
								$ft_photo_page_url = Family_Tree_Plugin::get_instance()->get_member_photo_url($member, $display_album['name'], $photo_id);
							?>
								<figure class="family-member-photo-item">
									<a href="<?php echo esc_url($ft_photo_page_url); ?>" title="<?php echo esc_attr($caption); ?>">
										<img src="<?php echo esc_url($thumb[0]); ?>" alt="<?php echo esc_attr($caption); ?>" loading="lazy" />
									</a>
									<?php if (intval($pa_settings['captions']) && $caption) : ?>
										<figcaption><?php echo esc_html($caption); ?></figcaption>
									<?php endif; ?>
								</figure>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php
					// Переключение альбомов (пред/след)
					$album_index = 0;
					foreach ($albums as $idx => $a) { if ($a['name'] === $display_album['name']) { $album_index = $idx; break; } }
					$prev_idx = $album_index - 1;
					$next_idx = $album_index + 1;
					?>
					<?php if (count($albums) > 1) : ?>
					<nav class="ft-photo-page__nav">
						<?php if ($prev_idx >= 0) : ?>
							<a class="ft-photo-page__nav-link" href="<?php echo esc_url($member_albums_url . sanitize_title($albums[$prev_idx]['name']) . '/'); ?>">&larr; <?php echo esc_html($albums[$prev_idx]['name']); ?></a>
						<?php else : ?><span></span><?php endif; ?>
						<span class="ft-photo-page__counter"><?php echo esc_html(($album_index + 1) . ' / ' . count($albums)); ?></span>
						<?php if ($next_idx < count($albums)) : ?>
							<a class="ft-photo-page__nav-link" href="<?php echo esc_url($member_albums_url . sanitize_title($albums[$next_idx]['name']) . '/'); ?>"><?php echo esc_html($albums[$next_idx]['name']); ?> &rarr;</a>
						<?php else : ?><span></span><?php endif; ?>
					</nav>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
</div>
<?php
Family_Tree_Template_Compat::close_content();
Family_Tree_Template_Compat::footer();
?>