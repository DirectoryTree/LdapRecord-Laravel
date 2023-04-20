<?php

namespace LdapRecord\Laravel\Testing\Emulated;

use Illuminate\Support\Arr;
use LdapRecord\Laravel\Testing\EmulatesQueries;
use LdapRecord\Models\Collection;

trait EmulatesModelQueries
{
    use EmulatesQueries {
        addFilterToDatabaseQuery as baseAddFilterToDatabaseQuery;
    }

    /**
     * @inheritdoc
     */
    public function newInstance(string $baseDn = null): static
    {
        return (new self($this->connection))
            ->setModel($this->model)
            ->setBaseDn($baseDn);
    }

    /**
     * @inheritdoc
     */
    public function insertAttributes(string $dn, array $attributes): bool
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function updateAttributes(string $dn, array $attributes): bool
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
     * @inheritdoc
     */
    public function deleteAttributes(string $dn, array $attributes): bool
    {
        if (! $model = $this->find($dn)) {
            return false;
        }

        foreach ($attributes as $attribute => $value) {
            if (empty($value)) {
                $model->{$attribute} = null;
            } elseif (Arr::exists($model->{$attribute} ?? [], $attribute)) {
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
     * @param \Illuminate\Database\Eloquent\Collection $resource
     *
     * @return array
     */
    public function parse(mixed $resource): array
    {
        return $resource->toArray();
    }

    /**
     * @inheritdoc
     */
    protected function process(array $results): Collection
    {
        return $this->model->newCollection($results)->transform(function ($result) {
            return $this->resultToModelInstance($result);
        });
    }

    /**
     * Transform the result into a model instance.
     *
     * @param array|\LdapRecord\Models\Model $object
     *
     * @return \LdapRecord\Models\Model
     */
    protected function resultToModelInstance($result)
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
     * @inheritdoc
     */
    protected function addFilterToDatabaseQuery($query, $field, $operator, $value)
    {
        if ($field === 'anr') {
            return $query->whereIn('name', $this->model->getAnrAttributes())
                ->whereHas('values', function ($query) use ($value) {
                    $query->where('value', 'like', "%$value%");
                });
        }

        return $this->baseAddFilterToDatabaseQuery($query, $field, $operator, $value);
    }

    /**
     * Override the possibility of the underlying LDAP model being compatible with ANR.
     */
    protected function modelIsCompatibleWithAnr(): bool
    {
        return true;
    }
}
