<?php
$content = file_get_contents('src/IRC/ChanServ.php');
$checkAccess = <<< 'PHP'
    public static function checkAccess(string $target): array
    {
        $parsed = self::parseTargetAndModes($target);
        $channel = self::normalizeChannelName($parsed['base_target']);
        $suppliedKey = $parsed['mode_flags']['k'] ?? null;

        $chanModel = \Fortress\Database\ChannelRepository::findByChannelName($channel);
        if ($chanModel !== null) {
            $currentModes = self::parseModeStringToArray($chanModel->getModes());
            if (!empty($currentModes['k'])) {
                if ($suppliedKey !== $currentModes['k']) {
                    return ['success' => false, 'message' => "CHANSERV: Channel '{\$channel}' is protected. Query mode +k=pass is required."];
                }
            }
        }
        return ['success' => true, 'base_target' => $channel];
    }
PHP;

$content = str_replace(
    'public static function getInfo(string $channel): array',
    $checkAccess . "\n\n    public static function getInfo(string $channel): array",
    $content
);

$getInfoMod = <<< 'PHP'
    public static function getInfo(string $channel): array
    {
        $access = self::checkAccess($channel);
        if (!$access['success']) {
            return $access;
        }
        $channel = $access['base_target'];
PHP;

$content = preg_replace(
    '/public static function getInfo\(string \$channel\): array\s+\{\s+\$channel = self::normalizeChannelName\(\$channel\);/s',
    $getInfoMod,
    $content
);

file_put_contents('src/IRC/ChanServ.php', $content);
echo "Patched ChanServ.php\n";
