<?php

namespace App\Twig;

use App\Service\Markup;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;


class MarkupExtension extends AbstractExtension
{
    public function __construct(
        private Markup $markup,
    ) {
    }

    public function getFilters()
    {
        return [
            new TwigFilter('parse_markup', [$this->markup, 'parse'], ['is_safe' => ['html']]),
            new TwigFilter('strip_markup', [$this->markup, 'strip']),
        ];
    }
}
