<?php

declare(strict_types=1);

namespace Fortress\IRC\Objects;

use Fortress\IRC\IrcObject;
use Fortress\IRC\ModeEntry;

/**
 * Network — the global unnamed IRC object (£).
 *
 * Server-wide configuration is stored as a single mode string in the
 * settings table under the key '£', e.g.:
 *
 *   +§maxchans=500+§motd=Welcome+§flood_limit=10
 *
 * Use Network::set('§maxchans', '500') and Network::get('§maxchans')
 * instead of SettingsManager / IrcSetting for server-scope properties.
 *
 * Only IRCops may mutate modes on £.
 */
class Network extends IrcObject
{
    /** Canonical DB key for the £ object's mode string. */
    public const OBJECT_KEY = '£';

    // ---------------------------------------------------------------
    // IrcObject abstract implementation
    // ---------------------------------------------------------------

    protected static function isAuthorizedToSetModes(string $target, string $requesterNick): bool
    {
        // Only IRCops may change server-wide configuration.
        return \Fortress\IRC\Serv\ServServ::isOper($requesterNick);
    }

    protected static function isTargetRegistered(string $target): bool
    {
        // £ is always "registered" — it is the network itself.
        return true;
    }

    protected static function getModesFromDb(string $target): ?string
    {
        $setting = \Fortress\Database\SettingRepository::findByKey(self::OBJECT_KEY);
        return $setting?->getSettingValue();
    }

    protected static function updateModesInDb(string $target, string $modes): void
    {
        $setting = \Fortress\Database\SettingRepository::findByKey(self::OBJECT_KEY);
        if ($setting !== null) {
            $setting->setSettingValue($modes);
            $setting->setUpdatedAt(time());
        } else {
            $setting = new \Fortress\Models\IrcSetting(self::OBJECT_KEY, $modes, 'Network object mode string (£)');
        }
        \Fortress\Database\SettingRepository::save($setting);
    }

    protected static function createAndSaveDefault(string $target, string $modes, string $requesterNick): void
    {
        // £ is always registered; this path is unreachable, but satisfies the contract.
        static::updateModesInDb($target, $modes);
    }

    protected static function getTargetNameForMessage(string $target): string
    {
        return '£ (Network)';
    }

    // ---------------------------------------------------------------
    // Convenience API — preferred over SettingsManager for server config
    // ---------------------------------------------------------------

    /**
     * Get the value of a server property.
     *
     *   Network::get('§maxchans');        // returns '500' or null
     *   Network::get('§maxchans', '256'); // returns default when unset
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $modesStr = static::getModesFromDb(self::OBJECT_KEY) ?? '';
        $parsed   = static::parseModeStringToArray($modesStr);
        $entry    = $parsed[$key] ?? null;
        return ($entry instanceof ModeEntry) ? ($entry->val ?? $default) : $default;
    }

    /**
     * Set a server property (requires IRCop).
     *
     *   Network::set('§maxchans', '500', $operNick);
     */
    public static function set(string $key, string $value, string $requesterNick = ''): array
    {
        return static::setModes(self::OBJECT_KEY, "+{$key}={$value}", $requesterNick);
    }

    /**
     * Unset a server property (requires IRCop).
     *
     *   Network::unset('§maxchans', $operNick);
     */
    public static function unset(string $key, string $requesterNick = ''): array
    {
        return static::setModes(self::OBJECT_KEY, "-{$key}", $requesterNick);
    }

    /**
     * Return all active server properties as a flat key => value array.
     *
     *   Network::all(); // ['§maxchans' => '500', '§motd' => 'Welcome', ...]
     */
    public static function all(): array
    {
        $modesStr = static::getModesFromDb(self::OBJECT_KEY) ?? '';
        $parsed   = static::parseModeStringToArray($modesStr);
        $out      = [];
        foreach ($parsed as $k => $v) {
            if (($v instanceof ModeEntry) && $v->plus && $v->val !== null) {
                $out[$k] = $v->val;
            }
        }
        return $out;
    }
}
