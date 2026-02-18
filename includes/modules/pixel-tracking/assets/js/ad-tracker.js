/**
 * AlvoBot Ad Tracker
 *
 * Captura eventos de anúncios do Google Ad Manager e envia para:
 *   - Meta Pixel (fbq)           — browser-side
 *   - Google Analytics 4 (gtag) — browser-side
 *   - AlvoBot REST API           — server-side → Meta CAPI (com deduplicação por event_id)
 *
 * Eventos rastreados:
 *   - ad_impression      : banner viewable (GPT impressionViewable — padrão Active View IAB)
 *   - ad_click           : clique em banner (blur/focus)
 *   - ad_vignette_open   : abertura de vinheta/interstitial
 *   - ad_vignette_click  : clique dentro da vinheta
 *
 * Estratégia de impressão:
 *   Usa exclusivamente o evento nativo 'impressionViewable' da GPT API.
 *   Isso implementa o padrão Active View (≥50% visível por ≥1s) sem código extra,
 *   elimina duplicidade e é a abordagem oficial recomendada pelo Google.
 *   Ref: https://developers.google.com/publisher-tag/reference#googletag.events.ImpressionViewableEvent
 *
 * Deduplicação browser ↔ CAPI:
 *   Cada evento gera um UUID (event_id).
 *   fbq recebe {eventID} e a REST API recebe o mesmo event_id.
 *   Meta usa esse campo para não contar a mesma conversão duas vezes.
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
	// Utilitários
	// -------------------------------------------------------------------------

	/**
	 * Gera um UUID v4.
	 * Usa crypto.randomUUID() quando disponível; fallback via Math.random().
	 *
	 * @return {string}
	 */
	function generateUUID() {
		if (
			typeof window.crypto !== 'undefined' &&
			typeof window.crypto.randomUUID === 'function'
		) {
			return window.crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(
			/[xy]/g,
			function (c) {
				var r = Math.random() * 16 | 0;
				return (c === 'x' ? r : (r & 0x3 | 0x8)).toString( 16 );
			}
		);
	}

	/**
	 * Lê um cookie por nome.
	 *
	 * @param  {string} name
	 * @return {string}
	 */
	function getCookie(name) {
		var match = document.cookie.match(
			new RegExp( '(?:^|;\\s*)' + name + '=([^;]*)' )
		);
		return match ? decodeURIComponent( match[1] ) : '';
	}

	// -------------------------------------------------------------------------
	// Dispatch helpers
	// -------------------------------------------------------------------------

	/**
	 * Mapa de nomes de evento GA4 → Meta (trackCustom).
	 * Mantém consistência entre browser-side e CAPI.
	 */
	var META_EVENT_NAMES = {
		ad_impression:     'AdImpression',
		ad_click:          'AdClick',
		ad_vignette_open:  'AdVignetteOpen',
		ad_vignette_click: 'AdVignetteClick',
	};

	/**
	 * Envia evento ao Meta Pixel com eventID para deduplicação com CAPI.
	 *
	 * @param {string} ga4EventName
	 * @param {Object} params
	 * @param {string} eventId       UUID gerado por dispatchAdEvent
	 */
	function sendToMeta(ga4EventName, params, eventId) {
		if (typeof window.fbq !== 'function') {
			log( 'fbq indisponível para', ga4EventName );
			return;
		}
		var metaName = META_EVENT_NAMES[ga4EventName] || ga4EventName;
		window.fbq( 'trackCustom', metaName, params, {eventID: eventId} );
		log( 'Meta evento enviado:', metaName, '(eventID:', eventId, ')', params );
	}

	/**
	 * Envia evento ao GA4.
	 *
	 * @param {string} eventName
	 * @param {Object} params
	 */
	function sendToGA4(eventName, params) {
		if (typeof window.gtag !== 'function') {
			log( 'gtag indisponível para', eventName );
			return;
		}
		window.gtag( 'event', eventName, params );
		log( 'GA4 evento enviado:', eventName, params );
	}

	/**
	 * Envia evento à REST API do AlvoBot para armazenamento e disparo via CAPI.
	 *
	 * O backend:
	 *   1. Salva o evento no banco (CPT) — aparece nos logs do painel
	 *   2. Resolve IP real, geo, UTMs server-side
	 *   3. Despacha para graph.facebook.com/v24.0/{pixel}/events (CAPI)
	 *   4. Usa o mesmo event_id para deduplicação com o evento browser-side
	 *
	 * keepalive: true garante entrega mesmo em navegações imediatas (ad_click).
	 *
	 * @param {string} ga4EventName
	 * @param {Object} adParams     { ad_position, ad_slot_id }
	 * @param {string} eventId      UUID compartilhado com fbq
	 */
	function sendToRestAPI(ga4EventName, adParams, eventId) {
		var config = window.alvobot_pixel_config;
		if ( ! config || ! config.api_event) {
			log( 'REST API indisponível (alvobot_pixel_config.api_event ausente)' );
			return;
		}

		var metaName = META_EVENT_NAMES[ga4EventName] || ga4EventName;

		var cleanUrl = window.location.href.replace( /#google_vignette$/, '' );

		var payload = {
			event_id:    eventId,
			event_name:  metaName,
			event_url:   cleanUrl,
			page_url:    cleanUrl,
			page_title:  document.title || '',
			page_id:     config.page_id || '0',
			fbp:         getCookie( '_fbp' ),
			fbc:         getCookie( '_fbc' ),
			user_agent:  navigator.userAgent || '',
			pixel_ids:   config.pixel_ids || '',
			custom_data: {
				ad_position: adParams.ad_position || '',
				ad_slot_id:  adParams.ad_slot_id  || '',
			},
		};

		var headers = {'Content-Type': 'application/json'};
		if (config.nonce) {
			headers['X-Alvobot-Nonce'] = config.nonce;
		}

		try {
			fetch(
				config.api_event,
				{
					method:    'POST',
					headers:   headers,
					body:      JSON.stringify( payload ),
					keepalive: true,
				}
			).then(
				function (response) {
					log( 'REST API resposta:', response.status, metaName, eventId );
				}
			).catch(
				function (err) {
					log( 'REST API erro:', metaName, err.message );
				}
			);
		} catch (e) {
			log( 'REST API exceção:', metaName, e.message );
		}
	}

	/**
	 * Ponto de entrada para todos os eventos de ad tracking.
	 * Gera um UUID único e despacha para Meta Pixel, GA4 e REST API (CAPI).
	 *
	 * @param {string} eventName   Chave GA4 (ex: "ad_impression")
	 * @param {string} adPosition  top | content_1 | content_2 | interstitial | unknown
	 * @param {string} adSlotId    adUnitPath ou '' para vinheta
	 */
	function dispatchAdEvent(eventName, adPosition, adSlotId) {
		var eventId = generateUUID();

		// Remove #google_vignette da URL para não poluir relatórios com o hash de controle do GAM.
		var cleanUrl = window.location.href.replace( /#google_vignette$/, '' );

		var adParams = {
			ad_position: adPosition,
			ad_slot_id:  adSlotId || '',
		};
		var fullParams = {
			ad_position: adPosition,
			ad_slot_id:  adSlotId || '',
			page_url:    cleanUrl,
			timestamp:   new Date().toISOString(),
		};

		log( 'dispatchAdEvent:', eventName, adPosition, '(eventId:', eventId, ')' );

		sendToMeta(    eventName, fullParams, eventId );
		sendToGA4(     eventName, fullParams           );
		sendToRestAPI( eventName, adParams,   eventId  );
	}

	// -------------------------------------------------------------------------
	// Helpers de posição / identificador
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
		var match = iframeId.match( /^google_ads_iframe_(\/[\w/.\-]+?)_\d+$/ );
		return match ? match[1] : '';
	}

	// -------------------------------------------------------------------------
	// 1. VINHETA — ad_vignette_open + ad_vignette_click
	// -------------------------------------------------------------------------

	var vignetteOpened = false;

	/**
	 * Verifica hash da URL.
	 * Chamado apenas em resposta a mudanças dinâmicas da URL (hashchange / popstate).
	 * NÃO é chamado no carregamento inicial — hash pré-existente é stale e não
	 * indica vinheta ativa. A detecção de vinheta já presente usa o MutationObserver.
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
	 * Estratégia A — Inserção do iframe real da vinheta:
	 *   ID padrão: "google_ads_iframe_/22976784714/exp_desktop_interstitial_N"
	 *   A inserção deste iframe é o sinal mais confiável de que a vinheta abriu.
	 *
	 * Estratégia B — Mudança de aria-hidden → "false" no container GPT:
	 *   Container: ins#gpt_unit_.../exp_desktop_interstitial_0
	 *   Nota: o Google também seta aria-hidden no <body>, que é ignorado aqui.
	 *
	 * O observer desconecta automaticamente após 30s caso a vinheta não apareça
	 * no carregamento inicial — a detecção via hash cobre navegações futuras.
	 */
	function watchVignetteDom() {
		if ( ! ('MutationObserver' in window)) {
			log( 'MutationObserver indisponível; detecção de vinheta via DOM desativada' );
			return;
		}

		// Verifica se iframe de vinheta já existe ao carregar (ex: retorno do histórico)
		var existingIframe = document.querySelector(
			'iframe[id*="google_ads_iframe"][id*="interstitial"]'
		);
		if (existingIframe) {
			vignetteOpened = true;
			log( 'Vinheta já visível no carregamento (iframe interstitial encontrado):', existingIframe.id );
			dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
			return;
		}

		function isVignetteNode(node) {
			if (node.nodeType !== 1) {
				return false;
			}
			var nodeId = node.id || '';

			// Estratégia A: iframe do anúncio da vinheta
			if (
				node.tagName === 'IFRAME' &&
				nodeId.indexOf( 'google_ads_iframe' ) !== -1 &&
				nodeId.indexOf( 'interstitial' ) !== -1
			) {
				return true;
			}

			// Estratégia B: container com "interstitial" no ID já visível
			if (
				nodeId.indexOf( 'interstitial' ) !== -1 &&
				node.getAttribute( 'aria-hidden' ) === 'false'
			) {
				return true;
			}

			return false;
		}

		var domObserverTimeout = null;

		function fireVignetteOpen(reason) {
			if (domObserverTimeout) {
				clearTimeout( domObserverTimeout );
			}
			vignetteOpened = true;
			log( 'Vinheta detectada via MutationObserver:', reason );
			dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
		}

		function handleMutations(mutations, observer) {
			if (vignetteOpened) {
				observer.disconnect();
				return;
			}

			for (var i = 0; i < mutations.length; i++) {
				var mutation = mutations[i];

				// Estratégia B: atributo aria-hidden mudou
				if (
					mutation.type === 'attributes' &&
					mutation.attributeName === 'aria-hidden'
				) {
					var target = mutation.target;

					// Ignora <body> e <html>: o Google Ad Manager seta aria-hidden no <body>
					// durante a vinheta, mas isso não é o container que procuramos.
					if (target === document.body || target === document.documentElement) {
						continue;
					}

					var targetId = target.id || '';
					if (
						targetId.indexOf( 'interstitial' ) !== -1 &&
						target.getAttribute( 'aria-hidden' ) === 'false'
					) {
						fireVignetteOpen( 'aria-hidden → false em ' + targetId );
						observer.disconnect();
						return;
					}
				}

				// Estratégia A: nó inserido no DOM
				if (mutation.type === 'childList') {
					for (var j = 0; j < mutation.addedNodes.length; j++) {
						var node = mutation.addedNodes[j];

						if (isVignetteNode( node )) {
							fireVignetteOpen( 'nó inserido: ' + (node.id || node.tagName) );
							observer.disconnect();
							return;
						}

						// Iframe pode estar aninhado dentro de um wrapper inserido
						if (node.nodeType === 1) {
							var nestedIframe = node.querySelector(
								'iframe[id*="google_ads_iframe"][id*="interstitial"]'
							);
							if (nestedIframe) {
								fireVignetteOpen( 'iframe aninhado: ' + nestedIframe.id );
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

		// Desconecta automaticamente após 30s caso a vinheta nunca apareça.
		domObserverTimeout = setTimeout(
			function () {
				domObserver.disconnect();
				log( 'MutationObserver: desconectado por timeout (30s sem vinheta)' );
			},
			30000
		);

		log( 'MutationObserver de vinheta configurado (timeout: 30s)' );
	}

	// -------------------------------------------------------------------------
	// 2. IMPRESSÃO DE BANNER (ad_impression)
	//
	// Usa exclusivamente o evento nativo 'impressionViewable' da GPT API.
	// O GPT já implementa o padrão Active View (≥50% visível por ≥1s) internamente,
	// sem necessidade de IntersectionObserver manual.
	// -------------------------------------------------------------------------

	/** Mapa de deduplicação: elementId → true (proteção contra disparo duplo) */
	var impressionsFired = {};

	/**
	 * Registra listeners nativos do GPT para impressões e debug de renderização.
	 *
	 * impressionViewable: dispara quando o slot atinge o padrão Active View IAB
	 *   (≥50% visível por ≥1s). Fonte oficial para contagem de impressões.
	 *
	 * slotRenderEnded: dispara ao final da renderização do slot.
	 *   isEmpty === true significa no-fill. Usado apenas para logging de debug.
	 *
	 * Guard alvobot_gpt_listeners_added previne registro duplo caso init()
	 * seja chamado mais de uma vez.
	 */
	function setupGptListeners() {
		if (
			typeof window.googletag === 'undefined' ||
			! window.googletag.cmd
		) {
			log( 'googletag não disponível; rastreamento de impressão inativo' );
			return;
		}

		window.googletag.cmd.push(
			function () {
				if (window.alvobot_gpt_listeners_added) {
					return;
				}
				window.alvobot_gpt_listeners_added = true;

				// --- impressionViewable: contagem de impressão via Active View ---
				window.googletag.pubads().addEventListener(
					'impressionViewable',
					function (event) {
						var slot      = event.slot;
						var elementId = slot.getSlotElementId();
						var unitPath  = slot.getAdUnitPath();
						var position  = positionFromAdIdentifier( unitPath );

						// Vinhetas são tratadas separadamente via MutationObserver + hash
						if (position === 'interstitial') {
							log( 'impressionViewable: ignorado (interstitial tratado separadamente)', unitPath );
							return;
						}

						if (impressionsFired[elementId]) {
							log( 'impressionViewable: já disparado para', elementId );
							return;
						}

						impressionsFired[elementId] = true;
						log( 'impressionViewable:', position, unitPath, '(elementId:', elementId, ')' );
						dispatchAdEvent( 'ad_impression', position, unitPath );
					}
				);

				// --- slotRenderEnded: debug de fill/no-fill ---
				window.googletag.pubads().addEventListener(
					'slotRenderEnded',
					function (event) {
						log(
							'slotRenderEnded:',
							event.slot.getSlotElementId(),
							event.isEmpty ? 'vazio (no-fill)' : 'preenchido'
						);
					}
				);

				log( 'GPT listeners registrados (impressionViewable + slotRenderEnded)' );
			}
		);
	}

	// -------------------------------------------------------------------------
	// 3. CLIQUE EM BANNER (ad_click) e CLIQUE EM VINHETA (ad_vignette_click)
	//
	// Técnica blur/focus: quando a window perde foco e document.activeElement
	// é um iframe de anúncio, o usuário clicou naquele anúncio.
	//
	// Diferenciação:
	//   - iframe com "interstitial" no ID → ad_vignette_click
	//   - qualquer outro iframe de anúncio → ad_click
	// -------------------------------------------------------------------------

	var clicksFired = {};

	/**
	 * Verifica se um elemento é um iframe do Google Ads.
	 *
	 * @param  {Element} el
	 * @return {boolean}
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
	 * Detecta cliques em anúncios via blur/focus.
	 *
	 * Cenários cobertos:
	 *   1. Clique em banner regular → ad_click (position: top | content_1 | content_2)
	 *   2. Clique em vinheta aberta → ad_vignette_click (position: interstitial)
	 *
	 * Após 3s devolve o foco à janela para permitir detecção de cliques subsequentes.
	 */
	function setupClickTracking() {
		window.addEventListener(
			'blur',
			function () {
				// setTimeout 0: aguarda o browser atualizar document.activeElement
				setTimeout(
					function () {
						var activeEl = document.activeElement;
						if ( ! isAdIframe( activeEl )) {
							return;
						}

						var iframeId = activeEl.id || '';
						var position = positionFromAdIdentifier( iframeId );
						var slotId   = slotIdFromIframeId( iframeId );

						// Determina tipo de clique: vinheta ou banner
						var eventName = (position === 'interstitial')
							? 'ad_vignette_click'
							: 'ad_click';

						if (clicksFired[iframeId]) {
							log( 'Clique já registrado para:', iframeId );
							return;
						}
						clicksFired[iframeId] = true;

						log( 'Clique detectado (blur/focus):', eventName, position, iframeId );
						dispatchAdEvent( eventName, position, slotId );

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

		log( 'Rastreamento de cliques configurado (ad_click + ad_vignette_click)' );
	}

	// -------------------------------------------------------------------------
	// Inicialização
	// -------------------------------------------------------------------------

	function init() {
		log( 'Inicializando AlvoBot Ad Tracker' );

		// 1. Vinheta via mudança dinâmica da URL
		//    - hashchange: hash muda para #google_vignette durante a sessão
		//    - popstate: Google usa history.pushState/replaceState para setar o hash
		//
		//    NÃO chamamos checkVignetteHash() aqui no init:
		//    se a URL já carregou com #google_vignette, é um hash stale (copiado,
		//    histórico do browser) — não indica vinheta ativa. O MutationObserver
		//    detecta vinhetas que já estão no DOM ao carregar a página.
		window.addEventListener( 'hashchange', checkVignetteHash );
		window.addEventListener( 'popstate',   checkVignetteHash );

		// 2. Vinheta via DOM (MutationObserver — iframe inserido + aria-hidden)
		watchVignetteDom();

		// 3. Impressões de banner (GPT impressionViewable — Active View nativo)
		setupGptListeners();

		// 4. Cliques em anúncios e vinheta (blur/focus)
		setupClickTracking();

		log( 'AlvoBot Ad Tracker inicializado' );
	}

	if (document.readyState === 'loading') {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
