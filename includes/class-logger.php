<?php
/**
 * Sistema de Logs
 * 
 * Classe responsável por:
 * - Registro de eventos do sistema
 * - Gerenciamento de arquivos de log
 * - Controle de níveis de log
 */

if (!defined('ABSPATH')) {
    exit;
}

class RevisorPTDLogger {
    
    /**
     * @var string Diretório de logs
     */
    private $log_dir;
    
    /**
     * @var array Níveis de log disponíveis
     */
    private $log_levels = array(
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    );
    
    /**
     * @var int Nível mínimo de log a ser registrado
     */
    private $min_log_level = 1; // info
    
    /**
     * Construtor
     */
    public function __construct() {
        $this->log_dir = REVISOR_PTD_PLUGIN_DIR . 'logs/';
        $this->ensure_log_directory();
    }
    
    /**
     * Garante que o diretório de logs existe
     */
    private function ensure_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Protege diretório com .htaccess
            file_put_contents($this->log_dir . '.htaccess', 'Deny from all');
            
            // Adiciona index.php vazio para segurança
            file_put_contents($this->log_dir . 'index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Registra mensagem de log
     * 
     * @param string $message Mensagem a ser registrada
     * @param string $level Nível do log (debug, info, warning, error, critical)
     * @param array $context Contexto adicional (opcional)
     */
    public function log($message, $level = 'info', $context = array()) {
        // Verifica se o nível deve ser registrado
        if (!isset($this->log_levels[$level]) || $this->log_levels[$level] < $this->min_log_level) {
            return;
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $user_info = $this->get_user_info();
        $log_file = $this->get_log_file();
        
        // Monta linha do log
        $log_line = sprintf(
            "[%s] [%s] [%s] %s",
            $timestamp,
            strtoupper($level),
            $user_info,
            $message
        );
        
        // Adiciona contexto se fornecido
        if (!empty($context)) {
            $log_line .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        $log_line .= PHP_EOL;
        
        // Escreve no arquivo
        $result = file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Se falhou ao escrever no arquivo, registra no log do WordPress
        if ($result === false) {
            error_log("Revisor PTD Logger: Falha ao escrever no arquivo de log: {$log_file}");
        }
        
        // Para logs críticos, também registra no log do WordPress
        if ($level === 'critical' || $level === 'error') {
            error_log("Revisor PTD [{$level}]: {$message}");
        }
    }
    
    /**
     * Obtém informações do usuário atual
     * 
     * @return string Informações do usuário
     */
    private function get_user_info() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return "User:{$user->user_login}({$user->ID})";
        } else {
            $ip = $this->get_client_ip();
            return "Guest:{$ip}";
        }
    }
    
    /**
     * Obtém IP do cliente
     * 
     * @return string IP do cliente
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Obtém arquivo de log do mês atual
     * 
     * @return string Caminho do arquivo de log
     */
    private function get_log_file() {
        $filename = 'revisor-ptd-' . date('Y-m') . '.log';
        return $this->log_dir . $filename;
    }
    
    /**
     * Métodos de conveniência para diferentes níveis
     */
    public function debug($message, $context = array()) {
        $this->log($message, 'debug', $context);
    }
    
    public function info($message, $context = array()) {
        $this->log($message, 'info', $context);
    }
    
    public function warning($message, $context = array()) {
        $this->log($message, 'warning', $context);
    }
    
    public function error($message, $context = array()) {
        $this->log($message, 'error', $context);
    }
    
    public function critical($message, $context = array()) {
        $this->log($message, 'critical', $context);
    }
    
    /**
     * Obtém logs do sistema
     * 
     * @param int $limit Número máximo de linhas
     * @param string $level Filtrar por nível
     * @param string $date_from Data inicial (Y-m-d)
     * @param string $date_to Data final (Y-m-d)
     * @return array Array com as linhas de log
     */
    public function get_logs($limit = 100, $level = null, $date_from = null, $date_to = null) {
        $logs = array();
        $log_files = $this->get_log_files();
        
        // Processa arquivos de log (mais recente primeiro)
        rsort($log_files);
        
        foreach ($log_files as $log_file) {
            if (!file_exists($log_file)) {
                continue;
            }
            
            $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            
            // Processa linhas (mais recente primeiro)
            $lines = array_reverse($lines);
            
            foreach ($lines as $line) {
                // Aplica filtros
                if ($level && !$this->line_matches_level($line, $level)) {
                    continue;
                }
                
                if (($date_from || $date_to) && !$this->line_matches_date_range($line, $date_from, $date_to)) {
                    continue;
                }
                
                $logs[] = $line;
                
                // Para se atingiu o limite
                if (count($logs) >= $limit) {
                    break 2;
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Obtém lista de arquivos de log
     * 
     * @return array Lista de arquivos
     */
    private function get_log_files() {
        $pattern = $this->log_dir . 'revisor-ptd-*.log';
        return glob($pattern);
    }
    
    /**
     * Verifica se linha corresponde ao nível
     * 
     * @param string $line Linha do log
     * @param string $level Nível a verificar
     * @return bool
     */
    private function line_matches_level($line, $level) {
        return stripos($line, '[' . strtoupper($level) . ']') !== false;
    }
    
    /**
     * Verifica se linha está no intervalo de datas
     * 
     * @param string $line Linha do log
     * @param string $date_from Data inicial
     * @param string $date_to Data final
     * @return bool
     */
    private function line_matches_date_range($line, $date_from, $date_to) {
        // Extrai data da linha do log
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
            $log_date = $matches[1];
            
            if ($date_from && $log_date < $date_from) {
                return false;
            }
            
            if ($date_to && $log_date > $date_to) {
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Limpa logs antigos
     * 
     * @param int $keep_days Número de dias a manter
     * @return int Número de arquivos removidos
     */
    public function cleanup_old_logs($keep_days = 90) {
        $log_files = $this->get_log_files();
        $cutoff_time = time() - ($keep_days * 24 * 60 * 60);
        $removed = 0;
        
        foreach ($log_files as $log_file) {
            if (filemtime($log_file) < $cutoff_time) {
                if (unlink($log_file)) {
                    $removed++;
                }
            }
        }
        
        $this->info("Limpeza de logs concluída: {$removed} arquivos removidos");
        
        return $removed;
    }
    
    /**
     * Limpa todos os logs
     * 
     * @return bool Sucesso da operação
     */
    public function clear_logs() {
        $log_files = $this->get_log_files();
        $success = true;
        
        foreach ($log_files as $log_file) {
            if (!unlink($log_file)) {
                $success = false;
            }
        }
        
        if ($success) {
            $this->info('Todos os logs foram limpos');
        } else {
            $this->error('Falha ao limpar alguns arquivos de log');
        }
        
        return $success;
    }
    
    /**
     * Obtém estatísticas dos logs
     * 
     * @return array Estatísticas
     */
    public function get_log_stats() {
        $log_files = $this->get_log_files();
        $stats = array(
            'total_files' => 0,
            'total_size' => 0,
            'oldest_file' => null,
            'newest_file' => null,
            'level_counts' => array_fill_keys(array_keys($this->log_levels), 0)
        );
        
        foreach ($log_files as $log_file) {
            $stats['total_files']++;
            $stats['total_size'] += filesize($log_file);
            
            $mtime = filemtime($log_file);
            if (!$stats['oldest_file'] || $mtime < filemtime($stats['oldest_file'])) {
                $stats['oldest_file'] = $log_file;
            }
            if (!$stats['newest_file'] || $mtime > filemtime($stats['newest_file'])) {
                $stats['newest_file'] = $log_file;
            }
            
            // Conta níveis de log
            $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $line) {
                    foreach ($this->log_levels as $level => $priority) {
                        if (stripos($line, '[' . strtoupper($level) . ']') !== false) {
                            $stats['level_counts'][$level]++;
                            break;
                        }
                    }
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Define nível mínimo de log
     * 
     * @param string $level Nível mínimo
     */
    public function set_min_level($level) {
        if (isset($this->log_levels[$level])) {
            $this->min_log_level = $this->log_levels[$level];
        }
    }
    
    /**
     * Obtém nível mínimo atual
     * 
     * @return string Nível mínimo
     */
    public function get_min_level() {
        foreach ($this->log_levels as $level => $priority) {
            if ($priority === $this->min_log_level) {
                return $level;
            }
        }
        return 'info';
    }
}