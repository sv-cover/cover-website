<?php
require_once 'src/framework/member.php'; // for member_full_name

use Twig\Extension\AbstractExtension as TwigAbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;


class I18NTwigExtension extends TwigAbstractExtension
{
    public function getName()
    {
        return 'i18n';
    }

    public function getFilters()
    {
        return [
            new TwigFilter('trans', '__'),
            new TwigFilter('translate_parts', '__translate_parts'),
            new TwigFilter('ordinal', 'ordinal'),
            new TwigFilter('full_name', function($member) {
                return $member ? member_full_name($member) : null;
            }),
            new TwigFilter('personal_full_name', function($member) {
                return $member ? member_full_name($member, BE_PERSONAL) : null;
            }),
            new TwigFilter('full_name_ignore_privacy', function($member) {
                return $member ? member_full_name($member, IGNORE_PRIVACY) : null;
            }),
            new TwigFilter('first_name', 'member_first_name'),
            new TwigFilter('date_relative', 'format_date_relative'),
            new TwigFilter('vformat', 'vsprintf'),
            new TwigFilter('human_join', 'implode_human'),
            new TwigFilter('human_file_size', 'human_file_size'),
            new TwigFilter('flip', 'array_flip'),
            new TwigFilter('values', 'array_values'),
            new TwigFilter('select', 'array_select'),
            new TwigFilter('sum', 'array_sum'),
            new TwigFilter('group_by', function($iters, $property) {
                $groups = [];

                foreach ($iters as $iter)
                    if (!isset($groups[$iter[$property]]))
                        $groups[$iter[$property]] = [$iter];
                    else
                        $groups[$iter[$property]][] = $iter;

                return $groups;
            }),
            new TwigFilter('sort_by', function($iters, ...$args) {
                $sort_args = [];

                foreach ($args as $sort_arg) {
                    if (!preg_match('/^(?P<index>[^\s]+)(?:\s+(?P<order>asc|desc))$/i', $sort_arg, $match))
                        throw new InvalidArgumentException('Cannot parse sort arg: '. $sort_arg);

                    $sort_args[] = array_select($iters, $match['index']);
                    switch ($match['order']) {
                        case 'desc':
                            $sort_args[] = SORT_DESC;
                            break;
                        case 'asc':
                        default:
                            $sort_args[] = SORT_ASC;
                            break;
                    }
                }

                $sort_args[] =& $iters;

                array_multisort(...$sort_args);

                return $iters;
            }),
            new TwigFilter('academic_year', function($date) {
                if ( is_string($date) )
                    $date = new DateTime($date);

                if ($date->format('n') < 9)
                    return $date->format('Y') - 1;

                return $date->format('Y');
            }),
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('__', '__'),
            new TwigFunction('__N', function($singular, $plural, $value, $count = null) {
                if ($count === null) $count = $value;
                return sprintf(_ngettext($singular, $plural, $count), $value);
            }, ['variadic' => true]),
            new TwigFunction('__translate_parts', '__translate_parts'),
            new TwigFunction('get_config_value', 'get_config_value'),
            new TwigFunction('var_dump', function($value) {
                ob_start();
                var_dump($value);
                return '<pre style="text-align: left">' . ob_get_clean() . '</pre>';
            }, ['is_safe' => ['html']])
        ];
    }

    public function getTests()
    {
        return [
            new TwigTest('numeric', 'is_numeric'),
            new TwigTest('instance_of', function($var, $classname) {
                return $var instanceof $classname;
            }),
            new TwigTest('past', function($date) {
                if (!$date)
                    return false;

                if (!($date instanceof DateTime))
                    $date = new DateTime($date);

                return $date < new DateTime();
            }),
            new TwigTest('future', function($date) {
                if (!$date)
                    return false;

                if (!($date instanceof DateTime))
                    $date = new DateTime($date);

                return $date > new DateTime();
            })
        ];
    }
}