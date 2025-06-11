<?php

namespace App\Helpers;

class PatientDataFormatter
{
    /**
     * Parse nascimento field to extract patient information
     *
     * @param string $nascimento
     * @return array
     */
    public static function parseNascimento($nascimento)
    {
        $result = [
            'name' => '',
            'initials' => '',
            'birthdate' => null,
            'age' => null,
            'internment_days' => null,
        ];
        
        if (empty($nascimento)) {
            return $result;
        }
        
        $lines = explode("\n", $nascimento);
        
        if (count($lines) > 0) {
            // First line is the name
            $result['name'] = trim($lines[0]);
            
            // Get initials
            $words = explode(' ', $result['name']);
            $initials = '';
            foreach ($words as $word) {
                if (strlen($word) > 0 && ctype_alpha($word[0])) {
                    $initials .= strtoupper(substr($word, 0, 1));
                }
            }
            $result['initials'] = $initials;
            
            // Process other lines
            foreach ($lines as $line) {
                // Extract birthdate
                if (strpos($line, 'DN.:') !== false) {
                    preg_match('/DN\.: (\d{2}\/\d{2}\/\d{2})/', $line, $matches);
                    if (isset($matches[1])) {
                        $result['birthdate'] = $matches[1];
                    }
                }
                
                // Extract age
                if (strpos($line, 'Idade:') !== false) {
                    preg_match('/Idade: (\d+) Ano\(s\)/', $line, $matches);
                    if (isset($matches[1])) {
                        $result['age'] = $matches[1];
                    }
                }
                
                // Extract attendance number
                if (strpos($line, 'Atend.:') !== false) {
                    preg_match('/Atend\.: (\d+)/', $line, $matches);
                    if (isset($matches[1])) {
                        $result['attendance_from_nascimento'] = $matches[1];
                    }
                }
                
                // Extract internment days
                if (strpos($line, 'Tempo Intern:') !== false) {
                    preg_match('/Tempo Intern: (\d+) dias/', $line, $matches);
                    if (isset($matches[1])) {
                        $result['internment_days'] = $matches[1];
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Format MEWS score with color code
     *
     * @param int|null $score
     * @return array With score and color
     */
    public static function formatMewsScore($score)
    {
        if ($score === null) {
            return [
                'score' => 'N/A', 
                'color' => 'secondary'
            ];
        }
        
        $score = (int)$score;
        
        if ($score >= 5) {
            return ['score' => $score, 'color' => 'danger'];
        } elseif ($score >= 3) {
            return ['score' => $score, 'color' => 'warning'];
        } else {
            return ['score' => $score, 'color' => 'success'];
        }
    }
}
