<?php

use PHPUnit\Framework\TestCase;

class QuizBuilderTest extends TestCase 
{
    /**
     * Testa se o arquivo do módulo existe
     */
    public function testQuizBuilderFileExists() 
    {
        $module_file = ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/quiz-builder/class-quiz-builder.php';
        $this->assertFileExists($module_file);
    }
    
    /**
     * Testa estrutura de um quiz
     */
    public function testQuizStructure() 
    {
        $quiz_data = [
            'title' => 'Quiz de Teste',
            'questions' => [
                [
                    'question' => 'Qual é a capital do Brasil?',
                    'options' => ['São Paulo', 'Rio de Janeiro', 'Brasília', 'Salvador'],
                    'correct' => 2
                ]
            ]
        ];
        
        $this->assertArrayHasKey('title', $quiz_data);
        $this->assertArrayHasKey('questions', $quiz_data);
        $this->assertIsArray($quiz_data['questions']);
        $this->assertNotEmpty($quiz_data['questions']);
        
        $first_question = $quiz_data['questions'][0];
        $this->assertArrayHasKey('question', $first_question);
        $this->assertArrayHasKey('options', $first_question);
        $this->assertArrayHasKey('correct', $first_question);
        $this->assertCount(4, $first_question['options']);
    }
    
    /**
     * Testa validação de quiz
     */
    public function testQuizValidation() 
    {
        // Quiz válido
        $valid_quiz = [
            'title' => 'Quiz Válido',
            'questions' => [
                ['question' => 'Pergunta?', 'options' => ['A', 'B'], 'correct' => 0]
            ]
        ];
        
        $this->assertTrue($this->validateQuiz($valid_quiz));
        
        // Quiz sem título
        $invalid_quiz = [
            'questions' => [
                ['question' => 'Pergunta?', 'options' => ['A', 'B'], 'correct' => 0]
            ]
        ];
        
        $this->assertFalse($this->validateQuiz($invalid_quiz));
        
        // Quiz sem perguntas
        $empty_quiz = [
            'title' => 'Quiz Vazio',
            'questions' => []
        ];
        
        $this->assertFalse($this->validateQuiz($empty_quiz));
    }
    
    /**
     * Testa shortcode do quiz
     */
    public function testQuizShortcode() 
    {
        $shortcode_content = '[quiz]{"title":"Test Quiz","questions":[{"question":"Q1","options":["A","B"],"correct":0}]}[/quiz]';
        
        $this->assertStringContainsString('[quiz]', $shortcode_content);
        $this->assertStringContainsString('[/quiz]', $shortcode_content);
        
        // Extrair JSON do shortcode
        preg_match('/\[quiz\](.*?)\[\/quiz\]/s', $shortcode_content, $matches);
        $this->assertCount(2, $matches);
        
        $json_content = $matches[1];
        $quiz_data = json_decode($json_content, true);
        
        $this->assertIsArray($quiz_data);
        $this->assertArrayHasKey('title', $quiz_data);
        $this->assertEquals('Test Quiz', $quiz_data['title']);
    }
    
    /**
     * Testa pontuação do quiz
     */
    public function testQuizScoring() 
    {
        $total_questions = 5;
        $correct_answers = 3;
        
        $score_percentage = ($correct_answers / $total_questions) * 100;
        
        $this->assertEquals(60, $score_percentage);
        $this->assertGreaterThanOrEqual(0, $score_percentage);
        $this->assertLessThanOrEqual(100, $score_percentage);
    }
    
    /**
     * Função auxiliar para validar quiz
     */
    private function validateQuiz($quiz) 
    {
        if (!isset($quiz['title']) || empty($quiz['title'])) {
            return false;
        }
        
        if (!isset($quiz['questions']) || !is_array($quiz['questions']) || empty($quiz['questions'])) {
            return false;
        }
        
        foreach ($quiz['questions'] as $question) {
            if (!isset($question['question']) || !isset($question['options']) || !isset($question['correct'])) {
                return false;
            }
            
            if (!is_array($question['options']) || count($question['options']) < 2) {
                return false;
            }
        }
        
        return true;
    }
}