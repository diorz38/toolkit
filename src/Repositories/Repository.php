<?php
namespace Ollieread\Toolkit\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Base Repository
 *
 * @package Ollieslab\Toolkit\Repositories
 */
class Repository
{

    protected static $_transaction = false;

    protected static $_cache = [];

    /**
     * @param string $model
     *
     * @return \Ollieread\Toolkit\Repositories\Repository
     */
    public static function for(string $model)
    {
        $mapping = config('toolkit.mapping');

        if (array_key_exists($model, self::$_cache)) {
            return new self::$_cache[$model];
        }

        if (array_key_exists($model, $mapping)) {
            $repository = $mapping[$model];

            if (class_exists($repository)) {
                self::$_cache[$model] = $repository;
                return new $repository;
            }
        }

        /*
         * If no repository class was found for the model, we'll return a generic one.
         */

        return new self($model);
    }

    /**
     * @var string
     */
    protected $model;

    public function __construct($model = null)
    {
        $this->model = $model;
    }

    /**
     * @return Model
     */
    protected function make()
    {
        return new $this->model;
    }

    /**
     * Accepts either the id or model. It's a safety method so that you can just pass argument in
     * and receiver the id back.
     *
     * @param $model
     *
     * @return mixed
     */
    protected function getId($model)
    {
        return $model instanceof Model ? $model->getKey() : $model;
    }

    /**
     * Create a new model.
     *
     * @param array $data
     *
     * @return mixed|null
     */
    public function create(array $data)
    {
        $model = $this->make();
        $model->fill($data);

        if ($model->save()) {
            return $model;
        }

        return null;
    }

    /**
     * Delete the model.
     *
     * @param $model
     *
     * @return bool|null
     */
    public function delete($model)
    {
        if ($model instanceof Model) {
            return $model->delete();
        }

        $id = $model;
        $model = $this->make();

        return $model->newQuery()->where($model->getKeyName(), $id)->delete();
    }

    /**
     * Helper method for retrieving models by a column or array of columns.
     *
     * @return mixed
     */
    public function getBy()
    {
        $model = $this->make();

        if (func_num_args() == 2) {
            list($column, $value) = func_get_args();
            $method = is_array($value) ? 'whereIn' : 'where';
            $model = $this->$method($column, $value);
        } elseif (func_num_args() == 1) {
            $columns = func_get_arg(0);

            if (is_array($columns)) {
                foreach ($columns as $column => $value) {
                    $method = is_array($value) ? 'whereIn' : 'where';
                    $model = $model->$method($column, $value);
                }
            }
        }

        return $model->get();
    }

    /**
     * Helper method for retrieving a model by a column or array of columns.
     *
     * @return mixed
     */
    public function getOneBy()
    {
        $model = $this->make();

        if (func_num_args() == 2) {
            list($column, $value) = func_get_args();
            $method = is_array($value) ? 'whereIn' : 'where';
            $model = $this->$method($column, $value);
        } elseif (func_num_args() == 1) {
            $columns = func_get_args();

            if (is_array($columns)) {
                foreach ($columns as $column => $value) {
                    $method = is_array($value) ? 'whereIn' : 'where';
                    $model = $model->$method($column, $value);
                }
            }
        }

        return $model->first();
    }

    /**
     * Magic method handling for dynamic functions such as getByAddress() or getOneById().
     *
     * @param       $name
     * @param array $arguments
     *
     * @return \Illuminate\Database\Eloquent\Collection|mixed|null
     */
    function __call($name, $arguments = [])
    {
        if (count($arguments) > 1) {
            // TODO: Should probably throw an exception here
            return null;
        }

        if (substr($name, 0, 5) == 'getBy') {
            return $this->getBy(snake_case(substr($name, 5)), $arguments[0]);
        } elseif (substr($name, 0, 8) == 'getOneBy') {
            $column = snake_case(substr($name, 8));

            return call_user_func_array([$this->make(), 'where'], [$column, $arguments[0]])->find();
        }
    }

    /**
     * Start or perform a transaction.
     *
     * @param \Closure|null $closure
     *
     * @throws \Exception
     */
    public static function transaction(\Closure $closure = null)
    {
        if (!self::$_transaction) {
            if ($closure) {
                DB::transaction($closure);
            } else {
                DB::beginTransaction();
                self::$_transaction = true;
            }
        } else {
            throw new \Exception('Attempting to start a transaction while already in a transaction');
        }
    }

    /**
     * Rollback the current transaction.
     *
     * @throws \Exception
     */
    public static function rollback()
    {
        if (self::$_transaction) {
            DB::rollback();
            self::$_transaction = false;
        } else {
            throw new \Exception('Attempting to rollback outside of a transaction');
        }
    }

    /**
     * Commit the current transaction.
     *
     * @throws \Exception
     */
    public static function commit()
    {
        if (self::$_transaction) {
            DB::commit();
            self::$_transaction = false;
        } else {
            throw new \Exception('Attempting to commit outside of a transaction');
        }
    }
}