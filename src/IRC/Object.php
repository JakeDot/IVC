<?php

declare(strict_types=1);

namespace cx\ivc\IRC;

/**
 * Represents a single parsed mode entry.
 *
 * Flag-only modes:  ->plus / ->minus are set; ->val is null.
 * Key=value modes:  ->val holds the value string; ->plus indicates it is set.
 */


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
        return \cx\ivc\IRC\ChanServ::parseModeStringToArray($modeStr);
    }

    public static function arrayToModeString(array $modes): string {
        return \cx\ivc\IRC\ChanServ::arrayToModeString($modes);
    }

    public static function parseModeFlags(string $modeStr): array
    {
        $arr = self::parseModeStringToArray($modeStr);
        $flags = [
            'n' => isset($arr['+n']) || isset($arr['n']),
            'N' => isset($arr['+N']) || isset($arr['N']),
            'S' => isset($arr['+S']) || isset($arr['S']),
            's' => isset($arr['+s']) || isset($arr['s']),
            'k' => isset($arr['+k']) ? $arr['+k'] : (isset($arr['k']) ? $arr['k'] : false),
            'v' => isset($arr['+v']) || isset($arr['v']),
            'V' => isset($arr['+V']) || isset($arr['V']),
            'o' => isset($arr['+o']) || isset($arr['o']),
            'O' => isset($arr['+O']) || isset($arr['O']),
            'a' => isset($arr['+a']) || isset($arr['a']),
            'A' => isset($arr['+A']) || isset($arr['A']),
            'm' => isset($arr['+m']) || isset($arr['m']),
            'e' => isset($arr['+e']) || isset($arr['e']),
            'd' => isset($arr['+d']) || isset($arr['d']),
            't' => isset($arr['+t']) || isset($arr['t']),
            'no_t' => !isset($arr['+t']) && !isset($arr['t']) && isset($arr['-t']),
            'i' => isset($arr['+i']) || isset($arr['+I']) || isset($arr['i']) || isset($arr['I']),
            'I' => isset($arr['+i']) || isset($arr['+I']) || isset($arr['i']) || isset($arr['I']),
            'r' => isset($arr['+r']) || isset($arr['+R']) || isset($arr['r']) || isset($arr['R']),
            'R' => isset($arr['+r']) || isset($arr['+R']) || isset($arr['r']) || isset($arr['R']),
            '$' => isset($arr['+$']) || isset($arr['$']),
            'raw' => isset($arr['+raw']) || isset($arr['raw']),
            'delta_modes' => isset($arr['+delta_modes']) || isset($arr['+deltamodes']) || isset($arr['+Δmodes']) || isset($arr['+Δ']) || isset($arr['delta_modes']) || isset($arr['deltamodes']) || isset($arr['Δmodes']) || isset($arr['Δ']),
        ];

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
                if (str_starts_with($k, '~')) {
                    $keyName = substr($k, 1);
                    unset($currentModesArr['+' . $keyName]);
                    unset($currentModesArr['-' . $keyName]);
                    unset($currentModesArr[$keyName]); // legacy
                } else if (str_starts_with($k, '+')) {
                    $keyName = substr($k, 1);
                    unset($currentModesArr['-' . $keyName]);
                    $currentModesArr[$k] = $v;
                } else if (str_starts_with($k, '-')) {
                    $keyName = substr($k, 1);
                    unset($currentModesArr['+' . $keyName]);
                    $currentModesArr[$k] = $v;
                } else {
                    if ($v === false) {
                        unset($currentModesArr[$k]);
                        unset($currentModesArr['+' . $k]);
                        $currentModesArr['-' . $k] = true;
                    } else {
                        unset($currentModesArr['-' . $k]);
                        $currentModesArr['+' . $k] = $v;
                    }
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
