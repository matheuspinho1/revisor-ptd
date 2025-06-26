<?php
/**
 * Manipulador de Documentos - VERSÃO CORRIGIDA PARA PDFs
 * 
 * Classe responsável por:
 * - Upload e validação de arquivos
 * - Extração de texto de diferentes formatos
 * - Gerenciamento de arquivos do sistema
 */
if (!defined('ABSPATH')) {
    exit;
}

class RevisorPTDDocumentHandler {
    
    /**
     * @var array Tipos de arquivo permitidos
     */
    private $allowed_types = array(
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain'
    );
    
    /**
     * @var int Tamanho máximo de arquivo em bytes (32MB)
     */
    private $max_file_size = 33554432;
    
    /**
     * Upload de documento base
     * 
     * @param array $file Array do arquivo $_FILES
     * @return array Resultado do upload
     */
    public function upload_base_document($file) {
        try {
            $this->validate_file($file);
            
            $upload_dir = REVISOR_PTD_PLUGIN_DIR . 'uploads/base-documents/';
            
            // Garante que o diretório existe
            if (!file_exists($upload_dir)) {
                if (!wp_mkdir_p($upload_dir)) {
                    throw new Exception('Não foi possível criar o diretório de upload: ' . $upload_dir);
                }
                chmod($upload_dir, 0755);
                file_put_contents($upload_dir . '.htaccess', 'deny from all');
            }
            
            $filename = $this->generate_unique_filename($file['name']);
            $filepath = $upload_dir . $filename;
            
            // Verifica se o diretório é gravável
            if (!is_writable($upload_dir)) {
                throw new Exception('Diretório de upload não tem permissão de escrita: ' . $upload_dir);
            }
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                $error = error_get_last();
                throw new Exception('Falha ao mover arquivo: ' . ($error ? $error['message'] : 'Erro desconhecido'));
            }
            
            // Define permissões seguras
            chmod($filepath, 0644);
            
            return array(
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'filesize' => filesize($filepath)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Upload de arquivo PTD
     * 
     * @param array $file Array do arquivo $_FILES
     * @return array Resultado do upload
     */
    public function upload_ptd_file($file) {
        try {
            $this->validate_file($file);
            
            $upload_dir = REVISOR_PTD_PLUGIN_DIR . 'uploads/ptd-files/';
            
            // Garante que o diretório existe
            if (!file_exists($upload_dir)) {
                if (!wp_mkdir_p($upload_dir)) {
                    throw new Exception('Não foi possível criar o diretório de upload: ' . $upload_dir);
                }
                chmod($upload_dir, 0755);
                file_put_contents($upload_dir . '.htaccess', 'deny from all');
            }
            
            $filename = 'ptd_' . time() . '_' . $this->sanitize_filename($file['name']);
            $filepath = $upload_dir . $filename;
            
            // Verifica se o diretório é gravável
            if (!is_writable($upload_dir)) {
                throw new Exception('Diretório de upload não tem permissão de escrita: ' . $upload_dir);
            }
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                $error = error_get_last();
                throw new Exception('Falha ao mover arquivo PTD: ' . ($error ? $error['message'] : 'Erro desconhecido'));
            }
            
            chmod($filepath, 0644);
            
            return array(
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => $this->get_file_url($filepath)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Upload de arquivo PCN
     * 
     * @param array $file Array do arquivo $_FILES
     * @return array Resultado do upload
     */
    public function upload_pcn_file($file) {
        try {
            $this->validate_file($file);
            
            $upload_dir = REVISOR_PTD_PLUGIN_DIR . 'uploads/pcn-files/';
            
            // Garante que o diretório existe
            if (!file_exists($upload_dir)) {
                if (!wp_mkdir_p($upload_dir)) {
                    throw new Exception('Não foi possível criar o diretório de upload: ' . $upload_dir);
                }
                chmod($upload_dir, 0755);
                file_put_contents($upload_dir . '.htaccess', 'deny from all');
            }
            
            $filename = 'pcn_' . time() . '_' . $this->sanitize_filename($file['name']);
            $filepath = $upload_dir . $filename;
            
            // Verifica se o diretório é gravável
            if (!is_writable($upload_dir)) {
                throw new Exception('Diretório de upload não tem permissão de escrita: ' . $upload_dir);
            }
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                $error = error_get_last();
                throw new Exception('Falha ao mover arquivo PCN: ' . ($error ? $error['message'] : 'Erro desconhecido'));
            }
            
            chmod($filepath, 0644);
            
            return array(
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => $this->get_file_url($filepath)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Valida arquivo enviado
     * 
     * @param array $file Array do arquivo $_FILES
     * @throws Exception Se arquivo inválido
     */
    private function validate_file($file) {
        // Verifica se houve erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->get_upload_error_message($file['error']));
        }
        
        // Verifica se o arquivo existe
        if (!file_exists($file['tmp_name'])) {
            throw new Exception('Arquivo temporário não encontrado');
        }
        
        // Verifica tamanho
        if ($file['size'] > $this->max_file_size) {
            throw new Exception('Arquivo muito grande. Tamanho máximo: 32MB');
        }
        
        if ($file['size'] <= 0) {
            throw new Exception('Arquivo vazio ou corrompido');
        }
        
        // Verifica extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($extension, $this->allowed_types)) {
            throw new Exception('Tipo de arquivo não permitido. Use: PDF, DOC, DOCX ou TXT');
        }
        
        // Verifica MIME type se disponível
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($mime_type && $mime_type !== $this->allowed_types[$extension]) {
                // Permite algumas variações comuns
                $allowed_variations = array(
                    'application/vnd.ms-office',
                    'application/zip', // DOCX pode ser detectado como ZIP
                    'text/x-c', // Alguns TXT podem ser detectados assim
                    'application/octet-stream' // Fallback genérico
                );
                
                if (!in_array($mime_type, $allowed_variations)) {
                    error_log("MIME type mismatch: expected {$this->allowed_types[$extension]}, got {$mime_type} for file {$file['name']}");
                    // Apenas avisa, não bloqueia por causa de variações de servidor
                }
            }
        }
        
        // Verifica nome do arquivo
        if (empty($file['name']) || strlen($file['name']) > 255) {
            throw new Exception('Nome do arquivo inválido');
        }
    }
    
    /**
     * Gera nome único para arquivo
     * 
     * @param string $original_name Nome original
     * @return string Nome único
     */
    private function generate_unique_filename($original_name) {
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $basename = pathinfo($original_name, PATHINFO_FILENAME);
        $basename = $this->sanitize_filename($basename);
        
        return $basename . '_' . uniqid() . '.' . $extension;
    }
    
    /**
     * Sanitiza nome do arquivo
     * 
     * @param string $filename Nome do arquivo
     * @return string Nome sanitizado
     */
    private function sanitize_filename($filename) {
        // Remove caracteres especiais e espaços
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        
        return substr($filename, 0, 100); // Limita tamanho
    }
    
    /**
     * Converte caminho do arquivo para URL - VERSÃO CORRIGIDA
     * 
     * @param string $filepath Caminho do arquivo
     * @return string URL do arquivo
     */
    private function get_file_url($filepath) {
        // Normaliza barras
        $filepath = str_replace('\\', '/', $filepath);
        $base_dir = str_replace('\\', '/', REVISOR_PTD_PLUGIN_DIR);
        
        // Verifica se o arquivo está no diretório do plugin
        if (strpos($filepath, $base_dir) !== false) {
            $relative_path = str_replace($base_dir, '', $filepath);
            return REVISOR_PTD_PLUGIN_URL . $relative_path;
        }
        
        // Método alternativo: usando upload do WordPress
        $upload_dir = wp_upload_dir();
        $upload_basedir = str_replace('\\', '/', $upload_dir['basedir']);
        
        if (strpos($filepath, $upload_basedir) !== false) {
            return str_replace($upload_basedir, $upload_dir['baseurl'], $filepath);
        }
        
        // Se nada funcionou, retorna URL via AJAX como fallback
        return admin_url('admin-ajax.php') . '?action=serve_pdf_content&direct=1&file=' . urlencode($filepath);
    }
    
    /**
     * Extrai texto de arquivo - VERSÃO CORRIGIDA PARA PDF
     * 
     * @param string $filepath Caminho do arquivo
     * @return string|array Texto extraído ou array com informações do PDF
     */
    public function extract_text_from_file($filepath) {
        if (!file_exists($filepath)) {
            return 'Arquivo não encontrado';
        }
        
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'txt':
                return $this->extract_from_txt($filepath);
                
            case 'pdf':
                // Para PDFs, retorna informações para processamento no frontend
                return array(
                    'type' => 'pdf',
                    'url' => $this->get_file_url($filepath),
                    'path' => $filepath
                );
                
            case 'doc':
            case 'docx':
                return $this->extract_from_docx($filepath);
                
            default:
                return 'Formato não suportado';
        }
    }
    
    /**
     * Extrai texto de arquivo TXT
     * 
     * @param string $filepath Caminho do arquivo
     * @return string Texto extraído
     */
    private function extract_from_txt($filepath) {
        $content = file_get_contents($filepath);
        
        if ($content === false) {
            return 'Erro ao ler arquivo TXT';
        }
        
        // Converte encoding se necessário
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        }
        
        return $this->clean_text($content);
    }
    
    /**
     * Extrai texto de arquivo DOC/DOCX
     * 
     * @param string $filepath Caminho do arquivo
     * @return string Texto extraído
     */
    private function extract_from_docx($filepath) {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        
        if ($extension === 'docx') {
            return $this->extract_from_docx_zip($filepath);
        } else {
            return $this->extract_from_doc_binary($filepath);
        }
    }
    
    /**
     * Extrai texto de DOCX (formato ZIP)
     * 
     * @param string $filepath Caminho do arquivo
     * @return string Texto extraído
     */
    private function extract_from_docx_zip($filepath) {
        if (!class_exists('ZipArchive')) {
            return 'Extensão ZipArchive não disponível para processar arquivos DOCX';
        }
        
        $zip = new ZipArchive();
        
        if ($zip->open($filepath) !== true) {
            return 'Erro ao abrir arquivo DOCX';
        }
        
        // Documento principal está em word/document.xml
        $content = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($content === false) {
            return 'Erro ao extrair conteúdo do DOCX';
        }
        
        // Remove tags XML e limpa texto
        $text = strip_tags($content);
        $text = html_entity_decode($text);
        
        return $this->clean_text($text);
    }
    
    /**
     * Extrai texto de DOC (formato binário)
     * 
     * @param string $filepath Caminho do arquivo
     * @return string Texto extraído
     */
    private function extract_from_doc_binary($filepath) {
        $content = file_get_contents($filepath);
        
        if ($content === false) {
            return 'Erro ao ler arquivo DOC';
        }
        
        // Remove caracteres binários e mantém apenas texto legível
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D\xC0-\xFF]/', ' ', $content);
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Verifica se conseguimos extrair texto significativo
        if (strlen(trim($text)) < 50) {
            return 'Não foi possível extrair texto significativo do arquivo DOC. Recomendamos converter para DOCX ou PDF.';
        }
        
        return $this->clean_text($text);
    }
    
    /**
     * Limpa e normaliza texto
     * 
     * @param string $text Texto a ser limpo
     * @return string Texto limpo
     */
    private function clean_text($text) {
        if (empty($text)) {
            return '';
        }
        
        // Remove caracteres de controle problemáticos (mas mantém quebras de linha)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normaliza diferentes tipos de quebra de linha
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        
        // Remove múltiplos espaços em branco (mas preserva quebras de linha)
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Remove múltiplas quebras de linha consecutivas
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Remove espaços no início e fim de cada linha
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $text = implode("\n", $lines);
        
        // Garante que é UTF-8 válido
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
            
            // Se ainda não é válido, remove caracteres problemáticos
            if (!mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8//IGNORE');
            }
        }
        
        return trim($text);
    }
    
    /**
     * Retorna mensagem de erro de upload
     * 
     * @param int $error_code Código de erro
     * @return string Mensagem de erro
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'Arquivo excede o tamanho máximo permitido pelo servidor';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Arquivo excede o tamanho máximo do formulário';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload foi parcialmente concluído';
            case UPLOAD_ERR_NO_FILE:
                return 'Nenhum arquivo foi enviado';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Diretório temporário não encontrado';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Falha ao escrever arquivo no disco';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload interrompido por extensão PHP';
            default:
                return 'Erro desconhecido no upload';
        }
    }
    
    /**
     * Remove arquivo do sistema
     * 
     * @param string $filepath Caminho do arquivo
     * @return bool Sucesso da operação
     */
    public function delete_file($filepath) {
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return true;
    }
    
    /**
     * Obtém informações do arquivo
     * 
     * @param string $filepath Caminho do arquivo
     * @return array Informações do arquivo
     */
    public function get_file_info($filepath) {
        if (!file_exists($filepath)) {
            return array('error' => 'Arquivo não encontrado');
        }
        
        return array(
            'filename' => basename($filepath),
            'size' => filesize($filepath),
            'type' => mime_content_type($filepath),
            'extension' => pathinfo($filepath, PATHINFO_EXTENSION),
            'modified' => filemtime($filepath)
        );
    }
}