<?php

declare(strict_types=1);

namespace cx\ivc\IRC\Serv;

use cx\ivc\IRC\IrcObject;
use cx\ivc\IRC\NoModeTrait;

/**
 * TEXTSERV (Text Manipulation Service) IRC System Bot
 * Provides basic text manipulation functions natively.
 */
class TextServ extends IrcObject
{
    use NoModeTrait;

    public const SERVICE_NAME = 'TEXTSERV';

    public static function process(string $command, string $text): array
    {
        $command = strtoupper(trim($command));
        $text    = trim($text);

        switch ($command) {
            case 'UPPER':
                $res = strtoupper($text);
                break;
            case 'LOWER':
                $res = strtolower($text);
                break;
            case 'REVERSE':
                $res = strrev($text);
                break;
            case 'LENGTH':
                $res = (string)strlen($text);
                break;
            case 'BASE64':
                $res = base64_encode($text);
                break;
            case 'UNBASE64':
                $res = base64_decode($text) ?: 'Invalid base64';
                break;
            default:
                $res = "TEXTSERV: Unknown command. Available: UPPER, LOWER, REVERSE, LENGTH, BASE64, UNBASE64";
                break;
        }

        return ['success' => true, 'message' => $res];
    }
}
