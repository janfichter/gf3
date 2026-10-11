<?php
/**
 * Совместимость шаблонов плагина с блочными (FSE) темами.
 *
 * Блочные темы не содержат header.php / footer.php, поэтому обычные
 * get_header() / get_footer() на них не выводят ничего, а block_template_part()
 * возвращает только внутреннее содержимое части — без обёртки
 * <header class="wp-block-template-part">. Из-за этого теряются:
 *  - структура .wp-site-blocks > header / footer;
 *  - правила вида .wp-site-blocks > footer { background: ... } в теме;
 *  - переход по skip-link.
 *
 * Класс заново собирает документ, отрисовывая части темы так, как это делает
 * template-canvas.php: нативный рендер блока core/template-part с его
 * tagName и className.
 *
 * @package genius-family-tree
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Family_Tree_Template_Compat {

	/**
	 * Кэш готового HTML частей (по slug).
	 *
	 * @var array<string,string>
	 */
	private static $parts = array();

	/**
	 * Части отрисованы до wp_head(), чтобы стили попали в head.
	 *
	 * @var bool
	 */
	private static $primed = false;

	/**
	 * Документ уже открыт.
	 *
	 * @var bool
	 */
	private static $document_open = false;

	/**
	 * <main> для контента плагина уже открыт.
	 *
	 * @var bool
	 */
	private static $content_open = false;

	/**
	 * Явно выбранный в настройках block-шаблон темы.
	 *
	 * @var string
	 */
	private static $template_slug = '';

	/**
	 * Имя кастомной шапки для обычной PHP-темы.
	 *
	 * @var string
	 */
	private static $classic_header = '';

	/**
	 * Имя кастомного подвала для обычной PHP-темы.
	 *
	 * @var string
	 */
	private static $classic_footer = '';

	/**
	 * Текущая тема — блочная (FSE).
	 *
	 * @return bool
	 */
	public static function is_block_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Задаёт block-шаблон темы, из которого берутся шапка и подвал.
	 *
	 * Влияет только на оболочку. Контентная часть страницы персоны
	 * формируется собственным шаблоном плагина и настройками админки,
	 * поэтому глобальные стили темы на неё не распространяются.
	 *
	 * @param string $slug Slug block-шаблона без расширения.
	 */
	public static function set_template_slug( $slug ) {
		if ( ! is_string( $slug ) ) {
			self::$template_slug = '';
			return;
		}
		// Slug приходит из get_block_templates() и уже нормализован ядром.
		// Повторный sanitize_title() испортил бы кириллицу: «внутренний»
		// превратился бы в «vnutrenniy», и шаблон перестал бы находиться.
		self::$template_slug = trim( $slug );
	}
	/**
	 * Имена кастомных header/footer для обычной PHP-темы.
	 *
	 * В классических темах разница между «внешним» и «внутренним» шаблоном
	 * обычно делается так: шаблон вызывает get_header( 'inner' ), и тема
	 * подключает header-inner.php. Чтобы выбор работал, не отдавая теме
	 * контент, читаем из файла шаблона эти имена и подключаем их сами.
	 *
	 * @param string $header_name Имя для get_header(), '' — обычный header.php.
	 * @param string $footer_name Имя для get_footer(), '' — обычный footer.php.
	 */
	public static function set_classic_shell( $header_name, $footer_name ) {
		self::$classic_header = self::clean_shell_name( $header_name );
		self::$classic_footer = self::clean_shell_name( $footer_name );
	}

	/**
	 * Приводит имя части к безопасному виду.
	 *
	 * get_header() склеивает имя с именем файла, поэтому оставляем только
	 * буквы, цифры, дефис и подчёркивание — иначе в путь попадёт произвольная
	 * строка из файла шаблона.
	 *
	 * @param mixed $name Имя части.
	 * @return string
	 */
	private static function clean_shell_name( $name ) {
		if ( ! is_string( $name ) ) {
			return '';
		}
		$name = preg_replace( '/[^A-Za-z0-9_\-]/', '', $name );
		return is_string( $name ) ? $name : '';
	}


	/**
	 * Вывод шапки страницы.
	 */
	public static function header() {
		if ( ! self::is_block_theme() ) {
			// Кастомная шапка выбранного шаблона, если тема её объявила.
			if ( '' !== self::$classic_header ) {
				get_header( self::$classic_header );
			} else {
				get_header();
			}
			return;
		}
		self::prime_parts();
		self::open_document();
		echo self::get_part_html( 'header' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- разметка темы.
	}

	/**
	 * Открыть <main> для контента плагина.
	 *
	 * Правила темы вида .wp-site-blocks > main не сработают, если контент лежит
	 * прямо в .wp-site-blocks. На классических темах <main> уже есть в header.php,
	 * поэтому здесь — no-op.
	 */
	public static function open_content() {
		if ( ! self::is_block_theme() || self::$content_open ) {
			return;
		}
		self::$content_open = true;
		echo '<main id="wp--skip-link--target" class="wp-block-group is-layout-constrained">';
	}

	/**
	 * Закрыть <main>.
	 */
	public static function close_content() {
		if ( ! self::is_block_theme() || ! self::$content_open ) {
			return;
		}
		self::$content_open = false;
		echo '</main>';
	}

	/**
	 * Вывод подвала страницы.
	 */
	public static function footer() {
		if ( ! self::is_block_theme() ) {
			// Кастомный подвал выбранного шаблона, если тема его объявила.
			if ( '' !== self::$classic_footer ) {
				get_footer( self::$classic_footer );
			} else {
				get_footer();
			}
			return;
		}
		self::close_content();
		echo self::get_part_html( 'footer' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- разметка темы.
		echo '</div>' . "\n"; // .wp-site-blocks
		wp_footer();
		echo '</body>' . "\n" . '</html>' . "\n";
		self::$document_open = false;
	}

	/**
	 * Отрисовать обе части до wp_head().
	 *
	 * Некоторые блоки подключают стили/скрипты во время рендера. Если части
	 * рисовать после wp_head(), подключения уедут в подвал, и страница мигнёт.
	 */
	private static function prime_parts() {
		if ( self::$primed ) {
			return;
		}
		self::$primed = true;
		self::get_part_html( 'header' );
		self::get_part_html( 'footer' );
	}

	/**
	 * Открыть документ, повторяя вывод template-canvas.php.
	 */
	private static function open_document() {
		if ( self::$document_open ) {
			return;
		}
		self::$document_open = true;
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
		<?php
	}

	/**
	 * HTML части темы с корректной обёрткой.
	 *
	 * @param string $slug header|footer.
	 * @return string
	 */
	private static function get_part_html( $slug ) {
		if ( isset( self::$parts[ $slug ] ) ) {
			return self::$parts[ $slug ];
		}
		self::$parts[ $slug ] = self::render_part( $slug );
		return self::$parts[ $slug ];
	}

	/**
	 * Отрисовать часть через нативный рендер core/template-part.
	 *
	 * Именно он добавляет tagName и className, которых не даёт block_template_part().
	 *
	 * @param string $slug header|footer.
	 * @return string
	 */
	private static function render_part( $slug ) {
		$block = self::find_part_block( $slug );

		if ( ! $block ) {
			// Родительского шаблона нет — синтезируем блок, core подставит свои
			// значения по умолчанию (<header class="wp-block-template-part">).
			$block = array(
				'blockName'    => 'core/template-part',
				'attrs'        => array(
					'slug'  => $slug,
					'theme' => get_stylesheet(),
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		}

		$html = render_block( $block );

		if ( '' === trim( (string) $html ) ) {
			$html = self::render_bare_part( $slug );
		}

		return (string) $html;
	}

	/**
	 * Запасной путь: block_template_part() без обёртки.
	 *
	 * Используется, только если тема не отдала ни блок, ни часть.
	 *
	 * @param string $slug header|footer.
	 * @return string
	 */
	private static function render_bare_part( $slug ) {
		if ( ! function_exists( 'block_template_part' ) ) {
			return '';
		}
		ob_start();
		$returned = block_template_part( $slug );
		$printed  = (string) ob_get_clean();

		if ( '' !== trim( $printed ) ) {
			return $printed;
		}
		return is_string( $returned ) ? $returned : '';
	}

	/**
	 * Найти блок core/template-part нужной части в шаблоне темы.
	 *
	 * Сначала пробуем глобал, который WP заполняет при разрешении block-шаблона,
	 * затем перебираем иерархию по slug'ам.
	 *
	 * @param string $slug header|footer.
	 * @return array|null
	 */
	private static function find_part_block( $slug ) {
		$content = self::get_parent_template_content();

		if ( '' === $content || ! function_exists( 'parse_blocks' ) ) {
			return null;
		}

		return self::search_template_part( parse_blocks( $content ), $slug );
	}

	/**
	 * Рекурсивный поиск по разобранным блокам.
	 *
	 * @param array  $blocks Блоки.
	 * @param string $slug   header|footer.
	 * @return array|null
	 */
	private static function search_template_part( $blocks, $slug ) {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'core/template-part' === ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) ) {
				$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				if ( isset( $attrs['slug'] ) && $slug === $attrs['slug'] ) {
					return $block;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$found = self::search_template_part( $block['innerBlocks'], $slug );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Содержимое родительского block-шаблона темы.
	 *
	 * @return string
	 */
	private static function get_parent_template_content() {
		// Явно выбранный шаблон важнее всего остального: иначе глобалы ядра
		// подставят содержимое шаблона по умолчанию.
		$explicit = self::get_explicit_template_content();
		if ( '' !== $explicit ) {
			return $explicit;
		}

		// WP заполняет эти глобалы при разрешении block-шаблона (template-loader.php).
		foreach ( array( 'wp_current_template_content', '_wp_current_template_content' ) as $key ) {
			if ( ! empty( $GLOBALS[ $key ] ) && is_string( $GLOBALS[ $key ] ) ) {
				return $GLOBALS[ $key ];
			}
		}

		if ( ! function_exists( 'get_block_template' ) ) {
			return '';
		}

		$stylesheet = get_stylesheet();

		foreach ( self::get_template_slug_candidates() as $slug ) {
			$template = get_block_template( $stylesheet . '//' . $slug, 'wp_template' );
			if ( $template && ! empty( $template->content ) ) {
				return $template->content;
			}
		}

		return '';
	}

	/**
	 * Содержимое явно выбранного в настройках блочного шаблона.
	 *
	 * Шаблон может лежать и в теме, и в базе — Site Editor (редактор сайта)
	 * сохраняет пользовательские шаблоны как записи типа wp_template, и их id
	 * не содержит префикса темы. Поэтому ищем по slug'у через
	 * get_block_templates(), а не через get_block_template( 'тема//slug' ).
	 *
	 * @return string
	 */
	private static function get_explicit_template_content() {
		if ( '' === self::$template_slug || ! function_exists( 'get_block_templates' ) ) {
			return '';
		}

		$all = array();
		foreach ( array( 'family_member', 'page' ) as $post_type ) {
			$found = get_block_templates( array( 'post_type' => $post_type ), 'wp_template' );
			if ( is_array( $found ) ) {
				foreach ( $found as $template ) {
					$all[] = $template;
				}
			}
		}
		if ( empty( $all ) ) {
			return '';
		}

		foreach ( $all as $template ) {
			if ( isset( $template->slug ) && self::$template_slug === $template->slug
				&& ! empty( $template->content ) ) {
				return (string) $template->content;
			}
		}

		// У пользовательских шаблонов поле theme пустое — ищем по самому id.
		foreach ( $all as $template ) {
			if ( isset( $template->id ) && self::$template_slug === $template->id
				&& ! empty( $template->content ) ) {
				return (string) $template->content;
			}
		}

		return '';
	}

	/**
	 * Иерархия block-шаблонов темы для текущего запроса.
	 *
	 * @return string[]
	 */
	private static function get_template_slug_candidates() {
		$slugs = array();

		// Явно выбранный в настройках шаблон имеет приоритет над иерархией.
		if ( '' !== self::$template_slug ) {
			$slugs[] = self::$template_slug;
		}

		if ( is_singular() ) {
			$post_type = get_post_type();
			$slugs[]   = 'single-' . $post_type;

			$queried = get_queried_object();
			if ( $queried instanceof WP_Post ) {
				$slugs[] = 'single-' . $post_type . '-' . $queried->post_name;
			}

			$slugs[] = 'single';
			$slugs[] = 'singular';
		} elseif ( is_post_type_archive() || is_tax() || is_category() || is_tag() ) {
			$slugs[] = 'archive';
		}

		$slugs[] = 'index';

		return array_values( array_unique( array_filter( $slugs ) ) );
	}
}
