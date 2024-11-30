<?php

namespace App\Service;

use App\Legacy\Policy\PolicyInterface;
use App\Legacy\Policy\PolicyNotFoundException;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use App\Service\Authentication;
use App\Service\Database;

class Policy
{
    private $policies = [];

    public function __construct(
        private Database $db,
        private Authentication $auth,
    ) {
    }

    public function get(string|DataIter|DataModel $model, ?string $original_model_name = null): PolicyInterface
    {
        if (is_string($model)) {// Providing a name, e.g. DataIterFotoboek
            if (substr($model, 0, strlen('DataModel')) == 'DataModel')
                $model_name = substr($model, strlen('DataModel'));
            elseif (substr($model, 0, strlen('DataIter')) == 'DataIter')
                $model_name = substr($model, strlen('DataIter'));
            else
                throw new \InvalidArgumentException('Cannot determine the policy for any class of which the name does not start with DataModel or DataIter.');
        }
        elseif (is_subclass_of($model, DataModel::class))
            $model_name = substr(get_class($model), strlen('DataModel'));
        elseif (is_subclass_of($model, DataIter::class))
            $model_name = substr(get_class($model), strlen('DataIter'));
        elseif ($model instanceof DataIter)
            return $this->get($model->model());

        $policy = $this->get_cached($model_name, $original_model_name);

        if ($policy !== null)
            return $policy;

        // But when the policy is not found, try the parent class
        $parent_class = get_parent_class($model);
        if ($parent_class !== false && is_subclass_of($parent_class, DataIter::class))
            return $this->get($parent_class, $model_name);

        // If there is no parent class, or the parent class is not DataIter, then give up.
        throw new PolicyNotFoundException(sprintf("Policy for '%s' not found.", $model_name));
    }

    private function get_cached(string $model_name, ?string $original_model_name = null)
    {
        // Look in the policy cache
        if (isset($this->policies[$model_name]))
            return $this->policies[$model_name];

        // No policy in cache, construct the class name and load it
        $policy_class = 'Policy' . $model_name;

        $policy_file = stream_resolve_include_path('src/Policy/' . $policy_class . '.php');

        $policy_class = 'App\\Policy\\' . $policy_class;

        // Policy class file does not exist? Then there is no policy for it.
        if ($policy_file === false)
            return $this->policies[$model_name] = null;

        require_once $policy_file;

        // Construct and cache the policy instance
        $policy = $this->policies[$model_name] = new $policy_class(
            $this->db,
            $this->auth,
        );

        // Also cache the policy under the original name for speedy lookup in the future
        if ($original_model_name !== null)
            $this->policies[$original_model_name] = $policy;

        return $policy;
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
