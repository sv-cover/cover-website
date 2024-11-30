<?php

namespace App\Twig;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;


class AppExtension extends AbstractExtension
{
    public function __construct(
        private UrlGeneratorInterface $router,
        private ContainerBagInterface $params,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_application', [$this, 'formatApplication']), // Used by (device) sessions
            new TwigFilter('hostname', fn($url) => \parse_url($url, \PHP_URL_HOST)), // Used in profile widget
            new TwigFilter('safe_phone_number_format', [$this, 'safePhoneNumberFormat']),
            new TwigFilter('academic_year', [$this, 'academicYear']), // Used by events macros
        ];
    }

    public function getFunctions(): array
    {
        return [
            // new TwigFunction('login_path', [$this, 'get_login_path']),
            // new TwigFunction('logout_path', [$this, 'get_logout_path']),
            // new TwigFunction('get_config_value', [$this, 'get_config_value']), // TODO: find better place
        ];
    }

    public function getTests(): array
    {
        return [
            // new TwigTest('numeric', 'is_numeric'),
            new TwigTest('instanceof', function($var, $classname) {
                return $var instanceof $classname;
            }),
            // new TwigTest('past', function($date) {
            //     if (!$date)
            //         return false;

            //     if (!($date instanceof DateTime))
            //         $date = new DateTime($date);

            //     return $date < new DateTime();
            // }),
            // new TwigTest('future', function($date) {
            //     if (!$date)
            //         return false;

            //     if (!($date instanceof DateTime))
            //         $date = new DateTime($date);

            //     return $date > new DateTime();
            // })
        ];
    }

    public function academicYear(\DateTime|string $date): string
    {
        if ( is_string($date) )
            $date = new \DateTime($date);

        if ($date->format('n') < 9)
            return $date->format('Y') - 1;

        return $date->format('Y');
    }

    public function formatApplication(string $application): string
    {
        $known_browsers = [
            'Firefox' => 'Firefox',
            'Microsoft Edge (Legacy)' => 'Edge',
            'Microsoft Edge' => 'Edg',
            'Internet Explorer' => 'MSIE',
            'IE Mobile' => 'IEMobile',
            'iPad' => 'iPad',
            'Android' => 'Android',
            'Google Chrome' => 'Chrome',
            'Safari' => 'Safari',
            'iCal calendar feed' => 'calendar'
        ];

        foreach ($known_browsers as $name => $hint)
            if (\stripos($application, $hint) !== false)
                return $name;

        return \ucwords($application);
    }

    /**
     * Format a string as a phone number or return it on failure. Alternative to
     * libphonenumber's phone_number_format filter, which raises an exception if
     * an malformed phonenumber is provided.
     * Ultimately, we should make sure the DB only contains valid phone numbers,
     * but this will take care of issues in the mean time.
     */
    public function safePhoneNumberFormat(string $phoneNumber, string $format, string $defaultCountry = 'NL'): string
    {
        try {
            $format = \constant('\libphonenumber\PhoneNumberFormat::' . $format);
            $phoneUtil = PhoneNumberUtil::getInstance();
            $_phoneNumber = $phoneUtil->parse($phoneNumber, $defaultCountry);
            return $phoneUtil->format($_phoneNumber, $format);
        } catch (\libphonenumber\NumberParseException $e) {
            return $phoneNumber;
        }
    }
}
