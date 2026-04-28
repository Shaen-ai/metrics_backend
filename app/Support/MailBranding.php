<?php

namespace App\Support;

/**
 * Customer-facing labels in email must never expose internal/repo names containing "mebel".
 */
final class MailBranding
{
    public static function fallbackLabel(): string
    {
        $v = trim((string) env('MAIL_BRAND_NAME', 'Tunzone'));

        return $v !== '' ? $v : 'Tunzone';
    }

    /** True if the value contains "mebel" (case-insensitive). */
    public static function containsMebel(string $value): bool
    {
        return (bool) preg_match('/mebel/ui', $value);
    }

    /**
     * Drop whitespace-separated tokens that contain "mebel"; trim. May be empty.
     */
    public static function stripMebelTokens(string $value): string
    {
        $parts = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return '';
        }
        $kept = array_values(array_filter(
            $parts,
            static fn (string $w) => ! preg_match('/mebel/ui', $w)
        ));

        return implode(' ', $kept);
    }

    /**
     * Prefer the cleaned string; if nothing remains, use {@see fallbackLabel()}.
     */
    public static function sanitizeDisplayName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return self::fallbackLabel();
        }
        if (! self::containsMebel($value)) {
            return $value;
        }
        $stripped = self::stripMebelTokens($value);

        return $stripped !== '' ? $stripped : self::fallbackLabel();
    }

    public static function configuredFromDisplayName(): string
    {
        $fallback = self::fallbackLabel();
        $raw = env('MAIL_FROM_NAME');
        if ($raw === null || $raw === '') {
            return $fallback;
        }
        $trimmed = trim((string) $raw, " \t\n\r\0\x0B\"'");
        if ($trimmed === '${APP_NAME}') {
            return self::sanitizeDisplayName((string) env('APP_NAME', $fallback));
        }

        return self::sanitizeDisplayName($trimmed);
    }

    public static function configuredReplyToDisplayName(): ?string
    {
        $raw = env('MAIL_REPLY_TO_NAME');
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }
        $trimmed = trim((string) $raw, " \t\n\r\0\x0B\"'");

        return self::sanitizeDisplayName($trimmed);
    }

    /**
     * For greetings: remove tokens containing "mebel"; if nothing left, use a neutral English form.
     */
    public static function greetingName(string $name): string
    {
        $clean = self::stripMebelTokens(trim($name));

        return $clean !== '' ? $clean : 'there';
    }
}
