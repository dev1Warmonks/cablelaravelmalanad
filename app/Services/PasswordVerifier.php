<?php

namespace App\Services;

/**
 * Single switch point for password checking.
 *
 * Legacy mode (default): the old CodeIgniter site shares the same database and stores
 * plain-text passwords, so we must compare plain text, but timing-safe.
 * At cut-over (when the old site is retired) set CABLEBILL_HASH_PASSWORDS=true after
 * rehashing the columns; no other code changes.
 */
class PasswordVerifier
{
    public static function check(string $input, ?string $stored): bool
    {
        $stored = (string) $stored;

        if (config('cablebill.hash_passwords')) {
            return password_verify($input, $stored);
        }

        return hash_equals($stored, $input);
    }
}
