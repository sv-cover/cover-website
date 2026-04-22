<?php

namespace App\Twig;

use App\Legacy\I18n;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;


class LegacyExtension extends AbstractExtension
{
    public function __construct(
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('trans', [I18n::class, 'translate']),
            new TwigFilter('translate_parts', [I18n::class, 'translateParts']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('__', [I18n::class, 'translate']),
            new TwigFunction('__N', [I18n::class, 'translatePluralize']),
            new TwigFunction('__translate_parts', [I18n::class, 'translateParts']),
            new TwigFunction('login_path', [$this, 'getLoginPath']),
            new TwigFunction('logout_path', [$this, 'getLogoutPath']),
        ];
    }

    public function getLoginPath($referrer = null, $name = 'login')
    {
        if (!isset($referrer))
            $referrer = $_SERVER['REQUEST_URI'];

        return $this->router->generate($name, compact('referrer'));
    }

    public function getLogoutPath($referrer = null, $name = 'logout')
    {
        if (!isset($referrer))
            $referrer = $_SERVER['REQUEST_URI'];

        return $this->router->generate($name, compact('referrer'));
    }
}
