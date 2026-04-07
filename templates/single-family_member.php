<?php
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
            $person_id = get_the_ID();
            // Получаем метаданные
            $first_name = get_post_meta($person_id, '_family_member_first_name', true);
            $middle_name = get_post_meta($person_id, '_family_member_middle_name', true);
            $last_name = get_post_meta($person_id, '_family_member_last_name', true);
            $maiden_name = get_post_meta($person_id, '_family_member_maiden_name', true);
            $gender = get_post_meta($person_id, '_family_member_gender', true);
            
            // --- Получаем поля дат ---
            $birth_day = get_post_meta($person_id, '_family_member_birth_day', true);
            $birth_month = get_post_meta($person_id, '_family_member_birth_month', true);
            $birth_year = get_post_meta($person_id, '_family_member_birth_year', true);
            // --- Получаем новое поле места рождения ---
            $birth_place = get_post_meta($person_id, '_family_member_birth_place', true);
            
            // --- Получаем поля старого/нового стиля для рождения ---
            $birth_old_style = get_post_meta($person_id, '_family_member_birth_old_style', true);
            $birth_new_day = get_post_meta($person_id, '_family_member_birth_new_day', true);
            $birth_new_month = get_post_meta($person_id, '_family_member_birth_new_month', true);
            $birth_new_year = get_post_meta($person_id, '_family_member_birth_new_year', true);

            $death_day = get_post_meta($person_id, '_family_member_death_day', true);
            $death_month = get_post_meta($person_id, '_family_member_death_month', true);
            $death_year = get_post_meta($person_id, '_family_member_death_year', true);
            // --- Получаем новые поля причины и места смерти ---
            $death_cause = get_post_meta($person_id, '_family_member_death_cause', true);
            $death_place = get_post_meta($person_id, '_family_member_death_place', true);
            
            // --- Получаем поля старого/нового стиля для смерти ---
            $death_old_style = get_post_meta($person_id, '_family_member_death_old_style', true);
            $death_new_day = get_post_meta($person_id, '_family_member_death_new_day', true);
            $death_new_month = get_post_meta($person_id, '_family_member_death_new_month', true);
            $death_new_year = get_post_meta($person_id, '_family_member_death_new_year', true);

            // --- Получаем новый флаг "умер" ---
            $is_deceased = get_post_meta($person_id, '_family_member_is_deceased', true);
            // Для обратной совместимости: если старый флаг установлен или есть дата смерти, считаем человека умершим
            $deceased_unknown_compat = get_post_meta($person_id, '_family_member_deceased_unknown', true);
            if ($deceased_unknown_compat || $death_year || $death_month || $death_day) {
                $is_deceased = 1;
            }

            // Формируем полное имя
            $full_name_parts = array();
            if ($first_name) $full_name_parts[] = $first_name;
            if ($middle_name) $full_name_parts[] = $middle_name;
            if ($last_name) $full_name_parts[] = $last_name;
			
			// Формируем имя с девичьей фамилией в скобках
			$full_name_with_maiden = '';
			if (!empty($full_name_parts)) {
				$full_name_with_maiden = implode(' ', $full_name_parts);
				if ($maiden_name && $gender === 'female') {
					$full_name_with_maiden .= ' (' . $maiden_name . ')';
				}
			} else {
				$full_name_with_maiden = 'Не указано';
			}

            // --- Формируем строку даты рождения ---
            $birth_info = 'Неизвестно';
            if ($birth_year || $birth_month || $birth_day) {
                $birth_parts = array();
                if ($birth_day) {
                    $birth_parts[] = sprintf('%02d', $birth_day); // Добавляем ведущий ноль
                }
                if ($birth_month) {
                    $birth_parts[] = sprintf('%02d', $birth_month); // Добавляем ведущий ноль
                }
                if ($birth_year) {
                    $birth_parts[] = $birth_year;
                }
                // Если есть только год, не добавляем точки
                if (count($birth_parts) == 1 && $birth_year) {
                     $birth_info = $birth_year;
                } else {
                    $birth_info = implode('.', $birth_parts);
                }
                
                // Добавляем подпись "по старому стилю" если установлен флаг
                if ($birth_old_style) {
                    $birth_info .= ' <span style="font-size: 0.9em;">(по ст. ст.)</span>';
                }
            }

            // --- Формируем строку даты смерти ---
            $death_info = '';
            if ($is_deceased) {
                if ($death_year || $death_month || $death_day) {
                    $death_parts = array();
                    if ($death_day) {
                        $death_parts[] = sprintf('%02d', $death_day); // Добавляем ведущий ноль
                    }
                    if ($death_month) {
                        $death_parts[] = sprintf('%02d', $death_month); // Добавляем ведущий ноль
                    }
                    if ($death_year) {
                        $death_parts[] = $death_year;
                    }
                    // Если есть только год, не добавляем точки
                    if (count($death_parts) == 1 && $death_year) {
                         $death_info = $death_year;
                    } else {
                        $death_info = implode('.', $death_parts);
                    }
                    
                    // Добавляем подпись "по старому стилю" если установлен флаг
                    if ($death_old_style) {
                        $death_info .= ' <span style="font-size: 0.9em;">(по ст. ст.)</span>';
                    }
                } else {
                    // Если человек умер, но дата не указана, показываем только "Умер(ла)"
                    // $death_info останется пустым, статус уже установлен
                }
            }
			
			// --- Формируем дату по новому стилю для рождения ---
			$birth_new_info = '';
			if ($birth_old_style && ($birth_new_year || $birth_new_month || $birth_new_day)) {
				$birth_new_parts = array();
				if ($birth_new_day) {
					$birth_new_parts[] = sprintf('%02d', $birth_new_day);
				}
				if ($birth_new_month) {
					$birth_new_parts[] = sprintf('%02d', $birth_new_month);
				}
				if ($birth_new_year) {
					$birth_new_parts[] = $birth_new_year;
				}
				if (count($birth_new_parts) == 1 && $birth_new_year) {
					$birth_new_info = $birth_new_year;
				} else {
					$birth_new_info = implode('.', $birth_new_parts);
				}
				$birth_new_info .= ' <span style="font-size: 0.9em;">(по н. ст.)</span>';
			}
			
			// --- Формируем дату по новому стилю для смерти ---
			$death_new_info = '';
			if ($death_old_style && ($death_new_year || $death_new_month || $death_new_day)) {
				$death_new_parts = array();
				if ($death_new_day) {
					$death_new_parts[] = sprintf('%02d', $death_new_day);
				}
				if ($death_new_month) {
					$death_new_parts[] = sprintf('%02d', $death_new_month);
				}
				if ($death_new_year) {
					$death_new_parts[] = $death_new_year;
				}
				if (count($death_new_parts) == 1 && $death_new_year) {
					$death_new_info = $death_new_year;
				} else {
					$death_new_info = implode('.', $death_new_parts);
				}
				$death_new_info .= ' <span style="font-size: 0.9em;">(по н. ст.)</span>';
			}
			
			// --- Получаем родственные связи ---
            $father_id = get_post_meta($person_id, '_family_member_father', true);
            $mother_id = get_post_meta($person_id, '_family_member_mother', true);
            $spouses = get_post_meta($person_id, '_family_member_spouses', true);
            $children = get_post_meta($person_id, '_family_member_children', true);
            if (!is_array($spouses)) $spouses = array();
            if (!is_array($children)) $children = array();
			
			// --- Получаем братьев и сестер ---
			$siblings = array();
			$should_show_relation_type = false; // Флаг: показывать ли пометки "по отцу/по матери"?

			// Сначала получим всех детей отца
			$father_children_ids = array();
			if ($father_id) {
				$father_children_meta = get_post_meta($father_id, '_family_member_children', true);
				if (is_array($father_children_meta)) {
					$father_children_ids = $father_children_meta;
				}
			}
			// Затем получим всех детей матери
			$mother_children_ids = array();
			if ($mother_id) {
				$mother_children_meta = get_post_meta($mother_id, '_family_member_children', true);
				if (is_array($mother_children_meta)) {
					$mother_children_ids = $mother_children_meta;
				}
			}

			// Определяем, нужно ли показывать пометки "по отцу/по матери"
			// Пометки показываем только если у текущей персоны указаны ОБА родителя
			if ($father_id && $mother_id) {
				$should_show_relation_type = true;
			}

			// Теперь формируем массив братьев и сестер с пометками
			$all_sibling_ids = array_unique(array_merge($father_children_ids, $mother_children_ids));

			foreach ($all_sibling_ids as $sibling_id) {
				// Исключаем текущую персону
				if ($sibling_id != $person_id) {
					// Проверяем, что запись существует и это family_member
					$sibling_post = get_post($sibling_id);
					if ($sibling_post && $sibling_post->post_type === 'family_member') {
						$relation_type = ''; // По умолчанию без пометки

						if ($should_show_relation_type) {
							// Логика только если у персоны оба родителя
							$is_by_father = in_array($sibling_id, $father_children_ids);
							$is_by_mother = in_array($sibling_id, $mother_children_ids);

							if ($is_by_father && $is_by_mother) {
								// Полный брат/сестра (по обоим родителям) - пометка не нужна
							} elseif ($is_by_father) {
								$relation_type = 'по отцу';
							} elseif ($is_by_mother) {
								$relation_type = 'по матери';
							}
						}
						// Если should_show_relation_type == false, $relation_type останется пустым

						$siblings[$sibling_id] = array(
							'post' => $sibling_post,
							'relation_type' => $relation_type
						);
					}
				}
			}
			
			// --- Получаем подпись ---
			$signature_attachment_id = get_post_meta($person_id, '_family_member_signature_id', true);
			$signature_url = '';
			if ($signature_attachment_id) {
				$signature_url = wp_get_attachment_url($signature_attachment_id);
			}
        ?>
        <div class="family-member-header">
            <h1><?php echo esc_html($full_name_with_maiden); ?></h1>
            <?php
            // Автоматически находим страницу с шорткодом [family_tree]
            $tree_page_url = Family_Tree_Plugin::find_family_tree_page_url();
            if ($tree_page_url) {
                $center_link = add_query_arg('center_on', get_the_ID(), $tree_page_url);
                echo '<a href="' . esc_url($center_link) . '" class="button">' . esc_html__('Показать в древе', 'genius-family-tree') . '</a>';
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
						<img src="<?php echo esc_url(FAMILY_TREE_PLUGIN_URL . 'assets/images/silhouette-' . ($gender === 'female' ? 'woman' : 'man') . '.svg'); ?>" 
							 alt="<?php echo esc_attr($full_name_with_maiden); ?>" width="150" height="150" />
					<?php endif; ?>
				</div>
				
				<div class="family-member-info">
					<h2>Краткая информация</h2>
					<table class="family-member-table">
						<!-- Поля пол, имя, отчество, фамилия, девичья фамилия убраны -->
						<tr>
							<th>Дата рождения:</th>
							<td colspan="2">
								<?php echo wp_kses_post($birth_info); ?>
								<?php if ($birth_old_style && $birth_new_info) : ?>
									<br><?php echo wp_kses_post($birth_new_info); ?>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ($birth_place) : ?>
						<tr>
							<th>Место рождения:</th>
							<td colspan="2"><?php echo esc_html($birth_place); ?></td>
						</tr>
						<?php endif; ?>
						<?php 
						// Отображаем информацию о смерти только если человек умер
						if ($is_deceased) : 
						?>
							<tr>
								<th>Дата смерти:</th>
								<td colspan="2">
									<?php 
										if ($death_info) {
											echo wp_kses_post($death_info);
										} else {
											echo esc_html(($gender === 'female') ? 'Умерла' : 'Умер');
										}
									?>
									<?php if ($death_old_style && $death_new_info) : ?>
										<br><?php echo wp_kses_post($death_new_info); ?>
									<?php endif; ?>
								</td>
							</tr>
							<?php if ($death_cause) : ?>
							<tr>
								<th>Причина смерти:</th>
								<td colspan="2"><?php echo esc_html($death_cause); ?></td>
							</tr>
							<?php endif; ?>
							<?php if ($death_place) : ?>
							<tr>
								<th>Место смерти:</th>
								<td colspan="2"><?php echo esc_html($death_place); ?></td>
							</tr>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ($signature_url) : ?>
						<tr>
							<th>Подпись:</th>
							<td colspan="2">
								<img src="<?php echo esc_url($signature_url); ?>" alt="<?php echo esc_attr__('Подпись', 'genius-family-tree'); ?>" style="max-width: 100%; height: auto; border: 1px solid #ddd; padding: 5px;" />
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>
				
                <?php if ($father_id || $mother_id) : ?>
                <div class="family-member-parents">
                    <h2>Родители</h2>
                    <table class="family-member-table">
                        <?php if ($father_id) : 
                            $father = get_post($father_id);
                            if ($father) :
                        ?>
                        <tr>
                            <th>Отец:</th>
                            <td colspan="2">
                                <a href="<?php echo esc_url(get_permalink($father_id)); ?>">
                                    <?php echo esc_html(get_the_title($father_id)); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; endif; ?>
                        <?php if ($mother_id) : 
                            $mother = get_post($mother_id);
                            if ($mother) :
                        ?>
                        <tr>
                            <th>Мать:</th>
                            <td colspan="2">
                                <a href="<?php echo esc_url(get_permalink($mother_id)); ?>">
                                    <?php echo esc_html(get_the_title($mother_id)); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; endif; ?>
                    </table>
                </div>
                <?php endif; ?>
				
				<?php if (!empty($siblings)) : ?>
				<div class="family-member-siblings">
					<h2>Браться и сестры</h2>
					<ul>
						<?php foreach ($siblings as $sibling_id => $sibling_data) : 
							$sibling_post = $sibling_data['post'];
							$relation_type = $sibling_data['relation_type'];
						?>
						<li>
							<a href="<?php echo esc_url(get_permalink($sibling_id)); ?>">
								<?php echo esc_html(get_the_title($sibling_id)); ?>
							</a>
							<?php if (!empty($relation_type)) : ?>
								<span class="sibling-relation-type">(<?php echo esc_html($relation_type); ?>)</span>
							<?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
				
				<?php if (!empty($spouses)) : ?>
				<div class="family-member-spouses">
					<h2>Супруги</h2>
					<ul>
						<?php foreach ($spouses as $spouse_data) : 
							if (isset($spouse_data['id'])) :
								$spouse = get_post($spouse_data['id']);
								if ($spouse) :
									// Формируем информацию о дате брака из новых полей
									$marriage_info = '';
									$married_parts = array();
									if (!empty($spouse_data['married_day'])) {
										$married_parts[] = sprintf('%02d', $spouse_data['married_day']);
									}
									if (!empty($spouse_data['married_month'])) {
										$married_parts[] = sprintf('%02d', $spouse_data['married_month']);
									}
									if (!empty($spouse_data['married_year'])) {
										$married_parts[] = $spouse_data['married_year'];
									}
									if (!empty($married_parts)) {
										$marriage_info = implode('.', $married_parts);
									}
									
									// Формируем информацию о дате развода из новых полей
									$divorce_info = '';
									if (!empty($spouse_data['divorced_unknown'])) {
										$divorce_info = 'дата неизвестна';
									} else {
										$divorced_parts = array();
										if (!empty($spouse_data['divorced_day'])) {
											$divorced_parts[] = sprintf('%02d', $spouse_data['divorced_day']);
										}
										if (!empty($spouse_data['divorced_month'])) {
											$divorced_parts[] = sprintf('%02d', $spouse_data['divorced_month']);
										}
										if (!empty($spouse_data['divorced_year'])) {
											$divorced_parts[] = $spouse_data['divorced_year'];
										}
										if (!empty($divorced_parts)) {
											$divorce_info = implode('.', $divorced_parts);
										}
									}
						?>
						<li>
							<a href="<?php echo esc_url(get_permalink($spouse_data['id'])); ?>">
								<?php echo esc_html(get_the_title($spouse_data['id'])); ?>
							</a>
							<?php if (!empty($marriage_info) || !empty($spouse_data['divorced_unknown']) || !empty($divorce_info)) : ?>
								<div class="spouse-dates" style="margin-left: 20px; font-size: 0.9em; color: #666;">
									<?php if (!empty($marriage_info)) : ?>
										<div>Брак: <?php echo esc_html($marriage_info); ?></div>
									<?php endif; ?>
									<?php if (!empty($spouse_data['divorced_unknown']) || !empty($divorce_info)) : ?>
										<div>Развод<?php if (!empty($divorce_info) && empty($spouse_data['divorced_unknown'])) : ?>: <?php echo esc_html($divorce_info); ?><?php endif; ?></div>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</li>
						<?php endif; endif; endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
				
				<?php if (!empty($children)) : ?>
				<div class="family-member-children">
					<h2>Дети</h2>
					<ul>
						<?php foreach ($children as $child_id) : 
							$child = get_post($child_id);
							if ($child) :
						?>
						<li>
							<a href="<?php echo esc_url(get_permalink($child_id)); ?>">
								<?php echo esc_html(get_the_title($child_id)); ?>
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