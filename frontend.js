/**
 * Post Filter for Block Editor — frontend.js
 *
 * Handles the live dropdown filter on the frontend.
 * Fetches filtered posts from the custom REST API endpoint and
 * replaces the post list HTML without a page reload.
 *
 * Depends on: pfbeData (wp_localize_script) containing:
 *   - restUrl {string} Full URL to /wp-json/pfbe/v1/posts
 *   - nonce   {string} wp_rest nonce
 *   - i18n    {Object} Translated strings
 *
 * @package PostFilterBlockEditor
 */

( function () {
	'use strict';

	/**
	 * CSS class added to the list container while a request is in flight.
	 *
	 * @type {string}
	 */
	var LOADING_CLASS = 'pfbe-loading';

	/**
	 * Initialise filter behaviour for every block instance on the page.
	 *
	 * Multiple instances are supported — each select targets its own
	 * post list via the data-list attribute.
	 *
	 * @return {void}
	 */
	function initFilterBlocks() {
		if ( typeof pfbeData === 'undefined' ) {
			return;
		}

		var selects = document.querySelectorAll( '.pfbe-select' );

		if ( ! selects.length ) {
			return;
		}

		selects.forEach( function ( select ) {
			select.addEventListener( 'change', onFilterChange );
		} );
	}

	/**
	 * Handle the select change event for a single block instance.
	 *
	 * @param  {Event} event DOM change event.
	 * @return {void}
	 */
	function onFilterChange( event ) {
		var select     = event.target;
		var listId     = select.getAttribute( 'data-list' );
		var difficulty = select.value;

		if ( ! listId ) {
			return;
		}

		var listEl = document.getElementById( listId );

		if ( ! listEl ) {
			return;
		}

		fetchPosts( difficulty, listEl, select );
	}

	/**
	 * Fetch posts from the REST API and update the DOM.
	 *
	 * @param  {string}      difficulty Filter value — 'easy', 'medium', 'hard', or ''.
	 * @param  {HTMLElement} listEl     The posts list container to update.
	 * @param  {HTMLElement} select     The select element (disabled during request).
	 * @return {void}
	 */
	function fetchPosts( difficulty, listEl, select ) {
		var url = new URL( pfbeData.restUrl );

		if ( difficulty ) {
			url.searchParams.set( 'difficulty', difficulty );
		}

		// Set loading state.
		listEl.classList.add( LOADING_CLASS );
		listEl.setAttribute( 'aria-busy', 'true' );
		select.setAttribute( 'disabled', 'disabled' );

		fetch( url.toString(), {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': pfbeData.nonce,
				'Accept':     'application/json',
			},
		} )
		.then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP error ' + response.status );
			}
			return response.json();
		} )
		.then( function ( data ) {
			if ( data && typeof data.html === 'string' ) {
				listEl.innerHTML = data.html;
			}
		} )
		.catch( function () {
			listEl.innerHTML = '<p class="pfbe-error">' + pfbeData.i18n.loadError + '</p>';
		} )
		.finally( function () {
			listEl.classList.remove( LOADING_CLASS );
			listEl.removeAttribute( 'aria-busy' );
			select.removeAttribute( 'disabled' );
		} );
	}

	// ---------------------------------------------------------------------------
	// Bootstrap — run after the DOM is ready.
	// ---------------------------------------------------------------------------

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initFilterBlocks );
	} else {
		initFilterBlocks();
	}

}() );
