<?php
/**
 * Plugin Name: Revisor de PTD com IA
 * Plugin URI: https://www.senac.br
 * Description: Plugin para revisão de Planos de Trabalho Docente usando IA configurável
 * Version: 2.3.4
 * Author: Equipe de Desenvolvimento Educacional
 * Text Domain: revisor-ptd-com-ia
 * Domain Path: /languages
 * License: GPL-2.0+
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Previne acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function() {
    if (isset($_REQUEST['action']) && strpos($_REQUEST['action'], 'process_ptd') !== false) {
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
});

// Limpa transients órfãos periodicamente
add_action('wp_scheduled_delete', function() {
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_revisor_ptd_request_%' 
         AND option_name NOT LIKE '%_timeout_%'"
    );
});

// Define constantes do plugin
define('REVISOR_PTD_VERSION', '2.3.4');
define('REVISOR_PTD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('REVISOR_PTD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('REVISOR_PTD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principal do plugin Revisor PTD com IA
 */
class RevisorPTDComIA {
    
    private $plugin_name;
    private $version;
    private $settings;
    private $document_handler;
    private $logger;

    public function __construct() {
        $this->plugin_name = 'revisor-ptd-com-ia';
        $this->version = REVISOR_PTD_VERSION;
        $this->settings = get_option('revisor_ptd_settings', array());
        
        $this->init_dependencies();
        $this->init_hooks();
        $this->init_default_settings();
    }

    private function verify_nonce_secure($action = 'revisor_ptd_nonce') {
        $debug_mode = true;
        
        if ($debug_mode) {
            error_log('=== DEBUG NONCE ===');
            error_log('Action: ' . $action);
            error_log('Current user: ' . get_current_user_id());
        }
        
        $current_nonce = '';
        $nonce_sources = array(
            isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '',
            isset($_POST['nonce']) ? $_POST['nonce'] : '',
            isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '',
            isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '',
            isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : ''
        );
        
        foreach ($nonce_sources as $nonce_candidate) {
            if (!empty($nonce_candidate)) {
                $current_nonce = $nonce_candidate;
                
                if ($debug_mode) {
                    error_log('Testando nonce: ' . $nonce_candidate);
                }
                
                if (wp_verify_nonce($nonce_candidate, $action)) {
                    if ($debug_mode) {
                        error_log('✅ Nonce VÁLIDO: ' . $nonce_candidate);
                    }
                    return true;
                }
            }
        }
        
        if ($debug_mode) {
            error_log('🔄 Tentando fallback de emergência...');
        }
        
        if (is_user_logged_in() && !empty($_POST['action'])) {
            $allowed_actions = array(
                'process_ptd_analysis',
                'process_ptd_with_pdf_texts',
                'serve_pdf_content'
            );
            
            if (in_array($_POST['action'], $allowed_actions)) {
                if ($debug_mode) {
                    error_log('✅ FALLBACK aplicado para ação: ' . $_POST['action']);
                }
                
                if ($this->logger) {
                    $this->logger->log('FALLBACK de nonce aplicado', 'warning', array(
                        'user_id' => get_current_user_id(),
                        'action' => $_POST['action'],
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ));
                }
                
                return true;
            }
        }
        
        if ($this->logger) {
            $this->logger->error('Falha na verificação de nonce', array(
                'action' => $action,
                'user_id' => get_current_user_id(),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'nonce_sources' => $nonce_sources
            ));
        }
        
        return false;
    }
    
    /**
     * AJAX: Processa PTD
     */
    public function process_ptd_with_pdf_texts() {
        if (!$this->verify_nonce_secure('revisor_ptd_nonce')) {
            wp_send_json_error(array(
                'message' => 'Sessão expirada. Recarregue a página e tente novamente.',
                'code' => 'NONCE_INVALID',
                'reload_required' => true
            ));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'Você precisa estar logado para usar esta funcionalidade.',
                'code' => 'NOT_LOGGED_IN'
            ));
            return;
        }
        
        nocache_headers();
        
        try {
            if ($this->logger) {
                $this->logger->log('Processando PDF para usuário: ' . get_current_user_id(), 'info');
            }
            
            if (!isset($_POST['request_id'])) {
                throw new Exception('ID da requisição não fornecido');
            }
            
            $request_id = sanitize_text_field($_POST['request_id']);
            $request_data = get_transient('revisor_ptd_request_' . $request_id);
            
            if (!$request_data) {
                throw new Exception('Requisição expirada ou inválida. Tente novamente.');
            }
            
            if (!isset($request_data['user_id']) || $request_data['user_id'] != get_current_user_id()) {
                delete_transient('revisor_ptd_request_' . $request_id);
                throw new Exception('Acesso negado. Faça login novamente.');
            }
            
            // ATUALIZA APENAS PTD
            if (isset($_POST['pdf_texts_ptd'])) {
                $request_data['ptd_text'] = stripslashes($_POST['pdf_texts_ptd']);
            }
            
            // Processa análise
            $analysis = $this->perform_analysis(
                $request_data['ptd_text'], 
                '', // PCN vazio
                $request_data['form_data']
            );
            
            delete_transient('revisor_ptd_request_' . $request_id);
            
            if ($this->logger) {
                $this->logger->log('Análise PDF concluída para usuário: ' . get_current_user_id(), 'info');
            }
            
            wp_send_json_success(array(
                'analysis' => $analysis,
                'message' => 'Análise concluída com sucesso!'
            ));
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erro no processamento PDF: ' . $e->getMessage(), array(
                    'user_id' => get_current_user_id(),
                    'request_id' => $request_id ?? 'unknown'
                ));
            }
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'PDF_PROCESS_ERROR'
            ));
        }
    }
	
	public function get_fresh_nonce() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Usuário não logado');
            return;
        }
        
        $fresh_nonce = wp_create_nonce('revisor_ptd_nonce');
        
        wp_send_json_success(array(
            'nonce' => $fresh_nonce,
            'user_id' => get_current_user_id(),
            'timestamp' => time()
        ));
    }
    
    private function init_dependencies() {
        require_once REVISOR_PTD_PLUGIN_DIR . 'includes/class-document-handler.php';
        require_once REVISOR_PTD_PLUGIN_DIR . 'includes/class-logger.php';
        require_once REVISOR_PTD_PLUGIN_DIR . 'includes/class-api-handler.php';
        require_once REVISOR_PTD_PLUGIN_DIR . 'includes/class-chunked-processor.php';
        
        $this->document_handler = new RevisorPTDDocumentHandler();
        $this->logger = new RevisorPTDLogger();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_shortcode('revisor_ptd_form', array($this, 'display_form_shortcode'));
        
        $this->init_ajax_hooks();
        add_action('init', array($this, 'increase_upload_limits'));
    }
    
    private function init_ajax_hooks() {
        // AJAX para administração
        add_action('wp_ajax_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_add_base_document', array($this, 'add_base_document'));
        add_action('wp_ajax_edit_base_document', array($this, 'edit_base_document'));
        add_action('wp_ajax_delete_base_document', array($this, 'delete_base_document'));
        add_action('wp_ajax_get_logs', array($this, 'get_logs'));
        add_action('wp_ajax_clear_logs', array($this, 'clear_logs'));
        add_action('wp_ajax_get_fresh_nonce', array($this, 'get_fresh_nonce'));
		
        // AJAX para usuários logados
        add_action('wp_ajax_process_ptd_analysis', array($this, 'process_ptd_analysis'));
        add_action('wp_ajax_process_ptd_with_pdf_texts', array($this, 'process_ptd_with_pdf_texts'));
        add_action('wp_ajax_serve_pdf_content', array($this, 'serve_pdf_content'));
    }
    
    private function init_default_settings() {
        if (empty($this->settings)) {
            $default_settings = array(
                'api_key' => '',
                'api_endpoint' => '',
                'analysis_prompt' => $this->get_default_prompt(),
                'max_tokens' => 4000,
                'temperature' => 0.3
            );
            
            update_option('revisor_ptd_settings', $default_settings);
            $this->settings = $default_settings;
        }
    }

    private function get_base_document_for_topic($document_name) {
        $documents = get_option('revisor_ptd_base_documents', array());
        
        foreach ($documents as $doc) {
            if (strpos($doc['filename'], $document_name) !== false) {
                $content = $this->extract_document_content($doc['filepath']);
                
                if (is_string($content) && !empty($content)) {
                    return substr($content, 0, 10000);
                } else {
                    if ($this->logger) {
                        $this->logger->log("Não foi possível extrair texto do documento: {$document_name}", 'warning');
                    }
                    return "Documento base '{$document_name}' não pôde ser lido.";
                }
            }
        }
        
        if ($this->logger) {
            $this->logger->log("Documento base não encontrado: {$document_name}", 'warning');
        }
        return "Documento base '{$document_name}' não encontrado no sistema.";
    }
    
    private function get_default_prompt() {
        return "Você é um especialista em educação profissional com expertise no Modelo Pedagógico Senac (MPS).

Analise o Plano de Trabalho Docente (PTD) fornecido e responda EXATAMENTE seguindo a estrutura solicitada.

FORMATO OBRIGATÓRIO DA RESPOSTA:

Primeiro, extraia e informe:
Nome do Curso: [extrair do PTD]
Unidade Curricular: [extrair do PTD]  
Carga Horária: [extrair do PTD]

Em seguida, analise cada tópico numerado respondendo às perguntas específicas:

1. Coerência entre competência, situação de aprendizagem e indicadores
- A competência está claramente relacionada à situação de aprendizagem e aos indicadores?
- Os fazeres previstos nos indicadores são efetivamente contemplados nas atividades propostas?

[Sua análise aqui, respondendo cada pergunta em um parágrafo]

2. Estrutura e clareza das atividades  
- As atividades estão descritas de forma clara e detalhada?
- Há uma sequência lógica que contempla contextualização, desenvolvimento e conclusão da situação de aprendizagem?
- As etapas das atividades são compreensíveis e executáveis?

[Sua análise aqui, respondendo cada pergunta em um parágrafo]

3. Articulação entre conhecimentos, habilidades e atitudes
- As atividades permitem mobilizar de forma integrada os elementos da competência (saberes, fazeres e atitudes/valores)?
- As propostas articulam teoria e prática de forma equilibrada?
- O ciclo ação-reflexão-ação está contemplado?

[Sua análise aqui, respondendo cada pergunta em um parágrafo]

4. Metodologias ativas e protagonismo do aluno
- As atividades propostas utilizam metodologias ativas?
- Promovem o protagonismo do estudante no processo de aprendizagem?
- Há variedade entre atividades individuais e coletivas?

[Sua análise aqui, respondendo cada pergunta em um parágrafo]

5. Uso de tecnologias
- As tecnologias digitais são utilizadas com intencionalidade pedagógica?

[Sua análise aqui, respondendo a pergunta em um parágrafo]

6. Marcas formativas
- As atividades contribuem para o desenvolvimento das Marcas Formativas?

[Sua análise aqui, respondendo a pergunta em um parágrafo]

7. Avaliação da aprendizagem
- Há diversidade de instrumentos e procedimentos avaliativos?
- As avaliações permitem identificar dificuldades dos alunos?
- O planejamento contempla avaliações diagnóstica, formativa e somativa?
- Estão previstos momentos de feedback aos alunos?

[Sua análise aqui, respondendo cada pergunta em um parágrafo]

8. Acessibilidade e inclusão
- O plano contempla adaptações para alunos PcD's (Pessoa com Deficiência)?
- Há recursos de acessibilidade digital, física ou pedagógica previstos?
- As atividades permitem diferentes formas de participação e expressão dos alunos?

[Sua análise aqui, respondendo cada pergunta em um parágrafo - se não há alunos PcD mencionados no contexto, adapte as respostas adequadamente]

INSTRUÇÕES IMPORTANTES:
- Responda CADA pergunta de forma clara e objetiva
- Use os documentos base fornecidos como referência
- Forneça sugestões práticas de melhoria quando identificar oportunidades
- Mantenha foco no Modelo Pedagógico Senac (MPS)
- Não use formatação markdown (asteriscos, hashtags, etc.)

CONTEXTO DO USUÁRIO:
{contexto_usuario}

DOCUMENTOS BASE PARA REFERÊNCIA:
{documentos_base}

PTD PARA ANÁLISE:
{ptd_content}

Analise o PTD seguindo rigorosamente a estrutura numerada de 1 a 8 com as respectivas perguntas.";
    }
    
    public function increase_upload_limits() {
        @ini_set('upload_max_filesize', '64M');
        @ini_set('post_max_size', '64M');
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '256M');
    }
    
    public function activate() {
        $this->create_directories();
        $this->create_tables();
        $this->init_default_settings();
        
        if ($this->logger) {
            $this->logger->log('Plugin ativado com sucesso', 'info');
        }
    }
    
    public function deactivate() {
        if ($this->logger) {
            $this->logger->log('Plugin desativado', 'info');
        }
    }
    
    private function create_directories() {
        $directories = array(
            REVISOR_PTD_PLUGIN_DIR . 'uploads/',
            REVISOR_PTD_PLUGIN_DIR . 'uploads/base-documents/',
            REVISOR_PTD_PLUGIN_DIR . 'uploads/ptd-files/',
            REVISOR_PTD_PLUGIN_DIR . 'logs/'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                file_put_contents($dir . '.htaccess', 'deny from all');
            }
        }
    }
    
    private function create_tables() {
        // Por enquanto usamos wp_options
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Revisor PTD com IA',
            'Revisor PTD',
            'manage_options',
            'revisor-ptd',
            array($this, 'display_settings_page'),
            'dashicons-text-page',
            30
        );
        
        add_submenu_page(
            'revisor-ptd',
            'Configurações',
            'Configurações',
            'manage_options',
            'revisor-ptd',
            array($this, 'display_settings_page')
        );
        
        add_submenu_page(
            'revisor-ptd',
            'Documentos Base',
            'Documentos Base',
            'manage_options',
            'revisor-ptd-documents',
            array($this, 'display_documents_page')
        );
        
        add_submenu_page(
            'revisor-ptd',
            'Logs do Sistema',
            'Logs',
            'manage_options',
            'revisor-ptd-logs',
            array($this, 'display_logs_page')
        );
    }
    
    public function register_settings() {
        register_setting('revisor_ptd_settings_group', 'revisor_ptd_settings');
        register_setting('revisor_ptd_documents_group', 'revisor_ptd_base_documents');
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'revisor-ptd') === false) {
            return;
        }
        
        wp_enqueue_style(
            'revisor-ptd-admin-css',
            REVISOR_PTD_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            $this->version
        );
        
        wp_enqueue_script(
            'revisor-ptd-admin-js',
            REVISOR_PTD_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            $this->version,
            true
        );
        
        wp_localize_script('revisor-ptd-admin-js', 'revisorPtdAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('revisor_ptd_nonce')
        ));
    }
    
    public function enqueue_frontend_scripts() {
        global $post;
        
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'revisor_ptd_form')) {
            return;
        }
        
        wp_enqueue_style(
            'revisor-ptd-frontend-css',
            REVISOR_PTD_PLUGIN_URL . 'assets/css/frontend-style.css',
            array(),
            $this->version
        );
        
        wp_enqueue_script(
            'pdfjs',
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
            array(),
            '3.11.174',
            true
        );
        
        wp_enqueue_script(
            'revisor-ptd-frontend-js',
            REVISOR_PTD_PLUGIN_URL . 'assets/js/frontend-script.js',
            array('jquery', 'pdfjs'),
            $this->version,
            true
        );
        
        wp_localize_script('revisor-ptd-frontend-js', 'revisorPtdAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('revisor_ptd_nonce'),
            'user_id' => get_current_user_id(),
            'timestamp' => time(),
            'is_user_logged_in' => is_user_logged_in(),
            'current_user_can_upload' => current_user_can('upload_files')
        ));
    }
    
    public function display_settings_page() {
        $settings = get_option('revisor_ptd_settings', array());
        include REVISOR_PTD_PLUGIN_DIR . 'admin/pages/settings.php';
    }
    
    public function display_documents_page() {
        $documents = get_option('revisor_ptd_base_documents', array());
        include REVISOR_PTD_PLUGIN_DIR . 'admin/pages/documents.php';
    }
    
    public function display_logs_page() {
        include REVISOR_PTD_PLUGIN_DIR . 'admin/pages/logs.php';
    }
    
    public function display_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Revisar Plano de Trabalho Docente com IA'
        ), $atts);
        
        ob_start();
        include REVISOR_PTD_PLUGIN_DIR . 'public/form-template.php';
        return ob_get_clean();
    }
    
    public function test_api_connection() {
        check_ajax_referer('revisor_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        try {
            $api_handler = new RevisorPTDAPIHandler($this->settings);
            $result = $api_handler->test_connection();
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Erro: ' . $e->getMessage());
        }
    }
    
    public function add_base_document() {
        check_ajax_referer('revisor_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        try {
            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Arquivo não foi enviado corretamente');
            }
            
            $title = sanitize_text_field($_POST['document_title']);
            $description = sanitize_textarea_field($_POST['document_description']);
            
            if (empty($title) || empty($description)) {
                throw new Exception('Título e descrição são obrigatórios');
            }
            
            $upload_result = $this->document_handler->upload_base_document($_FILES['document_file']);
            
            if (!$upload_result['success']) {
                throw new Exception($upload_result['message']);
            }
            
            $documents = get_option('revisor_ptd_base_documents', array());
            $document_id = uniqid('doc_');
            
            $documents[$document_id] = array(
                'title' => $title,
                'description' => $description,
                'filename' => $upload_result['filename'],
                'filepath' => $upload_result['filepath'],
                'created' => current_time('mysql')
            );
            
            update_option('revisor_ptd_base_documents', $documents);
            
            if ($this->logger) {
                $this->logger->log("Documento base adicionado: {$title}", 'info');
            }
            
            wp_send_json_success(array(
                'message' => 'Documento adicionado com sucesso',
                'document_id' => $document_id,
                'document' => $documents[$document_id]
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function edit_base_document() {
        check_ajax_referer('revisor_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        try {
            $document_id = sanitize_text_field($_POST['document_id']);
            $title = sanitize_text_field($_POST['document_title']);
            $description = sanitize_textarea_field($_POST['document_description']);
            
            $documents = get_option('revisor_ptd_base_documents', array());
            
            if (!isset($documents[$document_id])) {
                throw new Exception('Documento não encontrado');
            }
            
            $documents[$document_id]['title'] = $title;
            $documents[$document_id]['description'] = $description;
            $documents[$document_id]['updated'] = current_time('mysql');
            
            update_option('revisor_ptd_base_documents', $documents);
            
            if ($this->logger) {
                $this->logger->log("Documento base editado: {$title}", 'info');
            }
            
            wp_send_json_success('Documento atualizado com sucesso');
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function delete_base_document() {
        check_ajax_referer('revisor_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        try {
            $document_id = sanitize_text_field($_POST['document_id']);
            $documents = get_option('revisor_ptd_base_documents', array());
            
            if (!isset($documents[$document_id])) {
                throw new Exception('Documento não encontrado');
            }
            
            $filepath = $documents[$document_id]['filepath'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            $title = $documents[$document_id]['title'];
            unset($documents[$document_id]);
            
            update_option('revisor_ptd_base_documents', $documents);
            
            if ($this->logger) {
                $this->logger->log("Documento base removido: {$title}", 'info');
            }
            
            wp_send_json_success('Documento removido com sucesso');
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Processa análise do PTD
     */
    public function process_ptd_analysis() {
        if (!$this->verify_nonce_secure('revisor_ptd_nonce')) {
            wp_send_json_error(array(
                'message' => 'Sessão expirada. Recarregue a página e tente novamente.',
                'code' => 'NONCE_INVALID',
                'reload_required' => true
            ));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'Você precisa estar logado para usar esta funcionalidade.',
                'code' => 'NOT_LOGGED_IN',
                'reload_required' => false
            ));
            return;
        }
        
        if (!current_user_can('read')) {
            wp_send_json_error(array(
                'message' => 'Você não tem permissão para usar esta funcionalidade.',
                'code' => 'INSUFFICIENT_PERMISSIONS',
                'reload_required' => false
            ));
            return;
        }
        
        nocache_headers();
        
        try {
            if ($this->logger) {
                $this->logger->log('Análise PTD iniciada por usuário: ' . get_current_user_id(), 'info');
            }
            
            // Validação APENAS do PTD
            if (!isset($_FILES['ptd_file']) || $_FILES['ptd_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Arquivo PTD é obrigatório');
            }
            
            // Processa upload APENAS do PTD
            $ptd_result = $this->document_handler->upload_ptd_file($_FILES['ptd_file']);
            if (!$ptd_result['success']) {
                throw new Exception($ptd_result['message']);
            }
            
            // Coleta dados do formulário
            $form_data = $this->collect_form_data();
            
            // Extrai texto APENAS do PTD
            $ptd_text = $this->document_handler->extract_text_from_file($ptd_result['filepath']);
            
            // Se há PDF, retorna para processamento no frontend
            if (is_array($ptd_text)) {
                $pdf_paths = array();
                $pdf_paths['ptd_path'] = $ptd_text['url'];
                
                $request_id = 'ptd_' . get_current_user_id() . '_' . time() . '_' . wp_generate_password(8, false);
                
                set_transient('revisor_ptd_request_' . $request_id, array(
                    'user_id' => get_current_user_id(),
                    'form_data' => $form_data,
                    'ptd_text' => is_string($ptd_text) ? $ptd_text : '',
                    'timestamp' => time()
                ), 1800);
                
                wp_send_json_success(array(
                    'pdf_processing_required' => true,
                    'pdf_paths' => $pdf_paths,
                    'request_id' => $request_id,
                    'total_chunks' => 8
                ));
                return;
            }
            
            // Processa análise diretamente
            $analysis = $this->perform_analysis($ptd_text, '', $form_data);
            
            if ($this->logger) {
                $this->logger->log('Análise PTD concluída para usuário: ' . get_current_user_id(), 'info');
            }
            
            wp_send_json_success(array(
                'analysis' => $analysis,
                'message' => 'Análise concluída com sucesso!'
            ));
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erro na análise PTD: ' . $e->getMessage(), array(
                    'user_id' => get_current_user_id(),
                    'file' => __FILE__,
                    'line' => __LINE__
                ));
            }
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'ANALYSIS_ERROR'
            ));
        }
    }
    
    public function serve_pdf_content() {
        if (isset($_GET['direct']) && isset($_GET['file'])) {
            if (!is_user_logged_in()) {
                wp_die('Acesso negado. Faça login para continuar.');
            }
            
            $file_path = urldecode($_GET['file']);
            $this->serve_file_direct($file_path);
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'Você precisa estar logado para acessar este recurso.',
                'code' => 'NOT_LOGGED_IN'
            ));
            return;
        }
        
        try {
            if (!isset($_POST['file_path'])) {
                throw new Exception('Caminho do arquivo não fornecido');
            }
            
            $file_path = sanitize_text_field($_POST['file_path']);
            $abs_file_path = $this->resolve_file_path($file_path);
            
            if (!$abs_file_path || !$this->is_file_access_allowed($abs_file_path)) {
                throw new Exception('Arquivo não encontrado ou acesso negado');
            }
            
            $content = file_get_contents($abs_file_path);
            if ($content === false) {
                throw new Exception('Falha ao ler arquivo');
            }
            
            wp_send_json_success(array(
                'content' => base64_encode($content),
                'filename' => basename($abs_file_path),
                'filesize' => strlen($content)
            ));
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erro em serve_pdf_content: ' . $e->getMessage());
            }
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'FILE_ACCESS_ERROR'
            ));
        }
    }
    
    private function is_file_access_allowed($file_path) {
        $real_path = realpath($file_path);
        if (!$real_path || !is_readable($real_path)) {
            return false;
        }
        
        $allowed_dirs = array(
            realpath(REVISOR_PTD_PLUGIN_DIR . 'uploads/'),
            realpath(wp_upload_dir()['basedir'])
        );
        
        foreach ($allowed_dirs as $allowed_dir) {
            if ($allowed_dir && strpos($real_path, $allowed_dir) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    private function serve_file_direct($file_path) {
        if (!$this->is_file_access_allowed($file_path)) {
            wp_die('Acesso não autorizado.');
        }
        
        $real_file_path = realpath($file_path);
        $mime = mime_content_type($real_file_path);
        
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real_file_path));
        header('Content-Disposition: inline; filename="' . basename($real_file_path) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        
        readfile($real_file_path);
        exit;
    }

    private function resolve_file_path($file_path) {
        if ($this->logger) {
            $this->logger->log('Resolvendo caminho: ' . $file_path, 'debug');
        }
        
        $file_path = str_replace(array('../', '..\\', '..'), '', $file_path);
        $file_path = str_replace('\\', '/', $file_path);
        
        $estrategias = array(
            $file_path,
            $this->url_to_path($file_path),
            REVISOR_PTD_PLUGIN_DIR . ltrim($file_path, '/'),
            REVISOR_PTD_PLUGIN_DIR . 'uploads/ptd-files/' . basename($file_path),
            REVISOR_PTD_PLUGIN_DIR . 'uploads/base-documents/' . basename($file_path),
            wp_upload_dir()['basedir'] . '/' . basename($file_path)
        );
        
        foreach ($estrategias as $caminho) {
            if ($caminho && file_exists($caminho)) {
                $real_path = realpath($caminho);
                if ($real_path) {
                    if ($this->logger) {
                        $this->logger->log('Arquivo encontrado em: ' . $caminho, 'debug');
                    }
                    return $real_path;
                }
            }
        }
        
        if ($this->logger) {
            $this->logger->log('Arquivo não encontrado em nenhuma estratégia', 'warning');
        }
        return false;
    }

    private function url_to_path($url) {
        if (!$url || strpos($url, 'http') !== 0) {
            return $url;
        }
        
        $url = strtok($url, '?');
        
        $site_url = site_url();
        $wp_content_url = WP_CONTENT_URL;
        $plugin_url = REVISOR_PTD_PLUGIN_URL;
        
        if (strpos($url, $plugin_url) === 0) {
            return str_replace($plugin_url, REVISOR_PTD_PLUGIN_DIR, $url);
        }
        
        if (strpos($url, $wp_content_url) === 0) {
            return str_replace($wp_content_url, WP_CONTENT_DIR, $url);
        }
        
        if (strpos($url, $site_url) === 0) {
            $relative_path = str_replace($site_url, '', $url);
            return ABSPATH . ltrim($relative_path, '/');
        }
        
        return null;
    }
    
    private function collect_form_data() {
        $form_fields = array(
            'student_count' => 'Quantidade de alunos',
            'special_needs' => 'Alunos com necessidades especiais',
            'age_range' => 'Faixa etária',
            'education_level' => 'Nível de escolaridade',
            'prior_experience' => 'Experiência prévia',
            'learning_environments' => 'Ambientes de aprendizagem',
            'tech_access_unit' => 'Acesso à tecnologia na unidade',
            'tech_access_outside' => 'Acesso à tecnologia fora da unidade',
            'diagnostic_assessment' => 'Avaliação diagnóstica',
            'learning_difficulties' => 'Dificuldades de aprendizagem'
        );
        
        $form_data = array();
        
        foreach ($form_fields as $field => $label) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                
                if (is_array($value)) {
                    $form_data[$label] = implode(', ', array_map('sanitize_text_field', $value));
                } else {
                    $form_data[$label] = sanitize_textarea_field($value);
                }
            }
        }
        
        return $form_data;
    }
    
    /**
     * Realiza análise do PTD
     */
    private function perform_analysis($ptd_text, $pcn_text, $form_data) {
        try {
            // Verifica tamanho apenas do PTD
            $total_length = strlen($ptd_text);
            $use_chunked = $total_length > 30000;
            
            if ($this->logger) {
                $this->logger->log('Iniciando análise', 'info', array(
                    'ptd_length' => strlen($ptd_text),
                    'total_length' => $total_length,
                    'use_chunked' => $use_chunked
                ));
            }
            
            if ($use_chunked) {
                $chunked_processor = new RevisorPTDChunkedProcessor($this->settings);
                $result = $chunked_processor->process_chunked_analysis($ptd_text, '', $form_data); // PCN vazio
                
                if (!$result['success']) {
                    throw new Exception($result['error']);
                }
                
                return $result['content'];
            } else {
                $user_context = $this->build_user_context($form_data);
                $base_documents = $this->get_base_documents_content();
                $prompt = $this->build_final_prompt($user_context, $base_documents, $ptd_text, ''); // PCN vazio
                
                $api_handler = new RevisorPTDAPIHandler($this->settings);
                $result = $api_handler->send_analysis_request($prompt);
                
                if (!$result['success']) {
                    throw new Exception($result['message']);
                }
                
                return $result['content'];
            }
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erro na análise: ' . $e->getMessage());
            }
            throw $e;
        }
    }
    
    private function build_user_context($form_data) {
        $context = "INFORMAÇÕES DA TURMA:\n";
        
        foreach ($form_data as $label => $value) {
            if (!empty($value)) {
                $context .= "- {$label}: {$value}\n";
            }
        }
        
        return $context;
    }
    
    private function get_base_documents_content() {
        $documents = get_option('revisor_ptd_base_documents', array());
        $content = '';
        
        foreach ($documents as $doc) {
            $doc_content = $this->document_handler->extract_text_from_file($doc['filepath']);
            
            if (is_string($doc_content) && !empty($doc_content)) {
                $content .= "\n--- {$doc['title']} ---\n";
                $content .= "{$doc['description']}\n\n";
                $content .= substr($doc_content, 0, 3000) . "\n\n";
            }
        }
        
        return $content;
    }
    
    /**
     * Constrói prompt final
     */
    private function build_final_prompt($user_context, $base_documents, $ptd_text, $pcn_text) {
        $prompt = $this->settings['analysis_prompt'];
        
        $user_context = $this->clean_text_for_prompt($user_context);
        $base_documents = $this->clean_text_for_prompt($base_documents);
        $ptd_text = $this->clean_text_for_prompt($ptd_text);
        
        $prompt = str_replace('{contexto_usuario}', $user_context, $prompt);
        $prompt = str_replace('{documentos_base}', $base_documents, $prompt);
        $prompt = str_replace('{ptd_content}', "PTD:\n" . $ptd_text, $prompt);
        
        // Remove referências ao PCN do prompt
        $prompt = str_replace('{pcn_content}', '', $prompt);
        
        $prompt = $this->clean_text_for_prompt($prompt);
        
        if ($this->logger) {
            $this->logger->log('Prompt construído', 'debug', array(
                'length' => strlen($prompt),
                'estimated_tokens' => ceil(strlen($prompt) / 4)
            ));
        }
        
        return $prompt;
    }
    
    private function clean_text_for_prompt($text) {
        if (empty($text)) {
            return '';
        }
        
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = preg_replace('/\r\n|\r/', '\n', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', '\n\n', $text);
        
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        return trim($text);
    }
    
    public function get_logs() {
        check_ajax_referer('revisor_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        $logs = $this->logger->get_logs();
        wp_send_json_success($logs);
    }
    
    public function clear_logs() {
        check_ajax_referer('revisor_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        $this->logger->clear_logs();
        wp_send_json_success('Logs limpos com sucesso');
    }
}

// Inicializa o plugin
add_action('plugins_loaded', function() {
    new RevisorPTDComIA();
});