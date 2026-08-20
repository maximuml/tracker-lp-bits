<?php

// Extracts global function definitions from a PHP file.
// Usage: php scripts/extract_functions.php <file>

$file = $argv[1] ?? '';
if (! $file || ! is_readable($file)) {
    fwrite(STDERR, "Usage: php extract_functions.php <file>\n");
    exit(1);
}

$code = file_get_contents($file);
$tokens = token_get_all($code);

$functions = [];
$count = count($tokens);
$i = 0;
$braceDepth = 0;
$currentFunc = null;

for ($i = 0; $i < $count; $i++) {
    $token = $tokens[$i];
    if (! is_array($token)) {
        $token = [$token, $token, -1];
    }
    $id = $token[0];
    $text = $token[1];
    $line = $token[2];

    if ($id === T_FUNCTION) {
        // Skip anonymous functions
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }
        if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
            $funcName = $tokens[$j][1];
            $funcLine = $tokens[$j][2];

            // Find parameter list
            $k = $j + 1;
            while ($k < $count && $tokens[$k] !== '(') {
                $k++;
            }
            $openParen = $k;
            $pDepth = 0;
            $params = [];
            $currentParam = ['name' => null, 'type' => null, 'default' => null];
            for ($k = $openParen + 1; $k < $count; $k++) {
                $t = $tokens[$k];
                if ($t === '(') {
                    $pDepth++;

                    continue;
                }
                if ($t === ')') {
                    if ($pDepth === 0) {
                        if ($currentParam['name']) {
                            $params[] = $currentParam;
                        }
                        break;
                    }
                    $pDepth--;

                    continue;
                }
                if ($pDepth > 0) {
                    continue;
                }
                if ($t === ',') {
                    if ($currentParam['name']) {
                        $params[] = $currentParam;
                    }
                    $currentParam = ['name' => null, 'type' => null, 'default' => null];

                    continue;
                }
                if (is_array($t)) {
                    if ($t[0] === T_VARIABLE) {
                        $currentParam['name'] = substr($t[1], 1);
                    } elseif ($t[0] === T_STRING || $t[0] === T_ARRAY || $t[0] === T_CALLABLE) {
                        if ($currentParam['name'] === null) {
                            $currentParam['type'] = ($currentParam['type'] ?? '').$t[1];
                        } else {
                            $currentParam['default'] = ($currentParam['default'] ?? '').$t[1];
                        }
                    } elseif (in_array(strtolower($t[1] ?? ''), ['null', 'true', 'false'], true) || $t[0] === T_LNUMBER || $t[0] === T_DNUMBER || $t[0] === T_CONSTANT_ENCAPSED_STRING) {
                        $currentParam['default'] = ($currentParam['default'] ?? '').$t[1];
                    } elseif ($t[0] === T_WHITESPACE) {
                        if ($currentParam['type'] !== null && $currentParam['name'] === null) {
                            $currentParam['type'] .= $t[1];
                        } elseif ($currentParam['name'] !== null) {
                            $currentParam['default'] = ($currentParam['default'] ?? '').$t[1];
                        }
                    }
                }
            }

            // Find opening brace and body (skip return type hints like : string, : bool, etc.)
            $k++;
            while ($k < $count && $tokens[$k] !== '{') {
                $t = $tokens[$k];
                if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_ARRAY], true)) {
                    $k++;

                    continue;
                }
                if ($t === ':' || $t === '?') {
                    $k++;

                    continue;
                }
                break;
            }
            if ($k < $count && $tokens[$k] === '{') {
                $openBrace = $k;
                $bDepth = 1;
                $bodyStart = $tokens[$k + 1][2] ?? ($line + 1);
                for ($k = $openBrace + 1; $k < $count; $k++) {
                    if ($tokens[$k] === '{') {
                        $bDepth++;
                    } elseif ($tokens[$k] === '}') {
                        $bDepth--;
                        if ($bDepth === 0) {
                            $closeBrace = $k;
                            break;
                        }
                    }
                }
                if (isset($closeBrace)) {
                    $body = '';
                    $firstToken = null;
                    $prevWasSpace = true;
                    for ($b = $openBrace + 1; $b < $closeBrace; $b++) {
                        $bt = $tokens[$b];
                        if (is_array($bt)) {
                            if (in_array($bt[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                                continue;
                            }
                            if ($bt[0] === T_WHITESPACE) {
                                if (! $prevWasSpace && $body !== '') {
                                    $body .= ' ';
                                    $prevWasSpace = true;
                                }

                                continue;
                            }
                            if ($firstToken === null) {
                                $firstToken = token_name($bt[0]).':'.$bt[1];
                            }
                            $body .= $bt[1];
                            $prevWasSpace = false;
                        } else {
                            if ($firstToken === null) {
                                $firstToken = $bt;
                            }
                            $body .= $bt;
                            $prevWasSpace = false;
                        }
                    }
                    $closeLine = is_array($tokens[$closeBrace]) ? $tokens[$closeBrace][2] : $line;

                    $functions[] = [
                        'name' => $funcName,
                        'line' => $funcLine,
                        'start_line' => $funcLine,
                        'end_line' => $closeLine,
                        'params' => array_map(fn ($p) => ['name' => $p['name'], 'type' => trim($p['type'] ?? ''), 'default' => $p['default']], $params),
                        'body_one_line' => trim(preg_replace('/\s+/', ' ', $body)),
                        'first_token' => $firstToken,
                    ];
                }
            }
        }
    }
}

echo json_encode($functions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
