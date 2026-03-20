<?php
/**
 * Documentation tab view
 *
 * @package Alvobot_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="quiz-docs-container">
	<div class="docs-content">
		<div class="docs-section">
			<h2><?php esc_html_e( 'Como Usar o Quiz Builder', 'alvobot-pro' ); ?></h2>
			<p><?php esc_html_e( 'O Quiz Builder AlvoBot Pro permite criar quizzes interativos e formulários usando shortcodes simples. Totalmente integrado ao sistema AlvoBot com navegação por URLs únicas.', 'alvobot-pro' ); ?></p>
			
			<h3><?php esc_html_e( 'Sintaxe Básica', 'alvobot-pro' ); ?></h3>
			<pre><code>[quiz redirect_url="/obrigado" style="modern" url_mode="suffix"]
[
	{
		"question": "Qual é a capital do Brasil?",
		"answers": ["São Paulo", "Brasília", "Rio de Janeiro"],
		"correct": [1],
		"explanation": "Brasília é a capital federal desde 1960"
	},
	{
		"question": "Quais são países da América do Sul?",
		"answers": ["Brasil", "Argentina", "França", "Chile"],
		"correct": [0, 1, 3],
		"explanation": "França fica na Europa"
	}
]
[/quiz]</code></pre>
			
			<h3><?php esc_html_e( 'Parâmetros Disponíveis', 'alvobot-pro' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Parâmetro', 'alvobot-pro' ); ?></th>
						<th><?php esc_html_e( 'Padrão', 'alvobot-pro' ); ?></th>
						<th><?php esc_html_e( 'Descrição', 'alvobot-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>redirect_url</code></td>
						<td>-</td>
						<td><?php esc_html_e( 'URL para redirecionar após completar', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>style</code></td>
						<td>default</td>
						<td><?php esc_html_e( 'Estilo visual: default, modern, minimal', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>show_progress</code></td>
						<td>true</td>
						<td><?php esc_html_e( 'Exibe barra de progresso (formato 1/5, 2/5...)', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>allow_back</code></td>
						<td>true</td>
						<td><?php esc_html_e( 'Permite voltar às questões anteriores', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>randomize</code></td>
						<td>false</td>
						<td><?php esc_html_e( 'Embaralha a ordem das questões', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>auto_advance</code></td>
						<td>true</td>
						<td><?php esc_html_e( 'Avança automaticamente ao selecionar (sempre ativo)', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>show_score</code></td>
						<td>true</td>
						<td><?php esc_html_e( 'Exibe pontuação ao final (modo quiz)', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>show_nav_buttons</code></td>
						<td>false</td>
						<td><?php esc_html_e( 'Exibe botões de navegação', 'alvobot-pro' ); ?></td>
					</tr>
					<tr>
						<td><code>url_mode</code></td>
						<td>params</td>
						<td><?php esc_html_e( 'Modo de URL: "params" ou "suffix" (compatível com AdSense)', 'alvobot-pro' ); ?></td>
					</tr>
				</tbody>
			</table>
			
			<h3><?php esc_html_e( 'Modos de Operação', 'alvobot-pro' ); ?></h3>
			<p><?php esc_html_e( 'O plugin detecta automaticamente o modo baseado na presença do campo "correct":', 'alvobot-pro' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Modo Quiz:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Quando as questões possuem respostas corretas. Suporte a múltiplas respostas corretas (array de índices). Calcula pontuação e pode exibir explicações.', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Modo Formulário:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Quando as questões não possuem respostas corretas. Apenas coleta respostas.', 'alvobot-pro' ); ?></li>
			</ul>
			
			<h3><?php esc_html_e( 'Personalização Visual de Respostas', 'alvobot-pro' ); ?></h3>
			<p><?php esc_html_e( 'Cada resposta pode ter estilo personalizado com as seguintes opções:', 'alvobot-pro' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Cor de fundo:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Define a cor de fundo da opção de resposta', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Cor do texto:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Define a cor do texto da resposta', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Tamanho da fonte:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Define o tamanho em pixels (10-32px)', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Peso da fonte:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Define a espessura da fonte (300-800)', 'alvobot-pro' ); ?></li>
			</ul>
			
			<h4><?php esc_html_e( 'Exemplo com Estilos Personalizados:', 'alvobot-pro' ); ?></h4>
			<pre><code>[quiz style="modern"]
[
	{
		"question": "Qual sua preferência?",
		"answers": [
			{
				"text": "Opção A",
				"styles": {
					"backgroundColor": "#e3f2fd",
					"color": "#1976d2",
					"fontSize": "16px",
					"fontWeight": "600"
				}
			},
			"Opção B simples",
			{
				"text": "Opção C destacada",
				"styles": {
					"backgroundColor": "#f3e5f5",
					"color": "#7b1fa2",
					"fontSize": "18px",
					"fontWeight": "700"
				}
			}
		]
	}
]
[/quiz]</code></pre>

			<h3><?php esc_html_e( 'Personalização de Perguntas', 'alvobot-pro' ); ?></h3>
			<p><?php esc_html_e( 'As perguntas também podem ter estilo personalizado com as seguintes opções:', 'alvobot-pro' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Cor de fundo:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Define a cor de fundo da pergunta', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Cor do texto:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Define a cor do texto da pergunta', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Tamanho da fonte:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Define o tamanho em pixels (12-36px)', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Peso da fonte:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Define a espessura da fonte (300-800)', 'alvobot-pro' ); ?></li>
			</ul>
			
			<h4><?php esc_html_e( 'Exemplo com Pergunta Estilizada:', 'alvobot-pro' ); ?></h4>
			<pre><code>{
	"question": "Pergunta importante com destaque",
	"answers": ["Sim", "Não"],
	"styles": {
		"backgroundColor": "#ffeb3b",
		"color": "#d32f2f",
		"fontSize": "20px",
		"fontWeight": "700"
	}
}</code></pre>

			<h3><?php esc_html_e( 'Gerador de Quiz com IA', 'alvobot-pro' ); ?></h3>
			<p><?php esc_html_e( 'Use este prompt completo com qualquer IA (ChatGPT, Claude, Gemini, etc.) para gerar automaticamente um quiz WordPress:', 'alvobot-pro' ); ?></p>
			<div class="docs-ai-prompt">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
					<h4 style="margin: 0;"><?php esc_html_e( 'Prompt Completo para IA:', 'alvobot-pro' ); ?></h4>
					<button type="button" id="copy-ai-prompt" class="alvobot-btn alvobot-btn-secondary">
						<i data-lucide="clipboard" class="alvobot-icon"></i>
						<?php esc_html_e( 'Copiar Prompt', 'alvobot-pro' ); ?>
					</button>
				</div>
				<div id="ai-prompt-content" style="background: #f5f5f5; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6;">
<strong>🚀 GERADOR MASTER DE QUIZ WordPress - ALVOBOT QUIZ PLUGIN</strong>

Você é um engenheiro especialista em criação de quizzes persuasivos e formulários de alta conversão para WordPress. Sua missão é gerar um shortcode perfeito do plugin Alvobot Quiz que maximize o engajamento e conduza o usuário até a URL final com interesse genuíno.

<strong>📋 INFORMAÇÕES DO PROJETO (PREENCHA TODAS):</strong>
📝 <strong>Tópico/Assunto:</strong> [DESCREVA AQUI O TEMA DO QUIZ - seja específico]
🎯 <strong>Objetivo Principal:</strong> [ESCOLHA: educacional, lead generation, pesquisa de satisfação, teste de conhecimento, qualificação de prospects, diagnóstico, avaliação de personalidade]
🎪 <strong>Tipo de Quiz:</strong> [ESCOLHA: Quiz com respostas corretas OU Formulário de pesquisa/coleta]
👥 <strong>Público-Alvo:</strong> [DESCREVA: idade, profissão, interesses, nível de conhecimento]
💰 <strong>Proposta de Valor:</strong> [O QUE o usuário ganha ao completar? Ex: diagnóstico gratuito, resultado personalizado, desconto, conteúdo exclusivo]
📄 <strong>Conteúdo Base:</strong> [COLE AQUI: artigo, texto, informações relevantes, dados do negócio]
🔢 <strong>Número de Questões:</strong> [ESPECIFIQUE: recomendado 3-8 questões para máximo engajamento]
🎨 <strong>Estilo Visual:</strong> [ESCOLHA: modern (gradientes e sombras), minimal (clean e simples), default (clássico)]
🔗 <strong>URL de Destino:</strong> [URL FINAL - ex: /obrigado, /resultado, /desconto, /diagnostico]
⚡ <strong>Tom/Personalidade:</strong> [ESCOLHA: profissional, descontraído, urgente, educativo, inspirador]

<strong>📖 DOCUMENTAÇÃO TÉCNICA COMPLETA:</strong>

O Alvobot Quiz é um plugin WordPress avançado que cria quizzes interativos usando shortcodes. 

<strong>🔧 SINTAXE EXATA:</strong>
[quiz parâmetros]
[JSON_ARRAY_QUESTÕES]
[/quiz]

<strong>⚙️ PARÂMETROS OBRIGATÓRIOS E OPCIONAIS:</strong>

<em>Parâmetros de Redirecionamento:</em>
- redirect_url="URL" → OBRIGATÓRIO para conversão
- url_mode="params" → Navegação rápida (padrão)
- url_mode="suffix" → Compatível com AdSense (recarrega página)

<em>Parâmetros de Estilo:</em>
- style="default" → Estilo padrão limpo
- style="modern" → Gradientes, sombras, visual premium  
- style="minimal" → Ultra clean, foco no conteúdo

<em>Parâmetros de Experiência:</em>
- show_progress="true" → Barra de progresso (aumenta conclusão)
- show_progress="false" → Sem barra (para quizzes curtos)
- allow_back="true" → Permite voltar (reduz ansiedade)
- allow_back="false" → Força progressão linear
- show_nav_buttons="true" → Botões próximo/anterior
- show_nav_buttons="false" → Apenas clique nas respostas
- auto_advance="true" → Avança automaticamente (mais fluido)
- auto_advance="false" → Controle manual (mais reflexão)
- randomize="true" → Embaralha questões (evita decoração)
- randomize="false" → Ordem fixa (para fluxo lógico)

<strong>🎨 PERSONALIZAÇÃO VISUAL AVANÇADA:</strong>

<em>Estilos de QUESTÕES (aplicados no objeto da questão):</em>
{
	"question": "Texto da pergunta",
	"styles": {
	"backgroundColor": "#cor_hex",    // Cor de fundo da pergunta
	"color": "#cor_hex",             // Cor do texto da pergunta  
	"fontSize": "20px",              // Tamanho: 12px-36px
	"fontWeight": "700"              // Peso: 300,400,500,600,700,800
	}
}

<em>Estilos de RESPOSTAS (aplicados em cada resposta):</em>
{
	"text": "Texto da resposta",
	"styles": {
	"backgroundColor": "#cor_hex",    // Cor de fundo da resposta
	"color": "#cor_hex",             // Cor do texto da resposta
	"fontSize": "16px",              // Tamanho: 10px-32px  
	"fontWeight": "600"              // Peso: 300,400,500,600,700,800
	}
}

<strong>📝 ESTRUTURAS DETALHADAS POR TIPO:</strong>

<em>🎓 QUIZ EDUCACIONAL (com respostas corretas):</em>
{
	"question": "Pergunta clara e direta",
	"answers": ["Opção A", "Opção B", "Opção C", "Opção D"],
	"correct": 2,                    // Índice da resposta correta (começa em 0)
	"explanation": "Explicação educativa e motivadora da resposta"
}

<em>📊 FORMULÁRIO DE PESQUISA (sem respostas corretas):</em>
{
	"question": "Pergunta para coleta de dados",
	"answers": ["Opção 1", "Opção 2", "Opção 3"]
	// SEM campo "correct" = modo formulário automático
}

<em>🎯 QUIZ DE QUALIFICAÇÃO (misto - algumas com correct, outras sem):</em>
[
	{
	"question": "Qual seu nível de experiência?",
	"answers": ["Iniciante", "Intermediário", "Avançado", "Expert"]
	// Sem "correct" = coleta dados
	},
	{
	"question": "Qual a melhor prática para SEO?",
	"answers": ["Keyword stuffing", "Conteúdo relevante", "Meta tags apenas", "Links pagos"],
	"correct": 1,
	"explanation": "Conteúdo relevante é a base do SEO moderno."
	// Com "correct" = testa conhecimento
	}
]

<em>🚀 QUESTÕES COM 1 RESPOSTA (Call-to-Action e Confirmações):</em>
{
	"question": "Deseja receber seu diagnóstico personalizado GRATUITO?",
	"answers": ["Sim, quero receber agora!"]
	// Apenas 1 resposta = CTA direto, confirmação ou transição
}

<em>💡 CASOS DE USO PARA 1 RESPOSTA:</em>
- Call-to-Actions: "Quero receber o resultado", "Solicitar proposta"
- Confirmações: "Aceito os termos", "Concordo com a política"  
- Transições: "Continuar para próxima etapa", "Prosseguir"
- Informações: "Clique para ver o diagnóstico", "Acessar conteúdo"

<strong>🎨 PALETA DE CORES ESTRATÉGICAS:</strong>

<em>Cores para CONVERSÃO (CTAs, respostas importantes):</em>
- Verde Sucesso: #4CAF50, #2E7D32, #1B5E20
- Azul Confiança: #2196F3, #1976D2, #0D47A1  
- Laranja Urgência: #FF9800, #F57C00, #E65100
- Roxo Premium: #9C27B0, #7B1FA2, #4A148C

<em>Cores para EMOCÕES (pesquisas, satisfação):</em>
- Vermelho Negativo: #F44336, #D32F2F, #B71C1C
- Amarelo Neutro: #FFEB3B, #FBC02D, #F57F17
- Verde Positivo: #4CAF50, #388E3C, #1B5E20
- Cinza Neutralidade: #9E9E9E, #616161, #424242

<em>Cores para EDUCAÇÃO (conhecimento, aprendizado):</em>
- Azul Académico: #1976D2, #303F9F, #0277BD
- Verde Crescimento: #388E3C, #2E7D32, #1B5E20
- Roxo Sabedoria: #7B1FA2, #6A1B9A, #4A148C

<strong>🚀 EXEMPLOS COMPLETOS COM 6 QUESTÕES - PROGRAMAS GOVERNAMENTAIS REAIS:</strong>

<em>🚗 EXEMPLO 1 - PRÉ-QUALIFICAÇÃO PARA CNH GRATUITA DO GOVERNO:</em>
[quiz style="modern" show_progress="true" auto_advance="true" redirect_url="/inscricao-cnh-gratuita"]
[
	{
	"question": "Você já possui Carteira Nacional de Habilitação (CNH)?",
	"styles": {
		"backgroundColor": "#1976D2",
		"color": "#ffffff",
		"fontSize": "20px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "Não, nunca tirei",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		"Sim, mas está vencida há mais de 5 anos",
		"Sim, mas foi cassada/suspensa",
		{
		"text": "Já possuo CNH válida",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Qual é a sua renda familiar mensal?",
	"answers": [
		{
		"text": "Até 2 salários mínimos",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "De 2 a 3 salários mínimos",
		"styles": {"backgroundColor": "#8BC34A", "color": "#ffffff"}
		},
		{
		"text": "De 3 a 5 salários mínimos",
		"styles": {"backgroundColor": "#FFEB3B", "color": "#F57C00"}
		},
		{
		"text": "Mais de 5 salários mínimos",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Você está inscrito no CadÚnico (Cadastro Único)?",
	"answers": [
		{
		"text": "Sim, estou inscrito",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Não sei o que é",
		"Não, mas gostaria de me inscrever",
		"Não preciso, tenho renda alta"
	]
	},
	{
	"question": "Qual o principal motivo para tirar a CNH?",
	"styles": {
		"backgroundColor": "#E3F2FD",
		"fontSize": "18px",
		"fontWeight": "600"
	},
	"answers": [
		{
		"text": "Conseguir um emprego melhor",
		"styles": {
			"backgroundColor": "#FF9800",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Independência e mobilidade",
		"Trabalhar como motorista de app",
		"Emergências familiares",
		"Realizar um sonho pessoal"
	]
	},
	{
	"question": "Você tem disponibilidade para as aulas teóricas e práticas?",
	"answers": [
		{
		"text": "Sim, tenho total disponibilidade",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Tenho disponibilidade parcial",
		"Só nos finais de semana",
		"Só no período noturno",
		"Preciso ajustar minha agenda"
	]
	},
	{
	"question": "O que você mais deseja receber AGORA para conseguir sua CNH gratuita?",
	"styles": {
		"backgroundColor": "#FF6D00",
		"color": "#ffffff",
		"fontSize": "22px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "📋 Passo a passo completo da inscrição",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "📍 Locais disponíveis na minha região",
		"styles": {
			"backgroundColor": "#2196F3",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "📚 Material de estudo GRATUITO",
		"styles": {
			"backgroundColor": "#9C27B0",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "⚡ TODOS os benefícios + bônus exclusivos",
		"styles": {
			"backgroundColor": "#FF5722",
			"color": "#ffffff",
			"fontWeight": "800",
			"fontSize": "20px"
		}
		}
	]
	}
]
[/quiz]

<em>🏠 EXEMPLO 2 - PRÉ-QUALIFICAÇÃO MINHA CASA MINHA VIDA:</em>
[quiz style="minimal" show_progress="true" allow_back="true" redirect_url="/pre-cadastro-mcmv"]
[
	{
	"question": "Qual é a sua renda familiar bruta mensal?",
	"styles": {
		"backgroundColor": "#2E7D32",
		"color": "#ffffff",
		"fontSize": "20px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "Até R$ 2.640 (Faixa 1)",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "R$ 2.640 a R$ 4.400 (Faixa 2)",
		"styles": {
			"backgroundColor": "#8BC34A",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "R$ 4.400 a R$ 8.000 (Faixa 3)",
		"styles": {"backgroundColor": "#FFEB3B", "color": "#F57C00"}
		},
		{
		"text": "Mais de R$ 8.000",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Você já possui imóvel próprio?",
	"answers": [
		{
		"text": "Não, nunca tive casa própria",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Não, mas já tive no passado",
		{
		"text": "Sim, mas pretendo trocar",
		"styles": {"backgroundColor": "#FF9800", "color": "#ffffff"}
		},
		{
		"text": "Sim, já tenho casa própria",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Há quanto tempo você trabalha com carteira assinada?",
	"answers": [
		{
		"text": "Mais de 2 anos",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "Entre 1 e 2 anos",
		"styles": {"backgroundColor": "#8BC34A", "color": "#ffffff"}
		},
		"Menos de 1 ano",
		"Sou autônomo/MEI",
		{
		"text": "Estou desempregado",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Qual tipo de imóvel você deseja?",
	"styles": {
		"backgroundColor": "#E8F5E8",
		"fontSize": "18px",
		"fontWeight": "600"
	},
	"answers": [
		{
		"text": "Apartamento em condomínio",
		"styles": {
			"backgroundColor": "#2196F3",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Casa térrea",
		"Casa sobrado",
		"Tanto faz, quero o mais barato",
		"Depende da localização"
	]
	},
	{
	"question": "Em qual região você gostaria de morar?",
	"answers": [
		"Zona central da cidade",
		{
		"text": "Periferia (mais barato)",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Próximo ao trabalho",
		"Próximo à família",
		"Qualquer lugar disponível"
	]
	},
	{
	"question": "O que seria MAIS VALIOSO para você realizar o sonho da casa própria?",
	"styles": {
		"backgroundColor": "#1B5E20",
		"color": "#ffffff",
		"fontSize": "22px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "🏠 Lista de imóveis disponíveis NA MINHA FAIXA",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "💰 Simulação do meu financiamento personalizada",
		"styles": {
			"backgroundColor": "#2196F3",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "📋 Checklist completo de documentos",
		"styles": {
			"backgroundColor": "#FF9800",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "🎯 ACESSO TOTAL: imóveis + simulação + documentos",
		"styles": {
			"backgroundColor": "#E91E63",
			"color": "#ffffff",
			"fontWeight": "800",
			"fontSize": "20px"
		}
		}
	]
	}
]
[/quiz]

<em>🎓 EXEMPLO 3 - PRÉ-QUALIFICAÇÃO PROUNI BOLSA INTEGRAL:</em>
[quiz style="modern" show_progress="true" auto_advance="false" redirect_url="/inscricao-prouni"]
[
	{
	"question": "Você fez o ENEM e obteve pelo menos 450 pontos na média?",
	"styles": {
		"backgroundColor": "#1976D2",
		"color": "#ffffff",
		"fontSize": "20px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "Sim, fiz e passei dos 450 pontos",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		"Fiz, mas não sei minha nota",
		"Fiz, mas não atingi 450 pontos",
		{
		"text": "Não fiz o ENEM",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Qual é a renda per capita da sua família?",
	"answers": [
		{
		"text": "Até 1,5 salário mínimo por pessoa",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "16px"
		}
		},
		{
		"text": "De 1,5 a 3 salários mínimos por pessoa",
		"styles": {
			"backgroundColor": "#8BC34A",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "Mais de 3 salários mínimos por pessoa",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		},
		"Não sei calcular"
	]
	},
	{
	"question": "Como você cursou o ensino médio?",
	"answers": [
		{
		"text": "Integralmente em escola pública",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "Em escola privada com bolsa integral",
		"styles": {
			"backgroundColor": "#8BC34A",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Parte em pública, parte em privada",
		{
		"text": "Integralmente em escola privada",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Você tem alguma deficiência?",
	"styles": {
		"backgroundColor": "#E3F2FD",
		"fontSize": "18px",
		"fontWeight": "600"
	},
	"answers": [
		{
		"text": "Sim, tenho deficiência",
		"styles": {
			"backgroundColor": "#9C27B0",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Não tenho deficiência",
		"Prefiro não informar"
	]
	},
	{
	"question": "Qual curso de graduação você deseja fazer?",
	"answers": [
		{
		"text": "Medicina/Odontologia",
		"styles": {
			"backgroundColor": "#FF5722",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "Engenharia/Tecnologia",
		"styles": {
			"backgroundColor": "#2196F3",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "Direito/Administração",
		"styles": {
			"backgroundColor": "#795548",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Pedagogia/Licenciaturas",
		"Ainda não decidi"
	]
	},
	{
	"question": "O que te ajudaria MAIS para conquistar sua vaga no ProUni?",
	"styles": {
		"backgroundColor": "#0D47A1",
		"color": "#ffffff",
		"fontSize": "22px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "🎯 Cursos com MAIOR chance de aprovação",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "📊 Minha nota de corte personalizada",
		"styles": {
			"backgroundColor": "#2196F3",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "📋 Documentos necessários organizados",
		"styles": {
			"backgroundColor": "#FF9800",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "🚀 ESTRATÉGIA COMPLETA: vagas + notas + documentos",
		"styles": {
			"backgroundColor": "#7B1FA2",
			"color": "#ffffff",
			"fontWeight": "800",
			"fontSize": "20px"
		}
		}
	]
	}
]
[/quiz]

<em>💰 EXEMPLO 4 - PRÉ-QUALIFICAÇÃO FIES FINANCIAMENTO ESTUDANTIL:</em>
[quiz style="modern" show_progress="true" randomize="false" redirect_url="/solicitacao-fies"]
[
	{
	"question": "Você participou do ENEM a partir de 2010?",
	"styles": {
		"backgroundColor": "#1976D2",
		"color": "#ffffff",
		"fontSize": "20px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "Sim, fiz ENEM e obtive mais de 450 pontos",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		"Fiz ENEM mas não sei minha nota",
		"Fiz ENEM mas tirei menos de 450",
		{
		"text": "Não fiz ENEM",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Qual a renda bruta mensal da sua família?",
	"answers": [
		{
		"text": "Até 3 salários mínimos",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700"
		}
		},
		{
		"text": "De 3 a 5 salários mínimos",
		"styles": {
			"backgroundColor": "#8BC34A",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "Mais de 5 salários mínimos",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		},
		"Não sei informar"
	]
	},
	{
	"question": "Você já está matriculado em alguma faculdade?",
	"answers": [
		{
		"text": "Sim, em faculdade privada",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Não, mas já fui aprovado",
		{
		"text": "Não, ainda estou procurando",
		"styles": {"backgroundColor": "#FF9800", "color": "#ffffff"}
		},
		{
		"text": "Estou em faculdade pública",
		"styles": {"backgroundColor": "#F44336", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Como você pretende pagar a faculdade?",
	"styles": {
		"backgroundColor": "#E3F2FD",
		"fontSize": "18px",
		"fontWeight": "600"
	},
	"answers": [
		{
		"text": "100% financiado pelo FIES",
		"styles": {
			"backgroundColor": "#2196F3",
			"color": "#ffffff",
			"fontWeight": "700"
		}
		},
		{
		"text": "Parte FIES, parte família",
		"styles": {
			"backgroundColor": "#8BC34A",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Só com recursos próprios",
		"Bolsa de estudos + FIES",
		"Ainda não sei"
	]
	},
	{
	"question": "Há quanto tempo você saiu do ensino médio?",
	"answers": [
		{
		"text": "Menos de 2 anos",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"De 2 a 5 anos",
		"De 5 a 10 anos", 
		{
		"text": "Mais de 10 anos",
		"styles": {"backgroundColor": "#FF9800", "color": "#ffffff"}
		},
		"Ainda estou cursando"
	]
	},
	{
	"question": "O que seria FUNDAMENTAL para você conseguir seu FIES?",
	"styles": {
		"backgroundColor": "#1565C0",
		"color": "#ffffff",
		"fontSize": "22px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "💰 Calculadora de financiamento personalizada",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "🏫 Faculdades que aceitam minha nota do ENEM",
		"styles": {
			"backgroundColor": "#2196F3",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "📋 Documentação completa necessária",
		"styles": {
			"backgroundColor": "#FF9800",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "🎓 PACOTE COMPLETO: cálculos + faculdades + docs",
		"styles": {
			"backgroundColor": "#E65100",
			"color": "#ffffff",
			"fontWeight": "800",
			"fontSize": "20px"
		}
		}
	]
	}
]
[/quiz]

<em>🧠 EXEMPLO 5 - QUALIFICAÇÃO PARA AUXÍLIO EMERGENCIAL:</em>
[quiz style="minimal" show_progress="true" auto_advance="true" redirect_url="/solicitar-auxilio"]
[
	{
	"question": "Qual é a sua situação de trabalho atual?",
	"styles": {
		"backgroundColor": "#FF5722",
		"color": "#ffffff",
		"fontSize": "20px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "Desempregado sem benefício",
		"styles": {
			"backgroundColor": "#F44336",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "Trabalho informal/autônomo",
		"styles": {
			"backgroundColor": "#FF9800",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "MEI com faturamento baixo",
		"styles": {
			"backgroundColor": "#FFEB3B",
			"color": "#F57C00",
			"fontWeight": "600"
		}
		},
		{
		"text": "Tenho emprego formal",
		"styles": {"backgroundColor": "#4CAF50", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Você é responsável pelo sustento da família?",
	"answers": [
		{
		"text": "Sim, sou o único responsável",
		"styles": {
			"backgroundColor": "#F44336",
			"color": "#ffffff",
			"fontWeight": "700"
		}
		},
		{
		"text": "Sim, mas divido com alguém",
		"styles": {
			"backgroundColor": "#FF9800",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"Não, mas contribuo",
		"Não sou responsável"
	]
	},
	{
	"question": "Quantas pessoas moram na sua casa?",
	"answers": [
		"Moro sozinho",
		{
		"text": "2 a 3 pessoas",
		"styles": {
			"backgroundColor": "#FF9800",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "4 a 6 pessoas",
		"styles": {
			"backgroundColor": "#F44336",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "Mais de 6 pessoas",
		"styles": {
			"backgroundColor": "#B71C1C",
			"color": "#ffffff",
			"fontWeight": "700"
		}
		}
	]
	},
	{
	"question": "Sua renda mensal atual é:",
	"styles": {
		"backgroundColor": "#FFEBEE",
		"fontSize": "18px",
		"fontWeight": "600"
	},
	"answers": [
		{
		"text": "Zero ou quase zero",
		"styles": {
			"backgroundColor": "#F44336",
			"color": "#ffffff",
			"fontWeight": "700"
		}
		},
		{
		"text": "Menos de 1 salário mínimo",
		"styles": {
			"backgroundColor": "#FF5722",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		"1 salário mínimo",
		{
		"text": "Mais de 1 salário mínimo",
		"styles": {"backgroundColor": "#4CAF50", "color": "#ffffff"}
		}
	]
	},
	{
	"question": "Você tem conta no banco?",
	"answers": [
		{
		"text": "Sim, conta corrente",
		"styles": {
			"backgroundColor": "#4CAF50",
			"color": "#ffffff",
			"fontWeight": "600"
		}
		},
		{
		"text": "Sim, conta poupança",
		"styles": {
			"backgroundColor": "#8BC34A",
			"color": "#ffffff"
		}
		},
		"Não tenho conta",
		"Conta está bloqueada"
	]
	},
	{
	"question": "O que você PRECISA URGENTE para resolver sua situação?",
	"styles": {
		"backgroundColor": "#B71C1C",
		"color": "#ffffff",
		"fontSize": "22px",
		"fontWeight": "700"
	},
	"answers": [
		{
		"text": "🚨 Como solicitar o auxílio HOJE MESMO",
		"styles": {
			"backgroundColor": "#F44336",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "💰 Outros benefícios que eu posso receber",
		"styles": {
			"backgroundColor": "#FF9800",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "📋 Documentos que preciso ter em mãos",
		"styles": {
			"backgroundColor": "#2196F3",
			"color": "#ffffff",
			"fontWeight": "700",
			"fontSize": "18px"
		}
		},
		{
		"text": "⚡ ACESSO TOTAL: solicitação + benefícios + documentos",
		"styles": {
			"backgroundColor": "#4A148C",
			"color": "#ffffff",
			"fontWeight": "800",
			"fontSize": "20px"
		}
		}
	]
	}
]
[/quiz]

<strong>🎯 ESTRATÉGIAS DE ENGAJAMENTO E CONVERSÃO:</strong>

<em>Para MAXIMIZAR CONCLUSÃO:</em>
1. Use show_progress="true" sempre
2. Limite a 3-7 questões (sweet spot: 6)
3. Perguntas progressivas: fáceis → pessoais → qualificadoras → CTA
4. auto_advance="true" para fluidez
5. Estile respostas importantes para guiar escolhas
6. **ÚLTIMA QUESTÃO = CTA OBRIGATÓRIA** para o post final

<em>Para AUMENTAR INTERESSE NA URL FINAL:</em>
1. Crie expectativa: "Descubra seu perfil...", "Veja seu resultado..."
2. Prometa valor específico: diagnóstico, relatório, desconto
3. Use urgência sutil: "Resultado disponível por 24h"
4. Personalize: baseie questões no público específico
5. **ÚLTIMA QUESTÃO**: Sempre termine com pergunta que desperte desejo pelo conteúdo final

<em>📋 ESTRUTURA OBRIGATÓRIA DA ÚLTIMA QUESTÃO (CTA):</em>
A 6ª questão DEVE ser uma Call-to-Action que:
- Gere expectativa pelo resultado/conteúdo final
- Prometa valor específico e tangível
- Use linguagem persuasiva e urgente
- Ofereça opções que demonstrem diferentes níveis de interesse
- Conduza naturalmente ao clique no redirect_url

<em>🎯 EXEMPLOS DE ÚLTIMAS QUESTÕES CTA:</em>
"O que você mais deseja receber agora?"
"Qual resultado seria mais valioso para você?"
"O que te ajudaria mais neste momento?"
"Qual informação seria mais importante?"

<em>Para CORES PERSUASIVAS:</em>
1. Verde (#4CAF50) = respostas positivas/corretas
2. Azul (#2196F3) = confiança/profissionalismo  
3. Laranja (#FF9800) = urgência/atenção
4. Roxo (#9C27B0) = premium/exclusividade
5. Vermelho (#F44336) = problemas/negatividade

<strong>⚠️ REGRAS CRÍTICAS PARA SHORTCODE PERFEITO:</strong>

1. <strong>JSON SEMPRE VÁLIDO:</strong> Aspas duplas, vírgulas corretas, colchetes balanceados
2. <strong>ÍNDICE CORRECT:</strong> Começa em 0, não em 1
3. <strong>SYNTAX EXATA:</strong> [quiz parâmetros][JSON][/quiz] sem espaços extras
4. <strong>URL OBRIGATÓRIA:</strong> redirect_url sempre presente para conversão
5. <strong>TESTES:</strong> Valide JSON em jsonlint.com antes de usar
6. <strong>CONTRASTE:</strong> Cores de texto legíveis sobre fundos
7. <strong>RESPONSIVIDADE:</strong> fontSize entre 14px-20px para mobile
8. <strong>✅ QUESTÕES COM 1 RESPOSTA:</strong> Aceitas para CTAs, confirmações e transições

<strong>📋 CHECKLIST PRÉ-ENTREGA:</strong>
✅ redirect_url definida e estratégica
✅ Estilo apropriado para o público  
✅ Questões progressivas e envolventes (5 qualificação + 1 CTA)
✅ Cores que guiam para conversão
✅ JSON sintaticamente perfeito
✅ Explicações motivadoras (se quiz educacional)
✅ Promessa de valor clara na jornada
✅ Pelo menos 1 questão com estilo personalizado
✅ <strong>NOVO:</strong> Use questões com 1 resposta para CTAs e confirmações diretas
✅ **OBRIGATÓRIO**: 6ª questão = CTA persuasiva para o post final
✅ **EMOJIS**: Use emojis nas opções da última questão para destacar valor
✅ **URGÊNCIA**: Palavras como "AGORA", "HOJE", "URGENTE" na CTA
✅ **VALOR MÁXIMO**: Última opção sempre "PACOTE COMPLETO" ou "ACESSO TOTAL"

<strong>🎯 SUA MISSÃO FINAL:</strong>
Gere um shortcode que:
1. Prenda atenção desde a primeira questão
2. Crie momentum e curiosidade crescente  
3. Qualifique/eduque progressivamente
4. Desperte desejo genuíno pelo resultado final
5. Conduza naturalmente à URL de conversão

<strong>✅ FORMATO DE ENTREGA:</strong>
Responda APENAS com o shortcode completo, sem explicações adicionais, pronto para colar no WordPress.

Agora, com base nas informações fornecidas acima, gere o shortcode perfeito:
				</div>
			</div>

			<h3><?php esc_html_e( 'Recursos do Builder Visual', 'alvobot-pro' ); ?></h3>
			<p><?php esc_html_e( 'O Alvobot Quiz inclui um builder visual completo com:', 'alvobot-pro' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Drag & Drop:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Arraste componentes para criar questões rapidamente', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Preview em Tempo Real:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Veja como o quiz aparecerá no frontend', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Templates Prontos:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Quiz educacional, pesquisa de satisfação, geração de leads e teste de personalidade', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Importação/Exportação:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Importe questões via JSON ou shortcode completo', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Duplicação de Elementos:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Duplique questões e respostas com todos os estilos preservados', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Personalização Individual:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Cada pergunta e resposta pode ter seu próprio estilo', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Múltiplas Respostas Corretas:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Marque múltiplas respostas como corretas usando checkboxes simples', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Configurações Avançadas:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Controle completo sobre navegação, progresso e comportamento', 'alvobot-pro' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Compatibilidade com AdSense', 'alvobot-pro' ); ?></h3>
			<p><?php esc_html_e( 'O plugin oferece duas opções de navegação:', 'alvobot-pro' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Modo Parâmetros (padrão):', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'Usa parâmetros GET (?question=2) - mais rápido', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Modo Sufixo:', 'alvobot-pro' ); ?></strong> <?php esc_html_e( 'URLs amigáveis (/post-name-1/, /post-name-2/) - compatível com AdSense', 'alvobot-pro' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Dicas Importantes', 'alvobot-pro' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Sempre use o editor de Texto do WordPress (nunca o Visual) ao editar shortcodes', 'alvobot-pro' ); ?></li>
				<li><?php esc_html_e( 'O plugin funciona sem JavaScript para acessibilidade', 'alvobot-pro' ); ?></li>
				<li><?php esc_html_e( 'Cada quiz gera um ID único baseado em suas questões', 'alvobot-pro' ); ?></li>
				<li><?php esc_html_e( 'As respostas são passadas via parâmetros GET na URL', 'alvobot-pro' ); ?></li>
				<li><?php esc_html_e( 'Use estilos personalizados para criar experiências visuais atrativas', 'alvobot-pro' ); ?></li>
				<li><?php esc_html_e( 'O contraste de texto é calculado automaticamente quando apenas a cor de fundo é definida', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( '✅ NOVO: Uma pergunta pode ter apenas uma opção de resposta', 'alvobot-pro' ); ?></strong></li>
				<li><?php esc_html_e( 'Use o modo "suffix" para sites com AdSense que precisam de recarregamento completo', 'alvobot-pro' ); ?></li>
				<li><?php esc_html_e( 'O formato de progresso foi simplificado para 1/5, 2/5, etc. para ser universal', 'alvobot-pro' ); ?></li>
				<li><?php esc_html_e( 'Auto-avanço está sempre ativo para melhor experiência do usuário', 'alvobot-pro' ); ?></li>
				<li><strong><?php esc_html_e( '🔧 Sistema integrado ao AlvoBot Pro para máxima compatibilidade', 'alvobot-pro' ); ?></strong></li>
			</ul>

			<h3><?php esc_html_e( 'Troubleshooting - Problemas Comuns', 'alvobot-pro' ); ?></h3>
			<div class="alvobot-card" style="margin: 20px 0; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
				<h4 style="color: #856404; margin: 0 0 15px;"><?php esc_html_e( '⚠️ Soluções para Problemas Comuns', 'alvobot-pro' ); ?></h4>
				
				<h5><?php esc_html_e( 'Botões não funcionam no Builder:', 'alvobot-pro' ); ?></h5>
				<ul>
					<li><?php esc_html_e( 'Verifique se está na página ?page=alvobot-quiz-builder', 'alvobot-pro' ); ?></li>
					<li><?php esc_html_e( 'Limpe cache do navegador (Ctrl+F5)', 'alvobot-pro' ); ?></li>
					<li><?php esc_html_e( 'Verifique console do navegador para erros JavaScript', 'alvobot-pro' ); ?></li>
				</ul>

				<h5><?php esc_html_e( 'Quiz não aparece no frontend:', 'alvobot-pro' ); ?></h5>
				<ul>
					<li><?php esc_html_e( 'Certifique-se que o módulo Quiz Builder está ativo no dashboard AlvoBot Pro', 'alvobot-pro' ); ?></li>
					<li><?php esc_html_e( 'Verifique se o shortcode está no editor de Texto (não Visual)', 'alvobot-pro' ); ?></li>
					<li><?php esc_html_e( 'Valide o JSON em jsonlint.com', 'alvobot-pro' ); ?></li>
				</ul>

				<h5><?php esc_html_e( 'Navegação entre questões não funciona:', 'alvobot-pro' ); ?></h5>
				<ul>
					<li><?php esc_html_e( 'Para sites com AdSense, use url_mode="suffix"', 'alvobot-pro' ); ?></li>
					<li><?php esc_html_e( 'Verifique se as rewrite rules foram atualizadas (vá em Configurações > Links Permanentes e clique Salvar)', 'alvobot-pro' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const copyButton = document.getElementById('copy-ai-prompt');
	const promptContent = document.getElementById('ai-prompt-content');
	
	if (copyButton && promptContent) {
		copyButton.addEventListener('click', function() {
			// Create a temporary textarea to copy the text
			const tempTextarea = document.createElement('textarea');
			tempTextarea.value = promptContent.textContent || promptContent.innerText;
			document.body.appendChild(tempTextarea);
			
			// Select and copy the text
			tempTextarea.select();
			tempTextarea.setSelectionRange(0, 99999); // For mobile devices
			
			try {
				const successful = document.execCommand('copy');
				if (successful) {
					// Update button text and style
					const originalHTML = copyButton.innerHTML;
					copyButton.innerHTML = '<i data-lucide="check" class="alvobot-icon"></i> <?php esc_html_e( 'Copiado!', 'alvobot-pro' ); ?>';
					copyButton.style.backgroundColor = '#4CAF50';
					copyButton.style.borderColor = '#4CAF50';
					copyButton.style.color = '#fff';
					
					// Reset button after 3 seconds
					setTimeout(function() {
						copyButton.innerHTML = originalHTML;
						copyButton.style.backgroundColor = '';
						copyButton.style.borderColor = '';
						copyButton.style.color = '';
					}, 3000);
				} else {
					alert('<?php esc_html_e( 'Erro ao copiar. Tente selecionar o texto manualmente.', 'alvobot-pro' ); ?>');
				}
			} catch (err) {
				// Fallback for older browsers
				alert('<?php esc_html_e( 'Use Ctrl+C para copiar o texto selecionado.', 'alvobot-pro' ); ?>');
			}
			
			// Remove temporary textarea
			document.body.removeChild(tempTextarea);
		});
	}
});
</script>

<style>
.docs-ai-prompt {
	margin: 20px 0;
}

#ai-prompt-content {
	max-height: 400px;
	overflow-y: auto;
	white-space: pre-wrap;
	word-wrap: break-word;
	user-select: all;
	-webkit-user-select: all;
	-moz-user-select: all;
	-ms-user-select: all;
}

#ai-prompt-content strong {
	color: #1976d2;
	font-weight: bold;
}

#ai-prompt-content em {
	color: #7b1fa2;
	font-style: italic;
}

#copy-ai-prompt {
	transition: all 0.3s ease;
	white-space: nowrap;
	padding: 8px 16px;
	border: 1px solid #ccc;
	border-radius: 4px;
	background: #f8f9fa;
	color: #495057;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 5px;
	font-size: 14px;
}

#copy-ai-prompt:hover {
	transform: translateY(-1px);
	box-shadow: 0 2px 8px rgba(0,0,0,0.15);
	background: #e9ecef;
	border-color: #adb5bd;
}

@media (max-width: 768px) {
	#ai-prompt-content {
		font-size: 12px;
		padding: 15px;
	}
	
	.docs-ai-prompt > div:first-child {
		flex-direction: column;
		gap: 10px;
		align-items: stretch;
	}
	
	#copy-ai-prompt {
		width: 100%;
		justify-content: center;
	}
}
</style>