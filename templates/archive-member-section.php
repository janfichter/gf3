<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Шаблон папок/альбомов конкретной персоны в не-фотоальбомном разделе
 * семейного архива (Документы, Пресса, Письма, кастомные разделы).
 * URL: /{archive-slug}/{section}/{member-slug}/
 */
$ft_section = sanitize_key(get_query_var('ft_section'));
$ft_sections = ft_get_archive_sections();
$ft_valid = (!empty($ft_section) && isset($ft_sections[$ft_section]) && !empty($ft_sections[$ft_section]['enabled']));

if ($ft_valid) {
	$ft_terms = ft_archive_section_terms($ft_section);
	$ft_cfg = $ft_sections[$ft_section];
}

$member = isset($member) && is_object($member) ? $member : null;
if (!$member) {
	$member_slug = get_query_var('ft_member');
	if ($member_slug) {
		$member = get_page_by_path($member_slug, OBJECT, 'family_member');
	}
}
if (!$member || !$ft_valid) {
	Family_Tree_Template_Compat::header();
	Family_Tree_Template_Compat::open_content();
	echo '<div class="content inter"><section class="content__inter _container"><p>' . esc_html__('Запрошенный раздел не найден.', 'genius-family-tree') . '</p></section></div>';
	Family_Tree_Template_Compat::close_content();
	Family_Tree_Template_Compat::footer();
	return;
}
// При полностью закрытом древе — заглушка вместо страницы раздела
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
$albums = ft_get_albums($member->ID, $ft_section);
// Показываем только папки, в которых есть материалы
$albums = array_values(array_filter($albums, function($a) { return !empty($a['photos']); }));
$archive_page_id = get_option('family_tree_archive_page_id', 0);
$archive_url = $archive_page_id ? get_permalink($archive_page_id) : '#';
$catalog_url = Family_Tree_Plugin::get_instance()->get_archive_page_url($ft_section);
$member_folders_url = trailingslashit($catalog_url) . $member->post_name . '/';
$page_title = (!empty($ft_cfg['title'])) ? $ft_cfg['title'] : $ft_terms['name'];
// Единые настройки «Семейный архив → Альбомы персоналий» применяются ко всем разделам.
$ft_pa = ft_get_person_albums_settings();
$ft_pa_layout_class = 'ft-layout-' . $ft_pa['layout'];
$ft_pa_caption_class = intval($ft_pa['captions']) ? 'ft-captions-' . $ft_pa['caption_position'] : '';
$ft_pa_style_vars = '--ft-album-cols:' . intval($ft_pa['columns']) . ';--ft-album-cols-tablet:' . intval($ft_pa['columns_tablet']) . ';--ft-album-cols-mobile:' . intval($ft_pa['columns_mobile']) . ';--ft-album-gap:' . intval($ft_pa['gap']) . 'px;--ft-album-img-height:' . intval($ft_pa['image_height']) . 'px;--ft-album-image-radius:' . ft_radius_css($ft_pa['image_radius'], '8px') . ';--ft-caption-py:' . intval($ft_pa['caption_padding_y']) . 'px;--ft-caption-px:' . intval($ft_pa['caption_padding_x']) . 'px;';
?>
<div class="content inter">
<section class="content__inter _container">
	<?php if ( function_exists( 'yoast_breadcrumb' ) ) { yoast_breadcrumb( '<div class="breadcrumbs">','</div>' ); } ?>
	<div class="family-archive-member">
		<div class="container">
			<style type="text/css"><?php echo ft_person_albums_css($ft_pa); ?></style>
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
						<a href="<?php echo esc_url($catalog_url); ?>" class="ft-pa-header-btn"><?php echo esc_html(sprintf(__('Каталог «%s»', 'genius-family-tree'), $page_title)); ?></a>
					</div>
				</div>
				</div>
			</div>

			<?php if (empty($albums)) : ?>
				<p><?php echo esc_html($ft_terms['no_folders_h2']); ?></p>
			<?php else : ?>
				<nav class="ft-album-switcher">
<?php foreach ($albums as $idx => $a) :
                        $a_slug = sanitize_title($a['name']);
                        $is_active = empty($album_name) ? ($idx === 0) : ($a_slug === $album_name);
                        $tab_cover_id = !empty($a['cover']) ? (int) $a['cover'] : (!empty($a['photos']) ? (int) reset($a['photos']) : 0);
                        $tab_cover_src = $tab_cover_id && wp_attachment_is_image($tab_cover_id) ? wp_get_attachment_image_src($tab_cover_id, 'thumbnail') : false;
                    ?>
                        <a href="<?php echo esc_url($member_folders_url . $a_slug . '/'); ?>" class="ft-album-tab<?php echo $is_active ? ' is-active' : ''; ?>">
                            <?php if ($tab_cover_src) : ?>
                                <span class="ft-album-tab-cover"><img src="<?php echo esc_url($tab_cover_src[0]); ?>" alt="" /></span>
                            <?php endif; ?>
                            <span class="ft-album-tab-name"><?php echo esc_html($a['name']); ?> (<?php echo count($a['photos']); ?>)</span>
                        </a>
                    <?php endforeach; ?>
				</nav>

				<?php
				// Определяем папку для отображения
				$display_album = $albums[0];
				if (!empty($album_name)) {
					foreach ($albums as $idx => $a) {
						if (sanitize_title($a['name']) === $album_name) {
							$display_album = $a;
							break;
						}
					}
				}
				$items = array_values(array_map('intval', (array) $display_album['photos']));
				?>
				<div class="family-member-photo-album ft-album-view <?php echo esc_attr(trim($ft_pa_layout_class . ' ' . $ft_pa_caption_class)); ?>" style="<?php echo esc_attr($ft_pa_style_vars); ?>">
					<h2 class="ft-albums-album-title"><?php echo esc_html($display_album['name']); ?></h2>
                    <?php if (!empty($display_album['description'])) : ?>
                        <div class="ft-album-description"><?php echo nl2br(esc_html($display_album['description'])); ?></div>
                    <?php endif; ?>
					<?php if (empty($items)) : ?>
						<p><?php echo esc_html($ft_terms['no_folders_h2']); ?></p>
					<?php else : ?>
					<div class="family-member-photo-grid">
						<?php foreach ($items as $item_id) :
							$item_post = get_post($item_id);
							if (!$item_post || 'attachment' !== $item_post->post_type) continue;
							$item_title = get_the_title($item_id);
							if ('' === $item_title) { $item_title = (string) wp_get_attachment_caption($item_id); }
							if ('' === $item_title) { $item_title = basename(get_attached_file($item_id)); }
							if (wp_attachment_is_image($item_id)) {
								$thumb = wp_get_attachment_image_src($item_id, 'medium');
								if (!$thumb) continue;
								$item_page_url = Family_Tree_Plugin::get_instance()->get_member_section_item_url($member, $ft_section, $display_album['name'], $item_id);
							?>
								<figure class="family-member-photo-item">
									<a href="<?php echo esc_url($item_page_url); ?>" title="<?php echo esc_attr($item_title); ?>">
										<img src="<?php echo esc_url($thumb[0]); ?>" alt="<?php echo esc_attr($item_title); ?>" loading="lazy" />
									</a>
									<?php if (intval($ft_pa['captions']) && $item_title) : ?>
										<figcaption><?php echo esc_html($item_title); ?></figcaption>
									<?php endif; ?>
								</figure>
							<?php } else { $file_url = wp_get_attachment_url($item_id); ?>
								<a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener" class="ft-album-file-card" title="<?php echo esc_attr($item_title); ?>">
									<span class="ft-album-file-card__icon" aria-hidden="true">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
									</span>
									<span class="ft-album-file-card__name"><?php echo esc_html($item_title); ?></span>
									<span class="ft-album-file-card__hint"><?php echo esc_html__('Открыть файл', 'genius-family-tree'); ?></span>
								</a>
							<?php } ?>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>

					<?php
					$album_index = 0;
					foreach ($albums as $idx => $a) { if ($a['name'] === $display_album['name']) { $album_index = $idx; break; } }
					$prev_idx = $album_index - 1;
					$next_idx = $album_index + 1;
					?>
					<?php if (count($albums) > 1) : ?>
					<nav class="ft-photo-page__nav">
						<?php if ($prev_idx >= 0) : ?>
							<a class="ft-photo-page__nav-link" href="<?php echo esc_url($member_folders_url . sanitize_title($albums[$prev_idx]['name']) . '/'); ?>">&larr; <?php echo esc_html($albums[$prev_idx]['name']); ?></a>
						<?php else : ?><span></span><?php endif; ?>
						<span class="ft-photo-page__counter"><?php echo esc_html(($album_index + 1) . ' / ' . count($albums)); ?></span>
						<?php if ($next_idx < count($albums)) : ?>
							<a class="ft-photo-page__nav-link" href="<?php echo esc_url($member_folders_url . sanitize_title($albums[$next_idx]['name']) . '/'); ?>"><?php echo esc_html($albums[$next_idx]['name']); ?> &rarr;</a>
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
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound