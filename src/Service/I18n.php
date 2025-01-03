<?php

namespace App\Service;

/**
 * Service for internationalization (i18n). The purpose of this service is to
 * provide backwards compatibility. The website has defaulted to English since
 * 2018, but the code still uses some of the functions.
 */
class I18n
{
    const string LANGUAGE = 'en';
    const string LOCALE = 'en_US';

    protected static bool $isInitialized = false;

    protected static function init(): void
    {
        /* Set language to use (locale needs to be available on OS) */
        putenv('LANG=' . self::LOCALE . '.UTF-8');
        setlocale(LC_ALL,  self::LOCALE . '.UTF-8');

        // Specify location of translation tables
        bindtextdomain('cover-web', realpath(dirname(__FILE__) . '/../../translations'));

        // Choose domain
        textdomain('cover-web');

        static::$isInitialized = true;
    }

    public static function getLanguage(): string
    {
        return self::LANGUAGE;
    }

    public static function translate($message)
    {
        if (!static::$isInitialized)
            static::init();

        if ($message == '')
            return '';

        return gettext($message);
    }

    public static function translateParts($message, $separators = ',')
    {
        $pattern = '/(\s*[' . preg_quote($separators, '/') . ']+\s*)/';
        $parts = preg_split($pattern, $message, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as &$part)
            if (!preg_match($pattern, $part))
                $part = self::translate($part);

        return implode('', $parts);
    }

    public static function _translatePluralize($singular, $plural, $number)
    {

        return ngettext($singular, $plural, $number);
    }

    public static function translatePluralize($singular, $plural, $number, $count = null)
    {
        if (!static::$isInitialized)
            static::init();

        if ($count === null)
            $count = $number;

        return sprintf(ngettext($singular, $plural, $count), $number);
    }
}
