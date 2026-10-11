/* Genius Family Tree — блоки Гутенберга быстрой вставки семейного архива.
   В инсертере: собственный раздел «Genius Family Tree» с логотипом и 6 блоков,
   каждый со своей зелёной иконкой (SVG с inline-стилем, устойчив к перекраске).
   Блок в редакторе — собственная превью-карточка с встроенными полями:
   «Персона / Альбом» — списки с автоподстановкой по slug, «Группа» — селект.
   На фронтенде блок превращается в шорткод архива (рендер на сервере). */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var __ = wp.i18n.__;
	var useState = wp.element.useState;
	var useBlockProps = wp.blockEditor.useBlockProps || function ( extra ) { return Object.assign( {}, { className: '' }, extra || {} ); };
	var SelectControl = wp.components.SelectControl;
	var helper = window.gftArchiveHelper || {};
	var logoUrl = helper.logo_url || '';
	var items = helper.items || [];
	var members = helper.members || [];
	var groups = helper.groups || [];

	/* Иконка категории в инсертере — сам логотип плагина (зелёный) */
	function logoNode( size ) {
		return el( 'img', {
			src: logoUrl,
			alt: '',
			width: size || 24,
			height: size || 24,
			style: { objectFit: 'contain' }
		} );
	}

	/* Категория «Genius Family Tree» создаётся на сервере (block_categories_all).
	   Здесь подставляем в её заголовок логотип вместо стандартной иконки.
	   Используем store core/blocks: пробуем updateCategories, затем addCategories,
	   затем устаревший wp.blocks.setCategories. Ошибки глушим — при сбое
	   остаётся серверная (символическая) иконка и заголовок. */
	function unifyCategoryIcon() {
		var logo = logoNode();
		var dispatch = wp.data && wp.data.dispatch ? wp.data.dispatch( 'core/blocks' ) : null;
		var select = wp.data && wp.data.select ? wp.data.select( 'core/blocks' ) : null;

		if ( dispatch && dispatch.updateCategories ) {
			try {
				var cats = select.getCategories();
				var next = cats.map( function ( c ) {
					if ( c.slug === 'gft-archive' ) {
						return Object.assign( {}, c, {
							title: __( 'Genius Family Tree', 'genius-family-tree' ),
							icon: logo
						} );
					}
					return c;
				} );
				dispatch.updateCategories( { categories: next } );
				return;
			} catch ( e ) {
				// пробуем следующий способ
			}
		}
		if ( dispatch && dispatch.addCategories ) {
			try {
				dispatch.addCategories( [ {
					slug: 'gft-archive',
					title: __( 'Genius Family Tree', 'genius-family-tree' ),
					icon: logo
				} ] );
				return;
			} catch ( e ) {
				// пробуем следующий способ
			}
		}
		try {
			if ( wp.blocks.setCategories ) {
				var all = wp.blocks.getCategories().map( function ( c ) {
					return c.slug === 'gft-archive' ? Object.assign( {}, c, {
						title: __( 'Genius Family Tree', 'genius-family-tree' ),
						icon: logo
					} ) : c;
				} );
				wp.blocks.setCategories( all );
			}
		} catch ( e ) {
			// категория остаётся с серверной иконкой
		}
	}

	/* Иконка каждого блока: свой SVG. Цвет задан прямо в строке SVGs
	   (fill/stroke), поэтому перекраска каталогом блоков (fill: currentColor)
	   на иконки не влияет. */
	function makeIcon( svgString ) {
		return function () {
			return el( 'span', {
				className: 'gft-iblk-icon',
				dangerouslySetInnerHTML: { __html: svgString }
			} );
		};
	}

	/* Опции персон для автодополнения (label + slug) */
	function memberOptions() {
		return members.map( function ( m ) {
			return { label: m.label, value: m.value, slug: m.slug || '' };
		} );
	}

	/* Опции альбомов выбранной персоны */
	function albumOptionsFor( personId ) {
		var person = null;
		for ( var i = 0; i < members.length; i++ ) {
			if ( members[ i ].value === personId ) {
				person = members[ i ];
				break;
			}
		}
		return person && person.albums ? person.albums : [];
	}

	/* Поле-список с живым поиском по имени и slug.
	   Открывается по фокусу, фильтруется при вводе, выбор — кликом по пункту
	   или Enter по первому совпадению. Пустой вариант «по умолчанию» — в начале. */
	function AutocompleteField( props ) {
		var q = useState( '' );
		var query = q[ 0 ];
		var setQuery = q[ 1 ];
		var s = useState( false );
		var open = s[ 0 ];
		var setOpen = s[ 1 ];

		var options = props.options || [];
		var selected = null;
		for ( var i = 0; i < options.length; i++ ) {
			if ( options[ i ].value === props.value ) {
				selected = options[ i ];
				break;
			}
		}

		var shown = options;
		if ( query ) {
			var lq = query.toLowerCase();
			shown = options.filter( function ( o ) {
				return o.label.toLowerCase().indexOf( lq ) !== -1 ||
					( o.slug && o.slug.toLowerCase().indexOf( lq ) !== -1 );
			} );
		}

		var choose = function ( value ) {
			setQuery( '' );
			setOpen( false );
			props.onChange( value );
		};

		var inputValue = query !== '' ? query : ( selected ? selected.label : '' );

		var list = null;
		if ( open ) {
			var rows;
			if ( ! shown.length ) {
				rows = [ el( 'li', { key: '__none', className: 'gft-autocomplete__empty' },
					props.noMatch || __( 'Ничего не найдено', 'genius-family-tree' ) ) ];
			} else {
				rows = shown.slice( 0, 60 ).map( function ( o ) {
					return el( 'li', {
						key: o.value || '__auto',
						className: 'gft-autocomplete__item' + ( o.value === props.value ? ' is-selected' : '' ),
						onMouseDown: function ( e ) {
							e.preventDefault();
							choose( o.value );
						}
					}, [
						el( 'span', { key: 'l', className: 'gft-autocomplete__lbl' }, o.label ),
						o.slug ? el( 'span', { key: 's', className: 'gft-autocomplete__slug' }, o.slug ) : null
					] );
				} );
			}
			list = el( 'ul', { className: 'gft-autocomplete__list' }, rows );
		}

		return el( 'div', { className: 'gft-autocomplete' + ( open ? ' is-open' : '' ) }, [
			el( 'label', { key: 'lbl', className: 'gft-autocomplete__label' }, props.label ),
			el( 'input', {
				key: 'in',
				type: 'text',
				className: 'gft-autocomplete__input',
				placeholder: placeholderText( selected, props ),
				value: inputValue,
				onFocus: function () {
					setQuery( '' );
					setOpen( true );
				},
				onChange: function ( e ) {
					setQuery( e.target.value );
					setOpen( true );
				},
				onBlur: function () {
					setQuery( '' );
					setTimeout( function () { setOpen( false ); }, 130 );
				},
				onKeyDown: function ( e ) {
					if ( e.key === 'Enter' && shown.length > 0 ) {
						e.preventDefault();
						choose( shown[ 0 ].value );
					}
					if ( e.key === 'Escape' ) {
						setOpen( false );
					}
				}
			} ),
			props.help ? el( 'p', { key: 'hp', className: 'gft-autocomplete__help' }, props.help ) : null,
			list
		] );
	}

	function placeholderText( selected, props ) {
		if ( selected ) {
			return selected.label;
		}
		return props.placeholder || __( 'Начните вводить имя или slug…', 'genius-family-tree' );
	}

	/* Рендер полей блока прямо в превью-карточку:
	   member/album — автодополнение со списком, group — обычный селект. */
	function renderInlineFields( item, attrs, setAttrs ) {
		var hasAlbumField = ( item.attrs || [] ).some( function ( f ) { return f.type === 'album'; } );

		return ( item.attrs || [] ).map( function ( field ) {
			var value = attrs[ field.key ] || '';

			if ( field.type === 'member' ) {
				var mOptions = memberOptions();
				mOptions.unshift( {
					label: __( 'Авто (по умолчанию)', 'genius-family-tree' ),
					value: '',
					slug: ''
				} );
				return el( AutocompleteField, {
					key: field.key,
					label: field.label,
					help: field.help || '',
					value: value,
					options: mOptions,
					noMatch: __( 'Персона не найдена — подставьте slug вручную', 'genius-family-tree' ),
					onChange: function ( v ) {
						var update = {};
						update[ field.key ] = v || '';
						// При смене персоны сбрасываем альбом — он у новой персоны свой
						if ( hasAlbumField ) {
							update.album = '';
						}
						setAttrs( update );
					}
				} );
			}

			if ( field.type === 'album' ) {
				var aOptions = albumOptionsFor( attrs.person || '' );
				aOptions = aOptions.slice();
				aOptions.unshift( {
					label: __( 'Первый альбом (по умолчанию)', 'genius-family-tree' ),
					value: '',
					slug: ''
				} );
				return el( AutocompleteField, {
					key: field.key,
					label: field.label,
					help: field.help || ( ! attrs.person
						? __( 'Сначала выберите персону', 'genius-family-tree' )
						: '' ),
					value: value,
					options: aOptions,
					noMatch: __( 'Альбомы персоны не найдены', 'genius-family-tree' ),
					onChange: function ( v ) {
						var update = {};
						update[ field.key ] = v || '';
						setAttrs( update );
					}
				} );
			}

			if ( field.type === 'group' ) {
				var gOptions = groups.map( function ( g ) {
					return { label: g.label, value: g.value };
				} );
				gOptions.unshift( {
					label: __( 'Авто (по умолчанию)', 'genius-family-tree' ),
					value: ''
				} );
				return el( SelectControl, {
					key: field.key,
					label: field.label,
					help: field.help || '',
					value: value,
					options: gOptions,
					onChange: function ( v ) {
						var update = {};
						update[ field.key ] = v || '';
						setAttrs( update );
					}
				} );
			}

			return null;
		} ).filter( function ( node ) { return node !== null; } );
	}

	/* Человекочитаемая сводка выбранных значений для подвала карточки */
	function summaryLines( item, attrs ) {
		var lines = [];
		( item.attrs || [] ).forEach( function ( field ) {
			var val = attrs[ field.key ] || '';
			if ( ! val ) {
				return;
			}
			if ( field.type === 'member' ) {
				var m = null;
				for ( var i = 0; i < members.length; i++ ) {
					if ( members[ i ].value === val ) { m = members[ i ]; break; }
				}
				lines.push( __( 'Персона:', 'genius-family-tree' ) + ' ' + ( m ? m.label : val ) );
			} else if ( field.type === 'album' ) {
				var person = null;
				for ( var j = 0; j < members.length; j++ ) {
					if ( members[ j ].value === ( attrs.person || '' ) ) { person = members[ j ]; break; }
				}
				var al = ( person && person.albums ) || [];
				var a = null;
				for ( var k = 0; k < al.length; k++ ) {
					if ( al[ k ].value === val ) { a = al[ k ]; break; }
				}
				lines.push( __( 'Альбом:', 'genius-family-tree' ) + ' ' + ( a ? a.label : val ) );
			} else if ( field.type === 'group' ) {
				var g = null;
				for ( var n = 0; n < groups.length; n++ ) {
					if ( groups[ n ].value === val ) { g = groups[ n ]; break; }
				}
				lines.push( __( 'Группа:', 'genius-family-tree' ) + ' ' + ( g ? g.label : val ) );
			} else {
				lines.push( field.label + ': ' + val );
			}
		} );
		return lines;
	}

	items.forEach( function ( item ) {
		var attributes = {};
		( item.attrs || [] ).forEach( function ( field ) {
			attributes[ field.key ] = {
				type: 'string',
				default: ''
			};
		} );

		registerBlockType( item.name, {
			apiVersion: 2,
			title: item.label,
			description: __( 'Быстрая вставка семейного архива: ' ) + item.shortcode,
			category: 'gft-archive',
			icon: makeIcon( item.icon ),
			keywords: item.keywords || [],
			attributes: attributes,
			supports: {
				html: false,
				inserter: true
			},
			edit: function ( props ) {
				var blockProps = useBlockProps( { className: 'gft-preview-wrap' } );
				var controls = renderInlineFields( item, props.attributes, props.setAttributes );
				var lines = summaryLines( item, props.attributes );
				var missing = ( item.required || [] ).filter( function ( key ) {
					return ! props.attributes[ key ];
				} );

				return el( 'div', blockProps, el( 'div', { className: 'gft-preview' }, [
					el( 'div', { key: 'head', className: 'gft-preview__head' }, [
						el( 'span', { key: 'ic', className: 'gft-iblk-icon', dangerouslySetInnerHTML: { __html: item.icon } } ),
						el( 'div', { key: 'tt', className: 'gft-preview__titles' }, [
							el( 'span', { key: 't', className: 'gft-preview__title' }, item.label ),
							el( 'span', { key: 's', className: 'gft-preview__tag' }, item.shortcode )
						] )
					] ),
					controls.length ? el( 'div', { key: 'fields', className: 'gft-preview__fields' }, controls ) : null,
					el( 'div', { key: 'foot', className: 'gft-preview__foot' }, [
						missing.length
							? el( 'span', { key: 'm', className: 'gft-preview__warn' },
								__( 'Выберите: ', 'genius-family-tree' ) + missing.join( ', ' ) )
							: null,
						lines.length ? el( 'span', { key: 'l', className: 'gft-preview__sum' }, lines.join( ' · ' ) ) : null,
						el( 'span', { key: 'h', className: 'gft-preview__hint' },
							__( 'Данные отобразятся на страницах сайта', 'genius-family-tree' ) )
					] )
				] ) );
			},
			save: function () {
				return null;
			}
		} );
	} );

	/* Иконка/заголовок категории подставляются после готовности store */
	unifyCategoryIcon();
} )( window.wp );