<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/IRC/IrcServices.php';

use Fortress\IRC\IrcServices;

function testModeParsing(string $command, string $expectedChan, string $expectedMode, string $expectedUser) {
    $parts = preg_split('/\s+/', $command);
    $channel = '#default';

    $modeArgs = IrcServices::parseModeCommandContext($parts, $channel);

    $targetChan = $modeArgs['targetChan'];
    $modeStr = $modeArgs['modeStr'];
    $targetUser = $modeArgs['targetUser'];

    $pass = ($targetChan === $expectedChan && $modeStr === $expectedMode && $targetUser === $expectedUser);

    if (!$pass) {
        echo "FAIL: $command\n";
        echo "  Expected: chan=$expectedChan, mode=$expectedMode, user=$expectedUser\n";
        echo "  Got     : chan=$targetChan, mode=$modeStr, user=$targetUser\n";
    } else {
        echo "PASS: $command\n";
    }
    return $pass;
}

$tests = [
    ['/mode #feed +v', '#feed', '+v', ''],
    ['/mode +v #feed', '#feed', '+v', ''],
    ['/mode #feed +v Alice', '#feed', '+v', 'Alice'],
    ['/mode +v #feed Alice', '#feed', '+v', 'Alice'],
    ['/mode +v Alice', '#default', '+v', 'Alice'],
    ['/mode -t', '#default', '-t', ''],
    ['/mode', '#default', '', ''],
    ['/mode +o Bob', '#default', '+o', 'Bob'],
    ['/mode Bob +v', '#default', '+v', 'Bob'],
    ['/mode Bob', '#default', '', 'Bob'],
    ['/mode #feed', '#feed', '', ''],
];

$allPassed = true;
foreach ($tests as $t) {
    if (!testModeParsing($t[0], $t[1], $t[2], $t[3])) {
        $allPassed = false;
    }
}

exit($allPassed ? 0 : 1);
