<?php
require_once 'src/framework/form.php';
require_once 'src/framework/markup.php';

use Twig\Extension\AbstractExtension as TwigAbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;


class HTMLTwigExtension extends TwigAbstractExtension
{
    public function getName()
    {
        return 'html';
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('html_email', [__CLASS__, 'email'], ['is_variadic' => true, 'is_safe' => ['html']]),
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('parse_markup', 'markup_parse', ['is_safe' => ['html']]),
            new TwigFilter('strip_markup', 'markup_strip'),
            new TwigFilter('excerpt', 'text_excerpt')
        ];
    }

    static public function email($email, array $arguments = [])
    {
        return sprintf('<a href="mailto:%s">%s</a>',
            markup_format_attribute($email),
            markup_format_text($email));
    }
}