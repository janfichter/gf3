<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Шаблон для отображения отдельной персоны
 */
get_header(); ?>
<script>
// Заглушка для предотвращения ошибок других скриптов
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
<div class="content inter">
<section class="content__inter _container">
	<?php
		if ( function_exists( 'yoast_breadcrumb' ) ) {
			yoast_breadcrumb( '<div class="breadcrumbs">','</div>' );
		}
	?>
<div class="family-member-single">
    <div class="container">
        <?php
        while (have_posts()) : the_post();
            $ft_person_id = get_the_ID();
            // Получаем метаданные
            $ft_first_name = get_post_meta($ft_person_id, '_family_member_first_name', true);
            $ft_middle_name = get_post_meta($ft_person_id, '_family_member_middle_name', true);
            $ft_last_name = get_post_meta($ft_person_id, '_family_member_last_name', true);
            $ft_maiden_name = get_post_meta($ft_person_id, '_family_member_maiden_name', true);
            $ft_gender = get_post_meta($ft_person_id, '_family_member_gender', true);
            
            // --- Получаем поля дат ---
            $ft_birth_day = get_post_meta($ft_person_id, '_family_member_birth_day', true);
            $ft_birth_month = get_post_meta($ft_person_id, '_family_member_birth_month', true);
            $ft_birth_year = get_post_meta($ft_person_id, '_family_member_birth_year', true);
            // --- Получаем новое поле места рождения ---
            $ft_birth_place = get_post_meta($ft_person_id, '_family_member_birth_place', true);
            
            // --- Получаем поля старого/нового стиля для рождения ---
            $ft_birth_old_style = get_post_meta($ft_person_id, '_family_member_birth_old_style', true);
            $ft_birth_new_day = get_post_meta($ft_person_id, '_family_member_birth_new_day', true);
            $ft_birth_new_month = get_post_meta($ft_person_id, '_family_member_birth_new_month', true);
            $ft_birth_new_year = get_post_meta($ft_person_id, '_family_member_birth_new_year', true);

            $ft_death_day = get_post_meta($ft_person_id, '_family_member_death_day', true);
            $ft_death_month = get_post_meta($ft_person_id, '_family_member_death_month', true);
            $ft_death_year = get_post_meta($ft_person_id, '_family_member_death_year', true);
            // --- Получаем новые поля причины и места смерти ---
            $ft_death_cause = get_post_meta($ft_person_id, '_family_member_death_cause', true);
            $ft_death_place = get_post_meta($ft_person_id, '_family_member_death_place', true);
            
            // --- Получаем поля старого/нового стиля для смерти ---
            $ft_death_old_style = get_post_meta($ft_person_id, '_family_member_death_old_style', true);
            $ft_death_new_day = get_post_meta($ft_person_id, '_family_member_death_new_day', true);
            $ft_death_new_month = get_post_meta($ft_person_id, '_family_member_death_new_month', true);
            $ft_death_new_year = get_post_meta($ft_person_id, '_family_member_death_new_year', true);

            // --- Получаем новый флаг "умер" ---
            $ft_is_deceased = get_post_meta($ft_person_id, '_family_member_is_deceased', true);
            // Для обратной совместимости: если старый флаг установлен или есть дата смерти, считаем человека умершим
            $ft_deceased_unknown_compat = get_post_meta($ft_person_id, '_family_member_deceased_unknown', true);
            if ($deceased_unknown_compat || $death_year || $death_month || $death_day) {
                $is_deceased = 1;
            }

            // Формируем полное имя
            $ft_full_name_parts = array();
            if ($ft_first_name) $ft_full_name_parts[] = $ft_first_name;
            if ($ft_middle_name) $ft_full_name_parts[] = $ft_middle_name;
            if ($ft_last_name) $ft_full_name_parts[] = $ft_last_name;
            
            // Формируем имя с девичьей фамилией в скобках
            $ft_full_name_with_maiden = '';
            if (!empty($ft_full_name_parts)) {
                $ft_full_name_with_maiden = implode(' ', $ft_full_name_parts);
                if ($ft_maiden_name && $ft_gender === 'female') {
                    $ft_full_name_with_maiden .= ' (' . $ft_maiden_name . ')';
                }
            } else {
                $ft_full_name_with_maiden = 'Не указано';
            }

            // --- Формируем строку даты рождения ---
            $ft_birth_info = 'Неизвестно';
            if ($ft_birth_year || $ft_birth_month || $ft_birth_day) {
                $ft_birth_parts = array();
                if ($ft_birth_day) {
                    $ft_birth_parts[] = sprintf('%02d', $ft_birth_day);
                }
                if ($ft_birth_month) {
                    $ft_birth_parts[] = sprintf('%02d', $ft_birth_month);
                }
                if ($ft_birth_year) {
                    $ft_birth_parts[] = $ft_birth_year;
                }
                if (count($ft_birth_parts) == 1 && $ft_birth_year) {
                     $ft_birth_info = $ft_birth_year;
                } else {
                    $ft_birth_info = implode('.', $ft_birth_parts);
                }
                
                if ($ft_birth_old_style) {
                    $ft_birth_info .= ' <span class="old-style-label">(' . esc_html__('по ст. ст.', 'genius-family-tree') . ')</span>';
                }
            }

            // --- Формируем строку даты смерти ---
            $ft_death_info = '';
            if ($ft_is_deceased) {
                if ($ft_death_year || $ft_death_month || $ft_death_day) {
                    $ft_death_parts = array();
                    if ($ft_death_day) {
                        $ft_death_parts[] = sprintf('%02d', $ft_death_day);
                    }
                    if ($ft_death_month) {
                        $ft_death_parts[] = sprintf('%02d', $ft_death_month);
                    }
                    if ($ft_death_year) {
                        $ft_death_parts[] = $ft_death_year;
                    }
                    if (count($ft_death_parts) == 1 && $ft_death_year) {
                         $ft_death_info = $ft_death_year;
                    } else {
                        $ft_death_info = implode('.', $ft_death_parts);
                    }
                    
                    if ($ft_death_old_style) {
                        $ft_death_info .= ' <span class="old-style-label">(' . esc_html__('по ст. ст.', 'genius-family-tree') . ')</span>';
                    }
                }
            }
            
            // --- Формируем дату по новому стилю для рождения ---
            $ft_birth_new_info = '';
            if ($ft_birth_old_style && ($ft_birth_new_year || $ft_birth_new_month || $ft_birth_new_day)) {
                $ft_birth_new_parts = array();
                if ($ft_birth_new_day) {
                    $ft_birth_new_parts[] = sprintf('%02d', $ft_birth_new_day);
                }
                if ($ft_birth_new_month) {
                    $ft_birth_new_parts[] = sprintf('%02d', $ft_birth_new_month);
                }
                if ($ft_birth_new_year) {
                    $ft_birth_new_parts[] = $ft_birth_new_year;
                }
                if (count($ft_birth_new_parts) == 1 && $ft_birth_new_year) {
                    $ft_birth_new_info = $ft_birth_new_year;
                } else {
                    $ft_birth_new_info = implode('.', $ft_birth_new_parts);
                }
                $ft_birth_new_info .= ' <span class="new-style-label">(' . esc_html__('по н. ст.', 'genius-family-tree') . ')</span>';
            }
            
            // --- Формируем дату по новому стилю для смерти ---
            $ft_death_new_info = '';
            if ($ft_death_old_style && ($ft_death_new_year || $ft_death_new_month || $ft_death_new_day)) {
                $ft_death_new_parts = array();
                if ($ft_death_new_day) {
                    $ft_death_new_parts[] = sprintf('%02d', $ft_death_new_day);
                }
                if ($ft_death_new_month) {
                    $ft_death_new_parts[] = sprintf('%02d', $ft_death_new_month);
                }
                if ($ft_death_new_year) {
                    $ft_death_new_parts[] = $ft_death_new_year;
                }
                if (count($ft_death_new_parts) == 1 && $ft_death_new_year) {
                    $ft_death_new_info = $ft_death_new_year;
                } else {
                    $ft_death_new_info = implode('.', $ft_death_new_parts);
                }
                $ft_death_new_info .= ' <span class="new-style-label">(' . esc_html__('по н. ст.', 'genius-family-tree') . ')</span>';
            }
            
            // --- Получаем родственные связи ---
            $ft_father_id = get_post_meta($ft_person_id, '_family_member_father', true);
            $ft_mother_id = get_post_meta($ft_person_id, '_family_member_mother', true);
            $ft_spouses = get_post_meta($ft_person_id, '_family_member_spouses', true);
            $ft_children = get_post_meta($ft_person_id, '_family_member_children', true);
            if (!is_array($ft_spouses)) $ft_spouses = array();
            if (!is_array($ft_children)) $ft_children = array();
            
            // --- Получаем братьев и сестер ---
            $ft_siblings = array();
            $ft_should_show_relation_type = false;

            // Сначала получим всех детей отца
            $ft_father_children_ids = array();
            if ($ft_father_id) {
                $ft_father_children_meta = get_post_meta($ft_father_id, '_family_member_children', true);
                if (is_array($ft_father_children_meta)) {
                    $ft_father_children_ids = $ft_father_children_meta;
                }
            }
            // Затем получим всех детей матери
            $ft_mother_children_ids = array();
            if ($ft_mother_id) {
                $ft_mother_children_meta = get_post_meta($ft_mother_id, '_family_member_children', true);
                if (is_array($ft_mother_children_meta)) {
                    $ft_mother_children_ids = $ft_mother_children_meta;
                }
            }

            // Определяем, нужно ли показывать пометки "по отцу/по матери"
            if ($ft_father_id && $ft_mother_id) {
                $ft_should_show_relation_type = true;
            }

            // Теперь формируем массив братьев и сестер с пометками
            $ft_all_sibling_ids = array_unique(array_merge($ft_father_children_ids, $ft_mother_children_ids));

            foreach ($ft_all_sibling_ids as $ft_sibling_id) {
                if ($ft_sibling_id != $ft_person_id) {
                    $ft_sibling_post = get_post($ft_sibling_id);
                    if ($ft_sibling_post && $ft_sibling_post->post_type === 'family_member') {
                        $ft_relation_type = '';

                        if ($ft_should_show_relation_type) {
                            $ft_is_by_father = in_array($ft_sibling_id, $ft_father_children_ids);
                            $ft_is_by_mother = in_array($ft_sibling_id, $ft_mother_children_ids);

                            if ($ft_is_by_father && $ft_is_by_mother) {
                            } elseif ($ft_is_by_father) {
                                $ft_relation_type = 'по отцу';
                            } elseif ($ft_is_by_mother) {
                                $ft_relation_type = 'по матери';
                            }
                        }

                        $ft_siblings[$ft_sibling_id] = array(
                            'post' => $ft_sibling_post,
                            'relation_type' => $ft_relation_type
                        );
                    }
                }
            }
            
            // --- Получаем подпись ---
            $ft_signature_attachment_id = get_post_meta($ft_person_id, '_family_member_signature_id', true);
            $ft_signature_url = '';
            if ($ft_signature_attachment_id) {
                $ft_signature_url = wp_get_attachment_url($ft_signature_attachment_id);
            }
        ?>
        <div class="family-member-header">
            <h1><?php echo esc_html($ft_full_name_with_maiden); ?></h1>
            <?php
            $ft_tree_page_url = Family_Tree_Plugin::find_family_tree_page_url();
            if ($ft_tree_page_url) {
                $ft_center_link = add_query_arg(array(
                    'center_on' => get_the_ID(),
                    'nonce' => wp_create_nonce('family_tree_center_on')
                ), $ft_tree_page_url);
                echo '<a href="' . esc_url($ft_center_link) . '" class="button">' . esc_html__('Показать в древе', 'genius-family-tree') . '</a>';
            }
            ?>
        </div>
		
		<div class="family-member-layout">
			<!-- Основной контент из Gutenberg -->
			<div class="family-member-content-gutenberg">
				<?php the_content(); ?>
			</div>
			
			<!-- Боковая панель с мета-информацией -->
			<div class="family-member-sidebar">
				<div class="family-member-avatar">
					<?php if (has_post_thumbnail()) : ?>
						<?php the_post_thumbnail('medium'); ?>
					<?php else : ?>
						<img src="<?php echo esc_url(FAMILY_TREE_PLUGIN_URL . 'assets/images/silhouette-' . ($ft_gender === 'female' ? 'woman' : 'man') . '.svg'); ?>" 
							 alt="<?php echo esc_attr($ft_full_name_with_maiden); ?>" width="150" height="150" />
					<?php endif; ?>
				</div>
				
				<div class="family-member-info">
					<h2>Краткая информация</h2>
					<table class="family-member-table">
						<!-- Поля пол, имя, отчество, фамилия, девичья фамилия убраны -->
						<tr>
							<th>Дата рождения:</th>
							<td colspan="2">
								<?php echo wp_kses_post($ft_birth_info); ?>
								<?php if ($ft_birth_old_style && $ft_birth_new_info) : ?>
									<br><?php echo wp_kses_post($ft_birth_new_info); ?>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ($ft_birth_place) : ?>
						<tr>
							<th>Место рождения:</th>
							<td colspan="2"><?php echo esc_html($ft_birth_place); ?></td>
						</tr>
						<?php endif; ?>
						<?php 
						// Отображаем информацию о смерти только если человек умер
						if ($ft_is_deceased) : 
						?>
							<tr>
								<th>Дата смерти:</th>
								<td colspan="2">
									<?php 
										if ($ft_death_info) {
											echo wp_kses_post($ft_death_info);
										} else {
											echo esc_html(($ft_gender === 'female') ? 'Умерла' : 'Умер');
										}
									?>
									<?php if ($ft_death_old_style && $ft_death_new_info) : ?>
										<br><?php echo wp_kses_post($ft_death_new_info); ?>
									<?php endif; ?>
								</td>
							</tr>
							<?php if ($ft_death_cause) : ?>
							<tr>
								<th>Причина смерти:</th>
								<td colspan="2"><?php echo esc_html($ft_death_cause); ?></td>
							</tr>
							<?php endif; ?>
							<?php if ($ft_death_place) : ?>
							<tr>
								<th>Место смерти:</th>
								<td colspan="2"><?php echo esc_html($ft_death_place); ?></td>
							</tr>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ($ft_signature_url) : ?>
						<tr>
							<th>Подпись:</th>
							<td colspan="2">
                                <img src="<?php echo esc_url($ft_signature_url); ?>" alt="<?php echo esc_attr__('Подпись', 'genius-family-tree'); ?>" class="signature-image" />
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>
				
                <?php if ($ft_father_id || $ft_mother_id) : ?>
                <div class="family-member-parents">
                    <h2>Родители</h2>
                    <table class="family-member-table">
                        <?php if ($ft_father_id) : 
                            $ft_father = get_post($ft_father_id);
                            if ($ft_father) :
                        ?>
                        <tr>
                            <th>Отец:</th>
                            <td colspan="2">
                                <a href="<?php echo esc_url(get_permalink($ft_father_id)); ?>">
                                    <?php echo esc_html(get_the_title($ft_father_id)); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; endif; ?>
                        <?php if ($ft_mother_id) : 
                            $ft_mother = get_post($ft_mother_id);
                            if ($ft_mother) :
                        ?>
                        <tr>
                            <th>Мать:</th>
                            <td colspan="2">
                                <a href="<?php echo esc_url(get_permalink($ft_mother_id)); ?>">
                                    <?php echo esc_html(get_the_title($ft_mother_id)); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; endif; ?>
                    </table>
                </div>
                <?php endif; ?>
				
				<?php if (!empty($ft_siblings)) : ?>
				<div class="family-member-siblings">
					<h2>Браться и сестры</h2>
					<ul>
						<?php foreach ($ft_siblings as $ft_sibling_id => $ft_sibling_data) : 
							$ft_sibling_post = $ft_sibling_data['post'];
							$ft_relation_type = $ft_sibling_data['relation_type'];
						?>
						<li>
							<a href="<?php echo esc_url(get_permalink($ft_sibling_id)); ?>">
								<?php echo esc_html(get_the_title($ft_sibling_id)); ?>
							</a>
							<?php if (!empty($ft_relation_type)) : ?>
								<span class="sibling-relation-type">(<?php echo esc_html($ft_relation_type); ?>)</span>
							<?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
				
				<?php if (!empty($ft_spouses)) : ?>
				<div class="family-member-spouses">
					<h2>Супруги</h2>
					<ul>
						<?php foreach ($ft_spouses as $ft_spouse_data) : 
							if (isset($ft_spouse_data['id'])) :
								$ft_spouse = get_post($ft_spouse_data['id']);
								if ($ft_spouse) :
									// Формируем информацию о дате брака из новых полей
									$ft_marriage_info = '';
									$ft_married_parts = array();
									if (!empty($ft_spouse_data['married_day'])) {
										$ft_married_parts[] = sprintf('%02d', $ft_spouse_data['married_day']);
									}
									if (!empty($ft_spouse_data['married_month'])) {
										$ft_married_parts[] = sprintf('%02d', $ft_spouse_data['married_month']);
									}
									if (!empty($ft_spouse_data['married_year'])) {
										$ft_married_parts[] = $ft_spouse_data['married_year'];
									}
									if (!empty($ft_married_parts)) {
										$ft_marriage_info = implode('.', $ft_married_parts);
									}
									
									// Формируем информацию о дате развода из новых полей
									$ft_divorce_info = '';
									if (!empty($ft_spouse_data['divorced_unknown'])) {
										$ft_divorce_info = 'дата неизвестна';
									} else {
										$ft_divorced_parts = array();
										if (!empty($ft_spouse_data['divorced_day'])) {
											$ft_divorced_parts[] = sprintf('%02d', $ft_spouse_data['divorced_day']);
										}
										if (!empty($ft_spouse_data['divorced_month'])) {
											$ft_divorced_parts[] = sprintf('%02d', $ft_spouse_data['divorced_month']);
										}
										if (!empty($ft_spouse_data['divorced_year'])) {
											$ft_divorced_parts[] = $ft_spouse_data['divorced_year'];
										}
										if (!empty($ft_divorced_parts)) {
											$ft_divorce_info = implode('.', $ft_divorced_parts);
										}
									}
						?>
						<li>
							<a href="<?php echo esc_url(get_permalink($ft_spouse_data['id'])); ?>">
								<?php echo esc_html(get_the_title($ft_spouse_data['id'])); ?>
							</a>
							<?php if (!empty($ft_marriage_info) || !empty($ft_spouse_data['divorced_unknown']) || !empty($ft_divorce_info)) : ?>
                                <div class="spouse-dates">
									<?php if (!empty($ft_marriage_info)) : ?>
										<div>Брак: <?php echo esc_html($ft_marriage_info); ?></div>
									<?php endif; ?>
									<?php if (!empty($ft_spouse_data['divorced_unknown']) || !empty($ft_divorce_info)) : ?>
										<div>Развод<?php if (!empty($ft_divorce_info) && empty($ft_spouse_data['divorced_unknown'])) : ?>: <?php echo esc_html($ft_divorce_info); ?><?php endif; ?></div>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</li>
						<?php endif; endif; endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
				
				<?php if (!empty($ft_children)) : ?>
				<div class="family-member-children">
					<h2>Дети</h2>
					<ul>
						<?php foreach ($ft_children as $ft_child_id) : 
							$ft_child = get_post($ft_child_id);
							if ($ft_child) :
						?>
						<li>
							<a href="<?php echo esc_url(get_permalink($ft_child_id)); ?>">
								<?php echo esc_html(get_the_title($ft_child_id)); ?>
							</a>
						</li>
						<?php endif; endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
			</div>
		</div>
        <?php endwhile; ?>
    </div>
</div>
</section>
</div>
<?php get_footer(); ?>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
