<?php

namespace LdapRecord\Laravel\Testing\Emulated;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use LdapRecord\Laravel\Testing\EmulatesQueries;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;

trait EmulatesModelQueries
{
    use EmulatesQueries {
        findOrFail as baseFindOrFail;
        addFilterToDatabaseQuery as baseAddFilterToDatabaseQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function newInstance(?string $baseDn = null): static
    {
        return (new self($this->connection))
            ->setModel($this->model)
            ->setBaseDn($baseDn);
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $dn, array $attributes): bool
    {
        if (! $model = $this->find($dn)) {
            return false;
        }

        foreach ($attributes as $name => $values) {
            $model->{$name} = array_merge($model->{$name} ?? [], Arr::wrap($values));
        }

        $model->save();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $dn, array $modifications): bool
    {
        if (! $model = $this->findEloquentModelByDn($dn)) {
            return false;
        }

        foreach ($modifications as $modification) {
            $this->applyBatchModificationToModel($model, $modification);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function replace(string $dn, array $attributes): bool
    {
        if (! $model = $this->find($dn)) {
            return false;
        }

        foreach ($attributes as $name => $values) {
            $model->{$name} = $values;
        }

        $model->save();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $dn, array $attributes): bool
    {
        if (! $model = $this->find($dn)) {
            return false;
        }

        foreach ($attributes as $attribute => $value) {
            if (empty($value)) {
                $model->{$attribute} = null;
            } elseif ($model->hasAttribute($attribute)) {
                $model->{$attribute} = array_values(
                    array_diff($model->{$attribute}, (array) $value)
                );
            }
        }

        $model->save();

        return true;
    }

    /**
     * Parse the database query results.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $resource
     */
    public function parse(mixed $resource): array
    {
        return $resource->toArray();
    }

    /**
     * {@inheritdoc}
     */
    protected function process(array $results): Collection
    {
        return $this->model->newCollection($results)->transform(function ($result) {
            return $this->resultToModelInstance($result);
        });
    }

    /**
     * Transform the result into a model instance.
     */
    protected function resultToModelInstance($result): Model
    {
        if ($result instanceof $this->model) {
            return $result;
        }

        return $this->model->newInstance()
            ->setDn($result['dn'])
            ->setRawAttributes(
                array_merge(
                    $this->transform($result),
                    [$result['guid_key'] => [$result['guid']]]
                )
            );
    }

    /**
     * {@inheritdoc}
     */
    public function findOrFail(string $dn, array|string $columns = ['*']): Model
    {
        return $this->baseFindOrFail($dn, $columns);
    }

    /**
     * {@inheritdoc}
     */
    protected function addFilterToDatabaseQuery(Builder $query, string $field, string $operator, ?string $value): void
    {
        if ($field === 'anr') {
            $query->whereIn('name', $this->model->getAnrAttributes())
                ->whereHas('values', function ($query) use ($value) {
                    $query->where('value', 'like', "%$value%");
                });

            return;
        }

        $this->baseAddFilterToDatabaseQuery($query, $field, $operator, $value);
    }

    /**
     * Override the possibility of the underlying LDAP model being compatible with ANR.
     */
    protected function modelIsCompatibleWithAnr(): bool
    {
        return true;
    }
}
