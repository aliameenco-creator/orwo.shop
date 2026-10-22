/**
 * Ask ORWO.
 *
 * A guided finder plus a small search engine that runs in the visitor's browser over the shop's own catalogue and
 * the FAQ answers written in the admin. It makes no outside requests and needs no API key. Anything it cannot
 * answer ends at the Ask a question form, so no one reaches a dead end.
 *
 * The guided finder reads the same filter definitions the shop uses, which travel in the index, so there is never a
 * second set of rules to maintain.
 */
(function () {
	'use strict';

	var config = window.orwoChat || {};
	var strings = config.strings || {};
	var panel = document.getElementById( 'orwo-chat-panel' );
	var log = panel ? panel.querySelector( '[data-orwo-chat-log]' ) : null;
	var form = panel ? panel.querySelector( '[data-orwo-chat-form]' ) : null;
	var input = form ? form.querySelector( 'input' ) : null;

	if ( ! panel || ! log ) {
		return;
	}

	var index = null;
	var loading = null;
	var started = false;
	var lastFocus = null;

	/* The conversation lives in this tab only and goes when it closes. */
	var finder = { format: '', filters: {}, step: 0, fields: [] };

	/* ---------- Plumbing ---------- */

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text && null !== text ) {
			node.textContent = text;
		}

		return node;
	}

	function scrollDown() {
		log.scrollTop = log.scrollHeight;
	}

	function say( text, who ) {
		var message = el( 'div', 'orwo-chat__message' + ( 'visitor' === who ? ' orwo-chat__message--visitor' : '' ) );
		String( text ).split( '\n' ).forEach( function ( line ) {
			message.appendChild( el( 'p', null, line ) );
		} );
		log.appendChild( message );
		scrollDown();

		return message;
	}

	function options( items ) {
		var wrap = el( 'div', 'orwo-chat__options' );
		items.forEach( function ( item ) {
			if ( ! item || ! item.label ) {
				return;
			}
			var button = el( 'button', 'orwo-chip', item.label );
			button.type = 'button';
			button.addEventListener( 'click', function () {
				wrap.remove();
				say( item.label, 'visitor' );
				item.action();
			} );
			wrap.appendChild( button );
		} );
		log.appendChild( wrap );
		scrollDown();

		return wrap;
	}

	function linkButton( label, url ) {
		var wrap = el( 'div', 'orwo-chat__options' );
		var link = el( 'a', 'orwo-chip', label );
		link.href = url;
		wrap.appendChild( link );
		log.appendChild( wrap );
		scrollDown();
	}

	function showProducts( products, seeAllUrl ) {
		if ( ! products.length ) {
			return;
		}
		var wrap = el( 'div', 'orwo-chat__results' );
		products.slice( 0, 5 ).forEach( function ( product ) {
			var link = el( 'a', 'orwo-chat__result' );
			link.href = product.url;
			if ( product.thumb ) {
				var image = new Image();
				image.src = product.thumb;
				image.alt = '';
				image.loading = 'lazy';
				link.appendChild( image );
			}
			link.appendChild( el( 'strong', null, product.title ) );
			if ( product.creator ) {
				link.appendChild( el( 'span', null, product.creator ) );
			}
			var price = product.priceHtml || '';
			link.appendChild( el( 'span', null, product.inStock ? price : ( strings.soldOut || '' ) ) );
			wrap.appendChild( link );
		} );
		log.appendChild( wrap );

		if ( seeAllUrl ) {
			linkButton( strings.seeAll || 'See all', seeAllUrl );
		}
		scrollDown();
	}

	/* ---------- The index ---------- */

	function load() {
		if ( index ) {
			return Promise.resolve( index );
		}
		if ( loading ) {
			return loading;
		}
		if ( ! config.index ) {
			return Promise.resolve( null );
		}

		loading = fetch( config.index, { credentials: 'same-origin' } )
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

		return loading;
	}

	/* ---------- Matching ---------- */

	function clean( text ) {
		return String( text || '' )
			.toLowerCase()
			.replace( /[^\p{L}\p{N}\s]/gu, ' ' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	function stem( word ) {
		// Only trim a plural when something recognisable is left. Trimming hard turned "this" into "thi", which
		// then matched "things" and gave a nonsense question a confident answer.
		if ( word.length > 4 && /ies$/.test( word ) ) {
			return word.slice( 0, -3 ) + 'y';
		}
		if ( word.length > 5 && /(ches|shes|xes|ses)$/.test( word ) ) {
			return word.slice( 0, -2 );
		}
		if ( word.length > 4 && /s$/.test( word ) && ! /ss$/.test( word ) ) {
			return word.slice( 0, -1 );
		}

		return word;
	}

	/**
	 * Whether a search word matches any whole word in a piece of text, allowing a prefix such as "creep" for
	 * "creepers". Substring matching anywhere in the string matched far too much.
	 */
	function hasWord( text, needle ) {
		if ( ! text || needle.length < 3 ) {
			return false;
		}

		return clean( text ).split( ' ' ).some( function ( word ) {
			return word === needle || ( word.length > needle.length && word.indexOf( needle ) === 0 );
		} );
	}

	function words( text ) {
		return clean( text ).split( ' ' ).filter( function ( word ) {
			return word.length > 1;
		} );
	}

	function formatsFromWords( list ) {
		var synonyms = ( index && index.synonyms ) || {};
		var found = [];

		list.forEach( function ( word ) {
			var keys = [ word, stem( word ) ];
			keys.forEach( function ( key ) {
				if ( synonyms[ key ] ) {
					synonyms[ key ].forEach( function ( slug ) {
						if ( found.indexOf( slug ) === -1 ) {
							found.push( slug );
						}
					} );
				}
			} );
			( ( index && index.formats ) || [] ).forEach( function ( format ) {
				if ( clean( format.label ).indexOf( stem( word ) ) !== -1 && found.indexOf( format.slug ) === -1 ) {
					found.push( format.slug );
				}
			} );
		} );

		return found;
	}

	function score( product, list, formatHints ) {
		var title = clean( product.title );
		var creator = clean( product.creator );
		var keywords = clean( ( product.keywords || [] ).join( ' ' ) );
		var total = 0;

		list.forEach( function ( word ) {
			var root = stem( word );
			if ( hasWord( title, word ) ) {
				total += 10;
			} else if ( hasWord( title, root ) ) {
				total += 7;
			}
			if ( creator && ( hasWord( creator, word ) || hasWord( creator, root ) ) ) {
				total += 9;
			}
			if ( product.genre && hasWord( product.genre.replace( /-/g, ' ' ), root ) ) {
				total += 5;
			}
			if ( hasWord( keywords, root ) ) {
				total += 3;
			}
		} );

		if ( formatHints.length && formatHints.indexOf( product.format ) !== -1 ) {
			total += 4;
		}
		if ( ! product.inStock ) {
			total -= 2;
		}

		return total;
	}

	function findAnswer( list ) {
		var answers = ( index && index.answers ) || [];
		var best = null;
		var bestScore = 0;

		answers.forEach( function ( answer ) {
			var hits = 0;
			( answer.triggers || [] ).forEach( function ( trigger ) {
				var triggerWords = words( trigger );
				if ( ! triggerWords.length ) {
					return;
				}
				var matched = triggerWords.filter( function ( word ) {
					return list.indexOf( word ) !== -1 || list.indexOf( stem( word ) ) !== -1 ||
						list.some( function ( item ) {
							return stem( item ) === stem( word );
						} );
				} ).length;
				
				if ( matched === triggerWords.length ) {
					hits = Math.max( hits, 3 + triggerWords.length );
				} else if ( matched > 0 ) {
					hits = Math.max( hits, matched );
				}
			} );
			if ( hits > bestScore ) {
				bestScore = hits;
				best = answer;
			}
		} );

		return bestScore >= 3 ? best : null;
	}

	function budgetFromWords( text ) {
		var match = clean( text ).match( /(?:under|below|less than|up to|max)\s*(\d+)/ );
		if ( match ) {
			return { min: 0, max: parseFloat( match[ 1 ] ) };
		}
		match = clean( text ).match( /(?:over|above|more than)\s*(\d+)/ );
		if ( match ) {
			return { min: parseFloat( match[ 1 ] ), max: 0 };
		}

		return null;
	}

	function filterProducts( criteria ) {
		var products = ( index && index.products ) || [];

		return products.filter( function ( product ) {
			if ( criteria.format && product.format !== criteria.format ) {
				return false;
			}
			if ( criteria.genre && product.genre !== criteria.genre ) {
				return false;
			}
			if ( criteria.budget ) {
				if ( criteria.budget.max > 0 && product.price > criteria.budget.max ) {
					return false;
				}
				if ( criteria.budget.min > 0 && product.price < criteria.budget.min ) {
					return false;
				}
			}
			var fields = criteria.fields || {};
			var ok = true;
			Object.keys( fields ).forEach( function ( key ) {
				var wanted = String( fields[ key ] ).toLowerCase();
				var has = String( ( product.fields || {} )[ key ] || '' ).toLowerCase();
				if ( wanted && has.indexOf( wanted ) === -1 ) {
					ok = false;
				}
			} );

			return ok;
		} );
	}

	function shopUrl( criteria ) {
		var base = config.shop || '/';
		( ( index && index.formats ) || [] ).forEach( function ( format ) {
			if ( format.slug === criteria.format ) {
				base = format.url;
			}
		} );

		var params = [];
		if ( criteria.genre ) {
			params.push( 'genre=' + encodeURIComponent( criteria.genre ) );
		}
		Object.keys( criteria.fields || {} ).forEach( function ( key ) {
			params.push( encodeURIComponent( key ) + '=' + encodeURIComponent( criteria.fields[ key ] ) );
		} );
		if ( criteria.budget ) {
			if ( criteria.budget.min > 0 ) {
				params.push( 'min_price=' + criteria.budget.min );
			}
			if ( criteria.budget.max > 0 ) {
				params.push( 'max_price=' + criteria.budget.max );
			}
		}

		return params.length ? base + '?' + params.join( '&' ) : base;
	}

	/* ---------- Routes ---------- */

	function greet() {
		if ( started ) {
			return;
		}
		started = true;

		say( config.greeting || '' );
		quickButtons();
	}

	function quickButtons() {
		options( [
			{ label: strings.choose || 'Help me choose', action: startFinder },
			{ label: strings.browse || 'Browse a format', action: browseFormats },
			{ label: strings.delivery || 'Delivery and returns', action: function () {
				answerByTrigger( 'delivery' );
			} },
			{ label: strings.track || 'Track my order', action: trackOrder },
			{ label: strings.ask || 'Ask a question', action: askForm }
		] );
	}

	function browseFormats() {
		var formats = ( index && index.formats ) || [];
		if ( ! formats.length ) {
			say( strings.noMatch || '' );
			askButton();
			return;
		}
		options( formats.map( function ( format ) {
			return {
				label: format.label,
				action: function () {
					window.location.href = format.url;
				}
			};
		} ) );
	}

	function startFinder() {
		finder = { format: '', filters: {}, fields: {}, step: 0, list: [] };
		var formats = ( index && index.formats ) || [];

		say( ( strings.browse || '' ) + '?' );
		options( formats.map( function ( format ) {
			return {
				label: format.label,
				action: function () {
					finder.format = format.slug;
					finder.list = format.finder || [];
					finder.step = 0;
					nextFinderStep();
				}
			};
		} ).concat( [ {
			label: strings.anyFormat || 'Any format',
			action: function () {
				finder.format = '';
				finder.list = [];
				askGenre();
			}
		} ] ) );
	}

	function nextFinderStep() {
		if ( finder.step >= finder.list.length ) {
			askGenre();
			return;
		}

		var field = finder.list[ finder.step++ ];
		say( field.question );
		options( ( field.options || [] ).map( function ( option ) {
			return {
				label: option,
				action: function () {
					finder.fields[ field.key ] = option;
					nextFinderStep();
				}
			};
		} ).concat( [ {
			label: strings.noPreference || 'No preference',
			action: nextFinderStep
		} ] ) );
	}

	function askGenre() {
		var genres = ( ( index && index.genres ) || [] ).slice( 0, 8 );
		if ( ! genres.length ) {
			askBudget();
			return;
		}

		say( strings.genreAsk || '' );
		options( genres.map( function ( genre ) {
			return {
				label: genre.name,
				action: function () {
					finder.genre = genre.slug;
					askBudget();
				}
			};
		} ).concat( [ {
			label: strings.noPreference || 'No preference',
			action: askBudget
		} ] ) );
	}

	function askBudget() {
		var bands = ( index && index.budgets ) || [];
		if ( ! bands.length ) {
			showFinderResults();
			return;
		}

		say( strings.budget || '' );
		options( bands.map( function ( band ) {
			return {
				label: band.label,
				action: function () {
					finder.budget = { min: band.min, max: band.max };
					showFinderResults();
				}
			};
		} ).concat( [ {
			label: strings.noPreference || 'No preference',
			action: showFinderResults
		} ] ) );
	}

	function showFinderResults() {
		var criteria = {
			format: finder.format,
			genre: finder.genre,
			budget: finder.budget,
			fields: finder.fields
		};

		var matches = filterProducts( criteria ).filter( function ( product ) {
			return product.inStock;
		} );

		if ( ! matches.length ) {
			// Loosen the narrowest thing first rather than ending on nothing.
			var loosened = Object.assign( {}, criteria, { fields: {} } );
			matches = filterProducts( loosened ).filter( function ( product ) {
				return product.inStock;
			} );
			say( matches.length ? ( strings.nothingFits || '' ) : ( strings.noMatch || '' ) );
			if ( ! matches.length ) {
				browseFormats();
				askButton();
				return;
			}
		} else {
			say( strings.results || '' );
		}

		showProducts( matches, shopUrl( criteria ) );
		quickButtons();
	}

	function answerByTrigger( text ) {
		load().then( function () {
			var answer = findAnswer( words( text ) );
			if ( answer ) {
				say( answer.title + '\n' + answer.text );
				if ( answer.buttonText && answer.buttonUrl ) {
					linkButton( answer.buttonText, answer.buttonUrl );
				}
			} else {
				say( strings.noMatch || '' );
				askButton();
			}
			quickButtons();
		} );
	}

	function trackOrder() {
		say( strings.trackText || '' );
		if ( strings.trackUrl ) {
			linkButton( strings.track || 'Track my order', strings.trackUrl );
		}
		quickButtons();
	}

	function askButton() {
		options( [ { label: strings.ask || 'Ask a question', action: askForm } ] );
	}

	function askForm( prefill ) {
		var wrap = el( 'div', 'orwo-chat__message' );
		var fields = el( 'form', 'orwo-chat__fields' );

		function field( type, name, label, value ) {
			var paragraph = el( 'p' );
			var id = 'orwo-ask-' + name;
			var labelEl = el( 'label', null, label );
			labelEl.setAttribute( 'for', id );
			var control = 'textarea' === type ? el( 'textarea' ) : el( 'input' );
			if ( 'textarea' !== type ) {
				control.type = type;
			} else {
				control.rows = 3;
			}
			control.id = id;
			control.name = name;
			control.required = true;
			if ( value ) {
				control.value = value;
			}
			paragraph.appendChild( labelEl );
			paragraph.appendChild( control );
			fields.appendChild( paragraph );

			return control;
		}

		var name = field( 'text', 'name', strings.name || 'Your name' );
		var email = field( 'email', 'email', strings.email || 'Your email address' );
		var question = field( 'textarea', 'question', strings.question || 'Your question', prefill || '' );

		var submit = el( 'button', 'orwo-button', strings.send || 'Send' );
		submit.type = 'submit';
		fields.appendChild( submit );

		var status = el( 'p', null, '' );
		fields.appendChild( status );

		fields.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			if ( ! fields.reportValidity() ) {
				return;
			}
			submit.disabled = true;
			status.textContent = strings.sending || '';

			fetch( config.rest + 'enquiry', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					name: name.value,
					email: email.value,
					question: question.value,
					website: '',
					nonce: config.nonce,
					page: config.page
				} )
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					submit.disabled = false;
					if ( result.ok && result.data && result.data.ok ) {
						fields.remove();
						say( result.data.message || strings.thanks || '' );
					} else {
						status.textContent = ( result.data && result.data.message ) || strings.failed || '';
					}
				} )
				.catch( function () {
					submit.disabled = false;
					status.textContent = strings.failed || '';
				} );
		} );

		wrap.appendChild( fields );
		log.appendChild( wrap );
		scrollDown();
		name.focus();
	}

	function logUnanswered( question ) {
		fetch( config.rest + 'unanswered', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { question: question } )
		} ).catch( function () {
			/* The learning loop is a convenience; a failure must never reach the visitor. */
		} );
	}

	/* ---------- Typed questions ---------- */

	function handleTyped( text ) {
		say( text, 'visitor' );

		load().then( function () {
			if ( ! index ) {
				say( strings.noMatch || '' );
				askButton();
				return;
			}

			var list = words( text );
			var answer = findAnswer( list );
			if ( answer ) {
				say( answer.text );
				if ( answer.buttonText && answer.buttonUrl ) {
					linkButton( answer.buttonText, answer.buttonUrl );
				}
				quickButtons();
				return;
			}

			var hints = formatsFromWords( list );
			var budget = budgetFromWords( text );
			var scored = ( index.products || [] ).map( function ( product ) {
				return { product: product, value: score( product, list, hints ) };
			} ).filter( function ( entry ) {
				if ( entry.value <= 0 ) {
					return false;
				}
				if ( budget ) {
					if ( budget.max > 0 && entry.product.price > budget.max ) {
						return false;
					}
					if ( budget.min > 0 && entry.product.price < budget.min ) {
						return false;
					}
				}

				return true;
			} ).sort( function ( a, b ) {
				return b.value - a.value;
			} );

			var strong = scored.filter( function ( entry ) {
				return entry.value >= 9;
			} );
			var medium = scored.filter( function ( entry ) {
				return entry.value >= 4 && entry.value < 9;
			} );

			if ( strong.length ) {
				say( strings.results || '' );
				showProducts( strong.map( function ( entry ) {
					return entry.product;
				} ), hints.length ? shopUrl( { format: hints[ 0 ], fields: {} } ) : null );
				quickButtons();
				return;
			}

			if ( medium.length ) {
				say( strings.didYouMean || '' );
				options( medium.slice( 0, 4 ).map( function ( entry ) {
					return {
						label: entry.product.title,
						action: function () {
							window.location.href = entry.product.url;
						}
					};
				} ) );
				askButton();
				return;
			}

			if ( hints.length ) {
				// The words named a format but nothing else: offer that format rather than giving up.
				say( strings.results || '' );
				var byFormat = filterProducts( { format: hints[ 0 ], fields: {}, budget: budget } ).filter( function ( product ) {
					return product.inStock;
				} );
				if ( byFormat.length ) {
					showProducts( byFormat, shopUrl( { format: hints[ 0 ], fields: {}, budget: budget } ) );
					quickButtons();
					return;
				}
			}

			logUnanswered( text );
			say( strings.noMatch || '' );
			browseFormats();
			askForm( text );
		} );
	}

	/* ---------- Product-page mode ---------- */

	function productChips() {
		var product = config.product || {};
		if ( ! product.id ) {
			return;
		}

		options( [
			{
				label: strings.isInStock || 'Is it in stock?',
				action: function () {
					say( product.inStock ? ( strings.inStock || '' ) + ( product.price ? ' — ' + product.price : '' ) : strings.soldOut || '' );
					productChips();
				}
			},
			{
				label: strings.specsTitle || 'What condition is it?',
				action: function () {
					var lines = ( product.specs || [] ).map( function ( row ) {
						return row.label + ': ' + row.value;
					} );
					say( lines.length ? lines.join( '\n' ) : ( product.condition || '' ) );
					productChips();
				}
			},
			{
				label: strings.willItPlay || 'Will it play on my player?',
				action: function () {
					var region = ( product.specs || [] ).filter( function ( row ) {
						return /region|resolution/i.test( row.label );
					} ).map( function ( row ) {
						return row.label + ': ' + row.value;
					} );
					if ( region.length ) {
						say( region.join( '\n' ) );
					} else {
						answerByTrigger( 'region' );
					}
					productChips();
				}
			},
			{
				label: strings.similar || 'Show similar',
				action: function () {
					load().then( function () {
						var similar = ( ( index && index.products ) || [] ).filter( function ( item ) {
							if ( item.id === product.id || ! item.inStock ) {
								return false;
							}

							return item.format === product.format &&
								( ( product.creator && item.creator === product.creator ) || ! product.creator || item.genre );
						} );
						showProducts( similar, product.format ? shopUrl( { format: product.format, fields: {} } ) : null );
						productChips();
					} );
				}
			},
			{ label: strings.ask || 'Ask a question', action: function () {
				askForm( product.title ? 'About ' + product.title + ': ' : '' );
			} }
		] );
	}

	/* ---------- Opening and closing ---------- */

	function focusable() {
		return Array.prototype.slice.call(
			panel.querySelectorAll( 'a[href], button:not([disabled]), input, textarea, select' )
		).filter( function ( node ) {
			return null !== node.offsetParent;
		} );
	}

	function open( prefill ) {
		if ( ! panel.hidden ) {
			if ( prefill ) {
				handleTyped( prefill );
			}
			return;
		}

		lastFocus = document.activeElement;
		panel.hidden = false;
		document.querySelectorAll( '[data-orwo-chat-open]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-expanded', 'true' );
		} );

		load().then( function () {
			if ( ! started ) {
				greet();
				if ( config.product && config.product.id ) {
					say( config.product.title );
					productChips();
				}
			}
			if ( prefill ) {
				handleTyped( prefill );
			}
		} );

		if ( input ) {
			input.focus();
		}
	}

	function close() {
		panel.hidden = true;
		document.querySelectorAll( '[data-orwo-chat-open]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-expanded', 'false' );
		} );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var opener = event.target.closest( '[data-orwo-chat-open]' );
		if ( opener ) {
			event.preventDefault();
			open();
			return;
		}

		var closer = event.target.closest( '[data-orwo-chat-close]' );
		if ( closer ) {
			event.preventDefault();
			close();
			return;
		}

		var chip = event.target.closest( '[data-orwo-chip]' );
		if ( chip ) {
			event.preventDefault();
			open( chip.getAttribute( 'data-orwo-chip' ) );
		}
	} );

	document.querySelectorAll( '[data-orwo-chat-start]' ).forEach( function ( startForm ) {
		startForm.addEventListener( 'submit', function ( event ) {
			var field = startForm.querySelector( 'input[name="q"]' );
			if ( ! field || ! field.value.trim() ) {
				return;
			}
			event.preventDefault();
			open( field.value.trim() );
			field.value = '';
		} );
	} );

	if ( form && input ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var text = input.value.trim();
			if ( ! text ) {
				return;
			}
			input.value = '';
			handleTyped( text );
		} );
	}

	panel.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			close();
			return;
		}
		if ( 'Tab' !== event.key ) {
			return;
		}

		// Focus stays inside the panel while it is open.
		var nodes = focusable();
		if ( ! nodes.length ) {
			return;
		}
		var first = nodes[ 0 ];
		var last = nodes[ nodes.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	} );
}());
