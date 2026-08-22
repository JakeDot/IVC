<?php
function parseModeStringToArray(string $modeStr): array {
    $modes = [];
    $add = true;
    $i = 0;
    $len = strlen($modeStr);
    $singleCharModes = 'nNSskvoamedtiplb';
    $knownWords = ['operators', 'network', 'raw', 'deltamodes', 'Δmodes', 'modes'];
    
    while ($i < $len) {
        $char = $modeStr[$i];
        if ($char === '+') {
            $add = true;
            $i++;
        } elseif ($char === '-') {
            $add = false;
            $i++;
        } else {
            $nextSign = strcspn($modeStr, "+-", $i);
            $part = substr($modeStr, $i, $nextSign);
            
            if (str_contains($part, '=')) {
                list($key, $val) = explode('=', $part, 2);
                if ($add) {
                    $modes[$key] = $val;
                } else {
                    $modes[$key] = false;
                }
            } else {
                $isCluster = true;
                if (strlen($part) === 1) {
                    $isCluster = true;
                } else {
                    for ($j = 0; $j < strlen($part); $j++) {
                        if (!str_contains($singleCharModes, $part[$j])) {
                            $isCluster = false;
                            break;
                        }
                    }
                }
                if (in_array(strtolower($part), $knownWords)) {
                    $isCluster = false;
                }
                
                if ($isCluster) {
                    for ($j = 0; $j < strlen($part); $j++) {
                         $modes[$part[$j]] = $add ? true : false;
                    }
                } else {
                    $modes[$part] = $add ? true : false;
                }
            }
            $i += $nextSign;
        }
    }
    return $modes;
}

function arrayToModeString(array $modes): string {
    $singleTrue = '';
    $singleFalse = '';
    $wordTrue = '';
    $wordFalse = '';
    $valModes = '';
    
    foreach ($modes as $k => $v) {
        $isWord = strlen($k) > 1;
        
        if ($v === false) {
            if ($isWord) $wordFalse .= '+' . $k . '=false'; // Actually for standard it might be -word, but let's just use -word
            else $singleFalse .= $k;
        } elseif ($v === true) {
            if ($isWord) $wordTrue .= '+' . $k;
            else $singleTrue .= $k;
        } else {
            $valModes .= '+' . $k . '=' . $v;
        }
    }
    
    // Actually, for $v === false, we want -word
    $wordFalse = '';
    foreach ($modes as $k => $v) {
        $isWord = strlen($k) > 1;
        if ($v === false) {
            if ($isWord) $wordFalse .= '-' . $k;
        }
    }
    
    $res = '';
    if ($singleTrue !== '') $res .= '+' . $singleTrue;
    if ($singleFalse !== '') $res .= '-' . $singleFalse;
    $res .= $wordTrue . $wordFalse . $valModes;
    
    return $res;
}

$parsed = parseModeStringToArray('+tnm+word1-mno-word2');
print_r($parsed);

$res = arrayToModeString($parsed);
echo $res . "\n";
