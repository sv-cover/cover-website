<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;


class I18nExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'i18n';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('trans', '__'),
            new TwigFilter('translate_parts', '__translate_parts'),
            new TwigFilter('ordinal', 'ordinal'),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('__', '__'),
            new TwigFunction('__N', function($singular, $plural, $value, $count = null) {
                if ($count === null) $count = $value;
                return sprintf(_ngettext($singular, $plural, $count), $value);
            }, ['variadic' => true]),
            new TwigFunction('__translate_parts', '__translate_parts'),
        ];
    }
}
