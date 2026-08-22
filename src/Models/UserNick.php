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
    private ?string $subscriptionTier;
    private string $subscriptionStatus;
    private int $subscriptionExpiresAt;
<<<<<<< HEAD
=======
    private ?string $vhost;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

    public function __construct(
        string $nickname,
        string $passwordHash,
        ?string $email = null,
        ?int $registeredAt = null,
        ?int $lastSeen = null,
        bool $isIdentified = false,
        ?string $subscriptionTier = null,
        string $subscriptionStatus = 'none',
<<<<<<< HEAD
        int $subscriptionExpiresAt = 0
=======
        int $subscriptionExpiresAt = 0,
        ?string $vhost = 'users.ivc.cx'
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    ) {
        $this->nickname = trim($nickname);
        $this->passwordHash = $passwordHash;
        $this->email = $email !== null ? trim($email) : null;
        $this->registeredAt = $registeredAt ?? time();
        $this->lastSeen = $lastSeen ?? time();
        $this->isIdentified = $isIdentified;
        $this->subscriptionTier = $subscriptionTier;
        $this->subscriptionStatus = strtolower(trim($subscriptionStatus));
        $this->subscriptionExpiresAt = $subscriptionExpiresAt;
<<<<<<< HEAD
=======
        $this->vhost = $vhost !== null ? trim($vhost) : 'users.ivc.cx';
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): void
    {
        $this->nickname = trim($nickname);
    }

<<<<<<< HEAD
=======
    public function getVhost(): ?string
    {
        return $this->vhost;
    }

    public function setVhost(?string $vhost): void
    {
        $this->vhost = $vhost !== null ? trim($vhost) : 'users.ivc.cx';
    }

    public function getCustomDomain(): ?string
    {
        return $this->getVhost();
    }

    public function setCustomDomain(?string $customDomain): void
    {
        $this->setVhost($customDomain);
    }

    public function getBaseUser(): string
    {
        $atPos = strrpos($this->nickname, '@');
        if ($atPos !== false && $atPos > 0) {
            return substr($this->nickname, 0, $atPos);
        }
        return $this->nickname;
    }

    public function getDomain(): string
    {
        if (!empty($this->vhost)) {
            return $this->vhost;
        }
        $atPos = strrpos($this->nickname, '@');
        if ($atPos !== false && $atPos > 0) {
            $dom = trim(substr($this->nickname, $atPos + 1));
            if ($dom !== '' && $dom !== '<anonymous>' && filter_var($dom, FILTER_VALIDATE_IP) === false) {
                return $dom;
            }
        }
        return '<anonymous>';
    }

    public function getStandardizedUsername(): string
    {
        $base = $this->getBaseUser();
        $domain = $this->getDomain();
        return "{$base}@{$domain}";
    }

    public static function parseIdent(string $identString): array
    {
        $identString = trim($identString);
        if ($identString === '') {
            return [
                'user' => 'anonymous',
                'domain' => '<anonymous>',
                'standardized' => 'anonymous@<anonymous>'
            ];
        }

        $atPos = strrpos($identString, '@');
        if ($atPos !== false && $atPos > 0) {
            $user = trim(substr($identString, 0, $atPos));
            $domain = trim(substr($identString, $atPos + 1));

            if ($user === '') {
                $user = 'anonymous';
            }

            if ($domain === '' || $domain === '<anonymous>' || filter_var($domain, FILTER_VALIDATE_IP) !== false) {
                $domain = '<anonymous>';
            }

            return [
                'user' => $user,
                'domain' => $domain,
                'standardized' => "{$user}@{$domain}"
            ];
        }

        // Single name or raw IP or @nick
        return [
            'user' => $identString,
            'domain' => '<anonymous>',
            'standardized' => "{$identString}@<anonymous>"
        ];
    }

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
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

    public function getSubscriptionTier(): ?string
    {
        return $this->subscriptionTier;
    }

    public function setSubscriptionTier(?string $subscriptionTier): void
    {
        $this->subscriptionTier = $subscriptionTier;
    }

    public function getSubscriptionStatus(): string
    {
        return $this->subscriptionStatus;
    }

    public function setSubscriptionStatus(string $subscriptionStatus): void
    {
        $this->subscriptionStatus = strtolower(trim($subscriptionStatus));
    }

    public function getSubscriptionExpiresAt(): int
    {
        return $this->subscriptionExpiresAt;
    }

    public function setSubscriptionExpiresAt(int $subscriptionExpiresAt): void
    {
        $this->subscriptionExpiresAt = $subscriptionExpiresAt;
    }

    public function isPremium(): bool
    {
        return in_array($this->subscriptionStatus, ['active', 'trialing'], true) && $this->subscriptionExpiresAt > time();
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
            'subscription_tier' => $this->subscriptionTier,
            'subscription_status' => $this->subscriptionStatus,
            'subscription_expires_at' => $this->subscriptionExpiresAt,
            'is_premium' => $this->isPremium() ? 1 : 0,
<<<<<<< HEAD
=======
            'vhost' => $this->vhost,
            'custom_domain' => $this->vhost,
            'domain' => $this->getDomain(),
            'standardized_username' => $this->getStandardizedUsername(),
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
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
            !empty($data['is_identified']),
            isset($data['subscription_tier']) ? (string)$data['subscription_tier'] : null,
            (string)($data['subscription_status'] ?? 'none'),
<<<<<<< HEAD
            isset($data['subscription_expires_at']) ? (int)$data['subscription_expires_at'] : 0
=======
            isset($data['subscription_expires_at']) ? (int)$data['subscription_expires_at'] : 0,
            isset($data['vhost']) ? (string)$data['vhost'] : (isset($data['custom_domain']) ? (string)$data['custom_domain'] : (isset($data['domain']) ? (string)$data['domain'] : null))
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
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
