<?php
/**
 * UserState.php
 * --------------
 * In-memory per-user state for the multi-step upload/broadcast flows.
 * Lives only for the life of the PHP process, same scope as Python's
 * context.user_data in the polling version of this bot.
 */

final class UserState
{
    /** @var array<int, array<string, mixed>> */
    private static array $store = [];

    public static function get(int $userId): array
    {
        return self::$store[$userId] ?? [];
    }

    public static function set(int $userId, string $key, mixed $value): void
    {
        self::$store[$userId][$key] = $value;
    }

    public static function value(int $userId, string $key, mixed $default = null): mixed
    {
        return self::$store[$userId][$key] ?? $default;
    }

    public static function clear(int $userId): void
    {
        unset(self::$store[$userId]);
    }
}
