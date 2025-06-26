/**
 * Frontend JavaScript para Revisor PTD - SEM PCN
 * 
 * Funcionalidades:
 * - Manipulação do formulário
 * - Upload de arquivo PTD
 * - Processamento de PDFs
 * - Comunicação AJAX
 * - Controle de progresso
 * - Download de relatórios
 */

jQuery(document).ready(function($) {
    
    // Configuração do PDF.js
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
    
    // Variáveis globais
    let isProcessing = false;
    let pdfCache = {};
    let analysisResult = '';
    
    // ===== INICIALIZAÇÃO =====
    
    initializeFileUploads();
    initializeFormValidation();
    initializeProgressIndicator();
    
    // ===== MANIPULAÇÃO DE ARQUIVOS =====
    
    /**
     * Inicializa upload de arquivo PTD
     */
    function initializeFileUploads() {
        $('.file-input').each(function() {
            const $input = $(this);
            const $wrapper = $input.closest('.file-upload-section');
            const $display = $wrapper.find('.file-upload-display');
            const $selected = $wrapper.find('.file-selected');
            
            // Click no display ativa o input
            $display.on('click', function() {
                $input.click();
            });
            
            // Mudança no input
            $input.on('change', function() {
                const file = this.files[0];
                if (file) {
                    showFileSelected($wrapper, file);
                    validateFile(file, $input);
                }
            });
            
            // Remover arquivo
            $wrapper.find('.remove-file').on('click', function() {
                clearFileSelection($wrapper);
            });
        });
        
        // Drag and drop
        $('.file-upload-display').on({
            'dragover dragenter': function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            },
            'dragleave': function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            },
            'drop': function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    const $input = $(this).siblings('.file-input');
                    $input[0].files = files;
                    $input.trigger('change');
                }
            }
        });
    }
    
    /**
     * Mostra arquivo selecionado
     */
    function showFileSelected($wrapper, file) {
        const $display = $wrapper.find('.file-upload-display');
        const $selected = $wrapper.find('.file-selected');
        
        $selected.find('.file-name').text(file.name);
        $display.hide();
        $selected.show();
    }
    
    /**
     * Limpa seleção de arquivo
     */
    function clearFileSelection($wrapper) {
        const $input = $wrapper.find('.file-input');
        const $display = $wrapper.find('.file-upload-display');
        const $selected = $wrapper.find('.file-selected');
        
        $input.val('');
        $selected.hide();
        $display.show();
        
        // Remove validação
        $wrapper.removeClass('file-valid file-invalid');
    }
    
    /**
     * Valida arquivo
     */
    function validateFile(file, $input) {
        const $wrapper = $input.closest('.file-upload-section');
        const maxSize = 32 * 1024 * 1024; // 32MB
        const allowedTypes = ['application/pdf', 'application/msword', 
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'text/plain'];
        
        let isValid = true;
        let errorMessage = '';
        
        // Verifica tamanho
        if (file.size > maxSize) {
            isValid = false;
            errorMessage = 'Arquivo muito grande. Máximo: 32MB';
        }
        
        // Verifica tipo
        if (!allowedTypes.includes(file.type)) {
            // Verifica por extensão como fallback
            const extension = file.name.toLowerCase().split('.').pop();
            if (!['pdf', 'doc', 'docx', 'txt'].includes(extension)) {
                isValid = false;
                errorMessage = 'Tipo de arquivo não permitido. Use: PDF, DOC, DOCX ou TXT';
            }
        }
        
        // Aplica resultado da validação
        if (isValid) {
            $wrapper.removeClass('file-invalid').addClass('file-valid');
        } else {
            $wrapper.removeClass('file-valid').addClass('file-invalid');
            showNotification(errorMessage, 'error');
            clearFileSelection($wrapper);
        }
        
        return isValid;
    }

    function checkSessionValidity() {
        if (!revisorPtdAjax || !revisorPtdAjax.nonce) {
            showNotification('Erro de configuração. Recarregando a página...', 'error');
            setTimeout(() => window.location.reload(), 2000);
            return false;
        }
        
        if (!revisorPtdAjax.is_user_logged_in) {
            showNotification('Você precisa estar logado para usar esta funcionalidade.', 'error');
            return false;
        }
        
        return true;
    }
	
	function getFreshNonce() {
        return new Promise((resolve, reject) => {
            // Tenta usar o nonce atual primeiro
            if (revisorPtdAjax && revisorPtdAjax.nonce) {
                resolve(revisorPtdAjax.nonce);
                return;
            }
            
            // Se não tem nonce, tenta obter um novo
            console.log('[NONCE] Obtendo nonce fresco...');
            
            $.ajax({
                url: revisorPtdAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_fresh_nonce'
                },
                timeout: 10000,
                success: function(response) {
                    if (response.success && response.data.nonce) {
                        console.log('[NONCE] Nonce fresco obtido:', response.data.nonce);
                        revisorPtdAjax.nonce = response.data.nonce;
                        resolve(response.data.nonce);
                    } else {
                        console.error('[NONCE] Falha ao obter nonce fresco');
                        reject('Falha ao obter nonce');
                    }
                },
                error: function() {
                    console.error('[NONCE] Erro na requisição de nonce');
                    reject('Erro na requisição de nonce');
                }
            });
        });
    }
    
    // ===== VALIDAÇÃO DO FORMULÁRIO =====
    
    /**
     * Inicializa validação do formulário
     */
    function initializeFormValidation() {
        $('#revisor-ptd-form').on('submit', function(e) {
            e.preventDefault();
            
            if (isProcessing) {
                return false;
            }
            
            if (validateForm()) {
                processAnalysis();
            }
        });
    }
    
    /**
     * Valida formulário - APENAS PTD
     */
    function validateForm() {
        let isValid = true;
        const errors = [];
        
        // Verifica PTD obrigatório
        const ptdFile = $('#ptd_file')[0].files[0];
        if (!ptdFile) {
            errors.push('Arquivo PTD é obrigatório');
            isValid = false;
        }
        
        // Mostra erros se houver
        if (!isValid) {
            showNotification('Corrija os seguintes erros:\n• ' + errors.join('\n• '), 'error');
        }
        
        return isValid;
    }
    
    // ===== PROCESSAMENTO DA ANÁLISE =====
    
    /**
     * Inicia processamento da análise - SEM PCN
     */
    function processAnalysis() {
        // Verificação de sessão antes de qualquer coisa
        if (!checkSessionValidity()) {
            return;
        }

        if (isProcessing) {
            return false;
        }

        isProcessing = true;
        
        toggleSubmitButton(true);
        showProgressIndicator();
        
        // Obtém nonce fresco antes da requisição
        getFreshNonce().then(function(nonce) {
            console.log('[NONCE] Usando nonce:', nonce);
            
            // DADOS PADRONIZADOS PARA AJAX COM NONCE FRESCO - APENAS PTD
            const formData = new FormData($('#revisor-ptd-form')[0]);
            formData.append('action', 'process_ptd_analysis');
            formData.append('_wpnonce', nonce);
            formData.append('nonce', nonce);
            
            updateProgress(10, 'Enviando arquivo PTD...');
            updateProgressStep(0, 'active');
            
            $.ajax({
                url: revisorPtdAjax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 180000,
                beforeSend: function() {
                    console.log('[AJAX] Enviando requisição com nonce:', nonce);
                },
                success: function(response) {
                    console.log('[AJAX] Resposta recebida:', response);
                    
                    if (response.success) {
                        if (response.data.pdf_processing_required) {
                            processPDFs(response.data);
                        } else {
                            handleAnalysisComplete(response.data);
                        }
                    } else {
                        if (response.data && response.data.reload_required) {
                            showNotification(response.data.message + ' Recarregando...', 'error');
                            setTimeout(() => window.location.reload(), 2000);
                        } else {
                            handleAnalysisError(response.data ? response.data.message : 'Erro desconhecido');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[AJAX] Erro na requisição:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    handleAjaxError(xhr, status, error);
                }
            });
            
        }).catch(function(error) {
            console.error('[NONCE] Erro ao obter nonce:', error);
            handleAnalysisError('Erro de sessão. Recarregue a página e tente novamente.');
        });
    }
    
    /**
     * Processa PDFs no frontend - APENAS PTD
     */
    function processPDFs(data) {
        totalChunks = data.total_chunks || 8;

        updateProgress(30, 'Processando PDF do PTD...');
        updateProgressStep(1, 'active');
        
        const pdfPaths = data.pdf_paths;
        const pdfPromises = [];
        
        let processedCount = 0;
        const totalPDFs = Object.keys(pdfPaths).length;
        
        // Processa apenas o PDF do PTD
        for (const [key, pdfUrl] of Object.entries(pdfPaths)) {
            const promise = extractTextFromPDF(pdfUrl)
                .then(text => {
                    processedCount++;
                    const progress = 30 + (processedCount / totalPDFs) * 30; // 30-60%
                    updateProgress(progress, `Processando PDF ${processedCount}/${totalPDFs}...`);
                    return { key, text };
                })
                .catch(error => {
                    console.error(`Erro ao processar PDF ${key}:`, error);
                    return { key, text: `[Erro ao processar PDF: ${error.message}]` };
                });
            
            pdfPromises.push(promise);
        }
        
        // Aguarda todos os PDFs
        Promise.all(pdfPromises)
            .then(results => {
                // Prepara dados para segunda requisição - APENAS PTD
                const analysisData = {
                    action: 'process_ptd_with_pdf_texts',
                    nonce: revisorPtdAjax.nonce,
                    request_id: data.request_id
                };
                
                // Adiciona texto extraído do PTD
                results.forEach(result => {
                    if (result.key === 'ptd_path') {
                        analysisData.pdf_texts_ptd = result.text;
                    }
                });
                
                sendFinalAnalysis(analysisData);
            })
            .catch(error => {
                handleAnalysisError('Erro no processamento de PDF: ' + error.message);
            });
    }

    function updateChunkProgress(chunk, total) {
        const baseProgress = 70;
        const progressRange = 25; // 70-95%
        const chunkProgress = baseProgress + (chunk / total) * progressRange;
        
        updateProgress(chunkProgress, `Analisando tópico ${chunk} de ${total}...`);
    }
    
    /**
     * Envia análise final - SEM PCN
     */
    function sendFinalAnalysis(data) {
        // Verificação de sessão
        if (!checkSessionValidity()) {
            return;
        }

        updateProgress(70, 'Enviando para análise de IA...');
        updateProgressStep(2, 'active');
        
        // Usa nonce fresco
        getFreshNonce().then(function(nonce) {
            console.log('[FINAL] Usando nonce:', nonce);
            
            // PADRONIZA O NONCE
            data._wpnonce = nonce;
            data.nonce = nonce;
            data.action = 'process_ptd_with_pdf_texts';
            
            const chunkInterval = simulateChunkProgress();
            
            $.ajax({
                url: revisorPtdAjax.ajax_url,
                type: 'POST',
                data: data,
                timeout: 300000,
                success: function(response) {
                    clearInterval(chunkInterval);
                    if (response.success) {
                        handleAnalysisComplete(response.data);
                    } else {
                        if (response.data && response.data.reload_required) {
                            showNotification(response.data.message + ' Recarregando...', 'error');
                            setTimeout(() => window.location.reload(), 2000);
                        } else {
                            handleAnalysisError(response.data ? response.data.message : 'Erro desconhecido');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    clearInterval(chunkInterval);
                    handleAjaxError(xhr, status, error);
                }
            });
        }).catch(function(error) {
            handleAnalysisError('Erro de sessão. Recarregue a página e tente novamente.');
        });
    }
    
    /**
     * Extrai texto de PDF usando PDF.js
     */
    function extractTextFromPDF(pdfUrl) {
        return new Promise((resolve, reject) => {
            console.log('[PDF] Iniciando extração de texto de:', pdfUrl);

            if (!pdfUrl || pdfUrl.trim() === '') {
                console.error('[PDF] URL do PDF vazia ou inválida');
                resolve('[Erro: URL do PDF inválida]');
                return;
            }
            
            // Verifica cache
            if (pdfCache[pdfUrl]) {
                console.log('[PDF] Usando texto em cache');
                resolve(pdfCache[pdfUrl]);
                return;
            }
            
            console.log('[PDF] Solicitando conteúdo do PDF via AJAX');
            
            // Dados para a requisição AJAX
            const ajaxData = {
                action: 'serve_pdf_content',
                nonce: revisorPtdAjax.nonce,
                file_path: pdfUrl
            };
            
            console.log('[PDF] Dados da requisição:', ajaxData);
            
            // Tenta acessar o PDF usando o endpoint
            $.ajax({
                url: revisorPtdAjax.ajax_url,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                timeout: 60000, // 60 segundos
                beforeSend: function() {
                    console.log('[PDF] Enviando requisição AJAX...');
                }
            })
            .done(function(pdfResponse) {
                console.log('[PDF] Resposta recebida:', pdfResponse);
                
                if (!pdfResponse) {
                    console.error('[PDF] Resposta vazia do servidor');
                    resolve('[Erro: Resposta vazia do servidor]');
                    return;
                }
                
                if (!pdfResponse.success) {
                    const errorMsg = 'Falha ao obter conteúdo do PDF: ' + 
                        (pdfResponse.data && pdfResponse.data.message ? 
                            pdfResponse.data.message : 'Resposta inválida do servidor');
                    console.error('[PDF]', errorMsg);
                    resolve('[Erro: ' + errorMsg + ']');
                    return;
                }
                
                if (!pdfResponse.data || !pdfResponse.data.content) {
                    console.error('[PDF] Conteúdo do PDF não encontrado na resposta');
                    resolve('[Erro: Conteúdo do PDF não encontrado na resposta]');
                    return;
                }
                
                // Decodifica os dados base64 para um Uint8Array
                const pdfData = pdfResponse.data.content;
                console.log('[PDF] Dados base64 recebidos, comprimento:', pdfData.length);
                
                let binary;
                try {
                    binary = atob(pdfData);
                    console.log('[PDF] String binária decodificada, comprimento:', binary.length);
                } catch (decodeError) {
                    console.error('[PDF] Erro ao decodificar base64:', decodeError);
                    resolve('[Erro: Falha na decodificação base64]');
                    return;
                }
                
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                console.log('[PDF] Array de bytes criado, comprimento:', bytes.length);
                
                // Verifica se os dados parecem ser um PDF válido
                const pdfHeader = String.fromCharCode.apply(null, bytes.slice(0, 4));
                if (pdfHeader !== '%PDF') {
                    console.warn('[PDF] AVISO: Dados não parecem ser um PDF válido. Header:', pdfHeader);
                }
                
                // Carrega o PDF com os dados binários
                console.log('[PDF] Carregando documento PDF com pdfjsLib.getDocument');
                
                const loadingTask = pdfjsLib.getDocument({ 
                    data: bytes,
                    verbosity: 0 // Reduz logs do PDF.js
                });
                
                console.log('[PDF] Aguardando carregamento do PDF...');
                
                loadingTask.promise
                    .then(function(pdfDoc) {
                        console.log('[PDF] PDF carregado com sucesso, páginas:', pdfDoc.numPages);
                        
                        if (pdfDoc.numPages === 0) {
                            console.warn('[PDF] PDF não contém páginas');
                            resolve('[Aviso: PDF não contém páginas]');
                            return;
                        }
                        
                        const pagePromises = [];
                        
                        // Função para processar uma página específica
                        function processPage(pageNum) {
                            return pdfDoc.getPage(pageNum)
                                .then(function(page) {
                                    console.log('[PDF] Processando página', pageNum);
                                    
                                    return page.getTextContent();
                                })
                                .then(function(textContent) {
                                    console.log('[PDF] Conteúdo extraído da página', pageNum, '- itens:', textContent.items.length);
                                    
                                    if (textContent.items.length === 0) {
                                        console.warn('[PDF] Página', pageNum, 'não contém texto extraível');
                                        return { pageNum: pageNum, text: '' };
                                    }
                                    
                                    // Extrai o texto de todos os itens
                                    const textItems = textContent.items.map(item => item.str);
                                    const pageText = textItems.join(' ');
                                    
                                    console.log('[PDF] Texto extraído da página', pageNum, '- caracteres:', pageText.length);
                                    
                                    return { pageNum: pageNum, text: pageText };
                                })
                                .catch(function(pageError) {
                                    console.error('[PDF] Erro ao processar página', pageNum, ':', pageError);
                                    return { 
                                        pageNum: pageNum, 
                                        text: `[Erro ao extrair texto da página ${pageNum}: ${pageError.message}]` 
                                    };
                                });
                        }
                        
                        // Cria promises para cada página
                        for (let i = 1; i <= pdfDoc.numPages; i++) {
                            console.log('[PDF] Preparando processamento da página', i, 'de', pdfDoc.numPages);
                            pagePromises.push(processPage(i));
                        }
                        
                        // Processa todas as páginas
                        return Promise.all(pagePromises);
                    })
                    .then(function(results) {
                        console.log('[PDF] Todas as páginas processadas');
                        
                        // Ordena os resultados por número da página e concatena
                        results.sort((a, b) => a.pageNum - b.pageNum);
                        
                        let fullText = '';
                        results.forEach(result => {
                            if (result.text && result.text.trim()) {
                                fullText += result.text + '\n\n';
                            }
                        });
                        
                        // Limpa o texto final
                        fullText = fullText
                            .replace(/\s+/g, ' ') // Remove múltiplos espaços
                            .replace(/\n\s*\n/g, '\n\n') // Normaliza quebras de linha
                            .trim();
                        
                        console.log('[PDF] Texto completo extraído - caracteres:', fullText.length);
                        
                        if (fullText.length < 50) {
                            console.warn('[PDF] AVISO: Texto extraído é muito curto');
                            const warningText = '[Aviso: Texto extraído do PDF é muito curto. O arquivo pode conter principalmente imagens ou estar protegido.]';
                            pdfCache[pdfUrl] = warningText;
                            resolve(warningText);
                            return;
                        }
                        
                        // Armazena no cache
                        pdfCache[pdfUrl] = fullText;
                        
                        console.log('[PDF] Extração concluída com sucesso');
                        resolve(fullText);
                    })
                    .catch(function(loadError) {
                        console.error('[PDF] Erro ao carregar/processar PDF:', loadError);
                        const errorText = `[Erro ao processar PDF: ${loadError.message}]`;
                        resolve(errorText);
                    });
            })
            .fail(function(xhr, status, error) {
                console.error('[PDF] Erro na requisição AJAX:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                let errorMessage = '[Erro na requisição: ';
                
                if (status === 'timeout') {
                    errorMessage += 'Tempo limite excedido';
                } else if (xhr.status === 0) {
                    errorMessage += 'Problema de conexão';
                } else if (xhr.status === 404) {
                    errorMessage += 'Arquivo não encontrado no servidor';
                } else if (xhr.status === 403) {
                    errorMessage += 'Acesso negado ao arquivo';
                } else {
                    errorMessage += `${error} (${xhr.status})`;
                }
                
                errorMessage += ']';
                console.error('[PDF]', errorMessage);
                resolve(errorMessage);
            });
        });
    }
    
    // ===== MANIPULAÇÃO DE RESULTADOS =====
    
    /**
     * Manipula análise completa
     */
    function handleAnalysisComplete(data) {
        updateProgress(100, 'Análise concluída!');
        updateProgressStep(3, 'completed');
        
        // Armazena a análise original globalmente para uso na limpeza
        window.lastAnalysis = data.analysis;
        
        // Pequeno delay para mostrar 100%
        setTimeout(() => {
            analysisResult = data.analysis;
            showAnalysisResult(analysisResult);
            hideProgressIndicator();
            isProcessing = false;
        }, 1000);
    }
    
    /**
     * Manipula erro na análise
     */
    function handleAnalysisError(errorMessage) {
        console.error('Erro na análise:', errorMessage);
        showNotification('Erro na análise: ' + errorMessage, 'error');
        hideProgressIndicator();
        toggleSubmitButton(false);
        isProcessing = false;
    }
    
    /**
     * Manipula erro AJAX
     */
    function handleAjaxError(xhr, status, error) {
        let errorMessage = 'Erro na comunicação com o servidor';
        
        if (status === 'timeout') {
            errorMessage = 'Tempo limite excedido. Tente novamente.';
        } else if (xhr.status === 429) {
            errorMessage = 'Muitas requisições. Aguarde alguns minutos.';
        } else if (xhr.status === 0) {
            errorMessage = 'Problema de conexão. Verifique sua internet.';
        }
        
        handleAnalysisError(errorMessage);
    }
    
    /**
     * Mostra resultado da análise
     */
    function showAnalysisResult(analysis) {
        const $form = $('#revisor-ptd-form');
        const $result = $('#analysis-result');
        
        // Formata e exibe o resultado
        const formattedAnalysis = formatAnalysisResult(analysis);
        $result.find('.result-content').html(formattedAnalysis);
        
        // Esconde formulário e mostra resultado
        $form.hide();
        $result.show();
        
        // Rola para o resultado
        $result[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    /**
     * Formata resultado da análise - SEM PCN
     */
    function formatAnalysisResult(analysis) {
        // Extrai informações do cabeçalho
        const courseMatch = analysis.match(/Nome do Curso:\s*([^\n\r]+?)(?=\s*(?:Unidade Curricular|UC|Carga|Coerência|\n))/i);
        const ucMatch = analysis.match(/Unidade Curricular:\s*([^\n\r]+?)(?=\s*(?:Carga Horária|Nome|Coerência|\n))/i);
        const hoursMatch = analysis.match(/Carga Horária:\s*([^\n\r]+?)(?=\s*(?:Coerência|Nome|Unidade|\n))/i);
        
        const course = courseMatch ? courseMatch[1].trim() : 'Não informado';
        const uc = ucMatch ? ucMatch[1].trim() : 'Não informado';  
        const hours = hoursMatch ? hoursMatch[1].trim() : 'Não informado';
              
        // Estrutura dos tópicos (mesma do código original)
        const topicStructure = [
            {
                title: "1. Coerência entre competência, situação de aprendizagem e indicadores",
                questions: [
                    "A competência está claramente relacionada à situação de aprendizagem e aos indicadores?",
                    "Os fazeres previstos nos indicadores são efetivamente contemplados nas atividades propostas?"
                ]
            },
            {
                title: "2. Estrutura e clareza das atividades",
                questions: [
                    "As atividades estão descritas de forma clara e detalhada?",
                    "Há uma sequência lógica que contempla contextualização, desenvolvimento e conclusão da situação de aprendizagem?",
                    "As etapas das atividades são compreensíveis e executáveis?"
                ]
            },
            {
                title: "3. Articulação entre conhecimentos, habilidades e atitudes",
                questions: [
                    "As atividades permitem mobilizar de forma integrada os elementos da competência (saberes, fazeres e atitudes/valores)?",
                    "As propostas articulam teoria e prática de forma equilibrada?",
                    "O ciclo ação-reflexão-ação está contemplado?"
                ]
            },
            {
                title: "4. Metodologias ativas e protagonismo do aluno",
                questions: [
                    "As atividades propostas utilizam metodologias ativas?",
                    "Promovem o protagonismo do estudante no processo de aprendizagem?",
                    "Há variedade entre atividades individuais e coletivas?"
                ]
            },
            {
                title: "5. Uso de tecnologias",
                questions: [
                    "As tecnologias digitais são utilizadas com intencionalidade pedagógica?"
                ]
            },
            {
                title: "6. Marcas formativas",
                questions: [
                    "As atividades contribuem para o desenvolvimento das Marcas Formativas?"
                ]
            },
            {
                title: "7. Avaliação da aprendizagem",
                questions: [
                    "Há diversidade de instrumentos e procedimentos avaliativos?",
                    "As avaliações permitem identificar dificuldades dos alunos?",
                    "O planejamento contempla avaliações diagnóstica, formativa e somativa?",
                    "Estão previstos momentos de feedback aos alunos?"
                ]
            },
            {
                title: "8. Acessibilidade e inclusão",
                questions: [
                    "O plano contempla adaptações para alunos PcD's (Pessoa com Deficiência)?",
                    "Há recursos de acessibilidade digital, física ou pedagógica previstos?",
                    "As atividades permitem diferentes formas de participação e expressão dos alunos?"
                ]
            }
        ];
        
        // Constrói HTML formatado
        let html = `
            <div class="analysis-document">
                <div class="document-header">
                    <h1>Relatório de Análise do Plano de Trabalho Docente</h1>
                    
                    <div class="document-info">
                        <div class="info-row">
                            <span class="info-label">Curso:</span>
                            <span class="info-value">${escapeHtml(course)}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Unidade Curricular:</span>
                            <span class="info-value">${escapeHtml(uc)}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Carga Horária:</span>
                            <span class="info-value">${escapeHtml(hours)}</span>
                        </div>
                    </div>
                </div>
                
                <div class="document-content">
        `;
        
        // Processa cada tópico
        topicStructure.forEach((topic, index) => {
            const topicNumber = index + 1;
            
            // Extrai conteúdo do tópico
            let topicContent = extractTopicContent(analysis, topicNumber, topic.title);
            
            html += `
                <div class="topic-section">
                    <h2 class="topic-title">${escapeHtml(topic.title)}</h2>
                    
                    <div class="topic-questions">
                        ${topic.questions.map(question => 
                            `<div class="question-item">• ${escapeHtml(question)}</div>`
                        ).join('')}
                    </div>
                    
                    <div class="topic-analysis">
                        ${topicContent ? formatTopicResponse(topicContent) : `
                            <div class="debug-info" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #e74c3c;">
                                <p class="no-content">Análise não disponível para este tópico.</p>
                                <details>
                                    <summary>Debug Info (Clique para expandir)</summary>
                                    <p><strong>Tópico:</strong> ${topicNumber}</p>
                                    <p><strong>Busca por:</strong> "${topicNumber}."</p>
                                    <p><strong>Próximo tópico:</strong> "${topicNumber + 1}."</p>
                                    <textarea readonly style="width: 100%; height: 100px; font-family: monospace; font-size: 11px;">${analysis.substring(0, 1000)}...</textarea>
                                </details>
                            </div>
                        `}
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
        
        return html;
    }
    
    /**
     * Extrai conteúdo de um tópico específico
     */
    function extractTopicContent(analysis, topicNumber, topicTitle) {
        // Normaliza o texto
        const cleanAnalysis = analysis
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/\n{3,}/g, '\n\n');
        
        // Títulos que a IA pode retornar
        const topicTitles = [
            'Coerência entre competência, situação de aprendizagem e indicadores',
            'Estrutura e clareza das atividades', 
            'Articulação entre conhecimentos, habilidades e atitudes',
            'Metodologias ativas e protagonismo do aluno',
            'Uso de tecnologias',
            'Marcas formativas',
            'Avaliação da aprendizagem',
            'Acessibilidade e inclusão'
        ];
        
        // ESTRATÉGIA 1: Tenta buscar com numeração primeiro (ideal)
        const nextTopicNumber = topicNumber + 1;
        const numberedPattern = new RegExp(`${topicNumber}\\.([\\s\\S]*?)(?=${nextTopicNumber}\\.|$)`, 'i');
        
        let match = cleanAnalysis.match(numberedPattern);
        
        if (match && match[1] && match[1].trim().length > 20) {
            let content = match[1].trim();
            content = cleanTopicContent(content, topicNumber);
            return content.length > 20 ? content : null;
        }
        
        // ESTRATÉGIA 2: Busca sem numeração (fallback)
        const currentTopicTitle = topicTitles[topicNumber - 1];
        const nextTopicTitle = topicTitles[topicNumber];
         
        if (!currentTopicTitle) {
            return null;
        }
        
        // Encontra a posição do título atual
        const currentTitleIndex = cleanAnalysis.indexOf(currentTopicTitle);
        
        if (currentTitleIndex === -1) {
            return null;
        }
               
        // Encontra onde termina o conteúdo deste tópico
        let endIndex;
        
        if (nextTopicTitle) {
            // Procura pelo próximo título
            const nextTitleIndex = cleanAnalysis.indexOf(nextTopicTitle, currentTitleIndex + currentTopicTitle.length);
            if (nextTitleIndex !== -1) {
                endIndex = nextTitleIndex;
            } else {
                // Se não encontrou o próximo, procura por "Sugestões de melhoria"
                const suggestionsIndex = cleanAnalysis.indexOf('Sugestões de melhoria', currentTitleIndex);
                endIndex = suggestionsIndex !== -1 ? suggestionsIndex : cleanAnalysis.length;
            }
        } else {
            // É o último tópico, procura por "Sugestões de melhoria"
            const suggestionsIndex = cleanAnalysis.indexOf('Sugestões de melhoria', currentTitleIndex);
            endIndex = suggestionsIndex !== -1 ? suggestionsIndex : cleanAnalysis.length;
        }
        
        // Extrai o conteúdo entre os títulos
        const startIndex = currentTitleIndex + currentTopicTitle.length;
        let content = cleanAnalysis.substring(startIndex, endIndex).trim();
            
        if (content.length < 20) {
            return null;
        }
        
        // Limpa o conteúdo
        content = cleanTopicContent(content, topicNumber);
        
        return content.length > 20 ? content : null;
    }

    /**
     * Limpa conteúdo do tópico - VERSÃO SIMPLIFICADA
     */
    function cleanTopicContent(content, topicNumber) {
        if (!content) return '';
        
        // Lista de frases/palavras que podem indicar corte no meio do texto
        const incompleteIndicators = [
            /\s+no\.\s*$/i,           // "especialmente no."
            /\s+de\s*$/i,             // "uso de"
            /\s+para\s*$/i,           // "ferramentas para"
            /\s+com\s*$/i,            // "trabalho com"
            /\s+da\s*$/i,             // "análise da"
            /\s+do\s*$/i,             // "estudo do"
            /\s+e\s*$/i,              // "colaboração e"
            /\s+ou\s*$/i,             // "individual ou"
            /\s+são\s*$/i,            // "atividades são"
            /\s+está\s*$/i,           // "processo está"
            /\s+tem\s*$/i,            // "plano tem"
            /\s+uso\s*$/i,            // "especialmente uso"
            /\s+das\s*$/i,            // "desenvolvimento das"
            /\s+aos\s*$/i,            // "adaptações aos"
            /\s+pelos\s*$/i           // "utilizadas pelos"
        ];
        
        // Verifica se o conteúdo termina de forma incompleta
        let isIncomplete = incompleteIndicators.some(regex => regex.test(content));
        
        if (isIncomplete) {
            const analysis = window.lastAnalysis || ''; 
            if (analysis) {
                // Pega as primeiras 50 palavras do conteúdo atual
                const firstWords = content.split(/\s+/).slice(0, 10).join(' ');
                
                // Busca por esse trecho na análise original e pega mais contexto
                const contextIndex = analysis.indexOf(firstWords);
                if (contextIndex !== -1) {
                    // Pega 500 caracteres a partir desse ponto
                    const extendedContent = analysis.substring(contextIndex, contextIndex + 500);
                    
                    // Verifica se conseguiu um texto mais completo
                    const isStillIncomplete = incompleteIndicators.some(regex => regex.test(extendedContent));
                    
                    if (!isStillIncomplete && extendedContent.length > content.length) {
                        content = extendedContent;
                    }
                }
            }
        }
        
        // Define os títulos para remover caso apareçam no meio do texto
        const topicTitlesToRemove = [
            'Coerência entre competência, situação de aprendizagem e indicadores',
            'Estrutura e clareza das atividades', 
            'Articulação entre conhecimentos, habilidades e atitudes',
            'Metodologias ativas e protagonismo do aluno',
            'Uso de tecnologias',
            'Marcas formativas',
            'Avaliação da aprendizagem',
            'Acessibilidade e inclusão',
            'Sugestões de melhoria'
        ];
        
        // Remove títulos de outros tópicos que possam estar no meio
       topicTitlesToRemove.forEach(title => {
    const regex = new RegExp(title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
    content = content.replace(regex, '');
});
        let html = `
            <div class="analysis-document">
                <div class="document-header">
                    <h1>Relatório de'), 'gi');
            content = content.replace(regex, '');
        });
        
        return content
            // Remove linhas com apenas espaços
            .replace(/^\s*$/gm, '')
            // Remove múltiplas quebras de linha
            .replace(/\n{3,}/g, '\n\n')
            // Remove espaços extras
            .replace(/[ \t]+/g, ' ')
            // Remove espaços no início e fim de cada linha
            .replace(/^[ \t]+|[ \t]+$/gm, '')
            // Trim geral
            .trim();
    }

    /**
     * Formata resposta do tópico - VERSÃO FINAL
     */
    function formatTopicResponse(content) {
        if (!content || content.trim().length === 0) {
            return '<p class="no-content">Conteúdo não disponível.</p>';
        }
        
        // Lista de todas as perguntas que devem ser removidas
        const questionsToRemove = [
            'A competência está claramente relacionada à situação de aprendizagem e aos indicadores?',
            'Os fazeres previstos nos indicadores são efetivamente contemplados nas atividades propostas?',
            'As atividades estão descritas de forma clara e detalhada?',
            'Há uma sequência lógica que contempla contextualização, desenvolvimento e conclusão da situação de aprendizagem?',
            'As etapas das atividades são compreensíveis e executáveis?',
            'As atividades permitem mobilizar de forma integrada os elementos da competência (saberes, fazeres e atitudes/valores)?',
            'As propostas articulam teoria e prática de forma equilibrada?',
            'O ciclo ação-reflexão-ação está contemplado?',
            'As atividades propostas utilizam metodologias ativas?',
            'Promovem o protagonismo do estudante no processo de aprendizagem?',
            'Há variedade entre atividades individuais e coletivas?',
            'As tecnologias digitais são utilizadas com intencionalidade pedagógica?',
            'As atividades contribuem para o desenvolvimento das Marcas Formativas?',
            'Há diversidade de instrumentos e procedimentos avaliativos?',
            'As avaliações permitem identificar dificuldades dos alunos?',
            'O planejamento contempla avaliações diagnóstica, formativa e somativa?',
            'Estão previstos momentos de feedback aos alunos?',
            'O plano contempla adaptações para alunos PcD\'s (Pessoa com Deficiência)?',
            'Há recursos de acessibilidade digital, física ou pedagógica previstos?',
            'As atividades permitem diferentes formas de participação e expressão dos alunos?'
        ];
        
        // Limpa o conteúdo removendo perguntas
        let cleanedContent = content;
        
        // Remove cada pergunta do conteúdo
        questionsToRemove.forEach(question => {
            // Remove a pergunta exata
            cleanedContent = cleanedContent.replace(question, '');
            // Remove com bullets
            cleanedContent = cleanedContent.replace('• ' + question, '');
            cleanedContent = cleanedContent.replace('- ' + question, '');
            cleanedContent = cleanedContent.replace('* ' + question, '');
        });
        
        // Remove linhas que são apenas perguntas (terminam com ?)
        const lines = cleanedContent.split('\n');
        const filteredLines = lines.filter(line => {
            const trimmed = line.trim();
            // Remove linhas vazias ou que são perguntas
            if (!trimmed) return false;
            if (trimmed.endsWith('?')) return false;
            // Remove bullets vazios
            if (trimmed.match(/^[•\-\*]\s*$/)) return false;
            return true;
        });
        
        cleanedContent = filteredLines.join('\n');
        
        // Remove múltiplas quebras de linha
        cleanedContent = cleanedContent.replace(/\n{3,}/g, '\n\n').trim();
        
        // Se não sobrou conteúdo, retorna mensagem padrão
        if (cleanedContent.length < 20) {
            return '<p class="no-content">Análise disponível para este tópico.</p>';
        }
        
        // Divide o conteúdo em parágrafos
        const paragraphs = cleanedContent
            .split(/\n\s*\n/)
            .map(p => p.trim())
            .filter(p => p.length > 20);
        
        if (paragraphs.length > 0) {
            return paragraphs
                .map(paragraph => `<p class="analysis-paragraph">${escapeHtml(paragraph)}</p>`)
                .join('');
        }
        
        // Se não conseguiu dividir em parágrafos, tenta por sentenças
        const sentences = cleanedContent
            .split(/\.(?=\s+[A-Z])/)
            .map(sentence => sentence.trim())
            .filter(sentence => sentence.length > 15)
            .map(sentence => {
                if (!/[.!?]$/.test(sentence)) {
                    sentence += '.';
                }
                return sentence;
            });
        
        if (sentences.length > 0) {
            // Agrupa sentenças em parágrafos
            const paragraphGroups = [];
            for (let i = 0; i < sentences.length; i += 2) {
                const paragraphSentences = sentences.slice(i, i + 2);
                paragraphGroups.push(paragraphSentences.join(' '));
            }
            
            return paragraphGroups
                .map(paragraph => `<p class="analysis-paragraph">${escapeHtml(paragraph)}</p>`)
                .join('');
        }
        
        // Último recurso: retorna o texto completo
        return `<p class="analysis-paragraph">${escapeHtml(cleanedContent)}</p>`;
    }

    /**
     * Função auxiliar para escapar HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ===== CONTROLE DE PROGRESSO =====
    
    /**
     * Inicializa indicador de progresso
     */
    function initializeProgressIndicator() {
        // Configurado via CSS/HTML
    }
    
    /**
     * Mostra indicador de progresso
     */
    function showProgressIndicator() {
        $('#progress-container').show();
        resetProgress();
    }

    let currentChunk = 0;
    let totalChunks = 8;

    function simulateChunkProgress() {
        let currentChunk = 1;
        const chunkInterval = setInterval(() => {
            if (currentChunk <= totalChunks) {
                updateChunkProgress(currentChunk, totalChunks);
                currentChunk++;
            } else {
                clearInterval(chunkInterval);
            }
        }, 3000); // Mesmo tempo do delay entre requisições
        
        return chunkInterval;
    }
    
    /**
     * Esconde indicador de progresso
     */
    function hideProgressIndicator() {
        $('#progress-container').hide();
    }
    
    /**
     * Atualiza progresso
     */
    function updateProgress(percentage, message) {
        $('#progress-container .progress-percentage').text(Math.round(percentage) + '%');
        $('#progress-container .progress-fill').css('width', percentage + '%');
        $('#progress-container .progress-message').text(message);
    }
    
    /**
     * Atualiza step do progresso
     */
    function updateProgressStep(stepIndex, status) {
        const $steps = $('#progress-steps li');
        
        $steps.eq(stepIndex)
            .removeClass('step-pending step-active step-completed')
            .addClass('step-' + status);
            
        // Marca anteriores como completed
        if (status === 'active' || status === 'completed') {
            $steps.slice(0, stepIndex).addClass('step-completed');
        }
    }
    
    /**
     * Reseta progresso
     */
    function resetProgress() {
        updateProgress(0, 'Iniciando...');
        $('#progress-steps li').removeClass('step-active step-completed').addClass('step-pending');
    }
    
    // ===== CONTROLES DE INTERFACE =====
    
    /**
     * Alterna botão de submit
     */
    function toggleSubmitButton(loading) {
        const $button = $('#submit-analysis');
        const $text = $button.find('.button-text');
        const $loading = $button.find('.button-loading');
        
        if (loading) {
            $button.prop('disabled', true);
            $text.hide();
            $loading.show();
        } else {
            $button.prop('disabled', false);
            $text.show();
            $loading.hide();
        }
    }
    
    /**
     * Mostra notificação
     */
    function showNotification(message, type = 'info') {
        // Remove notificações existentes
        $('.revisor-notification').remove();
        
        const $notification = $(`
            <div class="revisor-notification notification-${type}">
                <div class="notification-content">
                    <span class="notification-icon">
                        ${type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️'}
                    </span>
                    <span class="notification-message">${message}</span>
                    <button class="notification-close">×</button>
                </div>
            </div>
        `);
        
        $('body').append($notification);
        
        // Auto-remove após 5 segundos
        setTimeout(() => {
            $notification.fadeOut(() => $notification.remove());
        }, 5000);
        
        // Click para fechar
        $notification.find('.notification-close').on('click', () => {
            $notification.fadeOut(() => $notification.remove());
        });
    }
    
    // ===== DOWNLOAD E AÇÕES =====
    
    /**
     * Handlers unificados para botões de ação
     */
    $(document).on('click', '.result-action-btn', function() {
        const action = $(this).data('action');
        
        if (action === 'download') {
            handleDownloadAnalysis();
        } else if (action === 'new') {
            handleNewAnalysis();
        }
    });

    /**
     * Download da análise
     */
    function handleDownloadAnalysis() {
        if (!analysisResult) {
            showNotification('Nenhuma análise disponível para download', 'error');
            return;
        }
        
        downloadAnalysisAsWord(analysisResult);
    }

    /**
     * Nova análise
     */
    function handleNewAnalysis() {
        // Reset formulário
        $('#revisor-ptd-form')[0].reset();
        $('.file-upload-section').each(function() {
            clearFileSelection($(this));
        });
        
        // Limpa variáveis
        analysisResult = '';
        pdfCache = {};
        
        // Reset estado de processamento e botão
        isProcessing = false;
        toggleSubmitButton(false); 
        
        // Esconde indicador de progresso se estiver visível
        hideProgressIndicator();
        
        // Remove notificações existentes
        $('.revisor-notification').remove();
        
        // Mostra formulário
        $('#analysis-result').hide();
        $('#revisor-ptd-form').show();
        
        // Rola para o topo
        $('.revisor-ptd-form-container')[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    /**
     * Download como Word
     */
    function downloadAnalysisAsWord(analysis) {
        const formattedContent = formatForWord(analysis);
        
        const htmlContent = `
            <html xmlns:o='urn:schemas-microsoft-com:office:office' 
                  xmlns:w='urn:schemas-microsoft-com:office:word' 
                  xmlns='http://www.w3.org/TR/REC-html40'>
            <head>
                <meta charset='utf-8'>
                <title>Análise PTD</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.6; }
                    h1 { font-size: 16pt; text-align: center; color: #2c3e50; }
                    h2 { font-size: 14pt; color: #2c3e50; }
                    h3 { font-size: 14pt; color: #3498db; border-bottom: 1pt solid #3498db; }
                    table { width: 100%; border-collapse: collapse; margin: 20pt 0; }
                    td { border: 1pt solid #333; padding: 8pt; }
                    td:first-child { background-color: #f0f0f0; font-weight: bold; }
                    p { margin: 0 0 12pt 0; text-align: justify; }
                </style>
            </head>
            <body>${formattedContent}</body>
            </html>
        `;
        
        const blob = new Blob([htmlContent], { type: 'application/msword' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        
        link.href = url;
        link.download = `analise-ptd-${new Date().getTime()}.doc`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        URL.revokeObjectURL(url);
        
        showNotification('Download iniciado!', 'success');
    }
    
    /**
     * Formata conteúdo para Word
     */
    function formatForWord(analysis) {
        // Implementação simplificada - pode ser expandida
        return analysis
            .replace(/\n/g, '<br>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>');
    }
    
    // ===== INICIALIZAÇÃO FINAL =====
          
    // Teste de conectividade
    if (typeof revisorPtdAjax === 'undefined') {
        console.error('AJAX não configurado corretamente');
        showNotification('Erro de configuração. Recarregue a página.', 'error');
    }
});