<?php

declare(strict_types=1);

namespace Fortress\IRC;

/**
 * Represents a single parsed mode entry.
 *
 * Flag-only modes:  ->plus / ->minus are set; ->val is null.
 * Key=value modes:  ->val holds the value string; ->plus indicates it is set.
 */
class ModeEntry
{
    public bool   $plus  = false;
    public bool   $minus = false;
    public ?string $val  = null;

    public function __construct(bool $plus = false, bool $minus = false, ?string $val = null)
    {
        $this->plus  = $plus;
        $this->minus = $minus;
        $this->val   = $val;
    }
}

/**
 * Provides no-op implementations of the IrcObject mode abstracts.
 *
 * Use this in IRC service classes that have no per-target mode concept
 * (e.g. MemoServ, HelpServ). The six abstract methods are satisfied with
 * safe, inert defaults so the class can still call setModes() / parseModeFlags()
 * if it ever needs to inspect an ad-hoc mode string.
 */
trait NoModeTrait
{
    protected static function isAuthorizedToSetModes(string $target, string $requesterNick): bool
    {
        return false;
    }

    protected static function isTargetRegistered(string $target): bool
    {
        return false;
    }

    protected static function getModesFromDb(string $target): ?string
    {
        return null;
    }

    protected static function updateModesInDb(string $target, string $modes): void
    {
        // No-op: this service does not maintain a per-target mode string.
    }

    protected static function createAndSaveDefault(string $target, string $modes, string $requesterNick): void
    {
        // No-op.
    }

    protected static function getTargetNameForMessage(string $target): string
    {
        return static::SERVICE_NAME;
    }
}

abstract class IrcObject
{
    /**
     * Parse an IRC mode string into an associative array.
     *
     * Returns:
     *   $modes[$key]        → ModeEntry  (canonical per-key entry)
     *   $modes['+' . $key]  → true       (shorthand: key is set)
     *   $modes['-' . $key]  → true       (shorthand: key is unset)
     *   $modes['0' . $key]  → true       (shorthand: key is cleared)
     *
     * @return array<string, ModeEntry|bool>
     */
    public static function parseModeStringToArray(string $modeStr): array {
        $modes = [];
        $modifier = '+';
        $i = 0;
        $len = strlen($modeStr);
        $singleCharModes = 'nNsSOoAaIiVvkmMedtiplbrR$';
        $knownWords = ['operators', 'network', 'raw', 'deltamodes', 'Δmodes', 'modes'];
        
        while ($i < $len) {
            $char = $modeStr[$i];
            if ($char === '+' || $char === '-' || $char === '0') {
                $modifier = $char;
                $i++;
            } else {
                $nextSign = strcspn($modeStr, "+-0", $i);
                $part = substr($modeStr, $i, $nextSign);
                
                if (str_contains($part, '=')) {
                    $parts = explode('=', $part, 2);
                    $key = $parts[0];
                    $val = $parts[1];

                    if (!isset($modes[$key]) || !($modes[$key] instanceof ModeEntry)) {
                        $modes[$key] = new ModeEntry();
                    }
                    if ($modifier === '+') {
                        $modes[$key]->plus = true;
                        $modes[$key]->val  = $val;
                        $modes['+' . $key] = true;
                    } elseif ($modifier === '-') {
                        $modes[$key]->minus = true;
                        $modes['-' . $key]  = true;
                    } else {
                        $modes[$key]       = new ModeEntry();
                        $modes['0' . $key] = true;
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
                             $c = $part[$j];
                             if (!isset($modes[$c]) || !($modes[$c] instanceof ModeEntry)) {
                                 $modes[$c] = new ModeEntry();
                             }
                             if ($modifier === '+') { $modes[$c]->plus  = true; $modes['+' . $c] = true; }
                             elseif ($modifier === '-') { $modes[$c]->minus = true; $modes['-' . $c] = true; }
                             else { $modes[$c] = new ModeEntry(); $modes['0' . $c] = true; }
                        }
                    } else {
                        if (!isset($modes[$part]) || !($modes[$part] instanceof ModeEntry)) {
                            $modes[$part] = new ModeEntry();
                        }
                        if ($modifier === '+') { $modes[$part]->plus  = true; $modes['+' . $part] = true; }
                        elseif ($modifier === '-') { $modes[$part]->minus = true; $modes['-' . $part] = true; }
                        else { $modes[$part] = new ModeEntry(); $modes['0' . $part] = true; }
                    }
                }
                $i += $nextSign;
            }
        }
        return $modes;
    }

    public static function arrayToModeString(array $modes): string {
        $singleTrue  = '';
        $singleFalse = '';
        $valModes    = '';
        
        foreach ($modes as $k => $v) {
            if (!($v instanceof ModeEntry) || str_starts_with($k, '+') || str_starts_with($k, '-') || str_starts_with($k, '0')) continue;
            
            if ($v->val !== null) {
                if ($v->plus) {
                    $valModes .= '+' . $k . '=' . $v->val;
                }
            } else {
                if ($v->plus)  $singleTrue  .= $k;
                if ($v->minus) $singleFalse .= $k;
            }
        }
        
        $res = '';
        if ($singleTrue  !== '') $res .= '+' . $singleTrue;
        if ($singleFalse !== '') $res .= '-' . $singleFalse;
        $res .= $valModes;
        return $res;
    }

    public static function parseModeFlags(string $modeStr): array
    {
        $arr = self::parseModeStringToArray($modeStr);
        $flags = [
            'n'          => !empty($arr['+n']),
            'N'          => !empty($arr['+N']),
            'S'          => !empty($arr['+S']),
            's'          => !empty($arr['+s']),
            'k'          => !empty($arr['+k']) ? ($arr['k']->val ?? true) : false,
            'v'          => !empty($arr['+v']),
            'V'          => !empty($arr['+V']),
            'o'          => !empty($arr['+o']),
            'O'          => !empty($arr['+O']),
            'a'          => !empty($arr['+a']),
            'A'          => !empty($arr['+A']),
            'm'          => !empty($arr['+m']),
            'e'          => !empty($arr['+e']),
            'd'          => !empty($arr['+d']),
            't'          => !empty($arr['+t']),
            'no_t'       => empty($arr['+t']),
            'i'          => !empty($arr['+i']) || !empty($arr['+I']),
            'I'          => !empty($arr['+i']) || !empty($arr['+I']),
            'r'          => !empty($arr['+r']) || !empty($arr['+R']),
            'R'          => !empty($arr['+r']) || !empty($arr['+R']),
            '$'          => !empty($arr['+$']),
            'raw'        => !empty($arr['+raw']),
            'delta_modes' => !empty($arr['+delta_modes']) || !empty($arr['+deltamodes']) || !empty($arr['+Δmodes']) || !empty($arr['+Δ']),
        ];

        // Merge backward-compat flat keys: $arr[$key] => value|true|false
        foreach ($arr as $k => $v) {
            if (($v instanceof ModeEntry) && !str_starts_with($k, '+') && !str_starts_with($k, '-') && !str_starts_with($k, '0')) {
                if ($v->val !== null) {
                    $arr[$k] = $v->val;
                } elseif ($v->plus) {
                    $arr[$k] = true;
                } elseif ($v->minus) {
                    $arr[$k] = false;
                }
            }
        }

        return array_merge($flags, $arr);
    }

    protected static abstract function isAuthorizedToSetModes(string $target, string $requesterNick): bool;
    protected static abstract function isTargetRegistered(string $target): bool;
    protected static abstract function getModesFromDb(string $target): ?string;
    protected static abstract function updateModesInDb(string $target, string $modes): void;
    protected static abstract function createAndSaveDefault(string $target, string $modes, string $requesterNick): void;
    protected static abstract function getTargetNameForMessage(string $target): string;
    
    public static function setModes(string $target, string $modes, string $requesterNick = ''): array
    {
        if (!empty($requesterNick) && static::isTargetRegistered($target) && !static::isAuthorizedToSetModes($target, $requesterNick)) {
            $name = static::getTargetNameForMessage($target);
            return ['success' => false, 'message' => "Permission denied. Cannot set modes for {$name}."];
        }

        if (static::isTargetRegistered($target)) {
            $currentModesStr = static::getModesFromDb($target) ?? '';
            $currentModesArr = static::parseModeStringToArray($currentModesStr);
            $newOperations   = static::parseModeStringToArray($modes);
            
            foreach ($newOperations as $k => $v) {
                if (!($v instanceof ModeEntry) || str_starts_with($k, '+') || str_starts_with($k, '-') || str_starts_with($k, '0')) continue;

                if (!isset($currentModesArr[$k]) || !($currentModesArr[$k] instanceof ModeEntry)) {
                    $currentModesArr[$k] = new ModeEntry();
                }

                if (!empty($newOperations['0' . $k])) {
                    $currentModesArr[$k] = new ModeEntry();
                } else {
                    if ($v->plus)  $currentModesArr[$k]->plus  = true;
                    if ($v->minus) $currentModesArr[$k]->minus = true;
                    if ($v->val !== null) $currentModesArr[$k]->val = $v->val;
                }
            }

            $currentModes = static::arrayToModeString($currentModesArr);
            static::updateModesInDb($target, $currentModes);
            $modes = $currentModes;
        } else {
            static::createAndSaveDefault($target, $modes, $requesterNick);
        }

        $name = static::getTargetNameForMessage($target);
        return ['success' => true, 'message' => "Modes for {$name} updated to {$modes}.", 'modes' => $modes];
    }
}

// Network has been extracted to IRC/Objects/Network.php
