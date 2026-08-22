const fs = require('fs');
let c = fs.readFileSync('src/IRC/IrcServices.php', 'utf8');

c = c.replace(
    /\$posSec = mb_strpos\(\$input, '§'\);\s+\$posDelta = mb_strpos\(\$input, '∆'\);\s+\$firstSubPos = null;\s+if \(\$posSec !== false && \$posDelta !== false\) \{\s+\$firstSubPos = min\(\$posSec, \$posDelta\);\s+\} elseif \(\$posSec !== false\) \{\s+\$firstSubPos = \$posSec;\s+\} elseif \(\$posDelta !== false\) \{\s+\$firstSubPos = \$posDelta;\s+\}/,
    `$firstSubPos = null;
        if (preg_match('/[§∆Δ]/u', $input, $matches, PREG_OFFSET_CAPTURE)) {
            $firstSubPos = mb_strlen(substr($input, 0, $matches[0][1]));
        }`
);

c = c.replace(
    /preg_split\('\/\(\[§∆\]\)\/u', \$subStr/,
    "preg_split('/([§∆Δ])/u', $subStr"
);

c = c.replace(
    /if \(\$symbol !== '§' && \$symbol !== '∆'\) \{/,
    "if ($symbol !== '§' && $symbol !== '∆' && $symbol !== 'Δ') {"
);

c = c.replace(
    /\$res\['message'\] \?\? \$res/,
    "is_array($res) ? $res['message'] : $res"
);

c = c.replace(
    /case 'REGISTER':\s+\$pass = \$args\[0\] \?\? '';\s+\$email = \$args\[1\] \?\? null;\s+\$res = NameServ::register\(\$senderNick, \$pass, \$email\);\s+break;/g,
    `case 'REGISTER':
                $pass = $args[0] ?? '';
                $email = $args[1] ?? null;
                $res = NameServ::register($senderNick, $pass, $email);
                
                if (is_array($res) && !empty($res['success']) && $email !== null && str_contains($email, '@')) {
                    self::handleIdentCommand($senderNick, $channel, ['/ident', $email]);
                    ChanServ::setModes('@me', '+ri', $senderNick);
                    $res['message'] .= " Automatically set +ri modes and identified with {$email}.";
                }
                break;`
);

fs.writeFileSync('src/IRC/IrcServices.php', c);
