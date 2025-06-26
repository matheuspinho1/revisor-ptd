<?php
/**
 * Página de Gerenciamento de Documentos Base
 * 
 * Interface para:
 * - Adicionar documentos base
 * - Editar documentos existentes
 * - Remover documentos
 * - Visualizar lista de documentos
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="revisor-ptd-admin-container">
        
        <!-- Adicionar Novo Documento -->
        <div class="postbox">
            <h3 class="hndle">
                <span>Adicionar Novo Documento Base</span>
            </h3>
            <div class="inside">
                <form id="add-document-form" enctype="multipart/form-data">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="document_title">Título do Documento *</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="document_title" 
                                       name="document_title" 
                                       class="regular-text" 
                                       placeholder="Ex: Guia de Práticas Educacionais do MPS"
                                       required />
                                <p class="description">
                                    Nome que identificará o documento na análise.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="document_description">Descrição/Orientação *</label>
                            </th>
                            <td>
                                <textarea id="document_description" 
                                          name="document_description" 
                                          rows="4" 
                                          class="large-text"
                                          placeholder="Descreva como este documento deve ser usado na análise..."
                                          required></textarea>
                                <p class="description">
                                    Instrução sobre como a IA deve usar este documento durante a análise.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="document_file">Arquivo *</label>
                            </th>
                            <td>
                                <input type="file" 
                                       id="document_file" 
                                       name="document_file" 
                                       accept=".pdf,.doc,.docx,.txt"
                                       required />
                                <p class="description">
                                    Formatos aceitos: PDF, DOC, DOCX, TXT. Tamanho máximo: 32MB.
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="submit-section">
                        <button type="submit" class="button button-primary">
                            Adicionar Documento
                        </button>
                        <span id="add-document-result"></span>
                        <div id="add-document-loading" class="spinner"></div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Lista de Documentos -->
        <div class="postbox">
            <h3 class="hndle">
                <span>Documentos Base Cadastrados</span>
            </h3>
            <div class="inside">
                <div class="tablenav top">
                    <div class="alignleft actions">
                       </div>
                    <div class="alignright">
                        <span class="displaying-num" id="documents-count">
                            <?php echo count($documents); ?> documento(s)
                        </span>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped documents-table">
                    <thead>
                        <tr>
                            <th scope="col" class="column-title">Título</th>
                            <th scope="col" class="column-description">Descrição</th>
                            <th scope="col" class="column-file">Arquivo</th>
                            <th scope="col" class="column-date">Data</th>
                            <th scope="col" class="column-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="documents-list">
                        <?php if (empty($documents)): ?>
                            <tr class="no-documents">
                                <td colspan="5" class="no-items">
                                    <div class="empty-state">
                                        <span class="dashicons dashicons-media-document"></span>
                                        <p>Nenhum documento base cadastrado.</p>
                                        <p class="description">Adicione documentos que servirão como referência para as análises.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $doc_id => $doc): ?>
                                <tr data-document-id="<?php echo esc_attr($doc_id); ?>">
                                    <td class="column-title">
                                        <strong><?php echo esc_html($doc['title']); ?></strong>
                                    </td>
                                    <td class="column-description">
                                        <div class="description-preview">
                                            <?php echo esc_html(wp_trim_words($doc['description'], 15)); ?>
                                        </div>
                                    </td>
                                    <td class="column-file">
                                        <span class="file-info">
                                             <?php echo esc_html($doc['filename']); ?>
                                            <?php if (file_exists($doc['filepath'])): ?>
                                                <br><small class="file-size">
                                                    <?php echo size_format(filesize($doc['filepath'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="column-date">
                                        <?php echo date('d/m/Y H:i', strtotime($doc['created'])); ?>
                                    </td>
                                    <td class="column-actions">
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="#" class="edit-document" data-id="<?php echo esc_attr($doc_id); ?>">
                                                    ✏️ Editar
                                                </a>
                                            </span>
                                            <span class="trash">
                                                <a href="#" class="delete-document" 
                                                   data-id="<?php echo esc_attr($doc_id); ?>"
                                                   data-title="<?php echo esc_attr($doc['title']); ?>">
                                                    🗑️ Excluir
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
					
                </table>
				<br>
				<button type="button" class="button action" id="refresh-documents">
                            Atualizar Lista
                        </button>
            </div>
        </div>
		
                   </div>
</div>


<!-- Modal de Edição -->
<div id="edit-document-modal" class="revisor-modal" style="display: none;">
    <div class="revisor-modal-content">
        <div class="revisor-modal-header">
            <h3>Editar Documento</h3>
            <span class="revisor-modal-close">&times;</span>
        </div>
        <div class="revisor-modal-body">
            <form id="edit-document-form">
                <input type="hidden" id="edit_document_id" name="document_id" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="edit_document_title">Título</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="edit_document_title" 
                                   name="document_title" 
                                   class="regular-text" 
                                   required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="edit_document_description">Descrição</label>
                        </th>
                        <td>
                            <textarea id="edit_document_description" 
                                      name="document_description" 
                                      rows="5" 
                                      class="large-text"
                                      required></textarea>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div class="revisor-modal-footer">
            <button type="button" class="button button-primary" id="save-document-changes">
                Salvar Alterações
            </button>
            <button type="button" class="button button-secondary revisor-modal-close">
                Cancelar
            </button>
            <div id="edit-document-loading" class="spinner"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Adicionar documento
    $('#add-document-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const formData = new FormData(form[0]);
        const result = $('#add-document-result');
        const loading = $('#add-document-loading');
        
        // Validações básicas
        if (!$('#document_title').val().trim()) {
            alert('Por favor, informe o título do documento.');
            return;
        }
        
        if (!$('#document_description').val().trim()) {
            alert('Por favor, informe a descrição do documento.');
            return;
        }
        
        if (!$('#document_file')[0].files.length) {
            alert('Por favor, selecione um arquivo.');
            return;
        }
        
        // Adiciona dados AJAX
        formData.append('action', 'add_base_document');
        formData.append('nonce', revisorPtdAjax.nonce);
        
        // Limpa resultado anterior
        result.removeClass('success error').text('');
        loading.addClass('is-active');
        form.find('button[type="submit"]').prop('disabled', true);
        
        $.ajax({
            url: revisorPtdAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 60000,
            success: function(response) {
                if (response.success) {
                    result.addClass('success').text(response.data.message);
                    form[0].reset();
                    refreshDocumentsList();
                } else {
                    result.addClass('error').text('Erro: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = 'Erro na requisição: ' + error;
                if (status === 'timeout') {
                    errorMsg = 'Tempo limite excedido. O arquivo pode ser muito grande.';
                }
                result.addClass('error').text(errorMsg);
            },
            complete: function() {
                loading.removeClass('is-active');
                form.find('button[type="submit"]').prop('disabled', false);
            }
        });
    });
    
    // Editar documento
    $(document).on('click', '.edit-document', function(e) {
        e.preventDefault();
        
        const docId = $(this).data('id');
        const row = $(this).closest('tr');
        const title = row.find('.column-title strong').text();
        const description = row.find('.column-description').text().trim();
        
        $('#edit_document_id').val(docId);
        $('#edit_document_title').val(title);
        $('#edit_document_description').val(description);
        
        $('#edit-document-modal').show();
    });
    
    // Salvar alterações
    $('#save-document-changes').on('click', function() {
        const form = $('#edit-document-form');
        const loading = $('#edit-document-loading');
        
        if (!$('#edit_document_title').val().trim() || !$('#edit_document_description').val().trim()) {
            alert('Por favor, preencha todos os campos.');
            return;
        }
        
        loading.addClass('is-active');
        $(this).prop('disabled', true);
        
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
                    refreshDocumentsList();
                } else {
                    alert('Erro: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('Erro na requisição: ' + error);
            },
            complete: function() {
                loading.removeClass('is-active');
                $('#save-document-changes').prop('disabled', false);
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
                    refreshDocumentsList();
                } else {
                    alert('Erro: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('Erro na requisição: ' + error);
            }
        });
    });
    
    // Atualizar lista
    $('#refresh-documents').on('click', function() {
        refreshDocumentsList();
    });
    
    // Função para atualizar lista de documentos
    function refreshDocumentsList() {
        location.reload(); // Por simplicidade, recarrega a página
        // Em uma implementação mais avançada, faria uma requisição AJAX
    }
    
    // Modal controls
    $('.revisor-modal-close').on('click', function() {
        $('.revisor-modal').hide();
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('revisor-modal')) {
            $('.revisor-modal').hide();
        }
    });
});
</script>