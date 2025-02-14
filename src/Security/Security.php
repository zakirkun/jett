<?php

namespace Zakirkun\Jett\Security;

class Security
{
    protected static array $config = [
        'query_timeout' => 30,
        'max_execution_time' => 30,
        'rate_limit' => [
            'enabled' => true,
            'attempts' => 60,
            'decay_minutes' => 1
        ]
    ];

    protected static array $rateLimits = [];

    public static function setConfig(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }

    public static function sanitize($value): string
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function escape($value): string
    {
        if (is_array($value)) {
            return array_map([self::class, 'escape'], $value);
        }
        
        return addslashes($value);
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function verifyToken(string $token, string $storedToken): bool
    {
        return hash_equals($token, $storedToken);
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function rateLimit(string $key, ?int $attempts = null, ?int $decayMinutes = null): bool
    {
        if (!self::$config['rate_limit']['enabled']) {
            return true;
        }

        $attempts = $attempts ?? self::$config['rate_limit']['attempts'];
        $decayMinutes = $decayMinutes ?? self::$config['rate_limit']['decay_minutes'];
        
        $now = time();
        
        if (!isset(self::$rateLimits[$key])) {
            self::$rateLimits[$key] = [
                'attempts' => 0,
                'reset_at' => $now + ($decayMinutes * 60)
            ];
        }

        if (self::$rateLimits[$key]['reset_at'] <= $now) {
            self::$rateLimits[$key] = [
                'attempts' => 0,
                'reset_at' => $now + ($decayMinutes * 60)
            ];
        }

        self::$rateLimits[$key]['attempts']++;

        return self::$rateLimits[$key]['attempts'] <= $attempts;
    }

    public static function getRateLimitRemaining(string $key): int
    {
        if (!isset(self::$rateLimits[$key])) {
            return self::$config['rate_limit']['attempts'];
        }

        return max(0, self::$config['rate_limit']['attempts'] - self::$rateLimits[$key]['attempts']);
    }

    public static function getRateLimitResetTime(string $key): int
    {
        return self::$rateLimits[$key]['reset_at'] ?? time();
    }

    public static function setQueryTimeout(int $seconds): void
    {
        self::$config['query_timeout'] = $seconds;
    }

    public static function setMaxExecutionTime(int $seconds): void
    {
        self::$config['max_execution_time'] = $seconds;
        set_time_limit($seconds);
    }
}
