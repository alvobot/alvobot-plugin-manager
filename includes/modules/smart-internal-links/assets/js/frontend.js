/**
 * Smart Internal Links - Frontend Ad Adjacency Guard
 *
 * Strategy:
 *  1. PHP renders CTAs with [data-sil-pending], keeping them invisible (opacity: 0).
 *     The element still occupies its reserved space, so there is zero CLS during repositioning.
 *  2. This script runs after window.load — by this point all ad network scripts
 *     (AdSense, Ad Manager, Ad Inserter, Ezoic, Mediavine, etc.) have already
 *     placed their containers in the DOM.
 *  3. For each pending CTA:
 *     a. If NOT adjacent to an ad → reveal immediately.
 *     b. If adjacent → try ±1 slot within the content container.
 *        - Success → move to safe slot → reveal.
 *        - Failure (e.g., surrounded by ads) → hide entirely (display:none).
 *           Better invisible than shown next to an ad.
 *
 * No-JS fallback: a <noscript> tag in the <head> forces opacity:1 for all CTAs,
 * so users without JS see them normally (PHP ad-detection still applies).
 */

( function () {
	'use strict';

	/**
	 * DOM selectors for ad containers.
	 * Mirrors PHP is_ad_block() patterns but operates on the fully-rendered DOM,
	 * including ads injected by JavaScript after PHP output.
	 *
	 * Order: most common first (AdSense, Ad Manager, Ad Inserter).
	 */
	var AD_SELECTORS = [
		// Google AdSense — <ins class="adsbygoogle">
		'ins.adsbygoogle',

		// Google Ad Manager / DFP — <div id="div-gpt-ad-...">
		'[id*="div-gpt-ad"]',

		// Ad Inserter — PHP outputs empty placeholder; JS fills it.
		// The placeholder keeps its classes even after JS injection.
		'[class*="ai-insert-"]',
		'[class*="ai-viewport-"]',
		'[data-insertion-position]',

		// Ezoic
		'[id^="ezoic-pub-ad"]',
		'[class*="ezoic-"]',

		// Raptive / AdThrive / CafeMedia
		'[class*="raptive-"]',
		'[class*="adthrive-"]',
		'[class*="cafemedia-"]',

		// Mediavine
		'[class*="mediavine-ad"]',
		'[id*="mediavine"]',

		// Advanced Ads
		'[class*="advads-"]',

		// WP QUADS / Quick AdSense
		'[class*="wp-quads"]',
		'[class*="quads-ad"]',

		// Setupad
		'[class*="setupad-"]',

		// Monumetric
		'[class*="monumetric-"]',

		// Carbon Ads
		'#carbonads',

		// Taboola
		'[class*="tbl-"]',
		'[class*="taboola-"]',

		// Outbrain
		'[class*="OUTBRAIN"]',
		'[class*="ob-widget-"]',

		// Mgid
		'[class*="mgid-"]',

		// PropellerAds
		'[class*="propeller-ads-"]',

		// BuySellAds
		'[class*="bsa-ads-"]',

		// WP Ad Manager
		'[class*="wpad-placement-"]',

		// AdSense / DFP iframes injected after page load
		'iframe[id*="google_ads_iframe"]',
		'iframe[src*="googlesyndication.com"]',
		'iframe[src*="doubleclick.net"]',
		'iframe[src*="googleadservices.com"]',
	];

	/**
	 * Returns true if `el` itself matches an ad selector,
	 * or if it contains any ad element.
	 *
	 * @param {Element|null} el
	 * @returns {boolean}
	 */
	function isAdElement( el ) {
		if ( ! el || el.nodeType !== 1 ) {
			return false;
		}
		for ( var i = 0; i < AD_SELECTORS.length; i++ ) {
			try {
				if ( el.matches( AD_SELECTORS[ i ] ) || el.querySelector( AD_SELECTORS[ i ] ) ) {
					return true;
				}
			} catch ( e ) {
				// Skip selectors unsupported by the current browser.
			}
		}
		return false;
	}

	/**
	 * Returns true if `el`'s immediate previous or next element sibling is an ad.
	 *
	 * @param {Element} el
	 * @returns {boolean}
	 */
	function isAdjacentToAd( el ) {
		return isAdElement( el.previousElementSibling ) ||
			isAdElement( el.nextElementSibling );
	}

	/**
	 * Attempts to move `cta` to a safe slot (±1) within its parent element.
	 *
	 * Rules:
	 * - Never moves to slot 0 (before the first child) — mirrors PHP's "after 2nd paragraph" rule.
	 * - Uses a detach-then-reattach approach so sibling indices remain clean during evaluation.
	 * - If both ±1 slots are also unsafe, restores the original position and returns false.
	 *
	 * @param {Element} cta
	 * @returns {boolean} true if successfully repositioned, false otherwise.
	 */
	function tryReposition( cta ) {
		var parent = cta.parentNode;
		if ( ! parent ) {
			return false;
		}

		// Snapshot of all siblings EXCLUDING the CTA itself.
		// After cta is removed from the DOM, siblings[i] === original parent.children[i > idx ? i+1 : i].
		var siblings = Array.prototype.filter.call(
			parent.children,
			function ( child ) { return child !== cta; }
		);

		// The CTA's current slot index among its siblings.
		// "Slot N" means: insert before siblings[N], i.e., after siblings[N-1].
		var currentSlot = Array.prototype.indexOf.call( parent.children, cta );

		// Detach CTA so sibling lookups below are clean.
		parent.removeChild( cta );

		/**
		 * Checks whether inserting at `slot` would be safe.
		 * Slot < 1 is forbidden (never before the second content element).
		 *
		 * @param {number} slot
		 * @returns {boolean}
		 */
		function slotIsSafe( slot ) {
			if ( slot < 1 ) {
				return false;
			}
			var prevEl = slot > 0 ? ( siblings[ slot - 1 ] || null ) : null;
			var nextEl = slot < siblings.length ? ( siblings[ slot ] || null ) : null;
			return ! isAdElement( prevEl ) && ! isAdElement( nextEl );
		}

		// Try one slot down, then one slot up.
		var targetSlot = null;
		if ( slotIsSafe( currentSlot + 1 ) ) {
			targetSlot = currentSlot + 1;
		} else if ( slotIsSafe( currentSlot - 1 ) ) {
			targetSlot = currentSlot - 1;
		}

		if ( targetSlot !== null ) {
			// Insert at the chosen safe slot.
			parent.insertBefore( cta, siblings[ targetSlot ] || null );
			return true;
		}

		// No safe slot found: restore original position.
		parent.insertBefore( cta, siblings[ currentSlot ] || null );
		return false;
	}

	/**
	 * Reveals a CTA by removing the [data-sil-pending] attribute.
	 * The CSS transition (opacity 0 → 1) kicks in automatically.
	 *
	 * @param {Element} cta
	 */
	function reveal( cta ) {
		cta.removeAttribute( 'data-sil-pending' );
	}

	/**
	 * Main routine: evaluate all pending CTAs and reveal or reposition them.
	 */
	function run() {
		var ctas = document.querySelectorAll( 'nav.alvobot-sil[data-sil-pending]' );
		if ( ! ctas.length ) {
			return;
		}

		Array.prototype.forEach.call( ctas, function ( cta ) {
			if ( ! isAdjacentToAd( cta ) ) {
				// PHP placed it correctly — just reveal.
				reveal( cta );
				return;
			}

			// Adjacent to an ad: attempt DOM repositioning.
			var moved = tryReposition( cta );
			if ( moved ) {
				reveal( cta );
			} else {
				// Completely surrounded by ads and no safe slot exists.
				// Hide entirely — never show a CTA glued to an ad.
				cta.style.display = 'none';
				cta.removeAttribute( 'data-sil-pending' );
			}
		} );
	}

	// Run after all third-party scripts (ad networks) have executed.
	// If the document is already complete (e.g., script loaded via defer/async
	// and the load event already fired), run synchronously.
	if ( document.readyState === 'complete' ) {
		run();
	} else {
		window.addEventListener( 'load', run );
	}

} )();
