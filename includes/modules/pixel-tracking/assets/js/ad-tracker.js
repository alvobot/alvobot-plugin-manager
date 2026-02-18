/**
 * AlvoBot Ad Tracker
 *
 * Captura eventos de anúncios do Google Ad Manager e envia para
 * Meta Pixel (fbq) e Google Analytics 4 (gtag).
 *
 * Eventos rastreados:
 *   - ad_vignette_open : abertura de interstitial/vinheta
 *   - ad_impression    : visualização de banner (via GPT impressionViewable + IntersectionObserver)
 *   - ad_click         : clique em banner ou vinheta (blur/focus)
 *
 * Estratégia de impressão (dois disparadores, uma deduplicação):
 *   Chave de dedup = elementId do GPT slot (ex: "av_top").
 *   Isso garante que IntersectionObserver e GPT impressionViewable
 *   nunca contam a mesma impressão duas vezes, mesmo que o adUnitPath
 *   do GPT contenha sufixos como "_rebid".
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
	// Helpers de posição
	// -------------------------------------------------------------------------

	/**
	 * Determina ad_position a partir de qualquer identificador de anúncio.
	 * Funciona com: ID de iframe, adUnitPath do GPT, element ID ("av_top"), etc.
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
		// content_1 / content_2 verificados antes de _top para evitar ambiguidade
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
	 * Ex: google_ads_iframe_/22976784714/exp_desktop_top_rebid_0
	 *     → /22976784714/exp_desktop_top_rebid
	 *
	 * @param  {string} iframeId
	 * @return {string}
	 */
	function slotIdFromIframeId(iframeId) {
		if ( ! iframeId) {
			return '';
		}
		// Remove prefixo "google_ads_iframe_" e sufixo numérico "_N"
		var match = iframeId.match( /^google_ads_iframe_(\/[\w/]+?)_\d+$/ );
		return match ? match[1] : '';
	}

	// -------------------------------------------------------------------------
	// GPT Slot Map
	// Consulta googletag.pubads().getSlots() para obter o mapeamento
	// elementId → adUnitPath real (com possíveis sufixos como _rebid).
	// -------------------------------------------------------------------------

	/**
	 * Retorna mapa: elementId → adUnitPath, para todos os slots GPT registrados.
	 * Chamado de forma síncrona (nosso script roda diferido, após o GPT estar pronto).
	 *
	 * @return {Object} ex: { "av_top": "/22976784714/exp_desktop_top_rebid", ... }
	 */
	function buildGPTSlotMap() {
		var map = {};
		if (
			typeof window.googletag === 'undefined' ||
			! window.googletag.pubads ||
			typeof window.googletag.pubads().getSlots !== 'function'
		) {
			return map;
		}
		try {
			window.googletag.pubads().getSlots().forEach(
				function (slot) {
					map[slot.getSlotElementId()] = slot.getAdUnitPath();
				}
			);
		} catch (e) {
			log( 'buildGPTSlotMap(): erro ao consultar GPT slots', e.message );
		}
		log( 'GPT slot map:', map );
		return map;
	}

	// -------------------------------------------------------------------------
	// 1. VINHETA (ad_vignette_open)
	// -------------------------------------------------------------------------

	var vignetteOpened = false;

	/**
	 * Verifica hash da URL. Disparado no load e em cada hashchange.
	 */
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

	/**
	 * Observa o DOM em busca do container da vinheta.
	 *
	 * Duas estratégias simultâneas:
	 *
	 * A) Inserção do iframe de anúncio da vinheta:
	 *    O iframe real da vinheta tem ID no padrão:
	 *    "google_ads_iframe_/22976784714/exp_desktop_interstitial_N"
	 *    Quando esse iframe é inserido no DOM → vinheta abriu.
	 *
	 * B) Mudança de aria-hidden → "false" em elemento com "interstitial" no ID:
	 *    O GPT registra a vinheta em ins#gpt_unit_.../exp_desktop_interstitial_0
	 *    com aria-hidden="true" inicialmente. Quando abre → aria-hidden="false".
	 */
	function watchVignetteDom() {
		if ( ! ('MutationObserver' in window)) {
			log( 'MutationObserver indisponível; detecção de vinheta via DOM desativada' );
			return;
		}

		// Verifica se iframe de vinheta já existe (ex: retorno do histórico)
		var existingIframe = document.querySelector(
			'iframe[id*="google_ads_iframe"][id*="interstitial"]'
		);
		if (existingIframe) {
			vignetteOpened = true;
			log( 'Vinheta já visível no carregamento (iframe interstitial encontrado):', existingIframe.id );
			dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
			return;
		}

		// Verifica também via aria-hidden (fallback)
		var existingVignette = document.querySelector(
			'[id*="interstitial"][data-vignette-loaded="true"]'
		);
		if (existingVignette && existingVignette.getAttribute( 'aria-hidden' ) === 'false') {
			vignetteOpened = true;
			log( 'Vinheta já visível no carregamento (aria-hidden=false)' );
			dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
			return;
		}

		function isVignetteNode(node) {
			if (node.nodeType !== 1) {
				return false;
			}
			var nodeId = node.id || '';
			// Estratégia A: iframe do anúncio da vinheta inserido
			if (
				node.tagName === 'IFRAME' &&
				nodeId.indexOf( 'google_ads_iframe' ) !== -1 &&
				nodeId.indexOf( 'interstitial' ) !== -1
			) {
				return true;
			}
			// Estratégia B: qualquer elemento com "interstitial" no ID que está visível
			if (
				nodeId.indexOf( 'interstitial' ) !== -1 &&
				node.getAttribute( 'aria-hidden' ) === 'false'
			) {
				return true;
			}
			return false;
		}

		function handleMutations(mutations, observer) {
			if (vignetteOpened) {
				observer.disconnect();
				return;
			}

			for (var i = 0; i < mutations.length; i++) {
				var mutation = mutations[i];

				// Estratégia B: mudança de aria-hidden em elemento "interstitial"
				if (
					mutation.type === 'attributes' &&
					mutation.attributeName === 'aria-hidden'
				) {
					var target   = mutation.target;
					var targetId = target.id || '';
					if (
						targetId.indexOf( 'interstitial' ) !== -1 &&
						target.getAttribute( 'aria-hidden' ) === 'false'
					) {
						vignetteOpened = true;
						log( 'Vinheta detectada via MutationObserver (aria-hidden → false)', targetId );
						dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
						observer.disconnect();
						return;
					}
				}

				// Estratégias A e B: nó inserido
				if (mutation.type === 'childList') {
					for (var j = 0; j < mutation.addedNodes.length; j++) {
						var node = mutation.addedNodes[j];
						if (isVignetteNode( node )) {
							vignetteOpened = true;
							log( 'Vinheta detectada via nó inserido:', node.id || node.tagName );
							dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
							observer.disconnect();
							return;
						}
						// Verifica descendentes do nó inserido (iframe pode estar aninhado)
						if (node.nodeType === 1) {
							var nestedIframe = node.querySelector(
								'iframe[id*="google_ads_iframe"][id*="interstitial"]'
							);
							if (nestedIframe) {
								vignetteOpened = true;
								log( 'Vinheta detectada via iframe aninhado em nó inserido:', nestedIframe.id );
								dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
								observer.disconnect();
								return;
							}
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
				attributeFilter: ['aria-hidden'],
			}
		);
		log( 'MutationObserver de vinheta configurado' );
	}

	// -------------------------------------------------------------------------
	// 2. IMPRESSÃO DE BANNER (ad_impression)
	//
	// Chave de dedup: elementId do slot GPT (ex: "av_top").
	// Isso é consistente entre GPT impressionViewable (usa getSlotElementId())
	// e IntersectionObserver (usa el.id), independente de sufixos como _rebid.
	// -------------------------------------------------------------------------

	/** Mapa de deduplicação: elementId → true */
	var impressionsFired = {};

	/**
	 * Descobre slots de anúncios a monitorar.
	 * Usa GPT slot map como fonte de verdade quando disponível:
	 *   - Inclui apenas elementos registrados no GPT (exclui _wrappers e elementos extras)
	 *   - Obtém o adUnitPath real (ex: /22976784714/exp_desktop_top_rebid)
	 * Fallback: descobre via [id^="av_"] excluindo _wrapper elements.
	 *
	 * @param  {Object} gptSlotMap  { elementId: adUnitPath }
	 * @return {Array<{el, id, position, adUnitPath}>}
	 */
	function buildSlotsFromDom(gptSlotMap) {
		var slots      = [];
		var hasGPTData = Object.keys( gptSlotMap ).length > 0;

		document.querySelectorAll( '[id^="av_"]' ).forEach(
			function (el) {
				var elId = el.id;
				var name = elId.slice( 3 ); // remove "av_"

				if ( ! name) {
					return;
				}

				// Se GPT está disponível: inclui apenas slots realmente registrados
				// (exclui automaticamente _wrapper e outros elementos extras)
				if (hasGPTData) {
					if ( ! gptSlotMap.hasOwnProperty( elId )) {
						log( 'Slot não registrado no GPT; ignorado:', elId );
						return;
					}
				} else {
					// Fallback: sem GPT, filtra manualmente _wrapper
					if (name.indexOf( '_wrapper' ) !== -1) {
						return;
					}
				}

				// adUnitPath real do GPT (inclui _rebid etc.) ou construído como fallback
				var adUnitPath = hasGPTData
					? gptSlotMap[elId]
					: '/22976784714/exp_desktop_' + name;

				slots.push(
					{
						el:          el,
						id:          elId,         // chave de dedup (elementId)
						position:    positionFromAdIdentifier( elId ),
						adUnitPath:  adUnitPath,   // para o campo ad_slot_id no evento
					}
				);
			}
		);

		log(
			'Slots para monitorar:',
			slots.map( function (s) { return s.id + ' → ' + s.adUnitPath; } )
		);
		return slots;
	}

	/**
	 * Estratégia primária: GPT pubads 'impressionViewable'.
	 * Implementa a definição IAB (50% visível por 1s) nativamente.
	 * Usa elementId como chave de dedup (consistente com IntersectionObserver).
	 *
	 * @return {boolean} true se o listener foi registrado.
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
						var slot      = event.slot;
						var elementId = slot.getSlotElementId();  // ex: "av_top"
						var unitPath  = slot.getAdUnitPath();     // ex: "/22976784714/exp_desktop_top_rebid"
						var position  = positionFromAdIdentifier( unitPath );

						// Ignora vinhetas (tratadas separadamente)
						if (position === 'interstitial' || position === 'unknown') {
							log( 'GPT impressionViewable: ignorado (', position, ')', unitPath );
							return;
						}

						// Dedup por elementId — chave consistente com IntersectionObserver
						if (impressionsFired[elementId]) {
							log( 'GPT impressionViewable: já disparado para', elementId );
							return;
						}

						impressionsFired[elementId] = true;
						log( 'GPT impressionViewable disparado:', position, unitPath, '(key:', elementId, ')' );
						dispatchAdEvent( 'ad_impression', position, unitPath );
					}
				);
			}
		);

		log( 'GPT impressionViewable listener configurado' );
		return true;
	}

	/**
	 * Estratégia de fallback: IntersectionObserver.
	 * Ativado mesmo quando GPT está disponível (impressionsFired previne duplo disparo).
	 * Verifica data-google-query-id antes de contar (confirma que o anúncio carregou).
	 *
	 * @param {Array} slots Retornado por buildSlotsFromDom().
	 */
	function setupImpressionObserver(slots) {
		if ( ! ('IntersectionObserver' in window)) {
			log( 'IntersectionObserver indisponível' );
			return;
		}

		slots.forEach(
			function (slot) {
				var el              = slot.el;
				var dedupeKey       = slot.id;   // elementId = "av_top"
				var visibilityTimer = null;

				var observer = new IntersectionObserver(
					function (entries) {
						entries.forEach(
							function (entry) {
								// GPT pode ter disparado primeiro — cancela o observer
								if (impressionsFired[dedupeKey]) {
									observer.disconnect();
									if (visibilityTimer) {
										clearTimeout( visibilityTimer );
										visibilityTimer = null;
									}
									return;
								}

								if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
									if ( ! visibilityTimer) {
										log( 'Banner visível ≥50%, timer de 1s iniciado:', slot.position );
										visibilityTimer = setTimeout(
											function () {
												visibilityTimer = null;

												// GPT pode ter disparado durante o timer
												if (impressionsFired[dedupeKey]) {
													log( 'IO timer: GPT já disparou para', dedupeKey, '; cancelado' );
													observer.disconnect();
													return;
												}

												// Confirma que o anúncio foi realmente carregado
												if ( ! el.hasAttribute( 'data-google-query-id' )) {
													log( 'IO: impressão ignorada — anúncio não carregado (sem data-google-query-id):', slot.id );
													return;
												}

												impressionsFired[dedupeKey] = true;
												log( 'IO: impressão disparada:', slot.position, '(key:', dedupeKey, ')' );
												dispatchAdEvent( 'ad_impression', slot.position, slot.adUnitPath );
												observer.disconnect();
											},
											1000
										);
									}
								} else {
									// Saiu de visibilidade antes de 1s
									if (visibilityTimer) {
										clearTimeout( visibilityTimer );
										visibilityTimer = null;
										log( 'IO: timer cancelado (banner saiu de visibilidade):', slot.position );
									}
								}
							}
						);
					},
					{threshold: 0.5}
				);

				observer.observe( el );
				log( 'IO configurado para:', slot.id );
			}
		);
	}

	/**
	 * Orquestra as duas estratégias de impressão com dedup unificado por elementId.
	 */
	function setupImpressions() {
		var gptSlotMap = buildGPTSlotMap();
		var slots      = buildSlotsFromDom( gptSlotMap );

		if ( ! slots.length) {
			log( 'Nenhum slot encontrado; rastreamento de impressão inativo' );
		}

		var gptActive = setupGPTImpressionTracking();
		if ( ! gptActive) {
			log( 'GPT não disponível; usando apenas IntersectionObserver' );
		}

		// IO sempre ativo como fallback/complemento
		// impressionsFired[elementId] previne duplo disparo entre GPT e IO
		setupImpressionObserver( slots );
	}

	// -------------------------------------------------------------------------
	// 3. CLIQUE EM ANÚNCIO (ad_click)
	// -------------------------------------------------------------------------

	var clicksFired = {};

	/**
	 * Verifica se um elemento é um iframe do Google Ads.
	 */
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

	/**
	 * Detecta clique em anúncio via técnica blur/focus.
	 * Quando a window perde foco e activeElement é um iframe de anúncio → ad_click.
	 * Após 3s devolve o foco para permitir detecção de cliques subsequentes.
	 */
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
							log( 'Clique já registrado para:', iframeId );
							return;
						}
						clicksFired[iframeId] = true;

						log( 'Clique detectado (blur/focus):', position, iframeId );
						dispatchAdEvent( 'ad_click', position, slotId );

						// Devolve foco após 3s para permitir novos cliques
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

		log( 'Rastreamento de cliques configurado' );
	}

	// -------------------------------------------------------------------------
	// Inicialização
	// -------------------------------------------------------------------------

	function init() {
		log( 'Inicializando AlvoBot Ad Tracker' );

		// 1. Vinheta via hash da URL
		checkVignetteHash();
		window.addEventListener( 'hashchange', checkVignetteHash );
		// popstate cobre casos onde o Google usa history.pushState/replaceState
		window.addEventListener( 'popstate', checkVignetteHash );

		// 2. Vinheta via DOM (MutationObserver)
		watchVignetteDom();

		// 3. Impressões (GPT + IntersectionObserver com dedup por elementId)
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
