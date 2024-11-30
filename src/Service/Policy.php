<?php

namespace App\Service;

use App\Legacy\Policy\PolicyInterface;
use App\Legacy\Policy\PolicyNotFoundException;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Policy as Policies;
use App\Service\Authentication;
use App\Service\Database;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class Policy
{
    const string MODEL_PREFIX = 'DataModel';

    private array $policies = [];

    public function __construct(
        private Authentication $auth,
        private Database $db,
        #[AutowireIterator('app.policy', defaultIndexMethod: 'getSupportedModel')]
        iterable $policies,
    ) {
        $this->policies = $policies instanceof \Traversable ? \iterator_to_array($policies) : $policies;
    }

    public function get(string|DataIter|DataModel $model, ?string $original_model_name = null): PolicyInterface
    {
        if (is_string($model)) {// Providing a name, e.g. DataIterFotoboek
            if (substr($model, 0, strlen('DataModel')) == 'DataModel')
                $modelName = substr($model, strlen('DataModel'));
            elseif (substr($model, 0, strlen('DataIter')) == 'DataIter')
                $modelName = substr($model, strlen('DataIter'));
            else
                throw new \InvalidArgumentException('Cannot determine the policy for any class of which the name does not start with DataModel or DataIter.');
        }
        elseif (is_subclass_of($model, DataModel::class))
            $modelName = substr(get_class($model), strlen('DataModel'));
        elseif (is_subclass_of($model, DataIter::class))
            $modelName = substr(get_class($model), strlen('DataIter'));
        elseif ($model instanceof DataIter)
            return $this->get($model->model());

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
            try {
                $model = $this->db->getModel($iter);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Can only check policy for an DataIter or a Model name string.');
            }
            return call_user_func([$this->get($iter), $check], $model->new_iter());
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
