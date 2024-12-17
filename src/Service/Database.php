<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class Database
{
    private $db;
    private $models = [];

    public function __construct(
        #[AutowireIterator('app.data-model')]
        iterable $models,
    ) {
        foreach ($models as $model) {
            $name = (new \ReflectionClass($model))->getShortName();
            $this->models[$name] = $model;
        }
    }

    public function getModel(string $name): mixed
    {
        if (!isset($this->models[$name]))
            throw new \InvalidArgumentException(sprintf(__("Could not find the model %s"), $name));

        return $this->models[$name];
    }
}
