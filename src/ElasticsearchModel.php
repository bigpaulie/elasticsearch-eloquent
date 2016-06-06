<?php

namespace Isswp101\Persimmon;

use Elasticsearch\Client;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Isswp101\Persimmon\Collection\ElasticsearchCollection;
use Isswp101\Persimmon\DAL\ElasticsearchDAL;
use Isswp101\Persimmon\QueryBuilder\QueryBuilder;
use Isswp101\Persimmon\Traits\Elasticsearchable;
use Isswp101\Persimmon\Traits\Paginationable;
use Isswp101\Persimmon\Traits\Relationshipable;
use ReflectionClass;
use Isswp101\Persimmon\Relationship\BelongsToRelationship;
use Isswp101\Persimmon\Relationship\HasManyRelationship;

class ElasticsearchModel extends Model
{
    use Elasticsearchable, Paginationable, Relationshipable;

    /**
     * @var ElasticsearchDAL
     */
    public $_dal;

    public function __construct(array $attributes = [])
    {
        $this->validateModelEndpoint();

        parent::__construct($attributes);
    }

    public function injectDependencies()
    {
        // @TODO: move logger to DAL
        $this->injectDataAccessLayer(new ElasticsearchDAL($this, app(Client::class)));
        // $this->injectLogger(app(Log::class));
    }

    public static function findWithParentId($id, $parent, array $columns = ['*'])
    {
        /** @var static $model */
        $model = parent::find($id, $columns, ['parent' => $parent]);

        if ($model) {
            $model->setParentId($parent);
        }

        return $model;
    }

    /**
     * Execute the query and get the result.
     *
     * @param QueryBuilder|array $query
     * @return ElasticsearchCollection|static[]
     */
    public static function search($query = [])
    {
        if ($query instanceof QueryBuilder) {
            $query = $query->build();
        }
        $model = static::createInstance();
        return $model->_dal->search($query);
    }

    /**
     * Execute the query and get the first result.
     *
     * @param QueryBuilder|array $query
     * @return static
     */
    public static function first($query = [])
    {
        if ($query instanceof QueryBuilder) {
            $query = $query->build();
        }
        $query['from'] = 0;
        $query['size'] = 1;
        return static::search($query)->first();
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param QueryBuilder|array $query
     * @return static
     * @throws ModelNotFoundException
     */
    public static function firstOrFail($query = [])
    {
        $model = static::first($query);
        if (is_null($model)) {
            $reflect = new ReflectionClass(get_called_class());
            throw new ModelNotFoundException(sprintf('Model `%s` not found', $reflect->getShortName()));
        }
        return $model;
    }

    /**
     * Apply the callback to the documents of the given query.
     *
     * @param QueryBuilder|array $query
     * @param callable $callback
     * @param int $limit
     * @return int hits.total
     */
    public static function map($query = [], callable $callback, $limit = -1)
    {
        if ($query instanceof QueryBuilder) {
            $query = $query->build();
        }

        $query['from'] = array_get($query, 'from', 0);
        $query['size'] = array_get($query, 'size', 50);

        $i = 0;
        $models = static::search($query);
        $total = $models->getTotal();
        while ($models) {
            foreach ($models as $model) {
                $callback($model);
                $i++;
            }

            $query['from'] += $query['size'];

            if ($i >= $total || ($limit > 0 && $i >= $limit)) {
                break;
            }

            $models = static::search($query);
        }

        return $total;
    }

    /**
     * Execute the query and get all items.
     *
     * @param QueryBuilder|array $query
     * @return Collection
     */
    public static function all($query = [])
    {
        if ($query instanceof QueryBuilder) {
            $query = $query->build();
        }
        $collection = collect();
        static::map($query, function (ElasticsearchModel $document) use ($collection) {
            $collection->put($document->getId(), $document);
        });
        return $collection;
    }

    protected function belongsTo($class)
    {
        return new BelongsToRelationship($this, $class);
    }

    protected function hasMany($class)
    {
        return new HasManyRelationship($this, $class);
    }
}