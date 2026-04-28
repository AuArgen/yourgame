<?php

namespace App;

class Lang {
    private static array $strings = [];
    private static string $locale = 'ru';

    public static function setLocale(string $locale): void {
        $locale = in_array($locale, ['ru', 'ky'], true) ? $locale : 'ru';
        if ($locale !== self::$locale || empty(self::$strings)) {
            self::$locale = $locale;
            self::$strings = [];
            self::load();
        }
    }

    public static function getLocale(): string {
        return self::$locale;
    }

    public static function get(string $key, array $params = []): string {
        if (empty(self::$strings)) {
            self::load();
        }
        $string = self::$strings[$key] ?? $key;
        foreach ($params as $k => $v) {
            $string = str_replace(':' . $k, (string) $v, $string);
        }
        return $string;
    }

    private static function load(): void {
        $file = dirname(__DIR__) . '/lang/' . self::$locale . '.php';
        if (file_exists($file)) {
            self::$strings = require $file;
        }
    }
}
