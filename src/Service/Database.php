<?php

namespace App\Service;

use App\Legacy\Database\DatabasePDO;

class Database
{
    private $db;
    private $loadedModels = [];

    public function __construct(
        string $host,
        string $user,
        string $password,
        string $database,
        bool $showQueries,
    ) {
        $this->db = new DatabasePDO(compact('host', 'user', 'password', 'database'));
        $this->db->track_history = $showQueries;
    }

    private function createModel(string $name): mixed
    {
        require_once 'src/Model/' . $name . '.php';

        if (!class_exists($name))
            throw new InvalidArgumentException(sprintf(__("Could not find the model %s"), $name));

        $refl = new \ReflectionClass($name);
        return $refl->newInstance($this->getDb());
    }

    public function getModel(string $name): mixed
    {
        if (!isset($this->loadedModels[$name]))
            $this->loadedModels[$name] = $this->createModel($name);

        return $this->loadedModels[$name];
    }

    public function getDb(): DatabasePDO
    {
        return $this->db;
    }

    public function __call(string $name, array $arguments): mixed
    {
        return call_user_func_array([$this->db, $name], $arguments);
    }
}
