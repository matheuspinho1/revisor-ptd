<?php
/**
 * Template do Formulário
 * 
 * Formulário responsivo com integração WordPress e funcionalidades de upload
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="revisor-ptd-form-container">
    <div class="form-header">
        <h2><?php echo esc_html($atts['title']); ?></h2>
        <p class="form-description">
            Faça upload do seu Plano de Trabalho Docente e preencha o formulário abaixo para análise do PTD com Inteligência Artificial.
        </p>
    </div>
    
    <form id="revisor-ptd-form" enctype="multipart/form-data">
        <?php wp_nonce_field('revisor_ptd_nonce', 'nonce'); ?>
        
        <!-- Upload de Arquivos - APENAS PTD -->
        <div class="form-files-section">
            <!-- PTD - Obrigatório -->
            <div class="form-section file-upload-section">
                <label class="form-label required" for="ptd_file">
                    📄 Plano de Trabalho Docente (PTD)
                </label>
                <div class="file-upload-wrapper">
                    <input type="file" 
                           id="ptd_file" 
                           name="ptd_file" 
                           accept=".pdf,.doc,.docx,.txt"
                           required 
                           class="file-input" />
                    <div class="file-upload-display">
                        <span class="upload-icon">📁</span>
                        <span class="upload-text">Clique para selecionar o arquivo PTD</span>
                        <small class="file-info">PDF, DOC, DOCX ou TXT - Máx: 16MB</small>
                    </div>
                </div>
                <div class="file-selected" style="display: none;">
                    <span class="file-name"></span>
                    <button type="button" class="remove-file">✕</button>
                </div>
            </div>
        </div>


        <!-- Informações do Docente -->
       
        <div class="form-section">
            <h3 class="section-title">👨‍🏫 Informações do Docente</h3>
                    
            <div class="form-group">
                <label class="form-label">
                    Qual é seu perfil pedagógico?
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="teacher_role" value="prof" />
                        <span class="radio-mark"></span>
                        Professor
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="teacher_role" value="supervisor" />
                        <span class="radio-mark"></span>
                        Supervisor Pedagógico
                    </label>
                                    </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    O PTD submetido para análise é oriundo de um Plano de Curso Nacional?
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="ptd_role" value="yes" />
                        <span class="radio-mark"></span>
                        Sim
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="ptd_role" value="no" />
                        <span class="radio-mark"></span>
                        Não
                    </label>
                                    </div>
                                    <span id="ptd-aviso" style="display: none; color: red; font-weight: bold;">
                                    ⚠️ Nossa ferramenta foi projetada para analisar PTD's oriundos de Planos de Curso Nacionais, você pode utilizar, mas o resultado pode não ser tão preciso. 
                                    </span>
            </div>
        </div>
        <!-- Informações do Docente -->
        
        <!-- Perfil da Turma -->
        <div class="form-section">
            <h3 class="section-title">👥 Perfil da Turma</h3>
            
            <div class="form-group">
                <label for="student_count" class="form-label">
                    Quantos alunos compõem a turma?
                </label>
                <input type="number" 
                       id="student_count" 
                       name="student_count" 
                       min="1" 
                       max="100"
                       class="form-input" 
                       placeholder="Ex: 25" />
            </div>
            
            <div class="form-group">
                <label for="special_needs" class="form-label">
                    Há alunos com deficiência, transtornos de aprendizagem ou outras condições que demandem adaptações? Se sim, quantos e quais?
                </label>
                <textarea id="special_needs" 
                          name="special_needs" 
                          rows="3" 
                          class="form-textarea"
                          placeholder="Descreva as necessidades especiais dos alunos, se houver..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="age_range" class="form-label">
                    Qual é a faixa etária predominante da turma?
                </label>
                <select id="age_range" name="age_range" class="form-select">
                    <option value="">Selecione uma opção</option>
                    <option value="under18">Menos de 18 anos</option>
                    <option value="18-25">18 a 25 anos</option>
                    <option value="26-35">26 a 35 anos</option>
                    <option value="36-45">36 a 45 anos</option>
                    <option value="over45">Mais de 45 anos</option>
                    <option value="diverse">Diversificada</option>
                </select>
            </div>
            
            <div class="form-group" style="display: none;">
                <label for="education_level" class="form-label">
                    Qual o grau de escolaridade ou nível de formação prévia dos alunos?
                </label>
                <select id="education_level" name="education_level" class="form-select">
                    <option value="">Selecione uma opção</option>
                    <option value="elementaryIncomplete">Ensino Fundamental Incompleto</option>
                    <option value="elementaryComplete">Ensino Fundamental Completo</option>
                    <option value="highSchoolIncomplete">Ensino Médio Incompleto</option>
                    <option value="highSchoolComplete">Ensino Médio Completo</option>
                    <option value="collegeIncomplete">Ensino Superior Incompleto</option>
                    <option value="collegeComplete">Ensino Superior Completo</option>
                    <option value="postGraduate">Pós-graduação</option>
                    <option value="diverse">Diversificada</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    Os alunos já têm experiência anterior na área do curso?
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="prior_experience" value="yes" />
                        <span class="radio-mark"></span>
                        Sim
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="prior_experience" value="no" />
                        <span class="radio-mark"></span>
                        Não
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="prior_experience" value="partial" />
                        <span class="radio-mark"></span>
                        Parcialmente
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Ambientes de Aprendizagem -->
        <div class="form-section">
            <h3 class="section-title">🏫 Ambientes de Aprendizagem</h3>
            
            <div class="form-group">
                <label class="form-label">
                    Indique os ambientes de aprendizagem disponíveis em sua unidade:
                </label>
                <div class="checkbox-group">
                      <label class="checkbox-item">
                        <input type="checkbox" name="learning_environments[]" value="classroom" />
                        <span class="checkbox-mark"></span>
                        Sala de aula
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="learning_environments[]" value="library" />
                        <span class="checkbox-mark"></span>
                        Biblioteca
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="learning_environments[]" value="computerLab" />
                        <span class="checkbox-mark"></span>
                        Laboratório de informática
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="learning_environments[]" value="healthLab" />
                        <span class="checkbox-mark"></span>
                        Laboratório Técnico/Especializado (Do próprio curso)
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="learning_environments[]" value="auditorium" />
                        <span class="checkbox-mark"></span>
                        Auditórios ou centro de convenções
                    </label>
                        <label class="checkbox-item">
                        <input type="checkbox" name="learning_environments[]" value="innovativeClassroom" />
                        <span class="checkbox-mark"></span>
                       Sala de aula inovadora
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Infraestrutura e Recursos -->
        <div class="form-section">
            <h3 class="section-title">🔧 Condições de Infraestrutura e Recursos</h3>
            
            <div class="form-group">
                <label class="form-label">
                    Os alunos têm acesso a dispositivos tecnológicos e internet na unidade?
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="tech_access_unit" value="yes" />
                        <span class="radio-mark"></span>
                        Sim
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="tech_access_unit" value="no" />
                        <span class="radio-mark"></span>
                        Não
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="tech_access_unit" value="partially" />
                        <span class="radio-mark"></span>
                        Parcialmente
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    Os alunos têm acesso a dispositivos tecnológicos e internet fora da unidade?
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="tech_access_outside" value="yes" />
                        <span class="radio-mark"></span>
                        Sim
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="tech_access_outside" value="no" />
                        <span class="radio-mark"></span>
                        Não
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="tech_access_outside" value="partially" />
                        <span class="radio-mark"></span>
                        Parcialmente
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Histórico de Aprendizagem -->
        <div class="form-section">
            <h3 class="section-title">📊 Histórico de Aprendizagem e Avaliação</h3>
            
            <div class="form-group">
                <label class="form-label">
                    Já foi realizada alguma avaliação diagnóstica da turma?
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="diagnostic_assessment" value="yes" />
                        <span class="radio-mark"></span>
                        Sim
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="diagnostic_assessment" value="no" />
                        <span class="radio-mark"></span>
                        Não
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="learning_difficulties" class="form-label">
                    Os alunos apresentam algum tipo de dificuldade no processo de ensino e aprendizagem? Qual?
                </label>
                <textarea id="learning_difficulties" 
                          name="learning_difficulties" 
                          rows="3" 
                          class="form-textarea"
                          placeholder="Descreva as principais dificuldades identificadas..."></textarea>
            </div>
        </div>
        
        <!-- Botão de Envio -->
        <div class="form-submit-section">
            <button type="submit" class="submit-button" id="submit-analysis">
                <span class="button-text" style="color: #fff;">Analisar</span>
                <span class="button-loading" style="display: none;">
                    <span class="spinner"></span>
                    Processando...
                </span>
            </button>
            
            <div class="form-notes">
                <p><small>
                    ⏱️ O processamento pode levar alguns minutos dependendo do tamanho dos arquivos.
                </small></p>
            </div>
        </div>
        
        <!-- Indicador de Progresso -->
        <div id="progress-container" style="display: none;">
            <div class="progress-section">
                <div class="progress-header">
                    <h4>📈 Progresso da Análise</h4>
                    <span class="progress-percentage">0%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-message">Preparando análise...</div>
            </div>
        </div>
    </form>
    
    <!-- Resultado da Análise -->
    <div id="analysis-result" style="display: none;">
        <!-- Botões no TOPO -->
        <div class="result-header">
            <h3>✅ Análise Concluída!</h3>
            <div class="result-actions">
                <button type="button" class="button-secondary result-action-btn" data-action="download">
                    💾 Baixar Relatório (Word)
                </button>
                <button type="button" class="button-primary result-action-btn" data-action="new">
                    🔄 Nova Análise
                </button>
            </div>
        </div>
        
        <!-- Conteúdo da análise -->
        <div class="result-content">
            <!-- Conteúdo da análise será inserido aqui via JavaScript -->
        </div>
        
        <!-- Botões na PARTE INFERIOR -->
        <div class="result-header">
			 <h3>✅ Análise Concluída!</h3>
            <div class="result-actions">
                <button type="button" class="button-secondary result-action-btn" data-action="download">
                    💾 Baixar Relatório (Word)
                </button>
                <button type="button" class="button-primary result-action-btn" data-action="new">
                    🔄 Nova Análise
                </button>
            </div>
        </div>
    </div>
</div>