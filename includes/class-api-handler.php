<?php
/**
 * Manipulador de API
 * 
 * Classe responsável por:
 * - Comunicação com APIs de IA
 * - Controle de rate limiting
 * - Formatação de requests/responses
 * - Tratamento de erros
 */

if (!defined('ABSPATH')) {
    exit;
}

class RevisorPTDAPIHandler {
    
    /**
     * @var array Configurações da API
     */
    private $settings;
    
    /**
     * @var RevisorPTDLogger Logger do sistema
     */
    private $logger;
    
    /**
     * @var int Timeout padrão para requisições (segundos)
     */
    private $default_timeout = 120;
    
    /**
     * @var int Número máximo de tentativas
     */
    private $max_retries = 3;
    
    /**
     * @var int Delay entre tentativas (segundos)
     */
    private $retry_delay = 2;
    
    /**
     * Construtor
     * 
     * @param array $settings Configurações da API
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->logger = new RevisorPTDLogger();
    }
    
    /**
     * Testa conexão com a API
     * 
     * @return array Resultado do teste
     */
    public function test_connection() {
        try {
            $this->validate_settings();
            
            $test_prompt = "Responda apenas: 'Conexão estabelecida com sucesso'";
            
            $response = $this->send_request($test_prompt, array(
                'max_tokens' => 50,
                'temperature' => 0
            ));
            
            if ($response['success']) {
                $this->logger->info('Teste de conexão com API bem-sucedido');
                return array(
                    'success' => true,
                    'message' => 'Conexão estabelecida com sucesso! API respondeu: ' . $response['content']
                );
            } else {
                throw new Exception($response['message']);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Falha no teste de conexão: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Envia requisição de análise
     * 
     * @param string $prompt Prompt completo
     * @return array Resultado da análise
     */
    public function send_analysis_request($prompt) {
        try {
            $this->validate_settings();
            
            $this->logger->info('Iniciando requisição de análise', array(
                'prompt_length' => strlen($prompt)
            ));
            
            // Configura parâmetros da requisição
            $params = array(
                'max_tokens' => isset($this->settings['max_tokens']) ? (int)$this->settings['max_tokens'] : 4000,
                'temperature' => isset($this->settings['temperature']) ? (float)$this->settings['temperature'] : 0.3
            );
            
            // Verifica se o prompt não é muito longo
            $this->validate_prompt_length($prompt);
            
            // Envia requisição
            $response = $this->send_request($prompt, $params);
            
            if ($response['success']) {
                $this->logger->info('Análise concluída com sucesso', array(
                    'response_length' => strlen($response['content'])
                ));
                
                return array(
                    'success' => true,
                    'content' => $this->format_analysis_response($response['content'])
                );
            } else {
                throw new Exception($response['message']);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Falha na análise: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Envia requisição para API
     * 
     * @param string $prompt Prompt a ser enviado
     * @param array $params Parâmetros adicionais
     * @return array Resposta da API
     */
    public function send_request($prompt, $params = array()) {
        $attempt = 0;
        $last_error = null;
        
        while ($attempt < $this->max_retries) {
            $attempt++;
            
            try {
                $this->logger->debug("Tentativa {$attempt}/{$this->max_retries} de requisição à API");
                
                // Limita o tamanho do prompt se necessário
                if (strlen($prompt) > 50000) {
                    $prompt = substr($prompt, 0, 50000) . "\n\n[Texto truncado devido ao tamanho]";
                }
                
                // Prepara corpo da requisição
                $body = array(
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => 'Você é um especialista em educação profissional. Responda de forma clara, estruturada e objetiva, sem usar marcadores markdown como asteriscos ou hashtags.'
                        ),
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'temperature' => isset($params['temperature']) ? (float)$params['temperature'] : 0.3,
                    'max_tokens' => isset($params['max_tokens']) ? (int)$params['max_tokens'] : 4000
                );
                
                // Converte para JSON e verifica se foi bem-sucedido
                $json_body = json_encode($body, JSON_UNESCAPED_UNICODE);
                if ($json_body === false) {
                    $json_error = json_last_error_msg();
                    throw new Exception("Erro ao codificar JSON: {$json_error}");
                }
                
                // Prepara cabeçalhos
                $headers = array(
                    'Content-Type' => 'application/json',
                    'api-key' => $this->settings['api_key'],
                    'Content-Length' => strlen($json_body)
                );
                
                // Faz requisição
                $response = wp_remote_post(
                    $this->settings['api_endpoint'],
                    array(
                        'method' => 'POST',
                        'headers' => $headers,
                        'body' => $json_body,
                        'timeout' => $this->default_timeout,
                        'user-agent' => 'RevisorPTD/' . REVISOR_PTD_VERSION,
                        'blocking' => true,
                        'compress' => false,
                        'decompress' => true,
                        'sslverify' => true
                    )
                );
                
                // Verifica se houve erro na requisição
                if (is_wp_error($response)) {
                    throw new Exception('Erro de conexão: ' . $response->get_error_message());
                }
                
                // Verifica código de resposta
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                
                $this->logger->debug("Resposta recebida", array(
                    'code' => $response_code,
                    'body_length' => strlen($response_body)
                ));
                
                // Trata diferentes códigos de resposta
                if ($response_code === 429) {
                    // Rate limiting - aguarda e tenta novamente
                    $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                    $delay = $retry_after ? (int)$retry_after : $this->retry_delay * $attempt;
                    
                    $this->logger->warning("Rate limit atingido, aguardando {$delay} segundos");
                    sleep($delay);
                    continue;
                    
                } elseif ($response_code !== 200) {
                    // Outros erros HTTP
                    $error_data = json_decode($response_body, true);
                    $error_message = isset($error_data['error']['message']) 
                        ? $error_data['error']['message'] 
                        : "Erro HTTP {$response_code}";
                    
                    // Se for erro 400 ou 413, pode ser prompt muito longo
                    if ($response_code === 400 || $response_code === 413) {
                        $error_message .= " (Prompt pode estar muito longo)";
                    }
                    
                    throw new Exception($error_message);
                }
                
                // Processa resposta bem-sucedida
                $response_data = json_decode($response_body, true);
                
                if (!$response_data || !isset($response_data['choices'][0]['message']['content'])) {
                    throw new Exception('Resposta inválida da API');
                }
                
                $content = $response_data['choices'][0]['message']['content'];
                
                // Log de sucesso
                $this->logger->debug('Requisição bem-sucedida', array(
                    'content_length' => strlen($content),
                    'attempts' => $attempt
                ));
                
                return array(
                    'success' => true,
                    'content' => $content,
                    'usage' => isset($response_data['usage']) ? $response_data['usage'] : null
                );
                
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                $this->logger->warning("Tentativa {$attempt} falhou: {$last_error}");
                
                // Se não é o último attempt, aguarda antes de tentar novamente
                if ($attempt < $this->max_retries) {
                    sleep($this->retry_delay * $attempt);
                }
            }
        }
        
        // Se chegou aqui, todas as tentativas falharam
        return array(
            'success' => false,
            'message' => "Falha após {$this->max_retries} tentativas. Último erro: {$last_error}"
        );
    }

    /**
 * Define delay entre requisições
 * 
 * @param int $delay Delay em segundos
 */
public function set_request_delay($delay) {
    $this->retry_delay = max(1, min(10, (int)$delay));
}
    
    /**
     * Valida configurações da API
     * 
     * @throws Exception Se configurações inválidas
     */
    private function validate_settings() {
        if (empty($this->settings['api_key'])) {
            throw new Exception('API Key não configurada');
        }
        
        if (empty($this->settings['api_endpoint'])) {
            throw new Exception('Endpoint da API não configurado');
        }
        
        if (!filter_var($this->settings['api_endpoint'], FILTER_VALIDATE_URL)) {
            throw new Exception('Endpoint da API inválido');
        }
    }
    
    /**
     * Valida tamanho do prompt
     * 
     * @param string $prompt Prompt a validar
     * @throws Exception Se prompt muito longo
     */
    private function validate_prompt_length($prompt) {
        $length = strlen($prompt);
        $estimated_tokens = ceil($length / 4);
        
        // Log para debug
        $this->logger->debug('Validando prompt', array(
            'length' => $length,
            'estimated_tokens' => $estimated_tokens
        ));
        
        $max_length = 100000;
        $max_tokens = 25000;  
        
        if ($length > $max_length) {
            throw new Exception("Prompt muito longo ({$length} caracteres). Máximo: {$max_length}. Tente usar documentos menores.");
        }
        
        if ($estimated_tokens > $max_tokens) {
            throw new Exception("Prompt muito longo ({$estimated_tokens} tokens estimados). Máximo: {$max_tokens}. Tente usar documentos menores.");
        }
        
        // Verifica se o prompt contém caracteres problemáticos
        if (!mb_check_encoding($prompt, 'UTF-8')) {
            throw new Exception('Prompt contém caracteres inválidos. Verifique os documentos enviados.');
        }
    }
    
    /**
     * Formata resposta da análise
     * 
     * @param string $content Conteúdo da resposta
     * @return string Conteúdo formatado
     */
    private function format_analysis_response($content) {
        // Remove marcadores markdown
        $content = preg_replace('/\*\*(.*?)\*\*/', '$1', $content);
        $content = preg_replace('/\*(.*?)\*/', '$1', $content);
        
        // Remove hashtags de títulos
        $content = preg_replace('/(#{1,6})\s*(.*?)(\n|$)/', "$2\n", $content);
        
        // Remove marcadores de lista
        $content = preg_replace('/^\s*[-*]\s*(.*)$/m', '$1', $content);
        $content = preg_replace('/^\s*\d+\.\s*(.*)$/m', '$1', $content);
        
        // Normaliza espaçamento
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = str_replace("\n ", "\n", $content);
        
        return trim($content);
    }
    
    /**
     * Estima número de tokens
     * 
     * @param string $text Texto a estimar
     * @return int Número estimado de tokens
     */
    public function estimate_tokens($text) {
        // Estimativa simples: ~1 token por 4 caracteres para português
        return ceil(strlen($text) / 4);
    }
    
    /**
     * Verifica se prompt está dentro dos limites
     * 
     * @param string $prompt Prompt a verificar
     * @return array Informações sobre o prompt
     */
    public function analyze_prompt($prompt) {
        $char_count = strlen($prompt);
        $estimated_tokens = $this->estimate_tokens($prompt);
        $max_tokens = isset($this->settings['max_tokens']) ? (int)$this->settings['max_tokens'] : 4000;
        
        return array(
            'character_count' => $char_count,
            'estimated_input_tokens' => $estimated_tokens,
            'max_output_tokens' => $max_tokens,
            'total_estimated_tokens' => $estimated_tokens + $max_tokens,
            'within_limits' => $estimated_tokens < 30000, // Limite conservador
            'recommendations' => $this->get_prompt_recommendations($char_count, $estimated_tokens)
        );
    }
    
    /**
     * Obtém recomendações para o prompt
     * 
     * @param int $char_count Número de caracteres
     * @param int $token_count Número estimado de tokens
     * @return array Recomendações
     */
    private function get_prompt_recommendations($char_count, $token_count) {
        $recommendations = array();
        
        if ($token_count > 25000) {
            $recommendations[] = 'Prompt muito longo - considere reduzir o tamanho dos documentos base';
        } elseif ($token_count > 15000) {
            $recommendations[] = 'Prompt longo - pode resultar em respostas mais lentas';
        }
        
        if ($char_count < 500) {
            $recommendations[] = 'Prompt muito curto - considere adicionar mais contexto';
        }
        
        return $recommendations;
    }
    
    /**
     * Obtém estatísticas de uso da API
     * 
     * @return array Estatísticas
     */
    public function get_usage_stats() {
        // Esta funcionalidade poderia ser expandida para rastrear uso real
        return array(
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_tokens_used' => 0,
            'average_response_time' => 0
        );
    }
    
    /**
     * Define timeout para requisições
     * 
     * @param int $timeout Timeout em segundos
     */
    public function set_timeout($timeout) {
        $this->default_timeout = max(30, min(300, (int)$timeout));
    }
    
    /**
     * Define número máximo de tentativas
     * 
     * @param int $retries Número de tentativas
     */
    public function set_max_retries($retries) {
        $this->max_retries = max(1, min(5, (int)$retries));
    }
}