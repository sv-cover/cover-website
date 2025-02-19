<?php

namespace App;

use App\Legacy\Database\DataModel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();
        \date_default_timezone_set($this->getContainer()->getParameter('app.timezone'));
    }

    public function process(ContainerBuilder $container): void
    {
        // Clear tag on DataModel. Unfortunately, this is the only way to make it work.
        $container->getDefinition(DataModel::class)->clearTag('app.data-model');
    }
}
