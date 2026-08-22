<?php
$file = 'src/IRC/IrcServices.php';
$content = file_get_contents($file);
$replacement = <<<PHP
    public static function processCommand(string \$senderNick, string \$channel, string \$text): ?array
    {
        \$text = trim(\$text);
        if (\$text === '') {
            return null;
        }

        // Apply global "me" aliases
        \$text = preg_replace('/(^|\s)@me(?=\s|$)/i', '\$1' . \$senderNick, \$text);
        \$text = preg_replace('/(^|\s)#me(?=\s|$)/i', '\$1' . \$channel, \$text);
        \$text = preg_replace('/(^|\s)\\\$me(?=\s|$)/i', '\$1server', \$text);
        \$text = preg_replace('/(^|\s)£me(?=\s|$)/i', '\$1' . (\$channel ?: '£'), \$text);

PHP;
$content = preg_replace('/public static function processCommand\(string \$senderNick, string \$channel, string \$text\): \?array\s*\{\s*\$text = trim\(\$text\);\s*if \(\$text === \'\'\) \{\s*return null;\s*\}/s', $replacement, $content);
file_put_contents($file, $content);
echo "Patched\n";
