<?php

namespace Isswp101\Persimmon\Model;

use Isswp101\Persimmon\Collection\ICollection;
use Isswp101\Persimmon\DI\Container;
use Isswp101\Persimmon\Exceptions\IllegalCollectionException;
use Isswp101\Persimmon\Exceptions\ModelNotFoundException;
use Isswp101\Persimmon\QueryBuilder\IQueryBuilder;
use Isswp101\Persimmon\Traits\Containerable;
use Isswp101\Persimmon\Traits\Eventable;
use Isswp101\Persimmon\Traits\Timestampable;

/**
 * @TODO
 * 1. Cache
 * 2. Consider columns when searching
 * 3. Check __clone()
 */
abstract class Eloquent implements IEloquent
{
    use Containerable, Timestampable, Eventable;

    protected $exists = false;
    protected $timestamps = false;

    /** @MustBeOverridden */
    const COLLECTION = null;

    const PRIMARY_KEY = 'id';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    abstract protected static function di(): Container;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public function getPrimaryKey(): string
    {
        return $this->{static::PRIMARY_KEY};
    }

    public function setPrimaryKey(string $key)
    {
        $this->{static::PRIMARY_KEY} = $key;
    }

    final public static function getCollectionName(): string
    {
        if (static::COLLECTION == null) {
            throw new IllegalCollectionException();
        }
        return static::COLLECTION;
    }

    public function exists(bool $value = null): bool
    {
        if ($value != null) {
            $this->exists = $value;
        }
        return $this->exists;
    }

    public static function all(IQueryBuilder $query, callable $callback = null): ICollection
    {
        $collection = static::di()->getRepository()->all(
            $query,
            static::class,
            function (IEloquent $model) use ($callback) {
                $model->exists(true);
                if ($callback != null) {
                    $callback($model);
                }
            });
        return $collection;
    }

    public static function find($id, array $columns = []): IEloquent
    {
        $model = static::di()->getRepository()->find($id, static::class, $columns);
        if ($model != null) {
            $model->exists = true;
        }
        return $model;
    }

    public static function findOrFail($id, array $columns = []): IEloquent
    {
        $model = static::find($id, $columns);
        if ($model == null) {
            throw new ModelNotFoundException(get_called_class(), $id);
        }
        return $model;
    }

    public static function create(array $attributes): IEloquent
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function destroy($id)
    {
        static::findOrFail($id)->delete();
    }

    public function save(array $columns = [])
    {
        if ($this->saving() === false) {
            return;
        }
        $repository = $this->di()->getRepository();
        if ($this->timestamps) {
            $this->updateTimestamps();
        }
        if (!$this->exists) {
            $repository->insert($this);
        } else {
            $repository->update($this);
        }
        $this->exists = true;
        if ($this->saved() === false) {
            return;
        }
    }

    public function delete()
    {
        if ($this->deleting() === false) {
            return;
        }
        $this->di()->getRepository()->delete($this->getPrimaryKey());
        $this->exists = false;
        if ($this->deleted() === false) {
            return;
        }
    }
}