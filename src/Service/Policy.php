<?php

namespace App\Service;

use App\Legacy\Policy\PolicyInterface;
use App\Legacy\Policy\PolicyNotFoundException;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Policy as Policies;
use App\Service\Authentication;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class Policy
{
    const asdf = 10;
    const string MODEL_NAMESPACE = 'App\DataModel\\';
    const string MODEL_PREFIX = 'App\DataModel\DataModel';

    private array $policies = [];
    private array $models = [];

    public function __construct(
        private Authentication $auth,
        #[AutowireIterator('app.policy', defaultIndexMethod: 'getSupportedModel')]
        iterable $policies,
        #[AutowireIterator('app.data-model')]
        iterable $models,
    ) {
        $this->policies = $policies instanceof \Traversable ? \iterator_to_array($policies) : $policies;

        foreach ($models as $model) {
            $name = (new \ReflectionClass($model))->getShortName();
            $this->models[$name] = $model;
        }
    }

    public function get(string|DataIter|DataModel $model, ?string $original_model_name = null): PolicyInterface
    {
        if (is_string($model) && (str_contains($model, 'DataModel') || str_contains($model, 'DataIter')))
            $modelName = preg_replace('{^.*(DataIter|DataModel)}', '', $model);
        elseif (is_string($model))
            $modelName = $model;
        elseif (is_subclass_of($model, DataModel::class))
            $modelName = substr((new \ReflectionClass($model))->getShortName(), strlen('DataModel'));
        elseif (is_subclass_of($model, DataIter::class))
            $modelName = substr((new \ReflectionClass($model))->getShortName(), strlen('DataIter'));
        elseif ($model instanceof DataIter)
            return $this->get($model->get_model());

        if (isset($this->policies[self::MODEL_PREFIX . $modelName]))
            return $this->policies[self::MODEL_PREFIX . $modelName];

        // But when the policy is not found, try the parent class
        $parent = get_parent_class($model);
        if ($parent !== false && is_subclass_of($parent, DataIter::class))
            return $this->get($parent, $modelName);

        // If there is no parent class, or the parent class is not DataIter, then give up.
        throw new PolicyNotFoundException(sprintf("Policy for '%s' not found.", $modelName));
    }

    private function checkPolicy(string $check, DataIter|string $iter): bool
    {
        if (is_string($iter)) {
            if (!str_starts_with($iter, 'DataModel'))
                $iter = 'DataModel' . $iter;

            if (!isset($this->models[$iter]))
                throw new \InvalidArgumentException(sprintf(__("Could not find the model %s"), $iter));

            return call_user_func([$this->get($iter), $check], $this->models[$iter]->new_iter());
        }
        return call_user_func([$this->get($iter), $check], $iter);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (
            strlen('userCan') < strlen($name)
            && substr($name, 0, strlen('userCan')) == 'userCan'
        )
            return $this->checkPolicy($name, ...$arguments);
    }

    public function __isset($name): bool
    {
        return strlen('userCan') < strlen($name)
            && substr($name, 0, strlen('userCan')) == 'userCan';
    }
}
