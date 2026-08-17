<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\Database;
use PDO;

/**
 * QUOTESERV (Quote Service) IRC System Bot
 * Manages quote storage in MySQL, random quote generation, user creation (/quote), admin editing/deleting, and subscriptions.
 */
class QuoteServ
{
    public const SERVICE_NAME = 'QUOTESERV';

    /**
     * Add a new quote
     */
    public static function addQuote(string $text, string $createdBy = 'Anonymous', ?string $author = null): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['success' => false, 'message' => 'QUOTESERV: Quote text cannot be empty.'];
        }

        $author = $author ? trim($author) : $createdBy;
        $now = time();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("INSERT INTO quotes (quote_text, author, created_by, created_at) VALUES (:text, :author, :by, :time)");
        $success = $stmt->execute([
            ':text' => $text,
            ':author' => $author,
            ':by' => $createdBy,
            ':time' => $now
        ]);

        if ($success) {
            $quoteId = (int)$pdo->lastInsertId();
            return [
                'success' => true,
                'message' => "QUOTESERV: Quote #{$quoteId} successfully created: \"{$text}\" — {$author}",
                'quote_id' => $quoteId
            ];
        }

        return ['success' => false, 'message' => 'QUOTESERV: Failed to save quote to database.'];
    }

    /**
     * Get a random quote
     */
    public static function getRandomQuote(): ?array
    {
        $pdo = Database::getConnection();
        $isMysql = Database::getDriver() === 'mysql';
        $orderSql = $isMysql ? 'RAND()' : 'RANDOM()';

        $stmt = $pdo->query("SELECT id, quote_text, author, created_by, created_at FROM quotes ORDER BY {$orderSql} LIMIT 1");
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Edit an existing quote (Admin functionality)
     */
    public static function editQuote(int $id, string $newText, ?string $newAuthor = null): array
    {
        $newText = trim($newText);
        if ($newText === '') {
            return ['success' => false, 'message' => 'QUOTESERV: New quote text cannot be empty.'];
        }

        $pdo = Database::getConnection();
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM quotes WHERE id = :id");
        $checkStmt->execute([':id' => $id]);
        if ((int)$checkStmt->fetchColumn() === 0) {
            return ['success' => false, 'message' => "QUOTESERV: Quote #{$id} not found."];
        }

        $sql = "UPDATE quotes SET quote_text = :text" . ($newAuthor !== null ? ", author = :author" : "") . " WHERE id = :id";
        $params = [':text' => $newText, ':id' => $id];
        if ($newAuthor !== null) {
            $params[':author'] = $newAuthor;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return ['success' => true, 'message' => "QUOTESERV: Quote #{$id} successfully updated."];
    }

    /**
     * Remove a quote (Admin functionality)
     */
    public static function deleteQuote(int $id): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM quotes WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => "QUOTESERV: Quote #{$id} successfully deleted."];
        }

        return ['success' => false, 'message' => "QUOTESERV: Quote #{$id} not found."];
    }

    /**
     * List all quotes
     *
     * @return array<int, array{id: int, quote_text: string, author: string, created_by: string, created_at: int}>
     */
    public static function listQuotes(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT id, quote_text, author, created_by, created_at FROM quotes ORDER BY id DESC LIMIT 50");
        return $stmt->fetchAll();
    }

    /**
     * Subscribe user to periodic quotes
     */
    public static function subscribe(string $nickname): array
    {
        $nickname = trim($nickname);
        if ($nickname === '') {
            return ['success' => false, 'message' => 'QUOTESERV: Nickname required to subscribe.'];
        }

        $pdo = Database::getConnection();
        $now = time();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotes_subscriptions WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => $nickname]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => true, 'message' => "QUOTESERV: You are already subscribed to periodic random quotes."];
        }

        $insert = $pdo->prepare("INSERT INTO quotes_subscriptions (nickname, subscribed_at) VALUES (:nick, :time)");
        $insert->execute([':nick' => $nickname, ':time' => $now]);

        return ['success' => true, 'message' => "QUOTESERV: Subscribed '{$nickname}' to periodic quotes. You will receive quotes in private chat!"];
    }

    /**
     * Unsubscribe user
     */
    public static function unsubscribe(string $nickname): array
    {
        $nickname = trim($nickname);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("DELETE FROM quotes_subscriptions WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => $nickname]);

        return ['success' => true, 'message' => "QUOTESERV: Unsubscribed '{$nickname}' from periodic quotes."];
    }

    /**
     * Check if user is subscribed
     */
    public static function isSubscribed(string $nickname): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotes_subscriptions WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => trim($nickname)]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
