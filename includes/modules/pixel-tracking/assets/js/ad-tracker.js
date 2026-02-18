/**
 * AlvoBot Ad Tracker
 *
 * Captura eventos de anúncios do Google Ad Manager e envia para
 * Meta Pixel (fbq) e Google Analytics 4 (gtag).
 *
 * Eventos rastreados:
 *   - ad_vignette_open : abertura de interstitial/vinheta
 *   - ad_impression    : visualização de banner (via GPT API ou IntersectionObserver)
 *   - ad_click         : clique em banner ou vinheta (técnica blur/focus)
 *
 * Estratégia de impressão:
 *   1. GPT pubads 'impressionViewable' (primário — mais preciso, IAB-compliant)
 *   2. IntersectionObserver ≥50% visível por ≥1s (fallback / complemento)
 *   O mapa impressionsFired evita duplo disparo entre as duas estratégias.
 */
(function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Debug
	// -------------------------------------------------------------------------

	var debug = !! (window.alvobot_pixel_config && window.alvobot_pixel_config.debug_enabled);
	var LOG_PREFIX = '[AlvoBot AdTracker]';

	function log() {
		if ( ! debug || ! window.console || ! window.console.log) {
			return;
		}
		var args = Array.prototype.slice.call( arguments );
		args.unshift( LOG_PREFIX );
		window.console.log.apply( window.console, args );
	}

	// -------------------------------------------------------------------------
	// Dispatch helpers
	// -------------------------------------------------------------------------

	var META_EVENT_NAMES = {
		ad_vignette_open: 'AdVignetteOpen',
		ad_impression:    'AdImpression',
		ad_click:         'AdClick',
	};

	function sendToMeta(ga4EventName, params) {
		if (typeof window.fbq !== 'function') {
			log( 'fbq indisponível para', ga4EventName );
			return;
		}
		var metaName = META_EVENT_NAMES[ga4EventName] || ga4EventName;
		window.fbq( 'trackCustom', metaName, params );
		log( 'Meta evento enviado:', metaName, params );
	}

	function sendToGA4(eventName, params) {
		if (typeof window.gtag !== 'function') {
			log( 'gtag indisponível para', eventName );
			return;
		}
		window.gtag( 'event', eventName, params );
		log( 'GA4 evento enviado:', eventName, params );
	}

	function dispatchAdEvent(eventName, adPosition, adSlotId) {
		var params = {
			ad_position: adPosition,
			ad_slot_id:  adSlotId || '',
			page_url:    window.location.href,
			timestamp:   new Date().toISOString(),
		};
		sendToMeta( eventName, params );
		sendToGA4( eventName, params );
	}

	// -------------------------------------------------------------------------
	// Helpers de posição (reutilizados por impressão e clique)
	// -------------------------------------------------------------------------

	/**
	 * Determina ad_position a partir de qualquer identificador de anúncio
	 * (ID de iframe, slot path do GPT ou ID de elemento DOM).
	 *
	 * @param  {string} identifier
	 * @return {string} top | content_1 | content_2 | interstitial | unknown
	 */
	function positionFromAdIdentifier(identifier) {
		if ( ! identifier) {
			return 'unknown';
		}
		if (identifier.indexOf( 'interstitial' ) !== -1) {
			return 'interstitial';
		}
		// content antes de top para evitar falso match em "_content_1_top" (hipotético)
		if (identifier.indexOf( '_content_1' ) !== -1) {
			return 'content_1';
		}
		if (identifier.indexOf( '_content_2' ) !== -1) {
			return 'content_2';
		}
		if (identifier.indexOf( '_top' ) !== -1) {
			return 'top';
		}
		return 'unknown';
	}

	/**
	 * Extrai o slot ID do Ad Manager a partir do ID do iframe.
	 * Ex: google_ads_iframe_/22976784714/exp_desktop_top_0 → /22976784714/exp_desktop_top
	 *
	 * @param  {string} iframeId
	 * @return {string}
	 */
	function slotIdFromIframeId(iframeId) {
		if ( ! iframeId) {
			return '';
		}
		var match = iframeId.match( /^google_ads_iframe_(\/[\w/]+?)_\d+$/ );
		return match ? match[1] : '';
	}

	// -------------------------------------------------------------------------
	// 1. VINHETA (ad_vignette_open)
	// -------------------------------------------------------------------------

	var vignetteOpened = false;

	function checkVignetteHash() {
		if (vignetteOpened) {
			return;
		}
		if (window.location.hash === '#google_vignette') {
			vignetteOpened = true;
			log( 'Vinheta detectada via hash da URL' );
			dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
		}
	}

	function watchVignetteDom() {
		if ( ! ('MutationObserver' in window)) {
			log( 'MutationObserver indisponível; detecção de vinheta via DOM desativada' );
			return;
		}

		function handleMutations(mutations, observer) {
			if (vignetteOpened) {
				observer.disconnect();
				return;
			}

			for (var i = 0; i < mutations.length; i++) {
				var mutation = mutations[i];

				if (
					mutation.type === 'attributes' &&
					mutation.attributeName === 'aria-hidden'
				) {
					var target   = mutation.target;
					var targetId = target.id || '';
					if (
						(targetId.indexOf( 'interstitial' ) !== -1 ||
							target.getAttribute( 'data-vignette-loaded' ) !== null) &&
						target.getAttribute( 'aria-hidden' ) === 'false'
					) {
						vignetteOpened = true;
						log( 'Vinheta detectada via MutationObserver (aria-hidden → false)', targetId );
						dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
						observer.disconnect();
						return;
					}
				}

				if (mutation.type === 'childList' && mutation.addedNodes.length) {
					for (var j = 0; j < mutation.addedNodes.length; j++) {
						var node   = mutation.addedNodes[j];
						var nodeId = node.id || '';
						if (
							node.nodeType === 1 &&
							(nodeId.indexOf( 'interstitial' ) !== -1 ||
								node.getAttribute( 'data-vignette-loaded' ) !== null) &&
							node.getAttribute( 'aria-hidden' ) === 'false'
						) {
							vignetteOpened = true;
							log( 'Vinheta detectada via nó inserido (aria-hidden=false)', nodeId );
							dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
							observer.disconnect();
							return;
						}
					}
				}
			}
		}

		var domObserver = new MutationObserver( handleMutations );
		domObserver.observe(
			document.body,
			{
				childList:       true,
				subtree:         true,
				attributes:      true,
				attributeFilter: ['aria-hidden', 'data-vignette-loaded'],
			}
		);
		log( 'MutationObserver de vinheta configurado' );
	}

	// -------------------------------------------------------------------------
	// 2. IMPRESSÃO DE BANNER (ad_impression)
	// -------------------------------------------------------------------------

	/** Controle de deduplicação compartilhado entre GPT e IntersectionObserver. */
	var impressionsFired = {};

	/**
	 * Descobre slots de anúncios dinamicamente via seletor genérico.
	 * Convenciona que elementos com id="av_{name}" mapeiam para:
	 *   position : name  (ex: "top", "content_1", "content_2")
	 *   slotId   : /22976784714/exp_desktop_{name}
	 *
	 * @return {Array<{el: Element, id: string, position: string, slotId: string}>}
	 */
	function buildSlotsFromDom() {
		var slots      = [];
		var NETWORK_ID = '22976784714';

		document.querySelectorAll( '[id^="av_"]' ).forEach(
			function (el) {
				var elId = el.id;
				var name = elId.slice( 3 ); // remove "av_"
				if ( ! name) {
					return;
				}
				slots.push(
					{
						el:       el,
						id:       elId,
						position: name,
						slotId:   '/' + NETWORK_ID + '/exp_desktop_' + name,
					}
				);
			}
		);

		log( 'Slots encontrados no DOM:', slots.map( function (s) { return s.id; } ) );
		return slots;
	}

	/**
	 * Estratégia primária: GPT pubads 'impressionViewable'.
	 * Usa a API nativa do Google Publisher Tag, que já implementa
	 * a definição IAB de viewability (50% visible / 1s).
	 *
	 * @return {boolean} true se o listener foi registrado com sucesso.
	 */
	function setupGPTImpressionTracking() {
		if (
			typeof window.googletag === 'undefined' ||
			! window.googletag.cmd
		) {
			log( 'googletag não disponível; pulando estratégia GPT' );
			return false;
		}

		window.googletag.cmd.push(
			function () {
				window.googletag.pubads().addEventListener(
					'impressionViewable',
					function (event) {
						var slot     = event.slot;
						var unitPath = slot.getAdUnitPath(); // ex: /22976784714/exp_desktop_top
						var position = positionFromAdIdentifier( unitPath );

						if (position === 'unknown' || position === 'interstitial') {
							return;
						}

						if (impressionsFired[unitPath]) {
							log( 'GPT impressionViewable: já disparado para', unitPath );
							return;
						}

						impressionsFired[unitPath] = true;
						log( 'GPT impressionViewable disparado:', position, unitPath );
						dispatchAdEvent( 'ad_impression', position, unitPath );
					}
				);
			}
		);

		log( 'GPT impressionViewable listener configurado' );
		return true;
	}

	/**
	 * Estratégia de fallback/complemento: IntersectionObserver.
	 * Também verifica se o anúncio foi realmente carregado via
	 * data-google-query-id antes de contar a impressão.
	 *
	 * @param {Array} slots Lista de slots retornada por buildSlotsFromDom().
	 */
	function setupImpressionObserver(slots) {
		if ( ! ('IntersectionObserver' in window)) {
			log( 'IntersectionObserver indisponível; rastreamento de impressão por fallback desativado' );
			return;
		}

		slots.forEach(
			function (slot) {
				var el             = slot.el;
				var visibilityTimer = null;

				var observer = new IntersectionObserver(
					function (entries) {
						entries.forEach(
							function (entry) {
								if (impressionsFired[slot.slotId]) {
									observer.disconnect();
									return;
								}

								if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
									if ( ! visibilityTimer) {
										log( 'Banner visível ≥50%, timer de 1s iniciado:', slot.position );
										visibilityTimer = setTimeout(
											function () {
												visibilityTimer = null;
												if (impressionsFired[slot.slotId]) {
													return;
												}

												// Verificação: o anúncio foi realmente carregado?
												if ( ! el.hasAttribute( 'data-google-query-id' )) {
													log( 'Impressão ignorada: anúncio ainda não carregado (sem data-google-query-id):', slot.id );
													return;
												}

												impressionsFired[slot.slotId] = true;
												log( 'Impressão de banner disparada (IntersectionObserver):', slot.position );
												dispatchAdEvent( 'ad_impression', slot.position, slot.slotId );
												observer.disconnect();
											},
											1000
										);
									}
								} else {
									if (visibilityTimer) {
										clearTimeout( visibilityTimer );
										visibilityTimer = null;
										log( 'Timer cancelado (banner saiu de visibilidade):', slot.position );
									}
								}
							}
						);
					},
					{threshold: 0.5}
				);

				observer.observe( el );
				log( 'IntersectionObserver configurado para:', slot.id );
			}
		);
	}

	/**
	 * Orquestra as duas estratégias de impressão.
	 * GPT e IntersectionObserver coexistem; impressionsFired previne duplo disparo.
	 */
	function setupImpressions() {
		var slots = buildSlotsFromDom();

		if ( ! slots.length) {
			log( 'Nenhum slot [id^="av_"] encontrado; rastreamento de impressão inativo' );
		}

		var gptActive = setupGPTImpressionTracking();
		if ( ! gptActive) {
			log( 'Usando apenas IntersectionObserver para impressões' );
		}

		// IntersectionObserver sempre configurado como fallback.
		// Se GPT já disparou para um slot, impressionsFired bloqueia o duplo envio.
		setupImpressionObserver( slots );
	}

	// -------------------------------------------------------------------------
	// 3. CLIQUE EM ANÚNCIO (ad_click)
	// -------------------------------------------------------------------------

	var clicksFired = {};

	function isAdIframe(el) {
		if ( ! el || el.tagName !== 'IFRAME') {
			return false;
		}
		var id = el.id || '';
		if (id.indexOf( 'google_ads_iframe' ) !== -1) {
			return true;
		}
		if (el.getAttribute( 'data-google-container-id' ) !== null) {
			return true;
		}
		if (el.getAttribute( 'data-is-safeframe' ) === 'true') {
			return true;
		}
		return false;
	}

	function setupClickTracking() {
		window.addEventListener(
			'blur',
			function () {
				setTimeout(
					function () {
						var activeEl = document.activeElement;
						if ( ! isAdIframe( activeEl )) {
							return;
						}

						var iframeId = activeEl.id || '';
						var position = positionFromAdIdentifier( iframeId );
						var slotId   = slotIdFromIframeId( iframeId );

						if (clicksFired[iframeId]) {
							log( 'Clique já disparado para este iframe:', iframeId );
							return;
						}
						clicksFired[iframeId] = true;

						log( 'Clique em anúncio detectado (blur/focus):', position, iframeId );
						dispatchAdEvent( 'ad_click', position, slotId );

						// Devolve foco à window e limpa flag após 3s para
						// permitir detecção de cliques subsequentes (ex: vinheta reaberta).
						setTimeout(
							function () {
								if (typeof window.focus === 'function') {
									window.focus();
								}
								delete clicksFired[iframeId];
							},
							3000
						);
					},
					0
				);
			}
		);

		log( 'Rastreamento de cliques (blur/focus) configurado' );
	}

	// -------------------------------------------------------------------------
	// Inicialização
	// -------------------------------------------------------------------------

	function init() {
		log( 'Inicializando AlvoBot Ad Tracker' );

		// 1. Vinheta via hash (carregamento + mudanças de hash)
		checkVignetteHash();
		window.addEventListener( 'hashchange', checkVignetteHash );

		// 2. Vinheta via DOM (MutationObserver)
		watchVignetteDom();

		// 3. Impressões (GPT + IntersectionObserver)
		setupImpressions();

		// 4. Cliques (blur/focus)
		setupClickTracking();

		log( 'AlvoBot Ad Tracker inicializado' );
	}

	if (document.readyState === 'loading') {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
