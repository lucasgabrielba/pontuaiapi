<?php

namespace Domains\Shared\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ListDataHelper
{
    /**
     * @var array|string[]
     *
     * @example include, search, order, per_page, page, select
     */
    protected array $keyWords = [
        'include',
        'search',
        'order',
        'per_page',
        'page',
        'select',
    ];

    protected array $parameters = [];

    protected string $modelClass;

    public function __construct(
        protected Model $model,
        protected ?Model $relationshipModel = null,
    ) {
        $this->modelClass = get_class($model);

        if (property_exists($this->modelClass, 'mappingAttributes') && is_array($this->modelClass::$mappingAttributes)) {
            $this->parameters = $this->modelClass::$mappingAttributes;
        }
    }

    public function list(array $filter, array $selectAttributes = []): LengthAwarePaginator
    {
        $query = $this->startQuery();

        // Handle includes (eager loading)
        if (isset($filter['include'])) {
            $this->handleIncludes($query, $filter['include']);
        }

        // Handle search in Laravel Scout
        if (isset($filter['search'])) {
            $this->searchInScout($query, $filter['search']);
        }

        // Handle additional filters like ?email=john.doe@example.com
        $this->searchBySpecificFields($query, $filter);

        // Handle select: &select=name,email,document
        if (isset($filter['select'])) {
            $this->handleSelect($query, $filter['select']);
        }

        // Handle order: &order=-created_at,status
        if (isset($filter['order'])) {
            $this->orderRows($query, $filter['order']);
        }

        // Handle pagination
        $perPage = $filter['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    private function startQuery()
    {
        if (! $this->relationshipModel) {
            return $this->model::query();
        }

        // Determine the relationship name based on the model class name
        $relationshipBaseName = Str::plural(Str::snake(class_basename($this->model)));
        $relationshipAlternativeName = 'all'.Str::camel($relationshipBaseName);

        // Check if, for example, "allAccounts" relationship exists
        if (method_exists($this->relationshipModel, $relationshipAlternativeName)) {
            return $this->relationshipModel->$relationshipAlternativeName();
        }

        // Check if, for example, "accounts" relationship exists
        if (method_exists($this->relationshipModel, $relationshipBaseName)) {
            return $this->relationshipModel->$relationshipBaseName();
        }

        // Fallback para a query principal caso a relação não seja encontrada
        return $this->model::query();
    }

    protected function mapParameter(string $param)
    {
        if (Str::contains($param, '.')) {
            $param = explode('.', $param);

            return $this->parameters[$param[1]] ?? $param[1];
        }

        return $this->parameters[$param] ?? $param;
    }

    protected function searchInScout($query, string $searchQuery): void
    {
        if (! method_exists($this->modelClass, 'search')) {
            return;
        }

        $findBySearch = $this->model::search($searchQuery)->raw();

        $idsFound = array_column($findBySearch['hits'], 'id');

        $query->whereIn('id', $idsFound);
    }

    protected function searchBySpecificFields($query, array $filter): void
    {
        foreach ($filter as $key => $value) {
            if (in_array($key, $this->keyWords)) {
                continue;
            }

            if (is_array($value)) {
                // Handle nested filters (e.g., client[name]=lucas)
                $this->handleNestedFilters($query, $key, $value);
                continue;
            }

            if (preg_match('/([^(]+)\(([^,]+),?([^,]*)\)/', $value, $matches)) {
                $this->handleSpecialCases($query, $matches, $key);
                continue;
            }

            $this->handleDefaultCase($query, $value, $key);
        }
    }

    protected function handleNestedFilters($query, string $relation, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                // Suporte para múltiplos níveis de relações, se necessário
                // Por exemplo: client[address][city]=São Paulo
                // Aqui você pode implementar recursivamente se desejar
                continue;
            }

            // Mapeia o campo se necessário
            $mappedField = $this->mapParameter($field);

            $query->whereHas($relation, function ($q) use ($mappedField, $value) {
                $q->where($mappedField, 'LIKE', $value . '%');
            });
        }
    }

    protected function handleSpecialCases($query, $matches, $key): void
    {
        switch ($matches[1]) {
            case 'lt':
                $query->where($this->mapParameter($key), '<', $matches[2]);
                break;
            case 'gt':
                $query->where($this->mapParameter($key), '>', $matches[2]);
                break;
            case 'between':
                $query->whereBetween($this->mapParameter($key), [$matches[2], $matches[3]]);
                break;
            case 'in':
                $query->whereIn($this->mapParameter($key), explode(',', $matches[2]));
                break;
            default:
                break;
        }
    }

    protected function handleDefaultCase($query, $value, $key): void
    {
        if (is_string($value) && strpos($value, ',') !== false) {
            $values = explode(',', $value);
            $query->whereIn($this->mapParameter($key), $values);
        } elseif (is_array($value)) {
            if ($this->isAssociativeArray($value)) {
                // Neste caso atual, arrays associativos já são tratados em searchBySpecificFields
                // Portanto, isso pode ser deixado vazio ou tratado conforme necessário
            } else {
                $query->whereIn($this->mapParameter($key), $value);
            }
        } else {
            $query->where($this->mapParameter($key), 'LIKE', $value . '%');
        }
    }
    
    protected function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function selectFields($query, array $selectAttributes): void
    {
        foreach ($selectAttributes as $selectAttribute) {
            $query->addSelect($this->mapParameter($selectAttribute));
        }
    }

    protected function orderRows($query, string $orderQuery): void
    {
        $orders = explode(',', $orderQuery);
        foreach ($orders as $order) {
            $direction = Str::startsWith($order, '-') ? 'desc' : 'asc';
            $query->orderBy($this->mapParameter(ltrim($order, '-')), $direction);
        }
    }

    protected function handleIncludes($query, $includes)
    {
        $relationships = explode(',', $includes);
        $query->with($relationships);
    }

    protected function handleSelect($query, $select)
    {
        $selectAttributes = explode(',', $select);
        $this->selectFields($query, $selectAttributes);
    }
}
