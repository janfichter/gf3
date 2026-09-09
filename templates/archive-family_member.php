<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Шаблон архива "Члены семьи"
 * Вместо обычного списка записей выводит алфавитный каталог фамилий
 * (тот же вывод, что даёт шорткод [family_surname_catalog]).
 */
get_header(); ?>
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
				$ft_catalog_page_title = get_post_type_object( 'family_member' ) ? get_post_type_object( 'family_member' )->labels->name : 'Члены семьи';
				echo '<h1 class="entry-title">' . esc_html( $ft_catalog_page_title ) . '</h1>';
				echo do_shortcode( '[family_surname_catalog]' );
			?>
		</div>
	</div>
</section>
</div>
<?php get_footer(); ?>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound