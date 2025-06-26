<?php
/**
 * Página de Configurações do Plugin
 * 
 * Interface administrativa para:
 * - Configurar API da IA
 * - Definir prompt de análise
 * - Ajustar parâmetros
 */

if (!defined('ABSPATH')) {
    exit;
}

// Processa salvamento das configurações
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'revisor_ptd_settings')) {
    $new_settings = array(
        'api_key' => sanitize_text_field($_POST['api_key']),
        'api_endpoint' => esc_url_raw($_POST['api_endpoint']),
        'analysis_prompt' => wp_kses_post($_POST['analysis_prompt']),
        'max_tokens' => intval($_POST['max_tokens']),
        'temperature' => floatval($_POST['temperature']),
        'chunk_size' => intval($_POST['chunk_size'] ?? 15000),
        'request_delay' => intval($_POST['request_delay'] ?? 3)
    );
    
    update_option('revisor_ptd_settings', $new_settings);
    $settings = $new_settings;
    
    echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
}

// Garante valores padrão
$default_values = array(
    'api_key' => '',
    'api_endpoint' => '',
    'analysis_prompt' => '',
    'max_tokens' => 4000,
    'temperature' => 0.3,
    'chunk_size' => 15000,
    'request_delay' => 3
);

foreach ($default_values as $key => $default) {
    if (!isset($settings[$key])) {
        $settings[$key] = $default;
    }
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="revisor-ptd-admin-container">
        <form method="post" action="">
            <?php wp_nonce_field('revisor_ptd_settings'); ?>
            
            <!-- Configurações da API -->
            <div class="postbox">
                <h3 class="hndle">
                    <span>🔧 Configurações da API</span>
                </h3>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_key">API Key</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="api_key" 
                                       name="api_key" 
                                       value="<?php echo esc_attr($settings['api_key']); ?>" 
                                       class="regular-text" 
                                       required />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="api_endpoint">Endpoint da API</label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="api_endpoint" 
                                       name="api_endpoint" 
                                       value="<?php echo esc_attr($settings['api_endpoint']); ?>" 
                                       class="regular-text" 
                                       required />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="max_tokens">Limite de Tokens</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="max_tokens" 
                                       name="max_tokens" 
                                       value="<?php echo esc_attr($settings['max_tokens']); ?>" 
                                       min="500" 
                                       max="8000" 
                                       step="100" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="temperature">Temperatura</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="temperature" 
                                       name="temperature" 
                                       value="<?php echo esc_attr($settings['temperature']); ?>" 
                                       min="0" 
                                       max="1" 
                                       step="0.1" />
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="chunk_size">Tamanho máximo por chunk (caracteres)</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="chunk_size" 
                                       name="chunk_size" 
                                       value="<?php echo esc_attr($settings['chunk_size']); ?>"
                                       min="5000"
                                       max="50000"
                                       step="1000"
                                       class="regular-text" />
                                <p class="description">Tamanho máximo de texto por requisição (padrão: 15000)</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="request_delay">Delay entre requisições (segundos)</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="request_delay" 
                                       name="request_delay" 
                                       value="<?php echo esc_attr($settings['request_delay']); ?>"
                                       min="1"
                                       max="10"
                                       step="1"
                                       class="small-text" />
                                <p class="description">Tempo de espera entre requisições para evitar rate limiting</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="test-connection-section">
                        <button type="button" id="test-api-connection" class="button button-secondary">
                            Testar Conexão
                        </button>
                        <span id="connection-result"></span>
                        <div id="connection-loading" class="spinner"></div>
                    </div>
                </div>
            </div>
            
            <!-- Prompt de Análise -->
            <div class="postbox">
                <h3 class="hndle">
                    <span>Configuração do Prompt</span>
                </h3>
                <div class="inside">               
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="analysis_prompt">Prompt:</label>
                            </th>
                            <td>
                                <textarea id="analysis_prompt" 
                                          name="analysis_prompt" 
                                          rows="20" 
                                          class="large-text code"
                                          ><?php echo esc_textarea($settings['analysis_prompt']); ?></textarea>
                                
                                <div class="prompt-tools">
                                    <button type="button" class="button button-secondary" id="reset-prompt">
                                       Restaurar Prompt Padrão
                                    </button>
                                    <button type="button" class="button button-secondary" id="preview-prompt">
                                        Prévia do Prompt
                                    </button>
                                </div>                           
                            </td>
                        </tr>
                    </table>
                </div>
            </div>       

            <?php submit_button('Salvar Configurações', 'primary', 'submit', true, array('id' => 'save-settings')); ?>
        </form>
    </div>
</div>

<!-- Modal de Prévia do Prompt -->
<div id="prompt-preview-modal" class="revisor-modal" style="display: none;">
    <div class="revisor-modal-content">
        <div class="revisor-modal-header">
            <h3>Prévia do Prompt</h3>
            <span class="revisor-modal-close">&times;</span>
        </div>
        <div class="revisor-modal-body">
            <pre id="prompt-preview-content"></pre>
        </div>
        <div class="revisor-modal-footer">
            <button type="button" class="button button-secondary revisor-modal-close">Fechar</button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Prompt padrão
    const DEFAULT_PROMPT = `Você é um especialista em educação profissional com expertise no Modelo Pedagógico Senac (MPS).

Analise o Plano de Trabalho Docente (PTD) fornecido e responda EXATAMENTE seguindo a estrutura solicitada.

FORMATO OBRIGATÓRIO DA RESPOSTA:

Primeiro, extraia e informe:
Nome do Curso: [extrair do PTD]
Unidade Curricular: [extrair do PTD]  
Carga Horária: [extrair do PTD]

Em seguida, analise cada tópico numerado respondendo às perguntas específicas:

1. Coerência entre competência, situação de aprendizagem e indicadores
- A competência (objetivo pedagógico) está claramente relacionada à situação de aprendizagem e aos indicadores?
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

{pcn_content}

Analise o PTD seguindo rigorosamente a estrutura numerada de 1 a 8 com as respectivas perguntas.`;
    
    // Resetar prompt pro padrao
    $('#reset-prompt').on('click', function() {
        if (confirm('Tem certeza que deseja restaurar o prompt padrão? Isso substituirá o prompt atual.')) {
            $('#analysis_prompt').val(DEFAULT_PROMPT);
        }
    });
    
    // Previa do prompt
    $('#preview-prompt').on('click', function() {
        const prompt = $('#analysis_prompt').val();
        $('#prompt-preview-content').text(prompt);
        $('#prompt-preview-modal').show();
    });
    
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