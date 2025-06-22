<?php
if (!defined('ABSPATH')) {
    exit;
}

// Inicializa a fila se não existir
$queue = new AlvoBotPro_Translation_Queue();
$queue->create_table();

$stats = $queue->get_queue_status();

// Verifica se o script está enfileirado, se não, carrega inline
$script_handle = 'alvobot-multi-languages-queue';
$script_enqueued = wp_script_is($script_handle, 'enqueued');

AlvoBotPro::debug_log('multi-languages', 'Script queue template - Script enfileirado: ' . ($script_enqueued ? 'sim' : 'não'));

// Localização para o JavaScript
if ($script_enqueued) {
    wp_localize_script($script_handle, 'alvobotMultiLanguages', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('alvobot-pro/v1'),
        'nonce' => wp_create_nonce('alvobot_nonce'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'translations' => array(
            'error' => __('Erro', 'alvobot-pro'),
            'success' => __('Sucesso', 'alvobot-pro'),
            'confirm_delete' => __('Tem certeza que deseja excluir?', 'alvobot-pro'),
            'processing' => __('Processando...', 'alvobot-pro')
        )
    ));
}
?>

<div class="alvobot-admin-wrap">
    <div class="alvobot-admin-container">
        <div class="alvobot-admin-header">
            <h1><?php echo esc_html__('Fila de Traduções', 'alvobot-pro'); ?></h1>
            <p><?php echo esc_html__('Gerencie e monitore as traduções em processamento automático', 'alvobot-pro'); ?></p>
        </div>

        <!-- Status Cards -->
        <div class="alvobot-grid alvobot-grid-5">
            <div class="alvobot-card alvobot-card-stat">
                <div class="alvobot-stat-item">
                    <div class="alvobot-stat-number" id="queue-total"><?php echo $stats->total ?? 0; ?></div>
                    <div class="alvobot-stat-label"><?php echo esc_html__('Total na Fila', 'alvobot-pro'); ?></div>
                </div>
            </div>
            
            <div class="alvobot-card alvobot-card-stat alvobot-card-pending">
                <div class="alvobot-stat-item">
                    <div class="alvobot-stat-number" id="queue-pending"><?php echo $stats->pending ?? 0; ?></div>
                    <div class="alvobot-stat-label"><?php echo esc_html__('Pendentes', 'alvobot-pro'); ?></div>
                </div>
            </div>
            
            <div class="alvobot-card alvobot-card-stat alvobot-card-processing">
                <div class="alvobot-stat-item">
                    <div class="alvobot-stat-number" id="queue-processing"><?php echo $stats->processing ?? 0; ?></div>
                    <div class="alvobot-stat-label"><?php echo esc_html__('Processando', 'alvobot-pro'); ?></div>
                </div>
            </div>
            
            <div class="alvobot-card alvobot-card-stat alvobot-card-completed">
                <div class="alvobot-stat-item">
                    <div class="alvobot-stat-number" id="queue-completed"><?php echo $stats->completed ?? 0; ?></div>
                    <div class="alvobot-stat-label"><?php echo esc_html__('Concluídas', 'alvobot-pro'); ?></div>
                </div>
            </div>
            
            <div class="alvobot-card alvobot-card-stat alvobot-card-failed">
                <div class="alvobot-stat-item">
                    <div class="alvobot-stat-number" id="queue-error"><?php echo $stats->failed ?? 0; ?></div>
                    <div class="alvobot-stat-label"><?php echo esc_html__('Falharam', 'alvobot-pro'); ?></div>
                </div>
            </div>
        </div>

        <!-- Controles -->
        <div class="alvobot-card">
            <div class="alvobot-card-header">
                <div>
                    <h2 class="alvobot-card-title"><?php echo esc_html__('Gerenciar Fila', 'alvobot-pro'); ?></h2>
                    <p class="alvobot-card-subtitle"><?php echo esc_html__('Controle o processamento e visualize itens da fila', 'alvobot-pro'); ?></p>
                </div>
            </div>
            
            <div class="alvobot-card-content">
                <div class="alvobot-toolbar">
                    <div class="alvobot-toolbar-left">
                        <div class="alvobot-btn-group">
                            <button type="button" class="alvobot-btn alvobot-btn-primary" id="process-queue">
                                <span class="dashicons dashicons-update"></span>
                                <?php echo esc_html__('Processar Fila', 'alvobot-pro'); ?>
                            </button>
                            <button type="button" class="alvobot-btn alvobot-btn-secondary" id="refresh-queue">
                                <span class="dashicons dashicons-update-alt"></span>
                                <?php echo esc_html__('Atualizar', 'alvobot-pro'); ?>
                            </button>
                            <button type="button" class="alvobot-btn alvobot-btn-outline" id="clear-completed">
                                <span class="dashicons dashicons-trash"></span>
                                <?php echo esc_html__('Limpar Concluídas', 'alvobot-pro'); ?>
                            </button>
                            <button type="button" class="alvobot-btn alvobot-btn-warning" id="reset-orphaned" title="Reseta itens travados em processamento">
                                <span class="dashicons dashicons-backup"></span>
                                <?php echo esc_html__('Resetar Travados', 'alvobot-pro'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="alvobot-toolbar-right">
                        <div class="alvobot-filter-group">
                            <label for="status-filter" class="alvobot-filter-label"><?php echo esc_html__('Filtrar por:', 'alvobot-pro'); ?></label>
                            <select id="status-filter" class="alvobot-select">
                                <option value=""><?php echo esc_html__('Todos os Status', 'alvobot-pro'); ?></option>
                                <option value="pending"><?php echo esc_html__('Pendente', 'alvobot-pro'); ?></option>
                                <option value="processing"><?php echo esc_html__('Processando', 'alvobot-pro'); ?></option>
                                <option value="completed"><?php echo esc_html__('Concluído', 'alvobot-pro'); ?></option>
                                <option value="failed"><?php echo esc_html__('Falhou', 'alvobot-pro'); ?></option>
                                <option value="partial"><?php echo esc_html__('Parcial', 'alvobot-pro'); ?></option>
                            </select>
                        </div>
                        
                        <input type="text" id="search-input" class="alvobot-input" placeholder="<?php echo esc_attr__('Buscar por título...', 'alvobot-pro'); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- DataTable com AG Grid -->
        <div class="alvobot-card">
            <div class="alvobot-card-header">
                <div>
                    <h2 class="alvobot-card-title"><?php echo esc_html__('Itens na Fila', 'alvobot-pro'); ?></h2>
                    <p class="alvobot-card-subtitle"><?php echo esc_html__('Lista completa de traduções em processamento', 'alvobot-pro'); ?></p>
                </div>
                <div>
                    <button type="button" class="alvobot-btn alvobot-btn-outline alvobot-btn-sm" id="export-csv">
                        <span class="dashicons dashicons-download"></span>
                        <?php echo esc_html__('Exportar CSV', 'alvobot-pro'); ?>
                    </button>
                </div>
            </div>
            
            <div class="alvobot-card-content alvobot-no-padding">
                <div id="queue-grid" class="ag-theme-alpine" style="height: 600px; width: 100%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Logs -->
<div id="logs-modal" class="alvobot-modal" style="display: none;">
    <div class="alvobot-modal-content alvobot-modal-large">
        <div class="alvobot-modal-header">
            <h2><?php echo esc_html__('Logs de Tradução', 'alvobot-pro'); ?> - <span id="logs-post-title"></span></h2>
            <button class="alvobot-modal-close" type="button">&times;</button>
        </div>
        
        <div class="alvobot-modal-body">
            <div class="alvobot-info-grid">
                <div class="alvobot-info-item">
                    <label><?php echo esc_html__('Post ID:', 'alvobot-pro'); ?></label>
                    <span id="logs-post-id"></span>
                </div>
                <div class="alvobot-info-item">
                    <label><?php echo esc_html__('Status:', 'alvobot-pro'); ?></label>
                    <span id="logs-status"></span>
                </div>
                <div class="alvobot-info-item">
                    <label><?php echo esc_html__('Progresso:', 'alvobot-pro'); ?></label>
                    <span id="logs-progress"></span>
                </div>
                <div class="alvobot-info-item">
                    <label><?php echo esc_html__('Idiomas:', 'alvobot-pro'); ?></label>
                    <span id="logs-languages"></span>
                </div>
                <div class="alvobot-info-item">
                    <label><?php echo esc_html__('Criado:', 'alvobot-pro'); ?></label>
                    <span id="logs-created"></span>
                </div>
                <div class="alvobot-info-item">
                    <label><?php echo esc_html__('Iniciado:', 'alvobot-pro'); ?></label>
                    <span id="logs-started"></span>
                </div>
            </div>
            
            <div class="alvobot-section alvobot-mt-lg">
                <h3 class="alvobot-section-title"><?php echo esc_html__('Log de Eventos', 'alvobot-pro'); ?></h3>
                <div id="logs-content" class="alvobot-log-viewer">
                    <!-- Logs serão carregados aqui -->
                </div>
            </div>
        </div>
        
        <div class="alvobot-modal-footer">
            <div class="alvobot-btn-group">
                <button type="button" class="alvobot-btn alvobot-btn-outline alvobot-modal-close">
                    <?php echo esc_html__('Fechar', 'alvobot-pro'); ?>
                </button>
                <button type="button" class="alvobot-btn alvobot-btn-primary" id="download-logs">
                    <span class="dashicons dashicons-download"></span>
                    <?php echo esc_html__('Baixar Logs', 'alvobot-pro'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- AG Grid CSS e JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.1.0/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.1.0/styles/ag-theme-alpine.css">
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31.1.0/dist/ag-grid-community.min.js"></script>

<script>
// Garantir que as variáveis estejam disponíveis
<?php if (!$script_enqueued): ?>
var alvobotMultiLanguages = {
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    restUrl: '<?php echo rest_url('alvobot-pro/v1'); ?>',
    nonce: '<?php echo wp_create_nonce('alvobot_nonce'); ?>',
    restNonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
    translations: {
        error: '<?php echo __('Erro', 'alvobot-pro'); ?>',
        success: '<?php echo __('Sucesso', 'alvobot-pro'); ?>',
        confirm_delete: '<?php echo __('Tem certeza que deseja excluir?', 'alvobot-pro'); ?>',
        processing: '<?php echo __('Processando...', 'alvobot-pro'); ?>'
    }
};
<?php endif; ?>

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('AlvoBot Queue: Iniciando script da fila de traduções');
    console.log('AlvoBot Queue: alvobotMultiLanguages disponível:', typeof alvobotMultiLanguages !== 'undefined');
    console.log('AlvoBot Queue: Script enfileirado:', <?php echo $script_enqueued ? 'true' : 'false'; ?>);
    
    // Verificar se variáveis estão disponíveis
    if (typeof alvobotMultiLanguages === 'undefined') {
        console.error('AlvoBot Queue: alvobotMultiLanguages não está definido');
        return;
    }
    
    // Configurações
    const CONFIG = {
        ajaxUrl: alvobotMultiLanguages.ajaxUrl,
        nonce: alvobotMultiLanguages.nonce,
        refreshInterval: 30000,
        pageSize: 25
    };
    
    console.log('AlvoBot Queue: CONFIG =', CONFIG);
    
    // Gerenciador da Fila
    class QueueManager {
        constructor() {
            this.gridApi = null;
            this.currentData = [];
            this.currentLogItem = null;
            this.refreshTimer = null;
            
            this.init();
        }
        
        init() {
            console.log('AlvoBot Queue: Inicializando gerenciador da fila');
            
            this.initGrid();
            this.bindEvents();
            this.loadData();
            this.startAutoRefresh();
        }
        
        initGrid() {
            console.log('AlvoBot Queue: Inicializando AG Grid');
            const gridDiv = document.querySelector('#queue-grid');
            if (!gridDiv) {
                console.error('AlvoBot Queue: Container do grid não encontrado');
                return;
            }
            console.log('AlvoBot Queue: Container do grid encontrado:', gridDiv);

            const columnDefs = [
                {
                    headerName: '#',
                    field: 'id',
                    width: 70,
                    sortable: true,
                    filter: 'agNumberColumnFilter',
                    cellStyle: { textAlign: 'center', fontSize: '13px', color: '#6b7280' }
                },
                {
                    headerName: 'Post',
                    field: 'post_title',
                    flex: 2,
                    minWidth: 200,
                    sortable: true,
                    filter: 'agTextColumnFilter',
                    cellRenderer: this.postCellRenderer.bind(this)
                },
                {
                    headerName: 'Status',
                    field: 'status',
                    width: 120,
                    sortable: true,
                    filter: 'agSetColumnFilter',
                    cellRenderer: this.statusCellRenderer.bind(this),
                    cellStyle: { textAlign: 'center' }
                },
                {
                    headerName: 'Progresso',
                    field: 'progress',
                    width: 100,
                    sortable: true,
                    filter: 'agNumberColumnFilter',
                    cellRenderer: this.progressCellRenderer.bind(this),
                    cellStyle: { textAlign: 'center' }
                },
                {
                    headerName: 'Idiomas',
                    field: 'target_langs',
                    width: 120,
                    sortable: false,
                    filter: false,
                    cellRenderer: this.languagesCellRenderer.bind(this),
                    cellStyle: { textAlign: 'center' }
                },
                {
                    headerName: 'Criado',
                    field: 'created_at',
                    width: 110,
                    sortable: true,
                    filter: 'agDateColumnFilter',
                    cellRenderer: this.dateCellRenderer.bind(this),
                    sort: 'desc',
                    cellStyle: { textAlign: 'center', fontSize: '13px', color: '#6b7280' }
                },
                {
                    headerName: 'Ações',
                    field: 'actions',
                    width: 100,
                    sortable: false,
                    filter: false,
                    cellRenderer: this.actionsCellRenderer.bind(this),
                    cellStyle: { textAlign: 'center' },
                    pinned: 'right'
                }
            ];

            const gridOptions = {
                columnDefs: columnDefs,
                rowData: [],
                defaultColDef: {
                    resizable: true,
                    sortable: true,
                    filter: true
                },
                animateRows: true,
                rowSelection: 'multiple',
                suppressRowClickSelection: true,
                pagination: true,
                paginationPageSize: CONFIG.pageSize,
                getRowId: (params) => params.data.id.toString(),
                onGridReady: (params) => {
                    this.gridApi = params.api;
                    console.log('AlvoBot Queue: AG Grid pronto');
                },
                localeText: {
                    page: 'Página',
                    to: 'até',
                    of: 'de',
                    next: 'Próximo',
                    previous: 'Anterior',
                    loadingOoo: 'Carregando...',
                    noRowsToShow: 'Nenhum item na fila'
                }
            };

            if (typeof agGrid === 'undefined') {
                console.error('AlvoBot Queue: AG Grid não está carregado');
                gridDiv.innerHTML = '<div style="padding: 40px; text-align: center; color: #666;"><p>🔄 Carregando AG Grid...</p><p>Se esta mensagem persistir, verifique sua conexão com a internet.</p></div>';
                
                // Tenta carregar AG Grid novamente após 2 segundos
                setTimeout(() => {
                    if (typeof agGrid !== 'undefined') {
                        console.log('AlvoBot Queue: AG Grid carregado com atraso');
                        new agGrid.Grid(gridDiv, gridOptions);
                    } else {
                        gridDiv.innerHTML = '<div style="padding: 40px; text-align: center; color: #d32f2f;"><p>❌ Erro: AG Grid não pode ser carregado</p><p>Verifique sua conexão com a internet e recarregue a página.</p></div>';
                    }
                }, 2000);
                return;
            }
            
            console.log('AlvoBot Queue: Criando instância do AG Grid');
            new agGrid.Grid(gridDiv, gridOptions);
        }
        
        // Renderizadores de células
        postCellRenderer(params) {
            const item = params.data;
            if (!item.post_id) return '<span style="color: #9ca3af;">Post não encontrado</span>';
            
            const editUrl = `<?php echo admin_url('post.php?action=edit&post='); ?>${item.post_id}`;
            const postType = item.post_type === 'page' ? 'Página' : 'Post';
            
            return `
                <div style="line-height: 1.4;">
                    <div style="font-weight: 500; margin-bottom: 2px;">
                        <a href="${editUrl}" target="_blank" style="color: #374151; text-decoration: none;">
                            ${item.post_title || 'Sem título'}
                        </a>
                    </div>
                    <div style="font-size: 12px; color: #6b7280;">
                        ${postType} #${item.post_id}
                    </div>
                </div>
            `;
        }
        
        statusCellRenderer(params) {
            const status = params.value;
            const statusConfig = {
                'pending': { text: 'Pendente', class: 'alvobot-badge-warning' },
                'processing': { text: 'Processando', class: 'alvobot-badge-info' },
                'completed': { text: 'Concluído', class: 'alvobot-badge-success' },
                'failed': { text: 'Falha', class: 'alvobot-badge-error' },
                'partial': { text: 'Parcial', class: 'alvobot-badge-warning' }
            };
            
            const config = statusConfig[status] || { text: status, class: 'alvobot-badge-default' };
            
            return `<span class="alvobot-badge ${config.class}">${config.text}</span>`;
        }
        
        progressCellRenderer(params) {
            const progress = parseInt(params.value || 0);
            const color = progress === 100 ? '#10b981' : 
                         progress >= 50 ? '#3b82f6' : '#6b7280';
            
            return `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="flex: 1; height: 6px; background: #f3f4f6; border-radius: 3px; overflow: hidden;">
                        <div style="height: 100%; background: ${color}; width: ${progress}%; transition: width 0.3s ease;"></div>
                    </div>
                    <span style="font-size: 12px; color: #6b7280; min-width: 35px;">${progress}%</span>
                </div>
            `;
        }
        
        languagesCellRenderer(params) {
            let languages = [];
            try {
                languages = typeof params.value === 'string' ? JSON.parse(params.value) : (params.value || []);
            } catch (e) {
                languages = [params.value || ''];
            }
            
            const displayLangs = languages.slice(0, 2);
            const remaining = languages.length - 2;
            
            let html = displayLangs.map(lang => 
                `<span class="alvobot-badge alvobot-badge-primary" style="margin-right: 2px;">${lang.trim().toUpperCase()}</span>`
            ).join('');
            
            if (remaining > 0) {
                html += `<span style="font-size: 11px; color: #6b7280;">+${remaining}</span>`;
            }
            
            return html;
        }
        
        dateCellRenderer(params) {
            if (!params.value) return '<span style="color: #d1d5db;">-</span>';
            
            try {
                const date = new Date(params.value);
                const now = new Date();
                const diffHours = Math.abs(now - date) / 36e5;
                
                if (diffHours < 1) {
                    return '<span style="color: #059669; font-weight: 500;">Agora há pouco</span>';
                } else if (diffHours < 24) {
                    return `<span style="color: #059669;">${Math.floor(diffHours)}h atrás</span>`;
                } else {
                    return `<span style="color: #6b7280;">${date.toLocaleDateString('pt-BR')}</span>`;
                }
            } catch (e) {
                return params.value;
            }
        }
        
        actionsCellRenderer(params) {
            const item = params.data;
            const canDelete = item.status !== 'processing';
            
            return `
                <div style="display: flex; gap: 4px; justify-content: center;">
                    <button class="alvobot-btn-icon" data-action="view-logs" data-id="${item.id}" title="Ver Logs">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    ${canDelete ? 
                        `<button class="alvobot-btn-icon alvobot-btn-danger" data-action="delete" data-id="${item.id}" title="Excluir">
                            <span class="dashicons dashicons-trash"></span>
                        </button>` :
                        `<button class="alvobot-btn-icon" disabled title="Item em processamento">
                            <span class="dashicons dashicons-trash" style="opacity: 0.3;"></span>
                        </button>`
                    }
                </div>
            `;
        }
        
        bindEvents() {
            // Controles principais
            $('#process-queue').on('click', () => this.processQueue());
            $('#refresh-queue').on('click', () => this.loadData());
            $('#clear-completed').on('click', () => this.clearCompleted());
            $('#reset-orphaned').on('click', () => this.resetOrphanedItems());
            $('#export-csv').on('click', () => this.exportToCsv());
            
            // Filtros
            $('#status-filter').on('change', () => this.applyFilters());
            $('#search-input').on('input', this.debounce(() => this.applyFilters(), 300));
            
            // Ações do grid (delegadas)
            $(document).on('click', '[data-action="view-logs"]', (e) => {
                const id = $(e.currentTarget).data('id');
                this.viewLogs(id);
            });
            
            $(document).on('click', '[data-action="delete"]', (e) => {
                const id = $(e.currentTarget).data('id');
                this.deleteItem(id);
            });
            
            // Modal
            $('.alvobot-modal-close').on('click', () => this.closeModal());
            $('#download-logs').on('click', () => this.downloadLogs());
            
            $('#logs-modal').on('click', (e) => {
                if (e.target.id === 'logs-modal') this.closeModal();
            });
        }
        
        loadData() {
            console.log('AlvoBot Queue: Carregando dados');
            console.log('AlvoBot Queue: URL AJAX:', CONFIG.ajaxUrl);
            console.log('AlvoBot Queue: Nonce:', CONFIG.nonce);
            console.log('AlvoBot Queue: Dados da requisição:', {
                action: 'alvobot_get_queue_status',
                nonce: CONFIG.nonce,
                page: 1,
                per_page: 200
            });
            
            $.ajax({
                url: CONFIG.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'alvobot_get_queue_status',
                    nonce: CONFIG.nonce,
                    page: 1,
                    per_page: 200
                },
                beforeSend: function() {
                    console.log('AlvoBot Queue: Enviando requisição AJAX...');
                },
                success: (response) => {
                    console.log('AlvoBot Queue: Resposta AJAX recebida', response);
                    if (response.success && response.data) {
                        console.log('AlvoBot Queue: Status:', response.data.status);
                        console.log('AlvoBot Queue: Queue items:', response.data.queue);
                        this.currentData = response.data.queue.items || [];
                        console.log('AlvoBot Queue: Total de itens carregados:', this.currentData.length);
                        this.updateGrid(this.currentData);
                        this.updateStats(response.data.status);
                    } else {
                        console.error('AlvoBot Queue: Erro na resposta', response);
                        let errorMessage = 'Resposta inválida';
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMessage = response.data;
                            } else if (response.data.message) {
                                errorMessage = response.data.message;
                            } else {
                                errorMessage = JSON.stringify(response.data);
                            }
                        }
                        this.showNotice('Erro ao carregar dados: ' + errorMessage, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AlvoBot Queue: Erro AJAX', {status, error});
                    this.showNotice('Erro de comunicação com o servidor', 'error');
                }
            });
        }
        
        updateGrid(data) {
            if (!this.gridApi) {
                console.error('AlvoBot Queue: Grid API não disponível');
                return;
            }
            
            console.log('AlvoBot Queue: Atualizando grid com', data.length, 'itens');
            console.log('AlvoBot Queue: Dados do grid:', data);
            this.gridApi.setRowData(data);
            this.applyFilters();
        }
        
        applyFilters() {
            if (!this.gridApi) return;
            
            const statusFilter = $('#status-filter').val();
            const searchText = $('#search-input').val();
            
            let filterModel = {};
            
            if (statusFilter) {
                filterModel.status = {
                    filterType: 'set',
                    values: [statusFilter]
                };
            }
            
            if (searchText) {
                filterModel.post_title = {
                    filterType: 'text',
                    filter: searchText,
                    type: 'contains'
                };
            }
            
            this.gridApi.setFilterModel(Object.keys(filterModel).length ? filterModel : null);
        }
        
        updateStats(stats) {
            if (!stats) return;
            
            $('#queue-total').text(stats.total || 0);
            $('#queue-pending').text(stats.pending || 0);
            $('#queue-processing').text(stats.processing || 0);
            $('#queue-completed').text(stats.completed || 0);
            $('#queue-error').text(stats.failed || 0);
        }
        
        processQueue() {
            $.ajax({
                url: CONFIG.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alvobot_process_translation_queue',
                    nonce: CONFIG.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('Processamento iniciado com sucesso', 'success');
                        this.loadData();
                    } else {
                        let errorMessage = 'Erro desconhecido';
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMessage = response.data;
                            } else if (response.data.message) {
                                errorMessage = response.data.message;
                            } else {
                                errorMessage = JSON.stringify(response.data);
                            }
                        }
                        this.showNotice('Erro ao processar fila: ' + errorMessage, 'error');
                    }
                }
            });
        }
        
        clearCompleted() {
            if (!confirm('Tem certeza que deseja limpar todas as traduções concluídas?')) return;
            
            $.ajax({
                url: CONFIG.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alvobot_clear_queue',
                    nonce: CONFIG.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('Fila limpa com sucesso', 'success');
                        this.loadData();
                    } else {
                        let errorMessage = 'Erro desconhecido';
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMessage = response.data;
                            } else if (response.data.message) {
                                errorMessage = response.data.message;
                            } else {
                                errorMessage = JSON.stringify(response.data);
                            }
                        }
                        this.showNotice('Erro ao limpar fila: ' + errorMessage, 'error');
                    }
                }
            });
        }
        
        resetOrphanedItems() {
            if (!confirm('Tem certeza que deseja resetar todos os itens travados? Eles serão recolocados na fila.')) return;
            
            $.ajax({
                url: CONFIG.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alvobot_reset_orphaned_items',
                    nonce: CONFIG.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const message = response.data.message || 'Itens resetados com sucesso';
                        this.showNotice(message, 'success');
                        this.loadData();
                    } else {
                        let errorMessage = 'Erro desconhecido';
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMessage = response.data;
                            } else if (response.data.message) {
                                errorMessage = response.data.message;
                            } else {
                                errorMessage = JSON.stringify(response.data);
                            }
                        }
                        this.showNotice('Erro ao resetar itens: ' + errorMessage, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Erro ao resetar itens:', {status, error});
                    this.showNotice('Erro de comunicação ao resetar itens', 'error');
                }
            });
        }
        
        deleteItem(id) {
            if (!confirm('Tem certeza que deseja excluir este item da fila?')) return;
            
            $.ajax({
                url: CONFIG.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alvobot_remove_queue_item',
                    nonce: CONFIG.nonce,
                    id: id
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('Item excluído com sucesso', 'success');
                        this.loadData();
                    } else {
                        let errorMessage = 'Erro desconhecido';
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMessage = response.data;
                            } else if (response.data.message) {
                                errorMessage = response.data.message;
                            } else {
                                errorMessage = JSON.stringify(response.data);
                            }
                        }
                        this.showNotice('Erro ao excluir item: ' + errorMessage, 'error');
                    }
                }
            });
        }
        
        viewLogs(id) {
            $.ajax({
                url: CONFIG.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alvobot_get_queue_logs',
                    nonce: CONFIG.nonce,
                    queue_id: id
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.showLogsModal(response.data);
                    } else {
                        let errorMessage = 'Erro desconhecido';
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMessage = response.data;
                            } else if (response.data.message) {
                                errorMessage = response.data.message;
                            } else {
                                errorMessage = JSON.stringify(response.data);
                            }
                        }
                        this.showNotice('Erro ao carregar logs: ' + errorMessage, 'error');
                    }
                }
            });
        }
        
        showLogsModal(data) {
            const item = data.item;
            const logs = data.logs || [];
            
            $('#logs-post-title').text(item.post_title || 'Sem título');
            $('#logs-post-id').text(item.post_id);
            $('#logs-status').html(`<span class="alvobot-badge">${this.getStatusText(item.status)}</span>`);
            $('#logs-progress').text(item.progress + '%');
            
            try {
                const languages = JSON.parse(item.target_langs);
                $('#logs-languages').text(languages.join(', '));
            } catch (e) {
                $('#logs-languages').text(item.target_langs || '-');
            }
            
            $('#logs-created').text(item.created_at ? new Date(item.created_at).toLocaleString('pt-BR') : '-');
            $('#logs-started').text(item.started_at ? new Date(item.started_at).toLocaleString('pt-BR') : '-');
            
            const logsContent = $('#logs-content');
            logsContent.empty();
            
            if (logs.length === 0) {
                logsContent.html('<div class="alvobot-empty-state"><p>Nenhum log disponível para este item.</p></div>');
            } else {
                logs.forEach(log => {
                    const logLevel = log.level || 'info';
                    const logTime = log.timestamp ? new Date(log.timestamp).toLocaleTimeString('pt-BR') : '';
                    const logMessage = log.message || '';
                    
                    logsContent.append(`
                        <div class="alvobot-log-entry alvobot-log-${logLevel}">
                            <span class="alvobot-log-time">${logTime}</span>
                            <span class="alvobot-log-level">${logLevel.toUpperCase()}</span>
                            <span class="alvobot-log-message">${logMessage}</span>
                        </div>
                    `);
                });
            }
            
            this.currentLogItem = item;
            $('#logs-modal').fadeIn(300);
        }
        
        closeModal() {
            $('#logs-modal').fadeOut(300);
        }
        
        downloadLogs() {
            if (!this.currentLogItem) {
                this.showNotice('Nenhum item selecionado para download', 'error');
                return;
            }
            
            const downloadUrl = `${CONFIG.ajaxUrl}?action=alvobot_download_queue_item_logs&queue_id=${this.currentLogItem.id}&nonce=${CONFIG.nonce}`;
            window.location.href = downloadUrl;
        }
        
        exportToCsv() {
            if (!this.gridApi) return;
            
            this.gridApi.exportDataAsCsv({
                fileName: 'alvobot_fila_traducao_' + new Date().toISOString().split('T')[0] + '.csv'
            });
            
            this.showNotice('Arquivo CSV exportado com sucesso!', 'success');
        }
        
        startAutoRefresh() {
            this.refreshTimer = setInterval(() => {
                this.loadData();
            }, CONFIG.refreshInterval);
        }
        
        getStatusText(status) {
            const statusMap = {
                'pending': 'Pendente',
                'processing': 'Processando',
                'completed': 'Concluído',
                'failed': 'Falha',
                'partial': 'Parcial'
            };
            return statusMap[status] || status;
        }
        
        showNotice(message, type = 'info') {
            const noticeClass = `alvobot-notice-${type}`;
            const notice = $(`
                <div class="alvobot-notice ${noticeClass} alvobot-notice-dismissible">
                    <p>${message}</p>
                    <button class="alvobot-notice-dismiss" type="button">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                </div>
            `);
            
            $('.alvobot-admin-header').after(notice);
            
            setTimeout(() => notice.fadeOut(500, () => notice.remove()), 5000);
            
            notice.find('.alvobot-notice-dismiss').on('click', () => {
                notice.fadeOut(300, () => notice.remove());
            });
        }
        
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    }
    
    // Inicializar
    console.log('AlvoBot Queue: Criando instância do QueueManager');
    try {
        window.AlvoBotQueueManager = new QueueManager();
        console.log('AlvoBot Queue: QueueManager criado com sucesso');
    } catch (error) {
        console.error('AlvoBot Queue: Erro ao criar QueueManager:', error);
    }
    
    console.log('AlvoBot Queue: Script carregado e inicializado');
});
</script>

