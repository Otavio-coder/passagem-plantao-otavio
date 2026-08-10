<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dias de funcionamento do Huddle
    |--------------------------------------------------------------------------
    |
    | A rotina de preenchimento do Huddle não ocorre aos finais de semana nem
    | em feriados. Os feriados abaixo bloqueiam o formulário nas datas listadas.
    |
    | - Datas fixas (todo ano): formato 'MM-DD' (ex.: '01-01' = 1º de janeiro).
    | - Datas específicas (um ano só): formato 'YYYY-MM-DD' (ex.: móveis como Páscoa).
    |
    */

    'holidays' => [
        // Feriados nacionais fixos
        '01-01', // Confraternização Universal
        '04-21', // Tiradentes
        '05-01', // Dia do Trabalho
        '09-07', // Independência
        '10-12', // Nossa Senhora Aparecida
        '11-02', // Finados
        '11-15', // Proclamação da República
        '12-25', // Natal

        // Feriados móveis / locais — adicionar por ano (YYYY-MM-DD)
        // '2026-02-17', // Carnaval
        // '2026-04-03', // Sexta-feira Santa
        // '2026-06-04', // Corpus Christi
    ],

    /*
    |--------------------------------------------------------------------------
    | Setores materializados pela rotina diária (huddle:open-day)
    |--------------------------------------------------------------------------
    |
    | Lista de cd_setor_atendimento que o comando huddle:open-day deve processar
    | todo dia. Se ficar vazio, o comando processa TODOS os setores ativos
    | (nurse_handover_beds + user_sector_preferences), igual ao aquecimento do SBAR.
    | Preencha para restringir o Huddle a setores específicos.
    |
    */

    'sectors' => [
        // 6228,
        // 6229,
    ],

    /*
    |--------------------------------------------------------------------------
    | Questionário do Huddle no Tasy (módulo QUE_)
    |--------------------------------------------------------------------------
    |
    | NR_SEQUENCIA do QUE_QUESTIONARIO correspondente ao formulário do Huddle
    | (ex.: "Teste avaliação"). É o id estável usado pelo comando
    | huddle:sync-questions para espelhar as perguntas/opções do Tasy para o
    | banco local. Defina no .env: HUDDLE_QUESTIONNAIRE_SEQ=NNNNNN
    |
    */

    'questionnaire_seq' => env('HUDDLE_QUESTIONNAIRE_SEQ'),

];
