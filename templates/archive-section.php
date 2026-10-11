<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Единый шаблон каталога персоналий семейного архива по алфавиту.
 * Работает для ВСЕХ разделов: фотоальбомы, документы, пресса, письма и кастомные.
 * URL: /{archive-slug}/{section}/
 */
$ft_section = sanitize_key(get_query_var('ft_section'));
$ft_sections = ft_get_archive_sections();
$ft_valid = (!empty($ft_section) && isset($ft_sections[$ft_section]) && !empty($ft_sections[$ft_section]['enabled']));

if ($ft_valid) {
	$ft_cfg = $ft_sections[$ft_section];
	$ft_terms = ft_archive_section_terms($ft_section);
	$ft_page_title = (!empty($ft_cfg['title'])) ? $ft_cfg['title'] : $ft_terms['name'];
	$ft_stats = ft_archive_section_stats($ft_section);
	$ft_rows = $ft_stats['rows'];
}

Family_Tree_Template_Compat::header();
Family_Tree_Template_Compat::open_content();
// При полностью закрытом древе — заглушка вместо каталога архива
if (ft_privacy_visibility() === 'blocked') {
    echo '<div class="content inter"><section class="content__inter _container">' . ft_privacy_render_blocked() . '</section></div>';
    Family_Tree_Template_Compat::close_content();
    Family_Tree_Template_Compat::footer();
    return;
}
$ft_ac_settings = ft_get_archive_catalog_settings();
$ft_ac_css = ft_archive_catalog_css($ft_ac_settings);
$ft_ac_google = Family_Tree_Plugin::get_google_fonts();
$ft_ac_fonts_to_load = array();
foreach (array('letter_font_family', 'litera_font_family', 'name_font_family', 'count_font_family') as $ft_ac_font_key) {
	$ft_ac_font_val = !empty($ft_ac_settings[$ft_ac_font_key]) ? $ft_ac_settings[$ft_ac_font_key] : '';
	if (!empty($ft_ac_font_val) && isset($ft_ac_google[$ft_ac_font_val])) {
		$ft_ac_fonts_to_load[] = $ft_ac_font_val;
	}
}
$ft_ac_fonts_to_load = array_unique($ft_ac_fonts_to_load);
foreach ($ft_ac_fonts_to_load as $ft_ac_google_font) {
	$ft_ac_weights = Family_Tree_Plugin::get_font_weights($ft_ac_google_font);
	$ft_ac_weights_str = implode(';', array_map('intval', $ft_ac_weights));
	echo '<link rel="stylesheet" href="' . esc_url('https://fonts.googleapis.com/css2?family=' . rawurlencode($ft_ac_google_font) . ':wght@' . $ft_ac_weights_str . '&display=swap') . '" />' . "\n";
}
$ft_ac_base_css = '.family-archive-catalog{background:var(--ft-bg);min-height:100vh;}.ft-archive-letters{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-bottom:40px;padding:24px;background:var(--ft-surface);border-radius:var(--ft-radius);border:1px solid var(--ft-border);box-shadow:var(--ft-shadow);}.ft-archive-letters .ft-letter{display:flex;align-items:center;justify-content:center;padding:10px 14px;text-decoration:none;font-weight:600;border-radius:6px;transition:all .2s ease;min-width:44px;text-align:center;font-size:14px;background:var(--ft-accent);color:#fff;}.ft-archive-letters .ft-letter:hover{background:var(--ft-accent-dark);color:#fff;transform:translateY(-1px);box-shadow:var(--ft-shadow-hover);}.ft-archive-letter-block{margin-bottom:40px;padding:24px;border:1px solid var(--ft-border);border-radius:var(--ft-radius);background:var(--ft-surface);box-shadow:var(--ft-shadow);}.ft-archive-letter-block h2{color:var(--ft-text);border-bottom:2px solid #f0f0f1;padding-bottom:12px;margin:0 0 20px;font-size:1.3em;font-weight:600;}.ft-archive-person-card{transition:box-shadow .2s,border-color .2s;}.ft-archive-person-card .ft-avatar{flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;overflow:hidden;}.ft-archive-person-card .ft-avatar img{width:100%;height:100%;object-fit:cover;display:block;}.ft-archive-person-card .ft-avatar .ft-avatar-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#999;font-size:18px;background:#f0f0f1;}.ft-archive-person-card .ft-card-body{min-width:0;}.ft-archive-person-card .ft-card-name{font-weight:600;}';
echo '<style>' . $ft_ac_base_css . $ft_ac_css . '</style>' . "\n";
?>
<div class="content inter">
<section class="content__inter _container">
	<?php if ( function_exists( 'yoast_breadcrumb' ) ) { yoast_breadcrumb( '<div class="breadcrumbs">','</div>' ); } ?>
	<div class="family-archive-catalog">
		<div class="container">
			<h1 class="entry-title"><?php echo esc_html($ft_valid ? $ft_page_title : __('Раздел не найден', 'genius-family-tree')); ?></h1>
			<?php
			$archive_page_id = get_option('family_tree_archive_page_id', 0);
			$archive_url = $archive_page_id ? get_permalink($archive_page_id) : '#';
			?>
			<nav class="ft-photo-page__nav" style="margin:0 0 24px;border-top:0;padding-top:0;">
				<a class="ft-photo-page__nav-link" href="<?php echo esc_url($archive_url); ?>">&larr; <?php echo esc_html__('Вернуться в семейный архив', 'genius-family-tree'); ?></a>
				<span></span>
				<span></span>
			</nav>

			<?php if (!$ft_valid) : ?>
				<p><?php echo esc_html__('Такого раздела не существует.', 'genius-family-tree'); ?></p>
			<?php elseif (empty($ft_rows)) : ?>
				<p><?php echo esc_html(sprintf(__('В разделе «%s» пока нет материалов.', 'genius-family-tree'), $ft_page_title)); ?></p>
			<?php else :
				$filter = isset($_GET['letter']) ? sanitize_text_field(wp_unslash($_GET['letter'])) : '';
				$members = get_posts(array(
					'post_type'      => 'family_member',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'nopaging'       => true,
					'no_found_rows'  => true,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'include'        => array_keys($ft_rows),
				));

				$grouped = array();
				foreach ($members as $member) {
					$albums = array_values(array_filter(ft_get_albums($member->ID, $ft_section), function($a) { return !empty($a['photos']); }));
					if (empty($albums)) continue;
					$last_name = get_post_meta($member->ID, '_family_member_last_name', true);
					$maiden_name = get_post_meta($member->ID, '_family_member_maiden_name', true);
					$first_name = get_post_meta($member->ID, '_family_member_first_name', true);
					$surname = !empty($last_name) ? $last_name : (!empty($maiden_name) ? $maiden_name : '');
					$letter = mb_strtoupper(mb_substr(mb_strtolower(trim($surname ?: $first_name)), 0, 1, 'UTF-8'), 'UTF-8');
					if (!$letter || !preg_match('/^[A-ZА-ЯЁ]$/u', $letter)) {
						$letter = '#';
					}
					$files = 0;
					foreach ($albums as $a) { $files += count((array) $a['photos']); }
					$grouped[$letter][] = array(
						'id'          => $member->ID,
						'title'       => $member->post_title,
						'url'         => trailingslashit(Family_Tree_Plugin::get_instance()->get_archive_page_url($ft_section)) . $member->post_name . '/',
						'album_count' => count($albums),
						'total_files' => $files,
					);
				}
				ksort($grouped);
				if (!empty($filter)) $filter = mb_strtoupper($filter, 'UTF-8');
				?>
				<div class="ft-archive-letters">
					<a href="<?php echo esc_url(remove_query_arg('letter')); ?>" class="ft-letter<?php echo empty($filter) ? ' active' : ''; ?>"><?php echo esc_html__('Все', 'genius-family-tree'); ?></a>
					<?php foreach ($grouped as $ltr => $m) : ?>
						<a href="<?php echo esc_url(add_query_arg('letter', $ltr)); ?>" class="ft-letter<?php echo $filter === $ltr ? ' active' : ''; ?>"><?php echo esc_html($ltr); ?></a>
					<?php endforeach; ?>
				</div>
				<?php foreach ($grouped as $letter => $members_list) : ?>
					<?php if (!empty($filter) && $filter !== $letter) continue; ?>
					<div class="ft-archive-letter-block">
						<h2><?php echo esc_html($letter); ?></h2>
						<div class="ft-archive-persons" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px;">
							<?php foreach ($members_list as $pm) :
								$ft_pm_private = ft_person_is_private($pm['id']);
								$ft_pm_title = ft_person_display_name($pm['id'], $pm['title']);
								$ft_thumb = '';
								if (!$ft_pm_private) {
									$thumb_id = get_post_thumbnail_id($pm['id']);
									if ($thumb_id && !ft_hide_field('photo')) {
										$ft_thumb_arr = wp_get_attachment_image_src($thumb_id, 'thumbnail');
										$ft_thumb = $ft_thumb_arr ? $ft_thumb_arr[0] : '';
									}
								}
								?>
								<?php if ($ft_pm_private) : ?>
									<div class="ft-archive-person-card" style="display:flex;align-items:center;gap:12px;padding:14px;background:#fff;border:1px solid #ddd;border-radius:8px;color:inherit;opacity:.65;">
										<span class="ft-avatar">
											<div class="ft-avatar-fallback"><span>&#128100;</span></div>
										</span>
										<div class="ft-card-body">
											<div class="ft-card-name" style="font-weight:600;color:#1d2327;font-size:15px;"><?php echo esc_html(ft_privacy_hidden_label()); ?></div>
										</div>
									</div>
								<?php else : ?>
									<a href="<?php echo esc_url($pm['url']); ?>" class="ft-archive-person-card" style="display:flex;align-items:center;gap:12px;padding:14px;background:#fff;border:1px solid #ddd;border-radius:8px;text-decoration:none;color:inherit;transition:box-shadow .2s,border-color .2s;">
										<?php if ($ft_thumb) : ?>
											<span class="ft-avatar"><img src="<?php echo esc_url($ft_thumb); ?>" alt="<?php echo esc_attr($ft_pm_title); ?>" loading="lazy" /></span>
										<?php else : ?>
											<span class="ft-avatar"><div class="ft-avatar-fallback"><span><?php echo esc_html(mb_strtoupper(mb_substr($ft_pm_title, 0, 1, 'UTF-8'), 'UTF-8')); ?></span></div></span>
										<?php endif; ?>
										<div class="ft-card-body">
											<div class="ft-card-name" style="font-weight:600;color:#1d2327;font-size:15px;"><?php echo esc_html($ft_pm_title); ?></div>
											<div class="ft-card-count" style="font-size:12px;color:#646970;margin-top:2px;">
												<?php echo esc_html(ft_archive_section_counts_label($ft_section, $pm['album_count'], $pm['total_files'])); ?>
											</div>
										</div>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
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