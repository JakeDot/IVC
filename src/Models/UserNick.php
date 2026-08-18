<?php

declare(strict_types=1);

namespace Fortress\Models;

/**
 * Model class representing a registered nickname (nameserv_nicks).
 */
class UserNick
{
    private string $nickname;
    private string $passwordHash;
    private ?string $email;
    private int $registeredAt;
    private int $lastSeen;
    private bool $isIdentified;

    public function __construct(
        string $nickname,
        string $passwordHash,
        ?string $email = null,
        ?int $registeredAt = null,
        ?int $lastSeen = null,
        bool $isIdentified = false
    ) {
        $this->nickname = trim($nickname);
        $this->passwordHash = $passwordHash;
        $this->email = $email !== null ? trim($email) : null;
        $this->registeredAt = $registeredAt ?? time();
        $this->lastSeen = $lastSeen ?? time();
        $this->isIdentified = $isIdentified;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): void
    {
        $this->nickname = trim($nickname);
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email !== null ? trim($email) : null;
    }

    public function getRegisteredAt(): int
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(int $registeredAt): void
    {
        $this->registeredAt = $registeredAt;
    }

    public function getLastSeen(): int
    {
        return $this->lastSeen;
    }

    public function setLastSeen(int $lastSeen): void
    {
        $this->lastSeen = $lastSeen;
    }

    public function isIdentified(): bool
    {
        return $this->isIdentified;
    }

    public function setIsIdentified(bool $isIdentified): void
    {
        $this->isIdentified = $isIdentified;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function toArray(): array
    {
        return [
            'nickname' => $this->nickname,
            'password_hash' => $this->passwordHash,
            'email' => $this->email,
            'registered_at' => $this->registeredAt,
            'last_seen' => $this->lastSeen,
            'is_identified' => $this->isIdentified ? 1 : 0,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['nickname'] ?? ''),
            (string)($data['password_hash'] ?? ''),
            isset($data['email']) ? (string)$data['email'] : null,
            isset($data['registered_at']) ? (int)$data['registered_at'] : null,
            isset($data['last_seen']) ? (int)$data['last_seen'] : null,
            !empty($data['is_identified'])
        );
    }

    public static function register(string $nickname, string $password, ?string $email = null): array
    {
        $nickname = trim($nickname);
        if (empty($nickname) || empty($password)) {
            return ['success' => false, 'message' => 'NAMESERV: Nickname and password are required.'];
        }

        if (\Fortress\Database\UserNickRepository::exists($nickname)) {
            return ['success' => false, 'message' => "NAMESERV: Nickname '{$nickname}' is already registered."];
        }

        $passHash = self::hashPassword($password);
        $userNick = new self($nickname, $passHash, $email, null, null, true);

        if (\Fortress\Database\UserNickRepository::save($userNick)) {
            return ['success' => true, 'message' => "NAMESERV: Nickname '{$nickname}' successfully registered and identified."];
        }

        return ['success' => false, 'message' => "NAMESERV: Registration failed due to database error."];
    }

    public static function identify(string $nickname, string $password): array
    {
        $nickname = trim($nickname);
        $userNick = \Fortress\Database\UserNickRepository::findByNickname($nickname);

        if ($userNick === null || !$userNick->verifyPassword($password)) {
            return ['success' => false, 'message' => 'NAMESERV: Password verification failed. Access denied.'];
        }

        \Fortress\Database\UserNickRepository::updateIdentification($userNick->getNickname(), true, time());

        return ['success' => true, 'message' => "NAMESERV: Password accepted. Nickname '{$nickname}' identified."];
    }
}
