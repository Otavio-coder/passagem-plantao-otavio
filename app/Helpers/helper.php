<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

if (!function_exists('nameFormat')) {

    /**
     * Retorna um nome formatado
     *
     * @param $name
     * @param array $exclude
     * @return array|string
     */
    function nameFormat($name, $exclude = [])
    {

        if (is_array($name)) {

            $fn = function (&$value, $key) use ($exclude) {
                $value = !in_array($key, $exclude) ? nameFormat($value) : $value;
            };

            array_walk($name, $fn);

            return $name;

        } else {

            $arr = [
                'da' => 'da',
                'das' => 'das',
                'de' => 'de',
                'di' => 'di',
                'do' => 'do',
                'dos' => 'dos',
                'e' => 'e',
                'el' => 'el',
                'i' => 'I',
                'ii' => 'II',
                'iii' => 'III',
                'iv' => 'IV',
                'v' => 'V',
                'vi' => 'VI',
                'vii' => 'VII',
                'viii' => 'VIII',
                'ix' => 'IX',
                'x' => 'X',
                'xi' => 'XI',
                'xii' => 'XII',
                'xiii' => 'XIII'
            ];

            $fn = function ($word) use ($arr) {
                return (!array_key_exists($word, $arr)) ? mb_convert_case(trim($word), MB_CASE_TITLE, 'utf-8') : $arr[$word];
            };

            return join(' ', array_map($fn, explode(' ', mb_strtolower(trim($name), 'utf-8'))));

        }

    }

}

if (!function_exists('camelToKebab')) {

    /**
     * Transforma uma string de CamelCase para kebab-case
     *
     * @param $string
     * @return string
     */
    function camelToKebab($string)
    {

        $divisor = strcspn($string, 'ABCDEFGHJIJKLMNOPQRSTUVWXYZ');

        $array = str_split($string, $divisor);

        return implode(
            '-',
            array_map('strtolower', $array)
        );

    }

}

if (!function_exists('kebabToCamel')) {

    /**
     * Transforma uma string de kebab-case para CamelCase
     *
     * @param $string
     * @return string
     */
    function kebabToCamel($string)
    {

        return lcfirst(
            implode('', array_map(
                    'ucfirst',
                    explode('-', $string)
                )
            )
        );

    }

}

if (!function_exists('prepare')) {

    /**
     * Prepara os dados para serem salvos no banco de dados
     *
     * @param $data
     * @param $column
     * @return array
     */
    function prepare($data, $column)
    {

        $data->$column = $data->id;

        unset($data->id);

        return collect($data)->toArray();

    }

}

if (!function_exists('debug')) {

    /**
     * Registra uma mensagem de debug no console
     *
     * @param $message
     */
    function debug($message)
    {
        print $message . PHP_EOL;
    }

}

if (!function_exists('getNewModel')) {

    /**
     * Retorna uma nova model
     *
     * @param $modelName
     * @param $source
     * @return mixed
     */
    function getNewModel($modelName, $source = null)
    {

        $className = str_replace('Repository', '', $modelName);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path() . "/Models" . $source)
        );

        $classes = new RegexIterator($files, '/\.php$/');
        $namespaces = [];

        foreach ($classes as $class) {

            $content = file_get_contents($class->getRealPath());
            $tokens = token_get_all($content);

            $namespace = null;

            for ($index = 0; isset($tokens[$index]); $index++) {

                if (!isset($tokens[$index][0])) {
                    continue;
                }

                if (T_NAMESPACE === $tokens[$index][0]) {

                    $index += 2;

                    while (isset($tokens[$index]) && is_array($tokens[$index])) {
                        $namespace .= $tokens[$index++][1];
                    }

                    if (!Str::contains($namespace, "Auth"))
                        $namespaces[] = $namespace;

                }

            }

        }

        foreach (array_unique($namespaces) as $namespace) {

            $class = "$namespace\\$className";

            if (class_exists($class))
                return new $class();

        }

        return false;

    }

}

if (!function_exists('preventCache')) {

    /**
     * Previne que um arquivo CSS ou JS seja carregado do cache aplicando um parametro de versÃ£o
     *
     * @return string|null
     */
    function preventCache()
    {
        return '?v=' . time();
    }

}

if (!function_exists('isCurrentRoute')) {

    /**
     * Verifica se o menu corresponde a rota atual
     *
     * @param $route_name
     * @return string
     */
    function isCurrentRoute($route_name)
    {

        if (!is_array($route_name)) {

            return Route::is($route_name) ? 'active' : '';

        } else {

            return in_array(Route::currentRouteName(), $route_name) ? 'active' : '';

        }

    }

}

if (!function_exists('errorMessageFormat')) {

    /**
     * Formata a mensagem de erro deixando o nome do campo em negrito
     *
     * @param $error
     * @return mixed
     */
    function errorMessageFormat($error)
    {

        if (Str::contains($error, ':')) {

            $attribute = Str::before(Str::after($error, ":"), ' ');

            $error = str_replace(':', '', str_replace($attribute, '<b>' . $attribute . '</b>', $error));

            $error = str_replace('_', ' ', $error);

        }

        return $error;
    }

}

if (!function_exists('logRequestBody')) {

    /**
     * Registra o log de um objeto JSON
     *
     * @param $title
     * @param $data
     */
    function logJson($title, $data)
    {

        \Illuminate\Support\Facades\Log::info(
            strtoupper($title) . ":: " . json_encode($data)
        );

    }

}

if (!function_exists('loadRepositories')) {

    /**
     * Carrega todos os repositórios
     *
     * @return stdClass
     */
    function loadRepositories()
    {

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path() . "/Repositories")
        );

        $classes = new RegexIterator($files, '/\.php$/');

        $repositories = new stdClass();

        foreach ($classes as $class) {

            $prefix = \Illuminate\Support\Str::camel(
                preg_replace('(Repository|.php)', '', $class->getFilename())
            );

            if ($prefix !== 'base') {

                $className = '\\App\Repositories\\' . str_replace('.php', '', $class->getFilename());

                $repositories->$prefix = new $className();

            }

        }

        return $repositories;
    }

}

if (!function_exists('color')) {

    /**
     * Retorna a cor de acordo com a chave
     *
     * @param string $key
     * @return mixed
     */
    function color($key)
    {

        $colors = [
            'danger' => 'red',
            'warning' => 'orange',
            'success' => 'teal'
        ];

        return $colors[$key];
    }

}

if (!function_exists('divide')) {

    /**
     * Realiza uma operação de divisão
     *
     * @param $value1
     * @param $value2
     * @return float|int
     */
    function divide($value1, $value2)
    {

        if ($value2 == 0) {

            return 0;
        }

        return $value1 / $value2;
    }

}

if (!function_exists('hospital')) {

    /**
     * Retorna o hospital
     *
     * @param $string
     * @return mixed|null
     */

    function hospital($string)
    {

        $sigla = null;

        $hospital = [
            'HSJ' => 'HOSPITAL SÃO JOSÉ',
            'HSR' => 'HOSPITAL SANTA RITA',
            'HCSA' => 'HOSPITAL DA CRIANÇA SANTO ANTÔNIO',
            'HDVS' => 'HOSPITAL DOM VICENTE SCHERER',
            'HSC' => 'HOSPITAL SANTA CLARA',
            'HSF' => 'HOSPITAL SÃO FRANCISCO',
            'PPF' => 'PAVILHÃO PEREIRA FILHO',
            'HDJB' => 'HOSPITAL DOM JOÃO BECKER',
            'HSAP' => 'HOSPITAL DE SANTO ANTÔNIO DA PATRULHA',
            'HNT' => 'HOSPITAL NORA TEIXEIRA',
            'MULTICENTROS' => 'MULTICENTROS',
        ];

        if (!is_null($string))
            $sigla = explode(' ', $string, 2)[0];

        if (!array_key_exists($sigla, $hospital))
            return null;

        return $hospital[$sigla];

    }
}

if (!function_exists('dateFormat')) {

    /**
     * Retorna uma data no formato especificado
     *
     * @param $value
     * @param string $format
     * @return string
     * @throws Exception
     */
    function dateFormat($value, $format = 'd/m/Y')
    {

        if (!is_null($value)) {

            if ($format === 'Y-m-d') {
                $value = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $value);
            }

            return (new \Illuminate\Support\Carbon($value))->format($format);

        }
    }
}

if ( !function_exists( 'formatDateTime' ) ) {

    function formatDateTime( $dateTime ): string
    {

        if ( is_null( $dateTime ) )
            return "-";

        return "{$dateTime->format( 'd/m' )} <span class='font-bold text-blue-700'>{$dateTime->format( 'H:i' )}</span>";

    }

}

if ( !function_exists( 'attendanceSession' ) ) {

    function attendanceSession( $attendance, $destroy = false )
    {

        if ( $destroy )
            session()->forget( 'attendance' );
        else
            session( [ 'attendance' => $attendance ] );

    }

}

function extractScaleScore($scaleValue) {
    // Se é null ou vazio, retorna null
    if ($scaleValue === null || $scaleValue === '') {
        return null;
    }

    // Se já é um número, retorna como inteiro
    if (is_numeric($scaleValue)) {
        return intval($scaleValue);
    }

    // Se é string, tenta extrair o número
    if (is_string($scaleValue)) {
        // Padrões de busca por score
        $patterns = [
            '/:\s*(\d+)/',           // "Braden: 15"
            '/Score:\s*(\d+)/',      // "Score: 5"
            '/Pontos:\s*(\d+)/',     // "Pontos: 3"
            '/^(\d+)$/',             // "15" (número puro)
            '/\b(\d+)\b/'            // qualquer número isolado
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $scaleValue, $matches)) {
                return intval($matches[1]);
            }
        }
    }

    return null;
}

/**
 * MEWS Risk Styling
 * ≥5: Crítico (vermelho escuro)
 * 4: Alto (laranja)
 * 3: Alerta (amarelo)
 * 0-2: Normal (cinza claro)
 */
function getMewsRiskStyling($score, $forceNormalText = false) {
    $num = extractScaleScore($score);
    if ($num === null) {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>'text-gray-800'];
    }

    $textColor = $forceNormalText ? 'text-gray-800' : 'text-gray-800';

    if ($num >= 5) {
        return ['bg'=>'bg-red-100','border'=>'border-red-300','text'=>$textColor];
    } elseif ($num == 4) {
        return ['bg'=>'bg-orange-100','border'=>'border-orange-300','text'=>$textColor];
    } elseif ($num == 3) {
        return ['bg'=>'bg-yellow-100','border'=>'border-yellow-300','text'=>$textColor];
    } else {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>$textColor];
    }
}

/**
 * PEWS Risk Styling (Pediátrico)
 * ≥4: Alto (vermelho)
 * 2-3: Moderado (amarelo)
 * 0-1: Normal (cinza claro)
 */
function getPewsRiskStyling($score, $forceNormalText = false) {
    $num = extractScaleScore($score);
    if ($num === null) {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>'text-gray-800'];
    }

    $textColor = $forceNormalText ? 'text-gray-800' : 'text-gray-800';

    if ($num >= 4) {
        return ['bg'=>'bg-red-100','border'=>'border-red-300','text'=>$textColor];
    } elseif ($num >= 2) {
        return ['bg'=>'bg-yellow-100','border'=>'border-yellow-300','text'=>$textColor];
    } else {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>$textColor];
    }
}

/**
 * Braden Risk Styling
 * ≤12: Alto Risco (vermelho)
 * 13-14: Moderado (amarelo)
 * 15-18: Leve (cinza)
 * 19-23: Sem risco (verde claro)
 */
function getBradenRiskStyling($score, $forceNormalText = false) {
    $num = extractScaleScore($score);
    if ($num === null) {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>'text-gray-800'];
    }

    $textColor = $forceNormalText ? 'text-gray-800' : 'text-gray-800';

    if ($num <= 12) {
        return ['bg'=>'bg-red-100','border'=>'border-red-300','text'=>$textColor];
    } elseif ($num <= 14) {
        return ['bg'=>'bg-yellow-100','border'=>'border-yellow-300','text'=>$textColor];
    } elseif ($num <= 18) {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>$textColor];
    } else {
        return ['bg'=>'bg-green-50','border'=>'border-green-300','text'=>$textColor];
    }
}

/**
 * Morse Risk Styling
 * ≥45: Alto (vermelho)
 * 25-44: Moderado (amarelo)
 * <25: Baixo (cinza)
 */
function getMorseRiskStyling($score, $forceNormalText = false) {
    $num = extractScaleScore($score);
    if ($num === null) {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>'text-gray-800'];
    }

    $textColor = $forceNormalText ? 'text-gray-800' : 'text-gray-800';

    if ($num >= 45) {
        return ['bg'=>'bg-red-100','border'=>'border-red-300','text'=>$textColor];
    } elseif ($num >= 25) {
        return ['bg'=>'bg-yellow-100','border'=>'border-yellow-300','text'=>$textColor];
    } else {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>$textColor];
    }
}

/**
 * Pain Scale Styling
 * ≥7: Intensa (vermelho)
 * 4-6: Moderada (amarelo)
 * 1-3: Leve (cinza)
 * 0: Sem dor (verde claro)
 */
function getPainRiskStyling($score, $forceNormalText = false) {
    $num = extractScaleScore($score);
    if ($num === null) {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>'text-gray-800'];
    }

    $textColor = $forceNormalText ? 'text-gray-800' : 'text-gray-800';

    if ($num >= 7) {
        return ['bg'=>'bg-red-100','border'=>'border-red-300','text'=>$textColor];
    } elseif ($num >= 4) {
        return ['bg'=>'bg-yellow-100','border'=>'border-yellow-300','text'=>$textColor];
    } elseif ($num >= 1) {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>$textColor];
    } else {
        return ['bg'=>'bg-green-50','border'=>'border-green-300','text'=>$textColor];
    }
}

/**
 * TEV Risk Styling
 * ≥7: Alto (vermelho)
 * 3-6: Moderado (amarelo)
 * 0-2: Baixo (cinza)
 */
function getTevRiskStyling($score, $forceNormalText = false) {
    $num = extractScaleScore($score);
    if ($num === null) {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>'text-gray-800'];
    }

    $textColor = $forceNormalText ? 'text-gray-800' : 'text-gray-800';

    if ($num >= 7) {
        return ['bg'=>'bg-red-100','border'=>'border-red-300','text'=>$textColor];
    } elseif ($num >= 3) {
        return ['bg'=>'bg-yellow-100','border'=>'border-yellow-300','text'=>$textColor];
    } else {
        return ['bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>$textColor];
    }
}



if (!function_exists('getCurrentShift')) {
    /**
     * Retorna o turno atual como 'M', 'T' ou 'N'
     * Opcionalmente aceita uma $time string parseable pelo Carbon
     */
    function getCurrentShift($time = null)
    {
        try {
            $dt = $time ? \Carbon\Carbon::parse($time) : now();
        } catch (\Exception $e) {
            $dt = now();
        }

        $minutes = $dt->hour * 60 + $dt->minute;

        // Manhã: 07:15 - 13:14 (435 - 794)
        // Tarde: 13:15 - 19:14 (795 - 1154)
        // Noite: 19:15 - 07:14 (1155+ e 0 - 434)
        if ($minutes >= 435 && $minutes <= 794) {
            return 'M';
        } elseif ($minutes >= 795 && $minutes <= 1154) {
            return 'T';
        } else {
            return 'N';
        }
    }
}

if (!function_exists('getShiftDateForSession')) {
    /**
     * Retorna a data correta para a sessão de chat baseada no turno.
     * Para o turno da noite (19h-07h), se estiver entre 00:00 e 07:00,
     * a data deve ser do dia anterior (quando o turno começou).
     *
     * @param \Carbon\Carbon|null $dateTime
     * @return string Data no formato Y-m-d
     */
    function getShiftDateForSession($dateTime = null)
    {
        $dt = $dateTime ? \Carbon\Carbon::parse($dateTime) : now();
        $hour = $dt->hour;

        // Se está entre 00:00 e 06:59, pertence ao turno da noite do dia anterior
        if ($hour >= 0 && $hour < 7) {
            return $dt->copy()->subDay()->toDateString();
        }

        return $dt->toDateString();
    }
}

if (!function_exists('getShiftInfo')) {
    /**
     * Retorna informações completas do turno: shift_id, data_sessao
     * Corrige a lógica do turno da noite que começa em um dia e termina no próximo.
     *
     * Turnos:
     * - Manhã (manha): 07:00 - 13:00
     * - Tarde (tarde): 13:00 - 19:00
     * - Noite (noite): 19:00 - 07:00 (próximo dia)
     *
     * @param \Carbon\Carbon|null $dateTime
     * @return array ['shift' => string, 'date' => string]
     */
    function getShiftInfo($dateTime = null)
    {
        $dt = $dateTime ? \Carbon\Carbon::parse($dateTime) : now();
        $hour = $dt->hour;

        // Determina o turno
        if ($hour >= 7 && $hour < 13) {
            $shift = 'manha';
            $date = $dt->toDateString();
        } elseif ($hour >= 13 && $hour < 19) {
            $shift = 'tarde';
            $date = $dt->toDateString();
        } else {
            // Turno da noite
            $shift = 'noite';
            // Se entre 00:00 e 06:59, o turno começou ontem
            if ($hour >= 0 && $hour < 7) {
                $date = $dt->copy()->subDay()->toDateString();
            } else {
                // Se entre 19:00 e 23:59, o turno começou hoje
                $date = $dt->toDateString();
            }
        }

        return [
            'shift' => $shift,
            'date' => $date
        ];
    }
}

function getMewsCardGradient($score, $isNewPatient = false) {
    $num = extractScaleScore($score);

    // Paciente novo sempre verde
    if ($isNewPatient) {
        return [
            'gradient' => 'from-green-50 to-green-100',
            'border' => 'border-2 border-green-400',
            'text' => 'text-green-800'
        ];
    }

    // Sem MEWS - azul claro padrão
    if ($num === null) {
        return [
            'gradient' => 'from-blue-50 to-blue-100',
            'border' => 'border border-gray-300',
            'text' => 'text-gray-800'
        ];
    }

    // MEWS ≥ 5: Vermelho intenso
    if ($num >= 5) {
        return [
            'gradient' => 'from-red-100 to-red-200',
            'border' => 'border-2 border-red-500',
            'text' => 'text-red-900'
        ];
    }

    // MEWS = 4: Laranja
    if ($num == 4) {
        return [
            'gradient' => 'from-orange-100 to-orange-200',
            'border' => 'border-2 border-orange-500',
            'text' => 'text-orange-900'
        ];
    }

    // MEWS = 3: Amarelo
    if ($num == 3) {
        return [
            'gradient' => 'from-yellow-100 to-yellow-200',
            'border' => 'border-2 border-yellow-500',
            'text' => 'text-yellow-900'
        ];
    }

    // MEWS 0-2: Azul claro (normal)
    return [
        'gradient' => 'from-blue-50 to-blue-100',
        'border' => 'border border-blue-300',
        'text' => 'text-gray-800'
    ];
}

if (!function_exists('getShiftFromTimestamp')) {
    /**
     * Retorna o turno (M/T/N) a partir de um timestamp d/m/Y H:i
     */
    function getShiftFromTimestamp($timestamp)
    {
        try {
            $dt = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $timestamp);
        } catch (\Exception $e) {
            return null;
        }
        $minutes = $dt->hour * 60 + $dt->minute;
        if ($minutes >= 435 && $minutes <= 794) return 'M';
        if ($minutes >= 795 && $minutes <= 1154) return 'T';
        if (($minutes >= 1155 && $minutes <= 1439) || ($minutes >= 0 && $minutes <= 434)) return 'N';
        return null;
    }
}
