<?php

declare(strict_types=1);

namespace App\Core;

final class I18n
{
    private static array $catalogues = [];

    public static function translate(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = self::normalize($locale ?? self::locale());
        $fallback = self::fallbackLocale();
        $value = self::get($locale, $key) ?? self::get($fallback, $key) ?? self::get('en', $key) ?? $key;

        foreach ($replace as $name => $replacement) {
            $value = str_replace(':' . $name, (string) $replacement, $value);
        }

        return $value;
    }

    public static function locale(): string
    {
        return self::normalize((string) (Session::get('user_locale') ?? Session::get('company_locale') ?? env('APP_LOCALE', 'es')));
    }

    public static function available(): array
    {
        return ['es', 'en'];
    }

    private static function fallbackLocale(): string
    {
        $locale = strtolower(substr((string) env('APP_FALLBACK_LOCALE', 'en'), 0, 2));

        return in_array($locale, self::available(), true) ? $locale : 'en';
    }

    private static function get(string $locale, string $key): ?string
    {
        $catalogue = self::catalogue($locale);
        $value = $catalogue;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }

    private static function catalogue(string $locale): array
    {
        if (isset(self::$catalogues[$locale])) {
            return self::$catalogues[$locale];
        }

        $directory = base_path('lang/' . $locale);
        $catalogue = [];

        foreach (glob($directory . '/*.php') ?: [] as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            $items = require $path;

            if (! is_array($items)) {
                continue;
            }

            if ($name === 'app') {
                $catalogue = array_replace_recursive($catalogue, $items);
                continue;
            }

            $catalogue[$name] = array_replace_recursive($catalogue[$name] ?? [], $items);
        }

        self::$catalogues[$locale] = $catalogue;

        return self::$catalogues[$locale];
    }

    private static function normalize(string $locale): string
    {
        $locale = strtolower(substr($locale, 0, 2));

        return in_array($locale, self::available(), true) ? $locale : 'es';
    }
}
