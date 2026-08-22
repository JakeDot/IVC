<?php
$content = file_get_contents('src/Signaling/RoomManager.php');

$joinRoomMod = <<< 'PHP'
    public static function joinRoom(string $roomId, string $clientId): array
    {
        if (class_exists('\\Fortress\\IRC\\ChanServ')) {
            $access = \Fortress\IRC\ChanServ::checkAccess($roomId);
            if (!$access['success']) {
                return ['error' => true, 'message' => $access['message']];
            }
            $roomId = $access['base_target'];
        }
PHP;

$content = preg_replace(
    '/public static function joinRoom\(string \$roomId, string \$clientId\): array\s+\{/',
    $joinRoomMod,
    $content
);

file_put_contents('src/Signaling/RoomManager.php', $content);
echo "Patched RoomManager.php\n";
