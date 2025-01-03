<?php

namespace App\Twig;

use App\Legacy\Database\DataModel;
use App\Service\I18n;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;


class LegacyExtension extends AbstractExtension
{
    private array $models = [];

    public function __construct(
        private UrlGeneratorInterface $router,
        #[AutowireIterator('app.data-model')]
        iterable $models,
    ) {
        foreach ($models as $model) {
            $name = (new \ReflectionClass($model))->getShortName();
            $this->models[$name] = $model;
        }
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('trans', [I18n::class, 'translate']),
            new TwigFilter('translate_parts', [I18n::class, 'translateParts']),
            // new TwigFilter('vformat', 'vsprintf'),
            // new TwigFilter('flip', 'array_flip'),
            // new TwigFilter('values', 'array_values'),
            // new TwigFilter('select', 'array_select'),
            // new TwigFilter('sum', 'array_sum'),
            // new TwigFilter('group_by', function($iters, $property) {
            //     $groups = [];

            //     foreach ($iters as $iter)
            //         if (!isset($groups[$iter[$property]]))
            //             $groups[$iter[$property]] = [$iter];
            //         else
            //             $groups[$iter[$property]][] = $iter;

            //     return $groups;
            // }),
            // new TwigFilter('sort_by', function($iters, ...$args) {
            //     $sort_args = [];

            //     foreach ($args as $sort_arg) {
            //         if (!preg_match('/^(?P<index>[^\s]+)(?:\s+(?P<order>asc|desc))$/i', $sort_arg, $match))
            //             throw new InvalidArgumentException('Cannot parse sort arg: '. $sort_arg);

            //         $sort_args[] = array_select($iters, $match['index']);
            //         switch ($match['order']) {
            //             case 'desc':
            //                 $sort_args[] = SORT_DESC;
            //                 break;
            //             case 'asc':
            //             default:
            //                 $sort_args[] = SORT_ASC;
            //                 break;
            //         }
            //     }

            //     $sort_args[] =& $iters;

            //     array_multisort(...$sort_args);

            //     return $iters;
            // }),
            // new TwigFilter('academic_year', function($date) {
            //     if ( is_string($date) )
            //         $date = new DateTime($date);

            //     if ($date->format('n') < 9)
            //         return $date->format('Y') - 1;

            //     return $date->format('Y');
            // }),
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
            new TwigFunction('model', [$this, 'getModel']),
        ];
    }

    public function getTests(): array
    {
        return [
            // new TwigTest('numeric', 'is_numeric'),
            // new TwigTest('instance_of', function($var, $classname) {
            //     return $var instanceof $classname;
            // }),
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

    public function getModel($name): DataModel
    {
        if (!str_starts_with($name, 'DataModel'))
            $name = 'DataModel' . $name;

        if (!isset($this->models[$name]))
            throw new \InvalidArgumentException(sprintf(__("Could not find the model %s"), $name));

        return $this->models[$name];
    }
}
