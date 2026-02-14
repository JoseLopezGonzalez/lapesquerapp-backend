<?php

/**
 * Polyfill for mb_trim, mb_ltrim, mb_rtrim (PHP 8.4+) for use with Scribe on PHP < 8.4.
 * Loaded via composer autoload "files".
 */

if (! function_exists('mb_trim')) {
    function mb_trim(string $string, ?string $characters = null, ?string $encoding = null): string
    {
        $encoding ??= mb_internal_encoding();
        if ($characters === null || $characters === '') {
            return preg_replace('/^[\s\p{Z}\p{C}\x{00A0}]+|[\s\p{Z}\p{C}\x{00A0}]+$/u', '', $string) ?? $string;
        }
        $pattern = '[' . preg_quote($characters, '/') . ']+';
        return preg_replace('/^' . $pattern . '|' . $pattern . '$/u', '', $string) ?? $string;
    }
}

if (! function_exists('mb_ltrim')) {
    function mb_ltrim(string $string, ?string $characters = null, ?string $encoding = null): string
    {
        $encoding ??= mb_internal_encoding();
        if ($characters === null || $characters === '') {
            return preg_replace('/^[\s\p{Z}\p{C}\x{00A0}]+/u', '', $string) ?? $string;
        }
        $pattern = '[' . preg_quote($characters, '/') . ']+';
        return preg_replace('/^' . $pattern . '/u', '', $string) ?? $string;
    }
}

if (! function_exists('mb_rtrim')) {
    function mb_rtrim(string $string, ?string $characters = null, ?string $encoding = null): string
    {
        $encoding ??= mb_internal_encoding();
        if ($characters === null || $characters === '') {
            return preg_replace('/[\s\p{Z}\p{C}\x{00A0}]+$/u', '', $string) ?? $string;
        }
        $pattern = '[' . preg_quote($characters, '/') . ']+';
        return preg_replace('/' . $pattern . '$/u', '', $string) ?? $string;
    }
}
