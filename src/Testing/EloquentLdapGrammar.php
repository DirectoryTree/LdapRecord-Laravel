<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Builder;
use LdapRecord\Query\Grammar;

class EloquentLdapGrammar extends Grammar
{
    /**
     * @var Builder
     */
    protected $query;

    public $relationMethod = 'whereHas';

    /**
     * Constructor.
     *
     * @param Builder $query
     */
    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    /**
     * {@inheritdoc}
     */
    protected function compileWhere(array $where)
    {
        $this->query->{$this->relationMethod}('attributes', function ($query) use ($where) {
            $query->where('name', '=', $where['field']);
            $query->{$this->relationMethod}('values', function ($q) use ($where) {
                $q->where('value', '=', $where['value']);
            });
        });

        return parent::compileWhere($where);
    }
}
