/**
 * Storefront behaviour. Vanilla JavaScript, no jQuery, no framework.
 *
 * Everything here is an upgrade on markup that already works without it: the filter panel is a real form, the sort
 * control is a real form, the chips are real links and the pagination is real links. With this script they stop
 * reloading the page.
 */
(function () {
	'use strict';

	var config = window.orwoShop || {};
	var strings = config.strings || {};

	function $( selector, scope ) {
		return ( scope || document ).querySelector( selector );
	}

	function $$( selector, scope ) {
		return Array.prototype.slice.call( ( scope || document ).querySelectorAll( selector ) );
	}

	/* ---------- Announcement strip ---------- */

	var strip = $( '[data-orwo-strip]' );
	if ( strip ) {
		var stripKey = 'orwo-strip-' + ( strip.textContent || '' ).trim().slice( 0, 40 );
		var dismissed = false;
		try {
			dismissed = window.localStorage.getItem( stripKey ) === '1';
		} catch ( error ) {
			dismissed = false;
		}
		if ( ! dismissed ) {
			strip.hidden = false;
		}
		var stripClose = $( '[data-orwo-strip-close]', strip );
		if ( stripClose ) {
			stripClose.addEventListener( 'click', function () {
				strip.hidden = true;
				measureHeader();
				try {
					window.localStorage.setItem( stripKey, '1' );
				} catch ( error ) {
					/* A private window cannot remember the choice; hiding it for this visit is enough. */
				}
			} );
		}
	}

	/* ---------- Header: condense on scroll, bring the format bar back on the way up ---------- */

	var header = $( '[data-orwo-header]' );
	var top = $( '[data-orwo-top]' );
	var space = $( '[data-orwo-top-space]' );

	// The header is fixed, so the spacer has to carry its height. Measuring only while the header is at full size
	// keeps the number right, and never measuring during a scroll keeps the page from shifting under the visitor.
	function measureHeader() {
		if ( ! top || ! space || ( header && header.classList.contains( 'is-condensed' ) ) ) {
			return;
		}
		space.style.height = top.offsetHeight + 'px';
	}

	measureHeader();
	window.addEventListener( 'load', measureHeader );
	window.addEventListener( 'resize', measureHeader );
	if ( 'fonts' in document ) {
		document.fonts.ready.then( measureHeader );
	}

	if ( header ) {
		var lastY = window.scrollY;
		var ticking = false;

		window.addEventListener( 'scroll', function () {
			if ( ticking ) {
				return;
			}
			ticking = true;
			window.requestAnimationFrame( function () {
				var y = window.scrollY;
				if ( y > 160 && y > lastY ) {
					header.classList.add( 'is-condensed' );
				} else if ( y < lastY || y < 80 ) {
					header.classList.remove( 'is-condensed' );
				}
				lastY = y;
				ticking = false;
			} );
		}, { passive: true } );
	}

	/* ---------- Mega menu: hover and keyboard ---------- */

	$$( '[data-orwo-mega]' ).forEach( function ( item ) {
		var panel = $( '.orwo-mega', item );
		var trigger = $( 'a', item );
		if ( ! panel || ! trigger ) {
			return;
		}
		var timer = null;

		function open() {
			window.clearTimeout( timer );
			$$( '.orwo-mega' ).forEach( function ( other ) {
				if ( other !== panel ) {
					other.hidden = true;
				}
			} );
			panel.hidden = false;
			trigger.setAttribute( 'aria-expanded', 'true' );
		}

		function close( delay ) {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				panel.hidden = true;
				trigger.setAttribute( 'aria-expanded', 'false' );
			}, delay || 0 );
		}

		item.addEventListener( 'mouseenter', open );
		item.addEventListener( 'mouseleave', function () {
			close( 120 );
		} );
		item.addEventListener( 'focusin', open );
		item.addEventListener( 'focusout', function ( event ) {
			if ( ! item.contains( event.relatedTarget ) ) {
				close();
			}
		} );
		item.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				close();
				trigger.focus();
			}
		} );
	} );

	/* ---------- Phone menu ---------- */

	var mobile = $( '#orwo-mobile-menu' );
	if ( mobile ) {
		var openers = $$( '[data-orwo-menu-open]' );
		var closer = $( '[data-orwo-menu-close]', mobile );

		function openMenu() {
			mobile.hidden = false;
			document.body.style.overflow = 'hidden';
			openers.forEach( function ( button ) {
				button.setAttribute( 'aria-expanded', 'true' );
			} );
			if ( closer ) {
				closer.focus();
			}
		}

		function closeMenu() {
			mobile.hidden = true;
			document.body.style.overflow = '';
			openers.forEach( function ( button ) {
				button.setAttribute( 'aria-expanded', 'false' );
			} );
		}

		openers.forEach( function ( button ) {
			button.addEventListener( 'click', openMenu );
		} );
		if ( closer ) {
			closer.addEventListener( 'click', closeMenu );
		}
		mobile.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				closeMenu();
			}
		} );

		$$( '[data-orwo-expand]', mobile ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var sub = button.closest( 'li' ).querySelector( '.orwo-mobile__sub' );
				if ( ! sub ) {
					return;
				}
				var open = button.getAttribute( 'aria-expanded' ) === 'true';
				button.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				sub.hidden = open;
			} );
		} );
	}

	/* ---------- Cart count: only asked for when a cart cookie exists ---------- */

	function hasCartCookie() {
		return /(^|;\s*)(woocommerce_items_in_cart|wp_woocommerce_session_|woocommerce_cart_hash)/.test( document.cookie );
	}

	var countEl = $( '[data-orwo-cart-count]' );
	if ( countEl && config.cart && hasCartCookie() ) {
		fetch( config.cart, { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( data && data.count > 0 ) {
					countEl.textContent = String( data.count );
					countEl.hidden = false;
				}
			} )
			.catch( function () {
				/* The count is a convenience: leaving it off is better than an error. */
			} );
	}

	/* ---------- Product card hover image ---------- */

	$$( '.orwo-card__tile[data-hover]' ).forEach( function ( tile ) {
		var loaded = false;
		tile.closest( '.orwo-card' ).addEventListener( 'mouseenter', function () {
			if ( loaded ) {
				return;
			}
			loaded = true;
			var image = new Image();
			image.className = 'orwo-card__hover';
			image.alt = '';
			image.loading = 'lazy';
			image.decoding = 'async';
			image.src = tile.getAttribute( 'data-hover' );
			tile.appendChild( image );
		}, { once: true } );
	} );

	/* ---------- Product gallery ---------- */

	var gallery = $( '[data-orwo-gallery]' );
	if ( gallery ) {
		var main = $( '.orwo-gallery__image', gallery );
		$$( '.orwo-gallery__thumb', gallery ).forEach( function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				if ( ! main ) {
					return;
				}
				main.src = thumb.getAttribute( 'data-full' );
				var srcset = thumb.getAttribute( 'data-srcset' );
				if ( srcset ) {
					main.srcset = srcset;
				}
				$$( '.orwo-gallery__thumb', gallery ).forEach( function ( other ) {
					other.classList.toggle( 'is-current', other === thumb );
					other.setAttribute( 'aria-pressed', other === thumb ? 'true' : 'false' );
				} );
			} );
		} );
	}

	/* ---------- Filters ---------- */

	var filtersForm = $( '[data-orwo-filters]' );
	var grid = $( '[data-orwo-grid]' );
	var panel = $( '.orwo-archive__panel' );

	if ( filtersForm && grid ) {
		var summary = $( '[data-orwo-summary]' );
		var chipList = $( '[data-orwo-chips]' );
		var sortForm = $( '[data-orwo-sort]' );
		var pagination = $( '[data-orwo-pagination]' );
		var requestId = 0;

		function collect() {
			var data = new FormData( filtersForm );
			var values = {};

			data.forEach( function ( value, key ) {
				if ( '' === String( value ) ) {
					return;
				}
				var name = key.replace( /\[\]$/, '' );
				if ( values[ name ] ) {
					values[ name ] += ',' + value;
				} else {
					values[ name ] = String( value );
				}
			} );

			if ( sortForm ) {
				var sortSelect = $( 'select[name="sort"]', sortForm );
				if ( sortSelect && sortSelect.value && 'newest' !== sortSelect.value ) {
					values.sort = sortSelect.value;
				} else {
					delete values.sort;
				}
			}

			return values;
		}

		function toQuery( values ) {
			var parts = [];
			Object.keys( values ).forEach( function ( key ) {
				parts.push( encodeURIComponent( key ) + '=' + encodeURIComponent( values[ key ] ) );
			} );

			return parts.join( '&' );
		}

		function apply( values, paged, push ) {
			var id = ++requestId;
			var query = toQuery( values );
			var url = config.base + ( query ? '?' + query : '' );

			if ( push ) {
				window.history.pushState( { orwo: true }, '', url );
			}

			grid.setAttribute( 'aria-busy', 'true' );

			var params = new URLSearchParams( query );
			params.set( 'view', config.view || 'shop' );
			if ( config.format ) {
				params.set( 'format', config.format );
			}
			if ( config.genre ) {
				params.set( 'genre', config.genre );
			}
			if ( config.creator ) {
				params.set( 'creator', config.creator );
			}
			if ( config.label ) {
				params.set( 'label', config.label );
			}
			params.set( 'paged', String( paged || 1 ) );

			return fetch( config.rest + 'products?' + params.toString(), { credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.ok ? response.json() : null;
				} )
				.then( function ( data ) {
					if ( ! data || id !== requestId ) {
						return;
					}

					if ( paged > 1 ) {
						grid.insertAdjacentHTML( 'beforeend', data.html );
					} else {
						grid.innerHTML = data.html || '<p class="orwo-grid__empty">' + ( strings.none || '' ) + '</p>';
					}

					grid.setAttribute( 'aria-busy', 'false' );

					if ( summary && data.summary ) {
						summary.textContent = data.summary;
					}
					updateCounts( data.counts || {} );
					updateChips( data.chips || [], values );
					updateMore( data );
					bindCards();
				} )
				.catch( function () {
					// If the request fails, fall back to a normal page load so the visitor still gets results.
					window.location.href = url;
				} );
		}

		function updateCounts( counts ) {
			$$( '[data-orwo-facet]', filtersForm ).forEach( function ( fieldset ) {
				var facet = fieldset.getAttribute( 'data-orwo-facet' );
				var facetCounts = counts[ facet ];
				if ( ! facetCounts ) {
					return;
				}
				$$( 'input[type="checkbox"]', fieldset ).forEach( function ( input ) {
					var count = facetCounts[ input.value ];
					var label = input.closest( 'label' );
					var output = label ? label.querySelector( '.orwo-filter__count' ) : null;
					if ( output ) {
						output.textContent = count ? String( count ) : '0';
					}
					var disable = ! count && ! input.checked;
					input.disabled = disable;
					if ( label ) {
						label.classList.toggle( 'is-disabled', disable );
					}
				} );
			} );
		}

		function updateChips( chips, values ) {
			if ( ! chipList ) {
				return;
			}
			chipList.textContent = '';
			if ( ! chips.length ) {
				chipList.hidden = true;
				return;
			}
			chipList.hidden = false;

			chips.forEach( function ( chip ) {
				var item = document.createElement( 'li' );
				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'orwo-chip';
				button.innerHTML = '<span></span><span class="orwo-chip__x" aria-hidden="true">&times;</span>';
				button.querySelector( 'span' ).textContent = chip.label;
				button.setAttribute( 'aria-label', ( strings.remove || 'Remove' ) + ': ' + chip.label );
				button.addEventListener( 'click', function () {
					removeFilter( chip.facet, chip.value );
				} );
				item.appendChild( button );
				chipList.appendChild( item );
			} );

			var clearItem = document.createElement( 'li' );
			var clear = document.createElement( 'button' );
			clear.type = 'button';
			clear.className = 'orwo-chip orwo-chip--clear';
			clear.textContent = strings.clear || 'Clear all';
			clear.addEventListener( 'click', function () {
				filtersForm.reset();
				$$( 'input[type="checkbox"]', filtersForm ).forEach( function ( input ) {
					input.checked = false;
				} );
				$$( 'input[type="number"]', filtersForm ).forEach( function ( input ) {
					input.value = '';
				} );
				apply( collect(), 1, true );
			} );
			clearItem.appendChild( clear );
			chipList.appendChild( clearItem );
		}

		function removeFilter( facet, value ) {
			if ( 'price' === facet ) {
				$$( 'input[name="min_price"],input[name="max_price"]', filtersForm ).forEach( function ( input ) {
					input.value = '';
				} );
			} else {
				$$( 'input[type="checkbox"]', filtersForm ).forEach( function ( input ) {
					var name = input.name.replace( /\[\]$/, '' );
					if ( name === facet && ( ! value || input.value === value || '1' === value ) ) {
						input.checked = false;
					}
				} );
			}
			apply( collect(), 1, true );
		}

		function updateMore( data ) {
			if ( ! pagination ) {
				return;
			}
			var list = $( '.orwo-pagination__list', pagination );
			var more = $( '[data-orwo-more]', pagination );
			var paged = data.paged || 1;

			if ( list ) {
				list.hidden = true;
			}
			if ( ! more ) {
				return;
			}
			if ( paged < data.pages ) {
				more.hidden = false;
				more.setAttribute( 'data-next', String( paged + 1 ) );
			} else {
				more.hidden = true;
			}
		}

		function bindCards() {
			$$( '.orwo-card__tile[data-hover]' ).forEach( function ( tile ) {
				if ( tile.getAttribute( 'data-bound' ) ) {
					return;
				}
				tile.setAttribute( 'data-bound', '1' );
				tile.closest( '.orwo-card' ).addEventListener( 'mouseenter', function () {
					if ( tile.querySelector( '.orwo-card__hover' ) ) {
						return;
					}
					var image = new Image();
					image.className = 'orwo-card__hover';
					image.alt = '';
					image.loading = 'lazy';
					image.src = tile.getAttribute( 'data-hover' );
					tile.appendChild( image );
				}, { once: true } );
			} );
		}

		filtersForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			apply( collect(), 1, true );
			closeSheet();
		} );

		filtersForm.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( 'input' ) ) {
				apply( collect(), 1, true );
			}
		} );

		// Price boxes: wait until typing stops.
		var priceTimer = null;
		$$( 'input[name="min_price"],input[name="max_price"]', filtersForm ).forEach( function ( input ) {
			input.addEventListener( 'input', function () {
				window.clearTimeout( priceTimer );
				priceTimer = window.setTimeout( function () {
					apply( collect(), 1, true );
				}, 400 );
			} );
		} );

		if ( sortForm ) {
			sortForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				apply( collect(), 1, true );
			} );
			var sortSelect = $( 'select[name="sort"]', sortForm );
			if ( sortSelect ) {
				sortSelect.addEventListener( 'change', function () {
					apply( collect(), 1, true );
				} );
			}
		}

		if ( pagination ) {
			var moreButton = $( '[data-orwo-more]', pagination );
			var list = $( '.orwo-pagination__list', pagination );
			if ( moreButton ) {
				moreButton.hidden = false;
				moreButton.setAttribute( 'data-next', String( ( parseInt( pagination.getAttribute( 'data-paged' ), 10 ) || 1 ) + 1 ) );
				if ( list ) {
					list.hidden = true;
				}
				moreButton.addEventListener( 'click', function () {
					var next = parseInt( moreButton.getAttribute( 'data-next' ), 10 ) || 2;
					apply( collect(), next, false );
				} );
			}
		}

		$$( '[data-orwo-chip-remove]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				removeFilter( link.getAttribute( 'data-orwo-chip-remove' ), link.getAttribute( 'data-orwo-chip-value' ) );
			} );
		} );

		window.addEventListener( 'popstate', function () {
			window.location.reload();
		} );

		/* The bottom sheet on phones. */
		function openSheet() {
			if ( ! panel ) {
				return;
			}
			panel.classList.add( 'is-open' );
			document.body.classList.add( 'orwo-filters-open' );
		}

		function closeSheet() {
			if ( ! panel ) {
				return;
			}
			panel.classList.remove( 'is-open' );
			document.body.classList.remove( 'orwo-filters-open' );
		}

		$$( '[data-orwo-filters-open]' ).forEach( function ( button ) {
			button.hidden = false;
			button.addEventListener( 'click', openSheet );
		} );
		$$( '[data-orwo-filters-close]' ).forEach( function ( button ) {
			button.hidden = false;
			button.addEventListener( 'click', closeSheet );
		} );
	}

	/* ---------- Search suggestions ---------- */

	$$( '[data-orwo-search]' ).forEach( function ( form ) {
		var input = $( 'input[type="search"]', form );
		var box = $( '[data-orwo-suggestions]', form );
		if ( ! input || ! box ) {
			return;
		}

		var index = null;
		var timer = null;

		function load() {
			if ( index || ! window.orwoChat || ! window.orwoChat.index ) {
				return Promise.resolve( index );
			}

			return fetch( window.orwoChat.index, { credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.ok ? response.json() : null;
				} )
				.then( function ( data ) {
					index = data;

					return index;
				} )
				.catch( function () {
					return null;
				} );
		}

		function suggest() {
			var term = input.value.trim().toLowerCase();
			if ( term.length < 2 ) {
				box.hidden = true;
				box.textContent = '';
				return;
			}

			load().then( function ( data ) {
				if ( ! data || ! data.products ) {
					return;
				}
				var matches = data.products.filter( function ( product ) {
					return ( product.title + ' ' + product.creator ).toLowerCase().indexOf( term ) !== -1;
				} ).slice( 0, 6 );

				box.textContent = '';
				if ( ! matches.length ) {
					box.hidden = true;
					return;
				}
				matches.forEach( function ( product ) {
					var link = document.createElement( 'a' );
					link.href = product.url;
					if ( product.thumb ) {
						var image = new Image();
						image.src = product.thumb;
						image.alt = '';
						image.loading = 'lazy';
						link.appendChild( image );
					}
					var text = document.createElement( 'span' );
					text.textContent = product.creator ? product.title + ' — ' + product.creator : product.title;
					link.appendChild( text );
					box.appendChild( link );
				} );
				box.hidden = false;
			} );
		}

		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( suggest, 180 );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				box.hidden = true;
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! form.contains( event.target ) ) {
				box.hidden = true;
			}
		} );
	} );

	/* ---------- Contact and newsletter forms ---------- */

	function submitForm( form, endpoint, statusSelector ) {
		var status = $( statusSelector, form );
		var button = $( 'button[type="submit"]', form );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			if ( ! form.reportValidity() ) {
				return;
			}

			var payload = {};
			new FormData( form ).forEach( function ( value, key ) {
				payload[ key ] = value;
			} );

			if ( button ) {
				button.disabled = true;
			}
			if ( status ) {
				status.classList.remove( 'is-error' );
				status.textContent = strings.sending || 'Sending…';
			}

			fetch( config.rest + endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( payload )
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					if ( button ) {
						button.disabled = false;
					}
					if ( ! status ) {
						return;
					}
					if ( result.ok && result.data && result.data.ok ) {
						status.textContent = result.data.message || '';
						form.reset();
					} else {
						status.classList.add( 'is-error' );
						status.textContent = ( result.data && result.data.message ) || 'That did not send.';
					}
				} )
				.catch( function () {
					if ( button ) {
						button.disabled = false;
					}
					if ( status ) {
						status.classList.add( 'is-error' );
						status.textContent = 'That did not send.';
					}
				} );
		} );
	}

	$$( '[data-orwo-contact]' ).forEach( function ( form ) {
		submitForm( form, 'enquiry', '.orwo-contact__status' );
	} );

	$$( '[data-orwo-signup]' ).forEach( function ( form ) {
		submitForm( form, 'signup', '.orwo-signup__status' );
	} );
}());
