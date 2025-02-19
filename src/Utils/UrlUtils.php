<?php

namespace App\Utils;

use Symfony\Component\HttpFoundation\RequestStack;

final class UrlUtils
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function isSameDomain($subdomain, $domain, $levels = 2)
    {
        $sub = explode('.', $subdomain);
        $top = explode('.', $domain);

        $levels = min($levels, count($sub), count($top));

        while ($levels-- > 0)
            if (array_pop($sub) != array_pop($top))
                return false;

        return true;
    }

    public function validateRedirect($url, $allowSubdomains = false, $allowExternal = false)
    {
        $parts = parse_url($url);

        $url = '';

        $isValidExternal = (
            $allowExternal
            && isset($parts['host'])
            && !((bool) ip2long($parts['host'])) // Disallow bare IP address
        );

        $request = $this->requestStack->getCurrentRequest();

        $isValidSubdomain = (
            $allowSubdomains
            && isset($parts['host'])
            && self::isSameDomain($parts['host'], $request->getHttpHost())
        );

        if ($isValidExternal || $isValidSubdomain)
            $url = '//' . $parts['host'];

        if (isset($parts['path']))
            $url .= $parts['path'];

        if (isset($parts['query']))
            $url .= '?' . $parts['query'];

        if (isset($parts['fragment']))
            $url .= '#' . $parts['fragment'];

        return $url;
    }
}
