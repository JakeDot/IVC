<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\Database;
use PDO;

/**
 * QUOTESERV (Quote Service) IRC System Bot
 * Manages creation, editing, deletion, subscriber periodic quotes, and random quote retrieval stored in MySQL / SQLite database.
 */
class QuoteServ
{
    public const SERVICE_NAME = 'QUOTESERV';

    /**
     * Add a new quote to the database
     */
    public static function addQuote(string $text, string $createdBy): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['success' => false, 'message' => 'QUOTESERV: Quote text cannot be empty.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO quotes (quote_text, created_by, created_at) VALUES (:text, :by, :time)");
        $time = time();
        $stmt->execute([
            ':text' => $text,
            ':by' => $createdBy,
            ':time' => $time
        ]);

        $id = (int)$pdo->lastInsertId();

        return [
            'success' => true,
            'message' => "QUOTESERV: Quote #{$id} added successfully.",
            'id' => $id,
            'quote_text' => $text,
            'created_by' => $createdBy
        ];
    }

    /**
     * Edit an existing quote by ID (Admin functionality)
     */
    public static function editQuote(int $id, string $newText, string $requester = ''): array
    {
        $newText = trim($newText);
        if ($newText === '') {
            return ['success' => false, 'message' => 'QUOTESERV: Updated quote text cannot be empty.'];
        }

        $pdo = Database::getConnection();
        $stmtCheck = $pdo->prepare("SELECT id FROM quotes WHERE id = :id");
        $stmtCheck->execute([':id' => $id]);
        if (!$stmtCheck->fetch()) {
            return ['success' => false, 'message' => "QUOTESERV: Quote #{$id} not found."];
        }

        $stmt = $pdo->prepare("UPDATE quotes SET quote_text = :text WHERE id = :id");
        $stmt->execute([':text' => $newText, ':id' => $id]);

        return [
            'success' => true,
            'message' => "QUOTESERV: Quote #{$id} has been updated."
        ];
    }

    /**
     * Delete an existing quote by ID (Admin functionality)
     */
    public static function deleteQuote(int $id, string $requester = ''): array
    {
        $pdo = Database::getConnection();
        $stmtCheck = $pdo->prepare("SELECT id FROM quotes WHERE id = :id");
        $stmtCheck->execute([':id' => $id]);
        if (!$stmtCheck->fetch()) {
            return ['success' => false, 'message' => "QUOTESERV: Quote #{$id} not found."];
        }

        $stmt = $pdo->prepare("DELETE FROM quotes WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return [
            'success' => true,
            'message' => "QUOTESERV: Quote #{$id} has been removed."
        ];
    }

    /**
     * Retrieve a specific quote by ID
     */
    public static function getQuote(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Retrieve a random quote from the database
     */
    public static function getRandomQuote(): ?array
    {
        $pdo = Database::getConnection();
        $driver = Database::getDriver();
        $randomFunc = ($driver === 'sqlite') ? 'RANDOM()' : 'RAND()';

        $stmt = $pdo->query("SELECT * FROM quotes ORDER BY {$randomFunc} LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * List recent quotes
     */
    public static function listQuotes(int $limit = 20): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM quotes ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Subscribe user to periodic quotes
     */
    public static function subscribe(string $nickname): array
    {
        $nickname = trim($nickname);
        if ($nickname === '') {
            return ['success' => false, 'message' => 'QUOTESERV: Invalid nickname for subscription.'];
        }

        $pdo = Database::getConnection();
        $stmtCheck = $pdo->prepare("SELECT nickname FROM quotes_subscriptions WHERE nickname = :nick");
        $stmtCheck->execute([':nick' => $nickname]);
        if ($stmtCheck->fetch()) {
            return ['success' => true, 'message' => "QUOTESERV: You are already subscribed to periodic quotes."];
        }

        $stmt = $pdo->prepare("INSERT INTO quotes_subscriptions (nickname, subscribed_at) VALUES (:nick, :time)");
        $stmt->execute([
            ':nick' => $nickname,
            ':time' => time()
        ]);

        return [
            'success' => true,
            'message' => "QUOTESERV: You have successfully subscribed to periodic quotes! You will receive random quotes in private chat."
        ];
    }

    /**
     * Unsubscribe user from periodic quotes
     */
    public static function unsubscribe(string $nickname): array
    {
        $nickname = trim($nickname);
        if ($nickname === '') {
            return ['success' => false, 'message' => 'QUOTESERV: Invalid nickname.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM quotes_subscriptions WHERE nickname = :nick");
        $stmt->execute([':nick' => $nickname]);

        return [
            'success' => true,
            'message' => "QUOTESERV: You have unsubscribed from periodic quotes."
        ];
    }

    /**
     * Check if a user is subscribed
     */
    public static function isSubscribed(string $nickname): bool
    {
        $nickname = trim($nickname);
        if ($nickname === '') {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotes_subscriptions WHERE nickname = :nick");
        $stmt->execute([':nick' => $nickname]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Get list of all subscribed nicks
     */
    public static function getSubscribers(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT nickname FROM quotes_subscriptions ORDER BY nickname ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
