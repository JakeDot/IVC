<?php

declare(strict_types=1);

namespace Fortress\IRC;

/**
 * HELPSERV (Help Service) IRC System Bot
 * Provides detailed help and syntax information for all available IRC services.
 */
class HelpServ
{
    public const SERVICE_NAME = 'HELPSERV';

    /**
     * Get help for a specific topic or list available topics
     */
    public static function getHelp(string $topic = ''): array
    {
        $topic = strtoupper(trim($topic));

        $helpDocs = [
            'NICKSERV' => "NICKSERV Commands:\n" .
                          "• REGISTER <password> [email] — Registers your current nickname.\n" .
                          "• IDENTIFY <password> — Identifies you to your registered nickname.\n" .
                          "• SET §domain=<custom.domain.com> — Sets your custom domain property (§domain).\n" .
                          "• INFO [nickname] — Displays information about a registered nickname.\n" .
                          "• /ident [user@custom.domain.com] — Identifies or sets custom domain.\n" .
                          "• /who [channel|nickname] — Shows users with their standardized user@domain format.\n" .
                          "• /whois <nickname> — Displays comprehensive user & domain identification info.",

            'CHANSERV' => "CHANSERV Commands:\n" .
                          "• REGISTER <#channel> [passkey] — Registers a channel under your nickname.\n" .
                          "• OP <#channel> <nickname> — Gives operator privileges (+o) to a user.\n" .
                          "• DEOP <#channel> <nickname> — Removes operator privileges (-o) from a user.\n" .
                          "• VOICE <#channel> <nickname> — Gives voice privileges (+v) to a user.\n" .
                          "• DEVOICE <#channel> <nickname> — Removes voice privileges (-v) from a user.\n" .
                          "• MODE <#channel> <modes> — Sets channel modes (e.g., +s for secret, +m for moderated, +t for topic protection).\n" .
                          "• TOPIC <#channel> <new topic> — Changes the channel topic.\n" .
                          "• INFO <#channel> — Displays information about a registered channel.",

            'MOTDSERV' => "MOTDSERV Commands:\n" .
                          "• INFO / GET — Displays the current Message of the Day.\n" .
                          "• SET <new message> — Updates the Message of the Day (Server Admin only).",

            'MEMOSERV' => "MEMOSERV Commands:\n" .
                          "• SEND <nickname> <message> — Sends an offline memo to a user.\n" .
                          "• READ [memo_id] — Reads a specific memo (or the first unread one).\n" .
                          "• LIST — Lists all your received memos.\n" .
                          "• DELETE / DEL <memo_id> — Deletes a specific memo.",

            'HOSTSERV' => "HOSTSERV Commands:\n" .
                          "• REQUEST <vhost> — Requests a virtual host (e.g. user.network.net).\n" .
                          "• ON — Activates your assigned virtual host.\n" .
                          "• OFF — Deactivates your assigned virtual host.\n" .
                          "• INFO [nickname] — Displays vhost information for a user.",

            'SERVSERV' => "SERVSERV Commands:\n" .
                          "• LIST — Lists all available network and foreign services.\n" .
                          "• REGISTER <name> <host> <endpoint> — Registers a foreign service.\n" .
                          "• INFO <service_name> — Displays information about a service.\n" .
                          "• COMMAND <service_name> <command> — Sends a command to a foreign service."
        ];

        if ($topic === '') {
            $msg = "HELPSERV: Available help topics:\n" .
                   "• " . implode("\n• ", array_keys($helpDocs)) . "\n" .
                   "Type /msg HELPSERV <topic> for more information on a specific service.";
            return ['success' => true, 'message' => $msg];
        }

        if (isset($helpDocs[$topic])) {
            return ['success' => true, 'message' => $helpDocs[$topic]];
        }

        return ['success' => false, 'message' => "HELPSERV: No help available for topic '{$topic}'."];
    }
}
