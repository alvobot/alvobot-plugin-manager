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
 * Proteção contra falso positivo em ad_vignette_click:
 *   Quando a vinheta abre, o Google dá foco automático ao iframe, disparando
 *   window.blur. Para não contar esse foco como clique, usamos vignetteOpenTimestamp:
 *   blurs que chegam dentro de 1s da abertura são ignorados.
 *   Após suprimir o auto-foco, window.focus() é restaurado para que o clique
 *   real do usuário possa gerar um novo blur e ser detectado corretamente.
 *
 * Deduplicação browser ↔ CAPI:
 *   Cada evento gera um UUID (event_id) compartilhado entre fbq e REST API.
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

	function getCookie(name) {
		var match = document.cookie.match(
			new RegExp( '(?:^|;\\s*)' + name + '=([^;]*)' )
		);
		return match ? decodeURIComponent( match[1] ) : '';
	}

	// -------------------------------------------------------------------------
	// Dispatch helpers
	// -------------------------------------------------------------------------

	var META_EVENT_NAMES = {
		ad_impression:     'AdImpression',
		ad_click:          'AdClick',
		ad_vignette_open:  'AdVignetteOpen',
		ad_vignette_click: 'AdVignetteClick',
	};

	function sendToMeta(ga4EventName, params, eventId) {
		if (typeof window.fbq !== 'function') {
			log( 'fbq indisponível para', ga4EventName );
			return;
		}
		var metaName = META_EVENT_NAMES[ga4EventName] || ga4EventName;
		window.fbq( 'trackCustom', metaName, params, {eventID: eventId} );
		log( 'Meta enviado:', metaName, '(eventID:', eventId, ')' );
	}

	function sendToGA4(eventName, params) {
		if (typeof window.gtag !== 'function') {
			log( 'gtag indisponível para', eventName );
			return;
		}
		window.gtag( 'event', eventName, params );
		log( 'GA4 enviado:', eventName );
	}

	function sendToRestAPI(ga4EventName, adParams, eventId) {
		var config = window.alvobot_pixel_config;
		if ( ! config || ! config.api_event) {
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

	function dispatchAdEvent(eventName, adPosition, adSlotId) {
		var eventId  = generateUUID();
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

	function positionFromAdIdentifier(identifier) {
		if ( ! identifier) {
			return 'unknown';
		}
		if (identifier.indexOf( 'interstitial' ) !== -1) {
			return 'interstitial';
		}
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

	var vignetteOpened        = false;
	var vignetteOpenTimestamp = 0; // timestamp da abertura — usado no grace period anti-auto-foco
	var interstitialFilled    = false; // set true quando slotRenderEnded reporta interstitial preenchido
	var interstitialFilledAt  = 0; // timestamp do último slotRenderEnded preenchido do interstitial
	var INTERSTITIAL_SIGNAL_TTL_MS = 45000; // janela para considerar sinal de interstitial "recente"

	/**
	 * Marca a vinheta como aberta e dispara ad_vignette_open (uma vez por página).
	 * Registra vignetteOpenTimestamp para filtrar o blur de auto-foco que vem logo
	 * após a abertura (ver setupClickTracking).
	 */
	function markVignetteAsOpen(source) {
		if (vignetteOpened) {
			return;
		}
		vignetteOpened        = true;
		vignetteOpenTimestamp = Date.now();
		log( 'Vinheta aberta (' + source + ')' );
		dispatchAdEvent( 'ad_vignette_open', 'interstitial', '/22976784714/exp_desktop_interstitial' );
	}

	function hasGoogleVignetteMarker(url) {
		var candidate = url || window.location.href || '';
		if (candidate.indexOf( '#google_vignette' ) !== -1) {
			return true;
		}
		return /(?:\?|&)google_vignette(?:=|&|$)/.test( candidate );
	}

	function hasInterstitialIframeInDom() {
		return !! document.querySelector( 'iframe[id*="google_ads_iframe"][id*="interstitial"]' );
	}

	function hasRecentInterstitialFillSignal() {
		if ( ! interstitialFilled || ! interstitialFilledAt) {
			return false;
		}
		if ((Date.now() - interstitialFilledAt) > INTERSTITIAL_SIGNAL_TTL_MS) {
			interstitialFilled = false; // evita sinal stale em sessões longas
			return false;
		}
		return true;
	}

	function checkVignetteSignals(source) {
		if (vignetteOpened) {
			return false;
		}

		var hasUrlSignal  = hasGoogleVignetteMarker( window.location.href );
		var hasDomSignal  = hasInterstitialIframeInDom();
		var hasBodySignal = !! (
			document.body &&
			document.body.getAttribute( 'aria-hidden' ) === 'true'
		);
		var hasFillSignal = hasRecentInterstitialFillSignal();

		// URL de vignette + algum sinal contextual de interstitial.
		if (hasUrlSignal && (hasFillSignal || hasDomSignal || hasBodySignal)) {
			markVignetteAsOpen( source + ': url marker + interstitial context' );
			return true;
		}

		// Fallback quando URL não muda, mas o body foi ocultado no fluxo de interstitial.
		if (hasBodySignal && (hasFillSignal || hasDomSignal)) {
			markVignetteAsOpen( source + ': body aria-hidden + interstitial context' );
			return true;
		}

		return false;
	}

	function installHistoryHooks() {
		if (
			! window.history ||
			window.alvobot_vignette_history_hooked
		) {
			return;
		}
		window.alvobot_vignette_history_hooked = true;

		var originalPushState = window.history.pushState;
		var originalReplaceState = window.history.replaceState;

		if (typeof originalPushState === 'function') {
			window.history.pushState = function () {
				var ret = originalPushState.apply( window.history, arguments );
				checkVignetteSignals( 'history.pushState' );
				return ret;
			};
		}

		if (typeof originalReplaceState === 'function') {
			window.history.replaceState = function () {
				var ret = originalReplaceState.apply( window.history, arguments );
				checkVignetteSignals( 'history.replaceState' );
				return ret;
			};
		}
	}

	/**
	 * Verifica hash da URL.
	 * Chamado em mudanças dinâmicas da URL (hash/popstate/history hooks).
	 */
	function checkVignetteHash() {
		checkVignetteSignals( 'url change' );
	}

	/**
	 * Observa o DOM em busca do iframe da vinheta.
	 * Auto-desconecta após 30s se a vinheta não aparecer.
	 */
	function watchVignetteDom() {
		if ( ! ('MutationObserver' in window)) {
			return;
		}

		// Em alguns dispositivos o hash chega antes do observer.
		if (checkVignetteSignals( 'dom watcher bootstrap' )) {
			return;
		}

		// Verifica se iframe já existe ao carregar (ex: retorno do histórico)
		if (hasInterstitialIframeInDom()) {
			markVignetteAsOpen( 'iframe found on load' );
			return;
		}

		var obs = new MutationObserver(
			function (mutations) {
				if (vignetteOpened) {
					obs.disconnect();
					return;
				}

				if (checkVignetteSignals( 'mutation pre-check' )) {
					obs.disconnect();
					return;
				}

				for (var i = 0; i < mutations.length; i++) {
					var m = mutations[i];

					// Estratégia A: aria-hidden mudou no container da vinheta
					if (m.type === 'attributes' && m.attributeName === 'aria-hidden') {
						var t   = m.target;
						var tid = t.id || '';

						// Estratégia C: body recebe aria-hidden=true + interstitial recém-preenchido.
						if (
							t === document.body &&
							t.getAttribute( 'aria-hidden' ) === 'true' &&
							hasRecentInterstitialFillSignal()
						) {
							markVignetteAsOpen( 'body aria-hidden=true' );
							obs.disconnect();
							return;
						}

						// Estratégia A original: container da vinheta tornou-se visível
						if (
							t !== document.body &&
							t !== document.documentElement &&
							tid.indexOf( 'interstitial' ) !== -1 &&
							t.getAttribute( 'aria-hidden' ) === 'false'
						) {
							markVignetteAsOpen( 'aria-hidden change: ' + tid );
							obs.disconnect();
							return;
						}
					}

					// Estratégia B: iframe de vinheta inserido no DOM
					if (m.type === 'childList') {
						for (var j = 0; j < m.addedNodes.length; j++) {
							var n   = m.addedNodes[j];
							var nid = (n.nodeType === 1 && n.id) ? n.id : '';

							if (
								n.nodeType === 1 &&
								n.tagName === 'IFRAME' &&
								nid.indexOf( 'google_ads_iframe' ) !== -1 &&
								nid.indexOf( 'interstitial' ) !== -1
							) {
								markVignetteAsOpen( 'iframe inserted: ' + nid );
								obs.disconnect();
								return;
							}

							if (
								n.nodeType === 1 &&
								nid.indexOf( 'interstitial' ) !== -1 &&
								n.getAttribute( 'aria-hidden' ) === 'false'
							) {
								markVignetteAsOpen( 'interstitial container visible: ' + nid );
								obs.disconnect();
								return;
							}

							// Iframe pode estar aninhado dentro de um wrapper
							if (n.nodeType === 1) {
								var nested = n.querySelector(
									'iframe[id*="google_ads_iframe"][id*="interstitial"]'
								);
								if (nested) {
									markVignetteAsOpen( 'nested iframe: ' + nested.id );
									obs.disconnect();
									return;
								}
							}
						}
					}
				}
			}
		);

		obs.observe(
			document.body,
			{
				childList:       true,
				subtree:         true,
				attributes:      true,
				attributeFilter: ['aria-hidden', 'style', 'class', 'hidden'],
			}
		);

		setTimeout( function () { obs.disconnect(); }, 30000 );
		log( 'MutationObserver de vinheta configurado (timeout: 30s)' );
	}

	// -------------------------------------------------------------------------
	// 2. IMPRESSÃO DE BANNER (ad_impression)
	//
	// Usa exclusivamente o evento nativo 'impressionViewable' da GPT API.
	// -------------------------------------------------------------------------

	var impressionsFired = {};

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

				// impressionViewable: Active View IAB nativo (≥50% visível por ≥1s)
				window.googletag.pubads().addEventListener(
					'impressionViewable',
					function (event) {
						var slot      = event.slot;
						var elementId = slot.getSlotElementId();
						var unitPath  = slot.getAdUnitPath();
						var position  = positionFromAdIdentifier( unitPath );

						// Vinheta é tratada separadamente
						if (position === 'interstitial') {
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

				// slotRenderEnded: debug de fill/no-fill + guarda de interstitial
				window.googletag.pubads().addEventListener(
					'slotRenderEnded',
					function (event) {
						log(
							'slotRenderEnded:',
							event.slot.getSlotElementId(),
							event.isEmpty ? 'vazio (no-fill)' : 'preenchido'
						);
						// Marca que o interstitial carregou — o MutationObserver usa
						// esse flag para confirmar que body aria-hidden=true é da vinheta
						if ( ! event.isEmpty && event.slot.getAdUnitPath().indexOf( 'interstitial' ) !== -1) {
							interstitialFilled = true;
							interstitialFilledAt = Date.now();
							log( 'Interstitial preenchido — aguardando abertura visual' );
							checkVignetteSignals( 'slotRenderEnded interstitial' );
						}
					}
				);

				log( 'GPT listeners registrados (impressionViewable + slotRenderEnded)' );
			}
		);
	}

	// -------------------------------------------------------------------------
	// 3. CLIQUE EM BANNER (ad_click) e CLIQUE EM VINHETA (ad_vignette_click)
	//
	// Técnica blur/focus para detectar cliques em iframes cross-origin.
	//
	// Proteção anti-falso-positivo para vinheta (3 camadas):
	//
	//   Camada 1 — Grace period:
	//     Quando a vinheta abre, o Google auto-foca o iframe (programaticamente),
	//     disparando window.blur sem nenhum clique real. Esse blur ocorre dentro
	//     do primeiro segundo após a abertura. Blurs nesse período são ignorados.
	//
	//   Camada 2 — Validação por navegação (deferred dispatch):
	//     Após o grace period, blur no iframe da vinheta NÃO dispara o evento
	//     imediatamente. Em vez disso, aguardamos um curto período para distinguir:
	//       - Clique real no anúncio → abre nova aba ou navega (page hidden / beforeunload)
	//       - Dismissal (fechar/clicar fora) → vinheta fecha (body aria-hidden muda)
	//     Apenas cliques que causam navegação são contados como ad_vignette_click.
	//
	//   Camada 3 — Deduplicação:
	//     clicksFired[iframeId] evita duplicatas por iframe (cooldown de 3s).
	// -------------------------------------------------------------------------

	var VIGNETTE_CLICK_GRACE_MS   = 1000; // ms de grace period após abertura da vinheta
	var VIGNETTE_CLICK_CONFIRM_MS = 2000; // ms para confirmar se houve navegação
	var clicksFired               = {};
	var pendingVignetteClick      = null; // apenas um pendente por vez

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
	 * Validação deferred de clique na vinheta.
	 *
	 * Em vez de disparar ad_vignette_click imediatamente no blur, aguarda sinais
	 * que confirmem que o usuário realmente clicou no anúncio (navegação) ou que
	 * simplesmente fechou a vinheta (dismissal).
	 *
	 * Sinais de CONFIRMAÇÃO (clique real):
	 *   - visibilitychange → hidden  (nova aba abriu ou navegação ocorreu)
	 *   - beforeunload               (navegação na mesma aba)
	 *
	 * Sinais de CANCELAMENTO (dismissal):
	 *   - body aria-hidden muda de 'true' para qualquer outro valor (vinheta fechou)
	 *   - #google_vignette removido da URL
	 *   - timeout sem nenhum sinal de navegação
	 */
	function deferVignetteClick(position, slotId, iframeId) {
		// Apenas um clique pendente por vez
		if (pendingVignetteClick) {
			log( 'Vignette click já pendente — ignorando blur adicional' );
			return;
		}

		log( 'Vignette click detectado — aguardando confirmação de navegação (' + VIGNETTE_CLICK_CONFIRM_MS + 'ms)' );

		var resolved  = false;
		var timeoutId = null;
		var dismissObs = null;

		function confirm(source) {
			if (resolved) {
				return;
			}
			resolved = true;
			cleanup();

			if (clicksFired[iframeId]) {
				log( 'Vignette click confirmado mas já disparado para:', iframeId );
				return;
			}
			clicksFired[iframeId] = true;

			log( 'Vignette click CONFIRMADO:', source );
			dispatchAdEvent( 'ad_vignette_click', position, slotId );

			setTimeout(
				function () {
					delete clicksFired[iframeId];
				},
				3000
			);
		}

		function cancel(source) {
			if (resolved) {
				return;
			}
			resolved = true;
			cleanup();
			log( 'Vignette click DESCARTADO:', source );

			// Restaura foco para detectar próximos eventos
			setTimeout(
				function () {
					if (typeof window.focus === 'function') {
						window.focus();
					}
				},
				500
			);
		}

		function cleanup() {
			pendingVignetteClick = null;
			if (timeoutId) {
				clearTimeout( timeoutId );
			}
			document.removeEventListener( 'visibilitychange', onVisibilityChange );
			window.removeEventListener( 'beforeunload', onBeforeUnload );
			window.removeEventListener( 'hashchange', onHashPopstate );
			window.removeEventListener( 'popstate', onHashPopstate );
			if (dismissObs) {
				dismissObs.disconnect();
			}
		}

		// --- Sinais de confirmação (clique real) ---

		function onVisibilityChange() {
			if (document.hidden) {
				confirm( 'page hidden (nova aba ou navegação)' );
			}
		}

		function onBeforeUnload() {
			confirm( 'beforeunload (navegação na mesma aba)' );
		}

		// --- Sinais de cancelamento (dismissal) ---

		function onHashPopstate() {
			if ( ! hasGoogleVignetteMarker( window.location.href )) {
				cancel( 'vinheta fechou (#google_vignette removido da URL)' );
			}
		}

		// Observa body aria-hidden mudando de 'true' (vinheta fechando)
		if ('MutationObserver' in window) {
			dismissObs = new MutationObserver(
				function () {
					var val = document.body.getAttribute( 'aria-hidden' );
					if (val !== 'true') {
						cancel( 'vinheta fechou (body aria-hidden=' + (val || 'removed') + ')' );
					}
				}
			);
			dismissObs.observe(
				document.body,
				{attributes: true, attributeFilter: ['aria-hidden']}
			);
		}

		document.addEventListener( 'visibilitychange', onVisibilityChange );
		window.addEventListener( 'beforeunload', onBeforeUnload );
		window.addEventListener( 'hashchange', onHashPopstate );
		window.addEventListener( 'popstate', onHashPopstate );

		pendingVignetteClick = {confirm: confirm, cancel: cancel};

		// Timeout: sem sinal de navegação → descarta (conservador)
		timeoutId = setTimeout(
			function () {
				cancel( 'timeout — nenhuma navegação detectada em ' + VIGNETTE_CLICK_CONFIRM_MS + 'ms' );
			},
			VIGNETTE_CLICK_CONFIRM_MS
		);
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

						var iframeId  = activeEl.id || '';
						var position  = positionFromAdIdentifier( iframeId );
						var slotId    = slotIdFromIframeId( iframeId );
						var eventName = 'ad_click';

						if (position === 'interstitial') {
							checkVignetteSignals( 'blur pre-check' );

							// Proteção 1: vinheta ainda não detectada
							if ( ! vignetteOpened) {
								markVignetteAsOpen( 'blur fallback: interstitial iframe got focus' );
							}

							// Proteção 2: grace period — auto-foco do Google ao abrir a vinheta
							var elapsed = Date.now() - vignetteOpenTimestamp;
							if (elapsed < VIGNETTE_CLICK_GRACE_MS) {
								log(
									'Blur ignorado: auto-foco da abertura (' + elapsed + 'ms < grace period) —',
									'restaurando foco em 1s'
								);
								setTimeout(
									function () {
										if (typeof window.focus === 'function') {
											window.focus();
										}
									},
									1000
								);
								return;
							}

							// Proteção 3: validação deferred — só confirma se houver navegação
							deferVignetteClick( position, slotId, iframeId );
							return;
						}

						// Banners normais: dispatch imediato (sem ambiguidade)
						if (clicksFired[iframeId]) {
							log( 'Clique já registrado para:', iframeId );
							return;
						}
						clicksFired[iframeId] = true;

						log( 'Clique detectado (blur/focus):', eventName, position, iframeId );
						dispatchAdEvent( eventName, position, slotId );

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

		// 1. Vinheta via mudança dinâmica da URL (hashchange / popstate + history API)
		installHistoryHooks();
		window.addEventListener( 'hashchange', checkVignetteHash );
		window.addEventListener( 'popstate',   checkVignetteHash );
		window.addEventListener(
			'pageshow',
			function () {
				checkVignetteSignals( 'pageshow' );
			}
		);

		// 2. Vinheta via DOM (MutationObserver)
		watchVignetteDom();

		// 2.1 Checagem guardada no init para capturar timing em que hash/URL já mudou
		//     mas o observer ainda não recebeu mutação.
		checkVignetteSignals( 'init' );

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
