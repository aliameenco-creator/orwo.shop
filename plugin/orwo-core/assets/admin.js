/**
 * Product screen behaviour: show only the chosen format's fields, and suggest existing genre, creator and label
 * names so the catalogue never collects "Miles Davis" twice. No jQuery.
 */
(function () {
	'use strict';

	var panel = document.querySelector( '.orwo-panel' );
	if ( ! panel ) {
		return;
	}

	var select = panel.querySelector( '#orwo-format' );
	var groups = Array.prototype.slice.call( panel.querySelectorAll( '.orwo-fields' ) );

	function showFormat( slug ) {
		groups.forEach( function ( group ) {
			group.hidden = group.getAttribute( 'data-for' ) !== slug;
		} );
	}

	if ( select ) {
		select.addEventListener( 'change', function () {
			showFormat( select.value );
		} );
		showFormat( select.value );
	}

	var terms = ( window.orwoAdmin && window.orwoAdmin.terms ) || {};

	panel.querySelectorAll( '.orwo-suggest' ).forEach( function ( input ) {
		var available = terms[ input.getAttribute( 'data-taxonomy' ) ] || [];
		if ( ! available.length ) {
			return;
		}

		var wrap = document.createElement( 'span' );
		wrap.className = 'orwo-suggest-wrap';
		input.parentNode.insertBefore( wrap, input );
		wrap.appendChild( input );

		var list = document.createElement( 'ul' );
		list.className = 'orwo-suggest-list';
		list.hidden = true;
		wrap.appendChild( list );

		function currentFragment() {
			var parts = input.value.split( ',' );
			return parts[ parts.length - 1 ].trim();
		}

		function apply( name ) {
			var parts = input.value.split( ',' );
			parts[ parts.length - 1 ] = ' ' + name;
			input.value = parts.join( ',' ).replace( /^\s+/, '' );
			list.hidden = true;
			input.focus();
		}

		function render() {
			var fragment = currentFragment().toLowerCase();
			list.textContent = '';
			if ( fragment.length < 1 ) {
				list.hidden = true;
				return;
			}
			var matches = available.filter( function ( name ) {
				return name.toLowerCase().indexOf( fragment ) !== -1;
			} ).slice( 0, 8 );
			if ( ! matches.length ) {
				list.hidden = true;
				return;
			}
			matches.forEach( function ( name ) {
				var item = document.createElement( 'li' );
				var button = document.createElement( 'button' );
				button.type = 'button';
				button.textContent = name;
				button.addEventListener( 'click', function () {
					apply( name );
				} );
				item.appendChild( button );
				list.appendChild( item );
			} );
			list.hidden = false;
		}

		input.addEventListener( 'input', render );
		input.addEventListener( 'focus', render );
		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				list.hidden = true;
			}
		} );
		document.addEventListener( 'click', function ( event ) {
			if ( ! wrap.contains( event.target ) ) {
				list.hidden = true;
			}
		} );
	} );
}());
