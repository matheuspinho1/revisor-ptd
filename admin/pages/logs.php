<?php
/**
 * Página de Logs do Sistema
 * 
 * Interface para:
 * - Visualizar logs de atividade
 * - Filtrar logs por data e nível
 * - Limpar logs
 * - Baixar logs
 */

if (!defined('ABSPATH')) {
    exit;
}

$logger = new RevisorPTDLogger();
$log_stats = $logger->get_log_stats();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="revisor-ptd-admin-container">
        
        <!-- Estatísticas -->
        <div class="postbox">
            <h3 class="hndle">
                <span>Estatísticas do Sistema</span>
            </h3>
            <div class="inside">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $log_stats['total_files']; ?></div>
                        <div class="stat-label">Arquivos de Log</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo size_format($log_stats['total_size']); ?></div>
                        <div class="stat-label">Tamanho Total</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $log_stats['level_counts']['error']; ?></div>
                        <div class="stat-label">Erros</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $log_stats['level_counts']['warning']; ?></div>
                        <div class="stat-label">Avisos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $log_stats['level_counts']['info']; ?></div>
                        <div class="stat-label">Informações</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $log_stats['level_counts']['debug']; ?></div>
                        <div class="stat-label">Debug</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="postbox">
            <h3 class="hndle">
                <span>Filtros de Pesquisa</span>
            </h3>
            <div class="inside">
                <form id="logs-filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="filter_date_from">Data Inicial:</label>
                            <input type="date" id="filter_date_from" name="date_from" />
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_date_to">Data Final:</label>
                            <input type="date" id="filter_date_to" name="date_to" />
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_level">Nível:</label>
                            <select id="filter_level" name="level">
                                <option value="">Todos os níveis</option>
                                <option value="debug">Debug</option>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_limit">Limite:</label>
                            <select id="filter_limit" name="limit">
                                <option value="50">50 registros</option>
                                <option value="100" selected>100 registros</option>
                                <option value="200">200 registros</option>
                                <option value="500">500 registros</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="button button-primary">
                             Filtrar Logs
                        </button>
                        <button type="button" class="button button-secondary" id="clear-filters">
                             Limpar Filtros
                        </button>
                        <button type="button" class="button button-secondary" id="auto-refresh">
                           Atualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Logs -->
        <div class="postbox">
            <h3 class="hndle">
                <span>Registros de Log</span>
                <div class="hndle-actions">
                    <button type="button" class="button button-secondary button-small" id="export-logs">
                        Exportar
                    </button>
                    <button type="button" class="button button-secondary button-small" id="clear-all-logs">
                        Limpar Todos
                    </button>
                </div>
            </h3>
            <div class="inside">
                <div id="logs-container">
                    <div class="loading-indicator">
                        <div class="spinner is-active"></div>
                        <p>Carregando logs...</p>
                    </div>
                </div>
                
                <div id="logs-pagination" class="tablenav bottom" style="display: none;">
                    <div class="alignright">
                        <span class="displaying-num" id="logs-count">0 registros</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configurações de Log -->
        <div class="postbox">
            <h3 class="hndle">
                <span>Configurações de Log</span>
            </h3>
            <div class="inside">
                <form id="log-settings-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="log_level">Nível Mínimo de Log</label>
                            </th>
                            <td>
                                <select id="log_level" name="log_level">
                                    <option value="debug">Debug (todos os logs)</option>
                                    <option value="info" selected>Info (informações importantes)</option>
                                    <option value="warning">Warning (apenas avisos e erros)</option>
                                    <option value="error">Error (apenas erros)</option>
                                    <option value="critical">Critical (apenas críticos)</option>
                                </select>
                                <p class="description">
                                    Define o nível mínimo de mensagens que serão registradas nos logs.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="log_retention">Retenção de Logs</label>
                            </th>
                            <td>
                                <select id="log_retention" name="log_retention">
                                    <option value="30">30 dias</option>
                                    <option value="60">60 dias</option>
                                    <option value="90" selected>90 dias</option>
                                    <option value="180">180 dias</option>
                                    <option value="365">1 ano</option>
                                </select>
                                <p class="description">
                                    Logs mais antigos que este período serão removidos automaticamente.
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="submit-section">
                        <button type="submit" class="button button-primary">
                            Salvar Configurações
                        </button>
                        <button type="button" class="button button-secondary" id="cleanup-old-logs">
                            Limpar Logs Antigos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    let autoRefreshInterval = null;
    let isAutoRefresh = false;
    
    // Carrega logs iniciais
    loadLogs();
    
    // Filtrar logs
    $('#logs-filter-form').on('submit', function(e) {
        e.preventDefault();
        loadLogs();
    });
    
    // Limpar filtros
    $('#clear-filters').on('click', function() {
        $('#logs-filter-form')[0].reset();
        loadLogs();
    });
    
    // Auto-refresh
    $('#auto-refresh').on('click', function() {
        if (isAutoRefresh) {
            clearInterval(autoRefreshInterval);
            $(this).text('🔄 Auto-atualizar').removeClass('button-primary').addClass('button-secondary');
            isAutoRefresh = false;
        } else {
            autoRefreshInterval = setInterval(loadLogs, 10000); // 10 segundos
            $(this).text('⏸️ Parar Auto-atualizar').removeClass('button-secondary').addClass('button-primary');
            isAutoRefresh = true;
        }
    });
    
    // Exportar logs
    $('#export-logs').on('click', function() {
        const params = $('#logs-filter-form').serialize();
        window.open(revisorPtdAjax.ajax_url + '?action=export_logs&' + params + '&nonce=' + revisorPtdAjax.nonce);
    });
    
    // Limpar todos os logs
    $('#clear-all-logs').on('click', function() {
        if (!confirm('Tem certeza que deseja limpar TODOS os logs?\n\nEsta ação não pode ser desfeita.')) {
            return;
        }
        
        $.ajax({
            url: revisorPtdAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'clear_logs',
                nonce: revisorPtdAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Logs limpos com sucesso!');
                    loadLogs();
                } else {
                    alert('Erro: ' + response.data);
                }
            },
            error: function() {
                alert('Erro na requisição');
            }
        });
    });
    
    // Limpar logs antigos
    $('#cleanup-old-logs').on('click', function() {
        const retention = $('#log_retention').val();
        
        if (!confirm(`Tem certeza que deseja limpar logs mais antigos que ${retention} dias?`)) {
            return;
        }
        
        $.ajax({
            url: revisorPtdAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cleanup_old_logs',
                nonce: revisorPtdAjax.nonce,
                retention_days: retention
            },
            success: function(response) {
                if (response.success) {
                    alert('Limpeza concluída! ' + response.data.removed + ' arquivo(s) removido(s).');
                    loadLogs();
                } else {
                    alert('Erro: ' + response.data);
                }
            },
            error: function() {
                alert('Erro na requisição');
            }
        });
    });
    
    // Função para carregar logs
    function loadLogs() {
        const container = $('#logs-container');
        const pagination = $('#logs-pagination');
        
        // Mostra loading
        container.html(`
            <div class="loading-indicator">
                <div class="spinner is-active"></div>
                <p>Carregando logs...</p>
            </div>
        `);
        
        // Coleta parâmetros de filtro
        const params = {
            action: 'get_logs',
            nonce: revisorPtdAjax.nonce,
            limit: $('#filter_limit').val() || 100,
            level: $('#filter_level').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        };
        
        $.ajax({
            url: revisorPtdAjax.ajax_url,
            type: 'POST',
            data: params,
            success: function(response) {
                if (response.success) {
                    displayLogs(response.data);
                } else {
                    container.html(`
                        <div class="error-message">
                            <p>❌ Erro ao carregar logs: ${response.data}</p>
                        </div>
                    `);
                }
            },
            error: function() {
                container.html(`
                    <div class="error-message">
                        <p>❌ Erro na requisição de logs</p>
                    </div>
                `);
            }
        });
    }
    
    // Função para exibir logs
    function displayLogs(data) {
        const container = $('#logs-container');
        const pagination = $('#logs-pagination');
        const logs = data.logs || [];
        
        if (logs.length === 0) {
            container.html(`
                <div class="empty-state">
                    <span class="dashicons dashicons-admin-page"></span>
                    <p>Nenhum log encontrado com os filtros aplicados.</p>
                </div>
            `);
            pagination.hide();
            return;
        }
        
        let html = '<div class="logs-display">';
        
        logs.forEach(function(log) {
            const logLevel = extractLogLevel(log);
            const logClass = getLogLevelClass(logLevel);
            
            html += `
                <div class="log-entry ${logClass}">
                    <div class="log-content">
                        <pre>${escapeHtml(log)}</pre>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.html(html);
        
        // Atualiza contador
        $('#logs-count').text(`${logs.length} registro(s)`);
        pagination.show();
    }
    
    // Função para extrair nível do log
    function extractLogLevel(logLine) {
        const match = logLine.match(/\[([A-Z]+)\]/);
        return match ? match[1].toLowerCase() : 'info';
    }
    
    // Função para obter classe CSS baseada no nível
    function getLogLevelClass(level) {
        const classes = {
            'debug': 'log-debug',
            'info': 'log-info',
            'warning': 'log-warning',
            'error': 'log-error',
            'critical': 'log-critical'
        };
        return classes[level] || 'log-info';
    }
    
    // Função para escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<style>
.revisor-ptd-admin-container {
    max-width: 100%;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #007cba;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9em;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #555;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.filter-actions {
    text-align: center;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.filter-actions .button {
    margin: 0 5px;
}

.hndle-actions {
    float: right;
    margin-top: -2px;
}

.hndle-actions .button {
    margin-left: 5px;
}

#logs-container {
    min-height: 300px;
    max-height: 600px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
}

.loading-indicator {
    text-align: center;
    padding: 40px;
    color: #666;
}

.loading-indicator .spinner {
    margin: 0 auto 20px;
}

.error-message {
    text-align: center;
    padding: 40px;
    color: #d63638;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
}

.empty-state .dashicons {
    font-size: 3em;
    margin-bottom: 15px;
    opacity: 0.5;
}

.logs-display {
    padding: 10px;
}

.log-entry {
    margin-bottom: 2px;
    border-radius: 4px;
    overflow: hidden;
}

.log-content {
    padding: 8px 12px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.log-debug .log-content {
    background: #f8f9fa;
    border-left: 3px solid #6c757d;
}

.log-info .log-content {
    background: #e3f2fd;
    border-left: 3px solid #2196f3;
}

.log-warning .log-content {
    background: #fff3cd;
    border-left: 3px solid #ffc107;
}

.log-error .log-content {
    background: #f8d7da;
    border-left: 3px solid #dc3545;
}

.log-critical .log-content {
    background: #f5c6cb;
    border-left: 3px solid #721c24;
    font-weight: bold;
}

.submit-section {
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.submit-section .button {
    margin-right: 10px;
}

@media (max-width: 768px) {
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .hndle-actions {
        float: none;
        margin-top: 10px;
    }
}
</style>