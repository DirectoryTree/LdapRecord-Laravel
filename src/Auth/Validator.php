<?php

namespace LdapRecord\Laravel\Auth;

class Validator
{
    /**
     * The validation rules.
     *
     * @var array
     */
    protected $rules = [];

    /**
     * Constructor.
     *
     * @param iterable $rules
     */
    public function __construct(iterable $rules = [])
    {
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    /**
     * Checks if each rule passes validation.
     *
     * If all rules pass, authentication is granted.
     *
     * @return bool
     */
    public function passes()
    {
        foreach ($this->rules as $rule) {
            if (! $rule->isValid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a rule fails validation.
     *
     * @return bool
     */
    public function fails()
    {
        return ! $this->passes();
    }

    /**
     * Adds a rule to the validator.
     *
     * @param Rule $rule
     */
    public function addRule(Rule $rule)
    {
        $this->rules[] = $rule;
    }

    /**
     * Get the rules on the validator.
     *
     * @return array
     */
    public function getRules()
    {
        return $this->rules;
    }
}
