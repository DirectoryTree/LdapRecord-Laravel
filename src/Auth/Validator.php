<?php

namespace LdapRecord\Laravel\Auth;

class Validator
{
    /**
     * The validation rules.
     */
    protected array $rules = [];

    /**
     * Constructor.
     */
    public function __construct(iterable $rules = [])
    {
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    /**
     * Determine if all rules pass validation.
     */
    public function passes(): bool
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
     */
    public function fails(): bool
    {
        return ! $this->passes();
    }

    /**
     * Adds a rule to the validator.
     */
    public function addRule(Rule $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * Get the rules on the validator.
     *
     * @return Rule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
