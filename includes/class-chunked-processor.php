<?php
/**
 * Processador de Análise em Chunks - VERSÃO DEFINITIVA CORRIGIDA
 * 
 * Divide a análise em múltiplas requisições menores para evitar erro 429
 */

if (!defined('ABSPATH')) {
    exit;
}

class RevisorPTDChunkedProcessor {
    
    /**
     * @var array Mapeamento de documentos base por tópico
     */
    private $topic_documents = array(
        '1' => 'guia_pratica_educacional',
        '2' => 'guia_pratica_educacional', 
        '3' => 'guia_pratica_educacional',
        '4' => 'Metodologias Ativas de Aprendizagem',
        '5' => 'anuario-de-tecnologias-educacionais',
        '6' => 'DocTec10_MarcasFormativas',
        '7' => 'DocTec5_AvaliacaoAprendizagem_2022',
        '8' => 'glossario_Pessoa com deficiencia'
    );
    
    /**
     * @var array Estrutura dos tópicos de análise
     */
    private $analysis_structure = array(
        array(
            'number' => 1,
            'title' => 'Coerência entre competência, situação de aprendizagem e indicadores',
            'questions' => array(
                'A competência está claramente relacionada à situação de aprendizagem e aos indicadores?',
                'Os fazeres previstos nos indicadores são efetivamente contemplados nas atividades propostas?'
            ),
            'document' => 'guia_pratica_educacional'
        ),
        array(
            'number' => 2,
            'title' => 'Estrutura e clareza das atividades',
            'questions' => array(
                'As atividades estão descritas de forma clara e detalhada?',
                'Há uma sequência lógica que contempla contextualização, desenvolvimento e conclusão da situação de aprendizagem?',
                'As etapas das atividades são compreensíveis e executáveis?'
            ),
            'document' => 'guia_pratica_educacional'
        ),
        array(
            'number' => 3,
            'title' => 'Articulação entre conhecimentos, habilidades e atitudes',
            'questions' => array(
                'As atividades permitem mobilizar de forma integrada os elementos da competência (saberes, fazeres e atitudes/valores)?',
                'As propostas articulam teoria e prática de forma equilibrada?',
                'O ciclo ação-reflexão-ação está contemplado?'
            ),
            'document' => 'guia_pratica_educacional'
        ),
        array(
            'number' => 4,
            'title' => 'Metodologias ativas e protagonismo do aluno',
            'questions' => array(
                'As atividades propostas utilizam metodologias ativas?',
                'Promovem o protagonismo do estudante no processo de aprendizagem?',
                'Há variedade entre atividades individuais e coletivas?'
            ),
            'document' => 'Metodologias Ativas de Aprendizagem'
        ),
        array(
            'number' => 5,
            'title' => 'Uso de tecnologias',
            'questions' => array(
                'As tecnologias digitais são utilizadas com intencionalidade pedagógica?'
            ),
            'document' => 'anuario-de-tecnologias-educacionais'
        ),
        array(
            'number' => 6,
            'title' => 'Marcas formativas',
            'questions' => array(
                'As atividades contribuem para o desenvolvimento das Marcas Formativas?'
            ),
            'document' => 'DocTec10_MarcasFormativas'
        ),
        array(
            'number' => 7,
            'title' => 'Avaliação da aprendizagem',
            'questions' => array(
                'Há diversidade de instrumentos e procedimentos avaliativos?',
                'As avaliações permitem identificar dificuldades dos alunos?',
                'O planejamento contempla avaliações diagnóstica, formativa e somativa?',
                'Estão previstos momentos de feedback aos alunos?'
            ),
            'document' => 'DocTec5_AvaliacaoAprendizagem_2022'
        ),
        array(
            'number' => 8,
            'title' => 'Acessibilidade e inclusão',
            'questions' => array(
                'O plano contempla adaptações para alunos PcD\'s (Pessoa com Deficiência)?',
                'Há recursos de acessibilidade digital, física ou pedagógica previstos?',
                'As atividades permitem diferentes formas de participação e expressão dos alunos?'
            ),
            'document' => 'glossario_Pessoa com deficiencia'
        )
    );
    
    /**
     * @var RevisorPTDLogger
     */
    private $logger;
    
    /**
     * @var RevisorPTDAPIHandler
     */
    private $api_handler;
    
    /**
     * @var array Configurações
     */
    private $settings;
    
    /**
     * Construtor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->logger = new RevisorPTDLogger();
        $this->api_handler = new RevisorPTDAPIHandler($settings);
        
        // Configurar timeouts e retries mais agressivos
        $this->api_handler->set_timeout(90);
        $this->api_handler->set_max_retries(3);
    }
    
    /**
     * Processa análise completa dividida em chunks
     * 
     * @param string $ptd_text Texto do PTD
     * @param string $pcn_text Texto do PCN
     * @param array $form_data Dados do formulário
     * @return array Resultado da análise
     */
    public function process_chunked_analysis($ptd_text, $pcn_text, $form_data) {
        $this->logger->info('Iniciando análise em chunks');
        
        try {
            // Log dos tamanhos dos documentos
            $this->logger->info('Tamanhos dos documentos', array(
                'ptd_length' => strlen($ptd_text),
                'pcn_length' => strlen($pcn_text)
            ));
            
            // 1. Extrair informações do cabeçalho primeiro
            $header_info = $this->extract_header_info($ptd_text, $pcn_text);
            
            // 2. Processar cada tópico individualmente
            $topic_results = array();
            $delay_between_calls = isset($this->settings['request_delay']) 
                ? (int)$this->settings['request_delay'] 
                : 3;
            
            foreach ($this->analysis_structure as $index => $topic) {
                $this->logger->info("Processando tópico {$topic['number']}: {$topic['title']}");
                
                // CORREÇÃO: Verificar se deve pular tópico de acessibilidade
                if ($topic['number'] == 8) {
                    if ($this->should_skip_accessibility($form_data)) {
                        $this->logger->info('Pulando tópico de acessibilidade - sem alunos PcD');
                        $topic_results[$topic['number']] = "Usuário não identificou ter alunos PcD's no formulário.";
                        continue;
                    }
                }
                
                // Aguardar entre requisições para evitar rate limiting
                if ($index > 0) {
                    sleep($delay_between_calls);
                }
                
                // Processar tópico individual
                $result = $this->process_single_topic(
                    $topic,
                    $ptd_text, // USANDO TEXTO COMPLETO
                    $pcn_text, // USANDO TEXTO COMPLETO
                    $form_data,
                    $header_info
                );
                
                if ($result['success']) {
                    // CORREÇÃO: Limpar resposta da IA antes de armazenar
                    $cleaned_content = $this->clean_ai_response($result['content']);
                    $topic_results[$topic['number']] = $cleaned_content;
                } else {
                    $this->logger->warning("Falha ao processar tópico {$topic['number']}: {$result['error']}");
                    $topic_results[$topic['number']] = "Não foi possível analisar este tópico.";
                }
            }
            
            // 3. Montar relatório final
            $final_report = $this->assemble_final_report($header_info, $topic_results, $form_data);
            
            $this->logger->info('Análise em chunks concluída com sucesso');
            
            return array(
                'success' => true,
                'content' => $final_report
            );
            
        } catch (Exception $e) {
            $this->logger->error('Erro na análise em chunks: ' . $e->getMessage());
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * FUNÇÃO ULTRA AGRESSIVA: Remove TODAS as perguntas repetidas
     */
   private function clean_ai_response($content) {
    // Log do conteúdo original para debug
    $this->logger->debug('Conteúdo original da IA antes da limpeza', array(
        'length' => strlen($content),
        'preview' => substr($content, 0, 500)
    ));
    
    // LISTA COMPLETA DE PERGUNTAS A REMOVER
    $all_questions = array(
        // Perguntas completas
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
        'As atividades permitem diferentes formas de participação e expressão dos alunos?',
        
        // Variações comuns com diferenças de pontuação/espaçamento
        'A competência está claramente relacionada à situação de aprendizagem e aos indicadores?',
        'As atividades contribuem para o desenvolvimento das ?',
        'O plano contempla adaptações para alunos PcD (Pessoa com Deficiência)?',
        'O plano contempla adaptações para alunos PcDs (Pessoa com Deficiência)?',
    );
    
    // PASSO 1: Processar linha por linha
    $lines = explode("\n", $content);
    $cleaned_lines = array();
    $skip_next = false;
    
    foreach ($lines as $index => $line) {
        $original_line = $line;
        $line = trim($line);
        
        // Skip linhas vazias mas preserva estrutura
        if (empty($line)) {
            // Só adiciona linha vazia se a última não foi vazia
            if (!empty($cleaned_lines) && end($cleaned_lines) !== '') {
                $cleaned_lines[] = '';
            }
            continue;
        }
        
        // PASSO 2: Verificar se a linha é uma pergunta exata
        $is_question = false;
        foreach ($all_questions as $question) {
            // Verifica se a linha contém a pergunta (com ou sem bullet)
            if (strpos($line, $question) !== false) {
                $is_question = true;
                break;
            }
            
            // Verifica sem considerar diferenças de espaçamento
            $normalized_line = preg_replace('/\s+/', ' ', $line);
            $normalized_question = preg_replace('/\s+/', ' ', $question);
            
            if (strpos($normalized_line, $normalized_question) !== false) {
                $is_question = true;
                break;
            }
        }
        
        // PASSO 3: Pular se for pergunta
        if ($is_question) {
            $this->logger->debug("Removendo linha com pergunta: " . $line);
            continue;
        }
        
        // PASSO 4: Verificar se é um bullet/marcador seguido de pergunta
        if (preg_match('/^[•\-\*\d+\.]\s*(.+)$/', $line, $matches)) {
            $bullet_content = trim($matches[1]);
            
            // Verifica se o conteúdo após o bullet é uma pergunta
            foreach ($all_questions as $question) {
                if (strpos($bullet_content, $question) !== false ||
                    $bullet_content === $question) {
                    $this->logger->debug("Removendo bullet com pergunta: " . $line);
                    $is_question = true;
                    break;
                }
            }
            
            if ($is_question) {
                continue;
            }
        }
        
        // PASSO 5: Verificar se termina com interrogação
        if (substr($line, -1) === '?') {
            // Verificar se é uma das perguntas conhecidas
            foreach ($all_questions as $question) {
                if (stripos($line, substr($question, 0, 20)) !== false) {
                    $this->logger->debug("Removendo linha terminada em ?: " . $line);
                    $is_question = true;
                    break;
                }
            }
            
            if ($is_question) {
                continue;
            }
        }
        
        // PASSO 6: Remover marcadores desnecessários
        $line = preg_replace('/^\[Análise da pergunta \d+\]\s*/', '', $line);
        $line = preg_replace('/^\[Sua análise para.*?\]\s*/', '', $line);
        $line = preg_replace('/^PERGUNTA \d+:\s*/', '', $line);
        
        // PASSO 7: Se a linha começa com número seguido de ponto e é curta, pode ser numeração de pergunta
        if (preg_match('/^\d+\.\s*.{1,100}$/', $line) && substr($line, -1) === '?') {
            $this->logger->debug("Removendo possível pergunta numerada: " . $line);
            continue;
        }
        
        // PASSO 8: Remover linhas que são apenas bullets vazios
        if (preg_match('/^[•\-\*]\s*$/', $line)) {
            continue;
        }
        
        // Se passou por todos os filtros, adicionar à lista limpa
        if (!empty(trim($line))) {
            $cleaned_lines[] = $line;
        }
    }
    
    // PASSO 9: Reconstruir o conteúdo
    $content = implode("\n", $cleaned_lines);
    
    // PASSO 10: Limpeza final adicional
    // Remove qualquer pergunta que possa ter sobrado usando regex
    foreach ($all_questions as $question) {
        // Escapa caracteres especiais para regex
        $escaped_question = preg_quote($question, '/');
        
        // Remove a pergunta com qualquer prefixo (bullet, número, etc)
        $content = preg_replace('/^[•\-\*\d+\.]*\s*' . $escaped_question . '\s*$/m', '', $content);
        
        // Remove a pergunta em qualquer lugar do texto
        $content = str_replace($question, '', $content);
    }
    
    // PASSO 11: Limpar espaçamento
    $content = preg_replace('/\n{3,}/', "\n\n", $content);
    $content = preg_replace('/^\s+$/m', '', $content);
    
    // PASSO 12: Verificação final - se ainda houver perguntas, fazer limpeza mais agressiva
    $final_check = strtolower($content);
    $has_questions = false;
    
    foreach ($all_questions as $question) {
        if (stripos($final_check, strtolower($question)) !== false) {
            $has_questions = true;
            break;
        }
    }
    
    if ($has_questions) {
        $this->logger->warning("Ainda há perguntas após limpeza. Aplicando limpeza ultra-agressiva.");
        
        // Split em parágrafos e remove qualquer um que contenha perguntas
        $paragraphs = explode("\n\n", $content);
        $clean_paragraphs = array();
        
        foreach ($paragraphs as $paragraph) {
            $paragraph_lower = strtolower($paragraph);
            $contains_question = false;
            
            foreach ($all_questions as $question) {
                if (stripos($paragraph_lower, strtolower(substr($question, 0, 30))) !== false) {
                    $contains_question = true;
                    break;
                }
            }
            
            if (!$contains_question && strlen(trim($paragraph)) > 20) {
                $clean_paragraphs[] = trim($paragraph);
            }
        }
        
        $content = implode("\n\n", $clean_paragraphs);
    }
    
    // PASSO 13: Trim final e validação
    $content = trim($content);
    
    // Log do resultado final
    $this->logger->debug('Conteúdo após limpeza completa', array(
        'length' => strlen($content),
        'preview' => substr($content, 0, 500),
        'has_questions' => $has_questions
    ));
    
    // Se não sobrou conteúdo significativo, retornar mensagem padrão
    if (strlen($content) < 50) {
        return "Análise processada com sucesso para este tópico.";
    }
    
    return $content;
}
    
    /**
     * Extrai informações do cabeçalho do PTD
     */
    private function extract_header_info($ptd_text, $pcn_text) {
        $this->logger->debug('Extraindo informações do cabeçalho');
        
        $prompt = "Extraia as seguintes informações do PTD abaixo e responda APENAS com os dados solicitados, sem explicações adicionais:

1. Nome do Curso
2. Unidade Curricular
3. Carga Horária

PTD:
" . substr($ptd_text, 0, 5000) . "

PCN (se necessário para complementar):
" . substr($pcn_text, 0, 3000) . "

Formate a resposta exatamente assim:
Nome do Curso: [nome]
Unidade Curricular: [nome]
Carga Horária: [valor]";
        
        try {
            $response = $this->api_handler->send_request($prompt, array(
                'max_tokens' => 200,
                'temperature' => 0
            ));
            
            if ($response['success']) {
                return $this->parse_header_info($response['content']);
            }
        } catch (Exception $e) {
            $this->logger->error('Erro ao extrair cabeçalho: ' . $e->getMessage());
        }
        
        return array(
            'course_name' => 'Não identificado',
            'unit_name' => 'Não identificado',
            'hours' => 'Não identificado'
        );
    }
    
    /**
     * Processa um único tópico
     */
    private function process_single_topic($topic, $ptd_text, $pcn_text, $form_data, $header_info) {
        $this->logger->debug("Processando tópico individual: {$topic['number']}");
        
        // Obter documento base específico para este tópico
        $base_document = $this->get_base_document_for_topic($topic['document']);
        
        // CORREÇÃO: Usar chunks inteligentes mas maiores para manter contexto
        $ptd_chunk = $this->get_relevant_chunk($ptd_text, $topic, 20000); // Aumentado
        $pcn_chunk = $this->get_relevant_chunk($pcn_text, $topic, 10000); // Aumentado
        
        // Log dos tamanhos dos chunks
        $this->logger->debug("Tamanhos dos chunks para tópico {$topic['number']}", array(
            'ptd_chunk_length' => strlen($ptd_chunk),
            'pcn_chunk_length' => strlen($pcn_chunk),
            'base_document_length' => strlen($base_document)
        ));
        
        // CORREÇÃO: Limpar todos os textos antes de usar
        $base_document = $this->clean_text_for_api($base_document);
        $ptd_chunk = $this->clean_text_for_api($ptd_chunk);
        $pcn_chunk = $this->clean_text_for_api($pcn_chunk);
        
        // Construir prompt específico para o tópico
        $prompt = $this->build_topic_prompt(
            $topic,
            $ptd_chunk,
            $pcn_chunk,
            $base_document,
            $form_data,
            $header_info
        );
        
        // CORREÇÃO: Limpar o prompt final também
        $prompt = $this->clean_text_for_api($prompt);
        
        // Log do tamanho do prompt
        $this->logger->debug("Prompt construído para tópico {$topic['number']}", array(
            'prompt_length' => strlen($prompt),
            'estimated_tokens' => ceil(strlen($prompt) / 4)
        ));
        
        // Enviar requisição
        try {
            $response = $this->api_handler->send_request($prompt, array(
                'max_tokens' => 2000, // Aumentado para respostas mais completas
                'temperature' => 0.3
            ));
            
            if ($response['success']) {
                return array(
                    'success' => true,
                    'content' => $response['content']
                );
            } else {
                return array(
                    'success' => false,
                    'error' => $response['message']
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Obtém documento base para o tópico
     */
    private function get_base_document_for_topic($document_name) {
        $documents = get_option('revisor_ptd_base_documents', array());
        
        foreach ($documents as $doc) {
            if (strpos($doc['filename'], $document_name) !== false) {
                $content = $this->extract_document_content($doc['filepath']);
                if (is_string($content)) {
                    // Limitar tamanho do documento base mas aumentar o limite
                    $limited_content = substr($content, 0, 15000); // Aumentado de 10000
                    $this->logger->debug("Documento base encontrado: {$document_name}", array(
                        'original_length' => strlen($content),
                        'limited_length' => strlen($limited_content)
                    ));
                    return $limited_content;
                }
            }
        }
        
        $this->logger->warning("Documento base não encontrado: {$document_name}");
        return '';
    }
    
    /**
     * Extrai conteúdo do documento
     */
    private function extract_document_content($filepath) {
        $handler = new RevisorPTDDocumentHandler();
        $content = $handler->extract_text_from_file($filepath);
        
        if (is_array($content)) {
            // É um PDF dos documentos base - precisa extrair o texto diretamente
            if (file_exists($filepath)) {
                // Para documentos base em PDF, vamos tentar extrair com método alternativo
                // ou retornar mensagem indicando necessidade de conversão
                return '[Documento base em PDF - converta para TXT ou DOCX para melhor compatibilidade]';
            }
        }
        
        return $content;
    }
    
    /**
     * Obtém chunk relevante do documento - VERSÃO MELHORADA
     */
    private function get_relevant_chunk($text, $topic, $max_chunk_size = 15000) {
        // Se o texto completo é menor que o limite, retornar tudo
        if (strlen($text) <= $max_chunk_size) {
            return $text;
        }
        
        // Estratégia: tentar encontrar seções relevantes com base em palavras-chave
        $keywords = $this->get_topic_keywords($topic['number']);
        
        // Tentar encontrar seção relevante
        $relevant_section = $this->find_relevant_section($text, $keywords, $max_chunk_size);
        
        if ($relevant_section && strlen($relevant_section) > 1000) {
            return $relevant_section;
        }
        
        // Fallback: retornar primeira parte do documento (maior)
        return substr($text, 0, $max_chunk_size);
    }
    
    /**
     * Encontra seção relevante do texto
     */
    private function find_relevant_section($text, $keywords, $max_size) {
        $best_match = '';
        $best_score = 0;
        
        // Dividir texto em seções (por quebras duplas de linha)
        $sections = preg_split('/\n\s*\n/', $text);
        
        foreach ($sections as $section) {
            if (strlen($section) > $max_size) {
                continue;
            }
            
            // Calcular score baseado em palavras-chave
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count(strtolower($section), strtolower($keyword));
            }
            
            if ($score > $best_score) {
                $best_score = $score;
                $best_match = $section;
            }
        }
        
        return $best_match;
    }
    
    /**
     * Obtém palavras-chave para o tópico
     */
    private function get_topic_keywords($topic_number) {
        $keywords_map = array(
            1 => array('competência', 'indicador', 'situação de aprendizagem', 'objetivo'),
            2 => array('atividade', 'estrutura', 'sequência', 'etapa', 'contextualização'),
            3 => array('conhecimento', 'habilidade', 'atitude', 'saber', 'fazer'),
            4 => array('metodologia', 'ativa', 'protagonismo', 'individual', 'coletiv'),
            5 => array('tecnologia', 'digital', 'recurso', 'ferramenta'),
            6 => array('marca', 'formativa', 'desenvolvimento'),
            7 => array('avaliação', 'diagnóstic', 'formativ', 'somativ', 'feedback'),
            8 => array('acessibilidade', 'inclusão', 'adaptação', 'PcD', 'deficiência')
        );
        
        return isset($keywords_map[$topic_number]) ? $keywords_map[$topic_number] : array();
    }
    
    /**
     * Constrói prompt para o tópico - VERSÃO MAIS CLARA
     */
    private function build_topic_prompt($topic, $ptd_chunk, $pcn_chunk, $base_document, $form_data, $header_info) {
        $context = $this->build_user_context($form_data);
        
        $prompt = "Você é um especialista em educação profissional com expertise no Modelo Pedagógico Senac (MPS).

INFORMAÇÕES DO CURSO:
Nome do Curso: {$header_info['course_name']}
Unidade Curricular: {$header_info['unit_name']}
Carga Horária: {$header_info['hours']}

CONTEXTO DA TURMA:
{$context}

Analise o tópico '{$topic['number']}. {$topic['title']}' respondendo às seguintes perguntas:

";
        
        foreach ($topic['questions'] as $index => $question) {
            $prompt .= ($index + 1) . ". {$question}\n";
        }
        
        if (!empty($base_document)) {
            $prompt .= "\nDOCUMENTO BASE DE REFERÊNCIA ({$topic['document']}):
{$base_document}

";
        }
        
        $prompt .= "TRECHO RELEVANTE DO PTD:
{$ptd_chunk}

";
        
        if (!empty($pcn_chunk)) {
            $prompt .= "TRECHO RELEVANTE DO PCN:
{$pcn_chunk}

";
        }
        
        $prompt .= "INSTRUÇÕES CRÍTICAS E OBRIGATÓRIAS:
- JAMAIS repita as perguntas na sua resposta
- JAMAIS inclua bullets ou hífens antes das análises  
- Responda SOMENTE com parágrafos de análise pura
- NÃO use marcadores como [Análise da pergunta X]
- NÃO copie as perguntas que eu listei acima
- Forneça um parágrafo completo para cada pergunta, na ordem apresentada
- Separe cada análise com UMA linha em branco
- Não use formatação markdown
- Seja objetivo e prático
- Base suas análises no PTD, PCN e documentos de referência fornecidos

EXEMPLO DO FORMATO CORRETO:
A competência está claramente relacionada... [seu texto de análise aqui]

As atividades descritas no PTD... [seu texto de análise aqui]

O planejamento contempla... [seu texto de análise aqui]

Responda agora APENAS com as análises, sem repetir perguntas:";
        
        return $prompt;
    }
    
    /**
     * Constrói contexto do usuário
     */
    private function build_user_context($form_data) {
        $context = "";
        
        foreach ($form_data as $label => $value) {
            if (!empty($value)) {
                $context .= "- {$label}: {$value}\n";
            }
        }
        
        return $context;
    }
    
    /**
     * Verifica se deve pular tópico de acessibilidade - VERSÃO CORRIGIDA
     */
    private function should_skip_accessibility($form_data) {
        // Verifica múltiplas variações de como o campo pode vir
        $accessibility_fields = [
            'Alunos com necessidades especiais',
            'special_needs',
            'Necessidades especiais',
            'PcD',
            'Pessoa com Deficiência'
        ];
        
        foreach ($accessibility_fields as $field) {
            if (isset($form_data[$field])) {
                $value = strtolower(trim($form_data[$field]));
                
                // Se há valor positivo, não pular
                if (!empty($value) && 
                    $value !== 'não' && 
                    $value !== 'nao' && 
                    $value !== 'nenhum' &&
                    $value !== 'não há' &&
                    $value !== 'nao ha' &&
                    $value !== 'sem necessidades' &&
                    $value !== '0' &&
                    $value !== 'zero') {
                    return false; // NÃO pular - há alunos PcD
                }
            }
        }
        
        return true; // Pular - não há alunos PcD identificados
    }
    
    /**
     * Monta relatório final - VERSÃO CORRIGIDA SEM PERGUNTAS REPETIDAS
     */
    private function assemble_final_report($header_info, $topic_results, $form_data) {
        $report = "Nome do Curso: {$header_info['course_name']}\n";
        $report .= "Unidade Curricular: {$header_info['unit_name']}\n";
        $report .= "Carga Horária: {$header_info['hours']}\n\n";
        
        foreach ($this->analysis_structure as $topic) {
            // Pular se não tem resultado para o tópico
            if (!isset($topic_results[$topic['number']])) {
                continue;
            }
            
            $report .= "{$topic['number']}. {$topic['title']}\n";
            
            // CORREÇÃO: Adicionar perguntas APENAS no relatório final
            foreach ($topic['questions'] as $question) {
                $report .= "• {$question}\n";
            }
            
            // CORREÇÃO: Adicionar resultado da análise SEM repetir perguntas
            $report .= "\n{$topic_results[$topic['number']]}\n\n";
        }
        
        return $report;
    }
    
    /**
     * Analisa header info da resposta
     */
    private function parse_header_info($response) {
        $info = array(
            'course_name' => 'Não identificado',
            'unit_name' => 'Não identificado',
            'hours' => 'Não identificado'
        );
        
        // Extrair Nome do Curso
        if (preg_match('/Nome do Curso:\s*(.+)/i', $response, $matches)) {
            $info['course_name'] = trim($matches[1]);
        }
        
        // Extrair Unidade Curricular
        if (preg_match('/Unidade Curricular:\s*(.+)/i', $response, $matches)) {
            $info['unit_name'] = trim($matches[1]);
        }
        
        // Extrair Carga Horária
        if (preg_match('/Carga Horária:\s*(.+)/i', $response, $matches)) {
            $info['hours'] = trim($matches[1]);
        }
        
        return $info;
    }
    
    /**
     * Limpa texto para evitar erros UTF-8 na API - NOVA FUNÇÃO
     */
    private function clean_text_for_api($text) {
        if (empty($text)) {
            return '';
        }
        
        // Remove caracteres de controle problemáticos
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normaliza quebras de linha
        $text = preg_replace('/\r\n|\r/', '\n', $text);
        
        // Remove múltiplos espaços em branco
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Remove múltiplas quebras de linha
        $text = preg_replace('/\n{3,}/', '\n\n', $text);
        
        // Garante que é UTF-8 válido
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
            
            // Se ainda não é válido, remove caracteres problemáticos
            if (!mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8//IGNORE');
            }
        }
        
        // Remove qualquer caractere que ainda possa causar problema
        $text = filter_var($text, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        
        return trim($text);
    }
}