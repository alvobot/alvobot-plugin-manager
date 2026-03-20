<?php
/**
 * Smart Internal Links - Content Injector
 * Insere blocos de links no conteúdo dos posts via the_content filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_Smart_Links_Injector {

	private $renderer;

	public function __construct() {
		$this->renderer = new AlvoBotPro_Smart_Links_Renderer();
		add_filter( 'the_content', array( $this, 'inject_links' ), 15 );
	}

	/**
	 * Injeta blocos de links no conteúdo do post
	 */
	public function inject_links( $content ) {
		// Guards
		if ( ! is_singular() || is_admin() || wp_doing_ajax() ) {
			return $content;
		}

		// Verificar se estamos dentro do loop principal (evitar loops secundários, widgets, etc.)
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// REST API requests devem retornar conteúdo original
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $content;
		}

		$meta = AlvoBotPro_Smart_Internal_Links::get_validated_meta( $post_id );

		if ( ! $meta || empty( $meta['enabled'] ) || empty( $meta['blocks'] ) ) {
			return $content;
		}

		// Separar conteúdo em parágrafos (suporta <p>, <div>, <section>, etc.)
		$paragraphs = $this->parse_paragraphs( $content );
		$total      = count( $paragraphs );

		if ( $total < 2 ) {
			return $content;
		}

		$settings = get_option( 'alvobot_smart_links_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$disclaimer = isset( $meta['disclaimer'] ) ? $meta['disclaimer'] : '';

		// Mapear blocos por posição — primeiro bloco com determinada posição vence;
		// duplicatas são descartadas (podem ocorrer por edição manual no modal).
		$blocks_by_position = array();
		foreach ( $meta['blocks'] as $block ) {
			$pos = isset( $block['position'] ) ? $block['position'] : '';
			if ( $pos && ! isset( $blocks_by_position[ $pos ] ) ) {
				$blocks_by_position[ $pos ] = $block;
			}
		}

		// Calcular índices de inserção
		$insertions = array();

		if ( isset( $blocks_by_position['after_first'] ) ) {
			$insertions[1] = $blocks_by_position['after_first'];
		}

		if ( isset( $blocks_by_position['middle'] ) && $total >= 6 ) {
			$mid_index                = intval( $total / 2 );
			$insertions[ $mid_index ] = $blocks_by_position['middle'];
		}

		if ( isset( $blocks_by_position['before_last'] ) && $total >= 4 ) {
			$last_index                = $total - 1;
			$insertions[ $last_index ] = $blocks_by_position['before_last'];
		}

		/**
		 * Filtra as posições de inserção calculadas.
		 *
		 * @param array $insertions         Mapa index => block.
		 * @param int   $total              Total de parágrafos.
		 * @param int   $post_id            Post ID.
		 * @param array $blocks_by_position Blocos indexados por posição.
		 */
		$insertions = apply_filters( 'alvobot_smart_links_insertion_positions', $insertions, $total, $post_id, $blocks_by_position );

		if ( empty( $insertions ) ) {
			return $content;
		}

		// Garantir distância mínima entre blocos (2 parágrafos)
		$sorted_keys = array_keys( $insertions );
		sort( $sorted_keys );
		$valid_keys = array();

		foreach ( $sorted_keys as $key ) {
			$too_close = false;
			foreach ( $valid_keys as $vk ) {
				if ( abs( $key - $vk ) < 2 ) {
					$too_close = true;
					break;
				}
			}
			if ( ! $too_close ) {
				$valid_keys[] = $key;
			}
		}

		$filtered_insertions = array();
		foreach ( $valid_keys as $key ) {
			$filtered_insertions[ $key ] = $insertions[ $key ];
		}

		// Ajustar posições para evitar adjacência com blocos de anúncio,
		// re-validando distância mínima de 2 parágrafos após cada ajuste.
		$adjusted_insertions = array();
		foreach ( $filtered_insertions as $index => $block ) {
			$used = array_keys( $adjusted_insertions );
			$safe = $this->find_non_ad_adjacent_index( $index, $paragraphs, $total, $used );

			// Nenhuma posição viável encontrada: descarta a inserção.
			if ( null === $safe ) {
				continue;
			}

			// Re-verificar distância mínima contra posições já confirmadas.
			$too_close = false;
			foreach ( $used as $u ) {
				if ( abs( $safe - $u ) < 2 ) {
					$too_close = true;
					break;
				}
			}

			// Conservador: não insere se ficou muito perto de outra CTA.
			if ( ! $too_close ) {
				$adjusted_insertions[ $safe ] = $block;
			}
		}
		$filtered_insertions = $adjusted_insertions;

		// Inserir blocos de trás para frente (para manter índices corretos)
		krsort( $filtered_insertions );
		foreach ( $filtered_insertions as $index => $block ) {
			$html = $this->renderer->render_block( $block, $disclaimer, $settings );
			if ( ! empty( $html ) ) {
				/**
				 * Filtra o HTML de um bloco antes de inserir no conteúdo.
				 *
				 * @param string $html     HTML renderizado do bloco.
				 * @param array  $block    Dados do bloco.
				 * @param int    $post_id  Post ID.
				 * @param array  $settings Configurações do módulo.
				 */
				$html = apply_filters( 'alvobot_smart_links_render_block', $html, $block, $post_id, $settings );
				array_splice( $paragraphs, $index, 0, array( $html ) );
			}
		}

		return implode( '', $paragraphs );
	}

	/**
	 * Separa o conteúdo HTML em blocos lógicos.
	 * Suporta parágrafos <p>, divs de page builders, e outros blocos HTML.
	 */
	private function parse_paragraphs( $content ) {
		// Padrão 1: Conteúdo baseado em <p> (WordPress clássico)
		if ( preg_match( '/<\/p>/i', $content ) ) {
			return $this->parse_by_tag( $content, 'p' );
		}

		// Padrão 2: Conteúdo baseado em <div> (page builders como Elementor, Divi)
		if ( preg_match( '/<\/div>/i', $content ) ) {
			return $this->parse_by_tag( $content, 'div' );
		}

		// Padrão 3: Conteúdo baseado em <br> (editor simples)
		$parts       = preg_split( '/(<br\s*\/?>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		$parts_count = count( $parts );
		if ( $parts_count > 2 ) {
			$paragraphs     = array();
			$pending_prefix = '';
			for ( $i = 0; $i < $parts_count; $i += 2 ) {
				$para = $parts[ $i ];
				if ( isset( $parts[ $i + 1 ] ) ) {
					$para .= $parts[ $i + 1 ];
				}
				if ( $this->is_ad_block( $para ) ) {
					$pending_prefix .= $para;
				} elseif ( trim( wp_strip_all_tags( $para ) ) ) {
					$paragraphs[]   = $pending_prefix . $para;
					$pending_prefix = '';
				} else {
					$pending_prefix .= $para;
				}
			}
			if ( $pending_prefix !== '' ) {
				if ( ! empty( $paragraphs ) ) {
					$paragraphs[ count( $paragraphs ) - 1 ] .= $pending_prefix;
				} else {
					$paragraphs[] = $pending_prefix;
				}
			}
			if ( count( $paragraphs ) >= 2 ) {
				return $paragraphs;
			}
		}

		// Fallback: retorna o conteúdo inteiro como 1 bloco (sem injeção)
		return array( $content );
	}

	/**
	 * Separa conteúdo por tag de fechamento (</p>, </div>, etc.).
	 *
	 * Blocos de anúncio são tratados de acordo com seu conteúdo:
	 *
	 * - Bloco de anúncio VAZIO (placeholder JS, ex: Ad Inserter):
	 *   Ancorado ao parágrafo ANTERIOR como sufixo, para que is_adjacent_to_ad()
	 *   o detecte corretamente ao verificar o parágrafo antes da inserção.
	 *
	 * - Bloco MISTO (div de anúncio vazia + texto no mesmo raw_block):
	 *   extract_leading_ad() separa a parte de anúncio (sufixo do parágrafo anterior)
	 *   do texto restante (novo parágrafo).
	 *
	 * - Bloco de anúncio COM TEXTO (ad renderizado diretamente com conteúdo):
	 *   Mantido como pending_prefix para o próximo parágrafo (comportamento original).
	 */
	private function parse_by_tag( $content, $tag ) {
		$parts = preg_split( '/(<\/' . preg_quote( $tag, '/' ) . '>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

		$raw_blocks  = array();
		$parts_count = count( $parts );
		for ( $i = 0; $i < $parts_count; $i += 2 ) {
			$para = $parts[ $i ];
			if ( isset( $parts[ $i + 1 ] ) ) {
				$para .= $parts[ $i + 1 ];
			}
			$raw_blocks[] = $para;
		}

		$paragraphs     = array();
		$pending_prefix = ''; // HTML de anúncio/vazio a ser prefixado no próximo parágrafo real

		foreach ( $raw_blocks as $block ) {
			if ( $this->is_ad_block( $block ) ) {
				$has_text = (bool) trim( wp_strip_all_tags( $block ) );

				if ( ! $has_text ) {
					// Placeholder vazio (ex: Ad Inserter div com data-code): ancora ao parágrafo anterior
					// para que is_adjacent_to_ad() o detecte ao verificar paragraphs[$index - 1].
					if ( ! empty( $paragraphs ) ) {
						$paragraphs[ count( $paragraphs ) - 1 ] .= $block;
					} else {
						$pending_prefix .= $block;
					}
				} else {
					// Bloco misto: tenta separar a div de anúncio vazia do texto que a acompanha
					$split = $this->extract_leading_ad( $block );
					if ( $split ) {
						// Ancora o trecho de anúncio no parágrafo anterior
						if ( ! empty( $paragraphs ) ) {
							$paragraphs[ count( $paragraphs ) - 1 ] .= $split['ad'];
						} else {
							$pending_prefix .= $split['ad'];
						}
						// O texto restante vira um novo parágrafo
						$paragraphs[]   = $pending_prefix . $split['text'];
						$pending_prefix = '';
					} else {
						// Ad com conteúdo inline (ex: <ins adsbygoogle>): mantém como prefixo
						$pending_prefix .= $block;
					}
				}
			} elseif ( trim( wp_strip_all_tags( $block ) ) ) {
				// Parágrafo com texto: consome o prefixo pendente
				$paragraphs[]   = $pending_prefix . $block;
				$pending_prefix = '';
			} else {
				// Vazio/whitespace: adia junto com qualquer anúncio pendente
				$pending_prefix .= $block;
			}
		}

		// Qualquer bloco pendente no final vai para o último parágrafo
		if ( $pending_prefix !== '' ) {
			if ( ! empty( $paragraphs ) ) {
				$paragraphs[ count( $paragraphs ) - 1 ] .= $pending_prefix;
			} else {
				$paragraphs[] = $pending_prefix;
			}
		}

		return $paragraphs;
	}

	/**
	 * Separa um bloco misto (div vazia de anúncio + texto na mesma fatia) em duas partes.
	 *
	 * Padrão alvo: o Ad Inserter injeta divs como
	 *   <div class='ai-viewports ...' data-code='BASE64...'></div>
	 * e, pelo fato do preg_split cortar em </p>, essa div vazia fica colada
	 * ao início do próximo raw_block junto com o <h2>, <p> etc. seguintes.
	 *
	 * O método extrai 1+ elementos de bloco VAZIOS (apenas whitespace entre open/close)
	 * no início do raw_block, retornando:
	 *   ['ad'  => '<div...></div>',          // sufixo para o parágrafo anterior
	 *    'text' => '<h2>...</h2><p>...</p>']  // texto que vira novo parágrafo
	 *
	 * Retorna null se nenhum elemento de anúncio vazio for encontrado no início.
	 *
	 * NOTA: apenas pares open/close são considerados (não self-closing) para evitar
	 * capturar erroneamente tags de abertura de elementos com conteúdo visível.
	 *
	 * @param string $block HTML do bloco misto.
	 * @return array|null
	 */
	private function extract_leading_ad( $block ) {
		// Captura 1 ou mais pares <tag></tag> VAZIOS (só whitespace entre eles) no início.
		// Apenas pares open+close são aceitos — evita capturar a tag de abertura de um
		// elemento que ainda tem conteúdo (o que partiria o HTML e deixaria tags órfãs).
		// A tag de fechamento não precisa ser a mesma tag de abertura (o PHP não valida HTML),
		// mas na prática Ad Inserter sempre emite <div></div>.
		$tag_names = 'div|ins|aside|section|figure|span|noscript';
		$open_tag  = '<(?:' . $tag_names . ')\b[^>]*>';
		$close_tag = '<\/(?:' . $tag_names . ')>';
		$pattern   = '/^(?:\s*' . $open_tag . '\s*' . $close_tag . '\s*)+/is';

		if ( ! preg_match( $pattern, $block, $matches ) ) {
			return null;
		}

		$ad_part   = $matches[0];
		$text_part = substr( $block, strlen( $ad_part ) );

		// Só vale se a parte de anúncio for de fato detectada como ad.
		if ( ! $this->is_ad_block( $ad_part ) ) {
			return null;
		}

		// A parte de texto restante deve ter conteúdo real.
		if ( '' === trim( wp_strip_all_tags( $text_part ) ) ) {
			return null;
		}

		return array(
			'ad'   => $ad_part,
			'text' => $text_part,
		);
	}

	/**
	 * Verifica se uma posição de inserção seria "insegura":
	 *   - adjacente a um bloco de anúncio (antes ou depois), ou
	 *   - o bloco ANTES da inserção termina com </h1>/</h2> (CTA abaixo de título standalone), ou
	 *   - o bloco DEPOIS da inserção começa com <h1>/<h2> (CTA imediatamente acima de um título).
	 *
	 * IMPORTANTE — por que a verificação é direcional:
	 * O split por </p> mescla um heading com o parágrafo seguinte num único bloco,
	 * ex.: "<h2>Título</h2><p>Conteúdo</p>". Esse bloco COMEÇA com <h2>, mas a CTA
	 * inserida APÓS ele aparece depois de todo o conteúdo do bloco (não colada ao heading).
	 * Por isso, só verificamos se o bloco ANTES termina com heading (não se começa),
	 * e só verificamos se o bloco DEPOIS começa com heading (não se termina).
	 *
	 * @param int    $index      Índice proposto para inserção.
	 * @param array  $paragraphs Array de parágrafos já parseados.
	 * @return bool
	 */
	private function is_adjacent_to_ad( $index, $paragraphs ) {
		// Bloco imediatamente antes da inserção
		if ( $index > 0 ) {
			$before = $paragraphs[ $index - 1 ];
			if ( $this->is_ad_block( $before ) ) {
				return true;
			}
			// Heading standalone no final (ex.: <h2>Veja também</h2> sem parágrafo seguinte)
			if ( preg_match( '/<\/h[12]>\s*$/i', rtrim( $before ) ) ) {
				return true;
			}
		}
		// Bloco imediatamente depois da inserção
		if ( isset( $paragraphs[ $index ] ) ) {
			$after = $paragraphs[ $index ];
			if ( $this->is_ad_block( $after ) ) {
				return true;
			}
			// Bloco começa com heading: CTA ficaria colada imediatamente acima do título
			if ( preg_match( '/^<h[12]\b/i', ltrim( $after ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Dada uma posição de inserção proposta, retorna uma posição segura que não fique
	 * colada a um bloco de anúncio. Tenta +1 primeiro (mais conteúdo entre CTA e anúncio),
	 * depois -1. Nunca sai do intervalo [1, $total - 1] para respeitar a regra de
	 * inserção somente a partir do 2° parágrafo.
	 *
	 * Retorna null quando nenhuma posição segura for encontrada — o chamador deve
	 * descartar a inserção nesses casos (melhor não inserir do que inserir adjacente ao ad).
	 *
	 * @param int   $index        Índice proposto.
	 * @param array $paragraphs   Array de parágrafos parseados.
	 * @param int   $total        Total de parágrafos.
	 * @param array $used_indices Índices já ocupados por outras inserções.
	 * @return int|null Índice seguro ou null se nenhuma posição viável existir.
	 */
	private function find_non_ad_adjacent_index( $index, $paragraphs, $total, $used_indices ) {
		$min = 1; // Nunca antes do 2° parágrafo.

		if ( ! $this->is_adjacent_to_ad( $index, $paragraphs ) ) {
			return $index;
		}

		// Tenta ir para baixo (+1).
		$down = $index + 1;
		if ( $down < $total && ! in_array( $down, $used_indices, true ) && ! $this->is_adjacent_to_ad( $down, $paragraphs ) ) {
			return $down;
		}

		// Tenta ir para cima (-1).
		$up = $index - 1;
		if ( $up >= $min && ! in_array( $up, $used_indices, true ) && ! $this->is_adjacent_to_ad( $up, $paragraphs ) ) {
			return $up;
		}

		// Nenhuma posição segura disponível: descarta a inserção.
		return null;
	}

	/**
	 * Detecta se um bloco HTML é um bloco de anúncio ou placeholder de anúncio.
	 *
	 * Cobre os seguintes cenários:
	 *
	 * INJEÇÃO PHP DIRETA (ad já renderizado no conteúdo):
	 *   - Google AdSense (<ins class="adsbygoogle">)
	 *   - Google Ad Manager / GPT (div-gpt-ad-*, googletag, gpt-ad-)
	 *   - Advanced Ads (advads-)
	 *   - WP QUADS / Quick AdSense (wp-quads, quadsmiddle)
	 *   - Ezoic (ezoic-)
	 *   - Raptive / CafeMedia (raptive-, cafemedia-)
	 *   - Mediavine (mediavine-ad-)
	 *   - AdThrive (adthrive-)
	 *   - Setupad (setupad-)
	 *   - Monumetric (monumetric-)
	 *   - Carbon Ads (carbonads)
	 *   - Media.net (medianet-ad, mn_)
	 *   - Taboola (tbl-, taboola-)
	 *   - Outbrain (OUTBRAIN, ob-widget-)
	 *   - Mgid (mgid-)
	 *   - PropellerAds (propeller-ads-)
	 *   - BuySellAds (bsa-ads-)
	 *
	 * INJEÇÃO VIA JAVASCRIPT (placeholder PHP → ad injetado no DOM pelo JS):
	 *   - Ad Inserter: ai-insert-{n}-{id}, ai-viewport-{n}, code-block-{n}
	 *   - Advanced Ads: advads-placement-*, .advads-{id}
	 *   - WP Ad Manager: wpad-placement-*
	 *   - scripts inline com adsbygoogle.push, googletag.cmd.push, etc.
	 *
	 * PADRÕES GENÉRICOS:
	 *   - Classes/IDs com prefixo/sufixo "ad", "ads", "advert"
	 *   - Blocos Gutenberg de plugins de anúncio
	 *
	 * @param string $html HTML do bloco.
	 * @return bool
	 */
	private function is_ad_block( $html ) {
		$patterns = array(

			// ── Google AdSense ─────────────────────────────────────────────────
			'/<ins\b[^>]*\badsbygoogle\b/i',
			'/adsbygoogle\s*=\s*window\.adsbygoogle/i',   // script push inline

			// ── Google Ad Manager / GPT ────────────────────────────────────────
			'/class=["\'][^"\']*\bdiv[-_]gpt[-_]ad\b/i',  // div-gpt-ad-*
			'/\bid=["\']div[-_]gpt[-_]ad/i',
			'/googletag\.(cmd|display|defineSlot)\b/i',    // inline GPT scripts
			'/class=["\'][^"\']*\bgpt[-_]ad\b/i',

			// ── Ad Inserter (WordPress plugin) ─────────────────────────────────
			// Placeholder JS: ai-insert-{n}-{post_id} + ai-insert-{n}
			'/class=["\'][^"\']*\bai[-_]insert[-_]\d/i',
			// Viewport wrapper: ai-viewport-{n}
			'/class=["\'][^"\']*\bai[-_]viewport[-_]\d/i',
			// data attribute exclusivo do Ad Inserter JS
			'/\bdata[-_]insertion[-_]position\s*=/i',
			// code-block-{n} (classe numerada gerada pelo Ad Inserter no wrapper do ad)
			// Exige sufixo numérico para não pegar syntax highlighters genéricos (ex: .code-block)
			'/class=["\'][^"\']*\bcode[-_]block[-_]\d+\b/i',

			// ── Advanced Ads ───────────────────────────────────────────────────
			'/class=["\'][^"\']*\badvads[-_]/i',
			'/class=["\'][^"\']*[-_]advads\b/i',
			'/class=["\'][^"\']*\badvads[-_]placement\b/i',

			// ── WP QUADS / Quick AdSense ───────────────────────────────────────
			'/class=["\'][^"\']*\bwp[-_]quads\b/i',
			'/class=["\'][^"\']*\bquadsmiddle\b/i',
			'/class=["\'][^"\']*\bquads[-_]ad\b/i',

			// ── Ezoic ──────────────────────────────────────────────────────────
			'/class=["\'][^"\']*\bezoic[-_]/i',
			'/\bid=["\']ezoic[-_]/i',

			// ── Raptive / CafeMedia ────────────────────────────────────────────
			'/class=["\'][^"\']*\braptive[-_]/i',
			'/class=["\'][^"\']*\bcafemedia[-_]/i',
			'/class=["\'][^"\']*\badthrive[-_]/i',       // AdThrive (agora Raptive)

			// ── Mediavine ──────────────────────────────────────────────────────
			'/class=["\'][^"\']*\bmediavine[-_]ad[-_]/i',
			'/\bid=["\']mediavine[-_]/i',

			// ── Setupad ────────────────────────────────────────────────────────
			'/class=["\'][^"\']*\bsetupad[-_]/i',

			// ── Monumetric ─────────────────────────────────────────────────────
			'/class=["\'][^"\']*\bmonumetric[-_]/i',

			// ── Carbon Ads ─────────────────────────────────────────────────────
			'/class=["\'][^"\']*\bcarbonads\b/i',
			'/\bid=["\']carbonads\b/i',

			// ── Media.net ──────────────────────────────────────────────────────
			'/class=["\'][^"\']*\bmedianet[-_]ad\b/i',
			'/\bid=["\']mn_/i',

			// ── Taboola ────────────────────────────────────────────────────────
			'/class=["\'][^"\']*\btbl[-_]/i',
			'/class=["\'][^"\']*\btaboola[-_]/i',
			'/\brc\.taboola\.com\b/i',

			// ── Outbrain ───────────────────────────────────────────────────────
			'/class=["\'][^"\']*\bOUTBRAIN\b/',
			'/class=["\'][^"\']*\bob[-_]widget[-_]/i',
			'/\bwidgets\.outbrain\.com\b/i',

			// ── Mgid ───────────────────────────────────────────────────────────
			'/class=["\'][^"\']*\bmgid[-_]/i',
			'/\bjsid\.mgid\.com\b/i',

			// ── PropellerAds ───────────────────────────────────────────────────
			'/class=["\'][^"\']*\bpropeller[-_]ads[-_]/i',

			// ── BuySellAds ─────────────────────────────────────────────────────
			'/class=["\'][^"\']*\bbsa[-_]ads[-_]/i',

			// ── WP Ad Manager ──────────────────────────────────────────────────
			'/class=["\'][^"\']*\bwpad[-_]placement[-_]/i',

			// ── Padrões genéricos de classe/id com "ad" ────────────────────────
			// classe começa com "ad-" ou "ads-"
			'/class=["\'][^"\']*(?<![a-z])ads?[-_]/i',
			// classe termina com "-ad" ou "_ad"
			'/class=["\'][^"\']*[-_]ads?(?=["\'\s])/i',
			// id começa com "ad-", "ads-" ou "advert"
			'/\bid=["\'](?:ads?[-_]|advert)/i',

			// ── Scripts inline de redes de anúncio ────────────────────────────
			'/(adsbygoogle|googletag|_mgq|taboola|outbrain|medianet)\s*[=.].*push\s*\(/i',

			// ── Comentários de blocos Gutenberg de plugins de anúncio ──────────
			'/<!--\s*wp:(?:advads|advanced-ads|ad-inserter|adsense|gadsense)\//i',

		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $html ) ) {
				return true;
			}
		}

		return false;
	}
}
