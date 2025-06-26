/**
 * Admin JavaScript para Revisor PTD
 * 
 * Funcionalidades administrativas:
 * - Teste de conexão com API
 * - Gerenciamento de documentos base
 * - Visualização de logs
 * - Configurações do sistema
 */

jQuery(document).ready(function($) {
    
    // ===== TESTE DE CONEXÃO COM API =====
    
    $('#test-api-connection').on('click', function() {
        const $button = $(this);
        const $result = $('#connection-result');
        const $loading = $('#connection-loading');
        
        // Reset estado
        $result.removeClass('success error').text('');
        $loading.addClass('is-active');
        $button.prop('disabled', true);
        
        $.ajax({
            url: revisorPtdAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'test_api_connection',
                nonce: revisorPtdAjax.nonce
            },
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').text('✅ ' + response.data);
                } else {
                    $result.addClass('error').text('❌ ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = 'Erro na requisição: ' + error;
                if (status === 'timeout') {
                    errorMsg = 'Tempo limite excedido. Verifique a configuração da API.';
                }
                $result.addClass('error').text('❌ ' + errorMsg);
            },
            complete: function() {
                $loading.removeClass('is-active');
                $button.prop('disabled', false);
            }
        });
    });
    
    // ===== GERENCIAMENTO DE DOCUMENTOS BASE =====
    
    // Adicionar documento
    $('#add-document-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $result = $('#add-document-result');
        const $loading = $('#add-document-loading');
        const $submitBtn = $form.find('button[type="submit"]');
        
        // Validação básica
        if (!validateDocumentForm($form)) {
            return;
        }
        
        const formData = new FormData($form[0]);
        formData.append('action', 'add_base_document');
        formData.append('nonce', revisorPtdAjax.nonce);
        
        // UI feedback
        $result.removeClass('success error').text('');
        $loading.addClass('is-active');
        $submitBtn.prop('disabled', true);
        
        $.ajax({
            url: revisorPtdAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 120000, // 2 minutos para upload
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').text('✅ ' + response.data.message);
                    $form[0].reset();
                    
                    // Adiciona à tabela se não estiver vazia
                    if (response.data.document) {
                        addDocumentToTable(response.data.document_id, response.data.document);
                    }
                    
                    showNotification('Documento adicionado com sucesso!', 'success');
                } else {
                    $result.addClass('error').text('❌ ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = 'Erro no upload: ' + error;
                
                if (status === 'timeout') {
                    errorMsg = 'Tempo limite excedido. O arquivo pode ser muito grande.';
                } else if (xhr.status === 413) {
                    errorMsg = 'Arquivo muito grande para o servidor.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Permissão negada. Verifique suas credenciais.';
                }
                
                $result.addClass('error').text('❌ ' + errorMsg);
            },
            complete: function() {
                $loading.removeClass('is-active');
                $submitBtn.prop('disabled', false);
            }
        });
    });
    
    // Editar documento
    $(document).on('click', '.edit-document', function(e) {
        e.preventDefault();
        
        const docId = $(this).data('id');
        const $row = $(this).closest('tr');
        const title = $row.find('.column-title strong').text();
        const description = $row.find('.column-description .description-preview').text();
        
        // Preenche modal
        $('#edit_document_id').val(docId);
        $('#edit_document_title').val(title);
        $('#edit_document_description').val(description);
        
        // Mostra modal
        $('#edit-document-modal').show();
    });
    
    // Salvar edição
    $('#save-document-changes').on('click', function() {
        const $button = $(this);
        const $loading = $('#edit-document-loading');
        const $form = $('#edit-document-form');
        
        if (!validateDocumentEditForm($form)) {
            return;
        }
        
        $loading.addClass('is-active');
        $button.prop('disabled', true);
        
        $.ajax({
            url: revisorPtdAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'edit_base_document',
                nonce: revisorPtdAjax.nonce,
                document_id: $('#edit_document_id').val(),
                document_title: $('#edit_document_title').val(),
                document_description: $('#edit_document_description').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#edit-document-modal').hide();
                    updateDocumentInTable(
                        $('#edit_document_id').val(),
                        $('#edit_document_title').val(),
                        $('#edit_document_description').val()
                    );
                    showNotification('Documento atualizado com sucesso!', 'success');
                } else {
                    showNotification('Erro: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Erro na requisição: ' + error, 'error');
            },
            complete: function() {
                $loading.removeClass('is-active');
                $button.prop('disabled', false);
            }
        });
    });
    
    // Excluir documento
    $(document).on('click', '.delete-document', function(e) {
        e.preventDefault();
        
        const docId = $(this).data('id');
        const title = $(this).data('title');
        
        if (!confirm(`Tem certeza que deseja excluir o documento "${title}"?\n\nEsta ação não pode ser desfeita.`)) {
            return;
        }
        
        $.ajax({
            url: revisorPtdAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_base_document',
                nonce: revisorPtdAjax.nonce,
                document_id: docId
            },
            success: function(response) {
                if (response.success) {
                    removeDocumentFromTable(docId);
                    showNotification('Documento excluído com sucesso!', 'success');
                } else {
                    showNotification('Erro: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Erro na requisição: ' + error, 'error');
            }
        });
    });
    
    // ===== LOGS DO SISTEMA =====
    
    // Carregar logs
    function loadLogs() {
        const $container = $('#logs-container');
        
        $container.html(`
            <div class="loading-indicator">
                <div class="spinner is-active"></div>
                <p>Carregando logs...</p>
            </div>
        `);
        
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
                    $container.html(`
                        <div class="error-message">
                            <p>❌ Erro ao carregar logs: ${response.data}</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $container.html(`
                    <div class="error-message">
                        <p>❌ Erro na requisição de logs</p>
                    </div>
                `);
            }
        });
    }
    
    // Exibir logs
    function displayLogs(logs) {
        const $container = $('#logs-container');
        
        if (!logs || logs.length === 0) {
            $container.html(`
                <div class="empty-state">
                    <span class="dashicons dashicons-admin-page"></span>
                    <p>Nenhum log encontrado com os filtros aplicados.</p>
                </div>
            `);
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
        $container.html(html);
        
        // Atualiza contador
        $('#logs-count').text(`${logs.length} registro(s)`);
    }
    
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
                    showNotification('Logs limpos com sucesso!', 'success');
                    loadLogs();
                } else {
                    showNotification('Erro: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('Erro na requisição', 'error');
            }
        });
    });
    
    // Auto-refresh logs
    let autoRefreshInterval = null;
    let isAutoRefresh = false;
    
    $('#auto-refresh').on('click', function() {
        const $button = $(this);
        
        if (isAutoRefresh) {
            clearInterval(autoRefreshInterval);
            $button.text('🔄 Auto-atualizar').removeClass('button-primary').addClass('button-secondary');
            isAutoRefresh = false;
        } else {
            autoRefreshInterval = setInterval(loadLogs, 15000); // 15 segundos
            $button.text('⏸️ Parar Auto-atualizar').removeClass('button-secondary').addClass('button-primary');
            isAutoRefresh = true;
        }
    });
    
    // ===== FUNÇÕES AUXILIARES =====
    
    /**
     * Valida formulário de documento
     */
    function validateDocumentForm($form) {
        const title = $form.find('#document_title').val().trim();
        const description = $form.find('#document_description').val().trim();
        const file = $form.find('#document_file')[0].files[0];
        
        if (!title) {
            showNotification('Por favor, informe o título do documento.', 'error');
            return false;
        }
        
        if (!description) {
            showNotification('Por favor, informe a descrição do documento.', 'error');
            return false;
        }
        
        if (!file) {
            showNotification('Por favor, selecione um arquivo.', 'error');
            return false;
        }
        
        // Validação do arquivo
        const maxSize = 32 * 1024 * 1024; // 32MB
        if (file.size > maxSize) {
            showNotification('Arquivo muito grande. Máximo: 32MB', 'error');
            return false;
        }
        
        const allowedTypes = ['application/pdf', 'application/msword', 
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'text/plain'];
        
        if (!allowedTypes.includes(file.type)) {
            const extension = file.name.toLowerCase().split('.').pop();
            if (!['pdf', 'doc', 'docx', 'txt'].includes(extension)) {
                showNotification('Tipo de arquivo não permitido. Use: PDF, DOC, DOCX ou TXT', 'error');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Valida formulário de edição
     */
    function validateDocumentEditForm($form) {
        const title = $form.find('#edit_document_title').val().trim();
        const description = $form.find('#edit_document_description').val().trim();
        
        if (!title) {
            showNotification('Por favor, informe o título do documento.', 'error');
            return false;
        }
        
        if (!description) {
            showNotification('Por favor, informe a descrição do documento.', 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Adiciona documento à tabela
     */
    function addDocumentToTable(docId, doc) {
        const $tbody = $('#documents-list');
        
        // Remove linha "nenhum documento" se existir
        $tbody.find('.no-documents').remove();
        
        const newRow = `
            <tr data-document-id="${docId}">
                <td class="column-title">
                    <strong>${escapeHtml(doc.title)}</strong>
                </td>
                <td class="column-description">
                    <div class="description-preview">
                        ${escapeHtml(doc.description.substring(0, 100))}${doc.description.length > 100 ? '...' : ''}
                    </div>
                </td>
                <td class="column-file">
                    <span class="file-info">
                        📄 ${escapeHtml(doc.filename)}
                    </span>
                </td>
                <td class="column-date">
                    ${new Date().toLocaleDateString('pt-BR')} ${new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}
                </td>
                <td class="column-actions">
                    <div class="row-actions">
                        <span class="edit">
                            <a href="#" class="edit-document" data-id="${docId}">
                                ✏️ Editar
                            </a>
                        </span>
                        <span class="trash">
                            <a href="#" class="delete-document" 
                               data-id="${docId}"
                               data-title="${escapeHtml(doc.title)}">
                                🗑️ Excluir
                            </a>
                        </span>
                    </div>
                </td>
            </tr>
        `;
        
        $tbody.append(newRow);
        updateDocumentsCount();
    }
    
    /**
     * Atualiza documento na tabela
     */
    function updateDocumentInTable(docId, title, description) {
        const $row = $(`tr[data-document-id="${docId}"]`);
        
        $row.find('.column-title strong').text(title);
        $row.find('.column-description .description-preview').text(
            description.substring(0, 100) + (description.length > 100 ? '...' : '')
        );
        $row.find('.delete-document').attr('data-title', title);
    }
    
    /**
     * Remove documento da tabela
     */
    function removeDocumentFromTable(docId) {
        $(`tr[data-document-id="${docId}"]`).remove();
        
        // Adiciona linha "nenhum documento" se tabela ficar vazia
        if ($('#documents-list tr').length === 0) {
            $('#documents-list').html(`
                <tr class="no-documents">
                    <td colspan="5" class="no-items">
                        <div class="empty-state">
                            <span class="dashicons dashicons-media-document"></span>
                            <p>Nenhum documento base cadastrado.</p>
                            <p class="description">Adicione documentos que servirão como referência para as análises.</p>
                        </div>
                    </td>
                </tr>
            `);
        }
        
        updateDocumentsCount();
    }
    
    /**
     * Atualiza contador de documentos
     */
    function updateDocumentsCount() {
        const count = $('#documents-list tr:not(.no-documents)').length;
        $('#documents-count').text(count + ' documento(s)');
    }
    
    /**
     * Extrai nível do log
     */
    function extractLogLevel(logLine) {
        const match = logLine.match(/\[([A-Z]+)\]/);
        return match ? match[1].toLowerCase() : 'info';
    }
    
    /**
     * Obtém classe CSS para nível do log
     */
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
    
    /**
     * Escapa HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Mostra notificação
     */
    function showNotification(message, type = 'info') {
        // Remove notificações existentes
        $('.admin-notification').remove();
        
        const $notification = $(`
            <div class="notice notice-${type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info')} is-dismissible admin-notification">
                <p><strong>Revisor PTD:</strong> ${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dispensar este aviso.</span>
                </button>
            </div>
        `);
        
        $('.wrap').prepend($notification);
        
        // Auto-remove após 5 segundos
        setTimeout(() => {
            $notification.fadeOut();
        }, 5000);
        
        // Click para fechar
        $notification.find('.notice-dismiss').on('click', () => {
            $notification.fadeOut();
        });
    }
    
    // ===== CONTROLES DE MODAL =====
    
    // Fechar modais
    $('.revisor-modal-close').on('click', function() {
        $(this).closest('.revisor-modal').hide();
    });
    
    // Fechar modal clicando fora
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('revisor-modal')) {
            $('.revisor-modal').hide();
        }
    });
    
    // ===== CONFIGURAÇÕES AVANÇADAS =====
    
    // Restaurar prompt padrão
    $('#reset-prompt').on('click', function() {
        if (!confirm('Tem certeza que deseja restaurar o prompt padrão?\nIsso substituirá o prompt atual.')) {
            return;
        }
        
        // Aqui você colocaria o prompt padrão
        const defaultPrompt = `Você é um especialista em educação profissional com expertise no Modelo Pedagógico Senac (MPS).

Sua tarefa é analisar o Plano de Trabalho Docente (PTD) fornecido e criar um relatório estruturado seguindo exatamente os tópicos solicitados.

INSTRUÇÕES IMPORTANTES:
1. Use APENAS as informações dos documentos base fornecidos como referência
2. Responda cada pergunta de forma clara e objetiva em até um parágrafo
3. Forneça sugestões práticas de melhoria quando necessário
4. Mantenha foco no Modelo Pedagógico Senac (MPS)

FORMATO OBRIGATÓRIO DA RESPOSTA:
Inicie sempre com um cabeçalho contendo:
- Nome do Curso: [extrair do PTD]
- Unidade Curricular: [extrair do PTD]  
- Carga Horária: [extrair do PTD]

CONTEXTO DO USUÁRIO:
{contexto_usuario}

DOCUMENTOS BASE PARA REFERÊNCIA:
{documentos_base}

PTD PARA ANÁLISE:
{ptd_content}

{pcn_content}

Analise o PTD acima seguindo rigorosamente a estrutura de tópicos solicitada.`;
        
        $('#analysis_prompt').val(defaultPrompt);
        showNotification('Prompt padrão restaurado!', 'success');
    });
    
    // Preview do prompt
    $('#preview-prompt').on('click', function() {
        const prompt = $('#analysis_prompt').val();
        $('#prompt-preview-content').text(prompt);
        $('#prompt-preview-modal').show();
    });
    
    // ===== INICIALIZAÇÃO =====
    
    // Carrega logs se estivermos na página de logs
    if ($('#logs-container').length) {
        loadLogs();
    }
    
    // Atualiza contador inicial de documentos
    updateDocumentsCount();
    
   
    // Teste básico de AJAX
    if (typeof revisorPtdAjax === 'undefined') {
        console.error('AJAX não configurado corretamente no admin');
        showNotification('Erro de configuração AJAX. Recarregue a página.', 'error');
    }
});