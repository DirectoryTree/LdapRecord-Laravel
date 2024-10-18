<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Database\Eloquent\Model as Eloquent;
use LdapRecord\Laravel\Events\Auth\RuleFailed;
use LdapRecord\Laravel\Events\Auth\RulePassed;
use LdapRecord\Models\Model as LdapRecord;

class Validator
{
    /**
     * The validation rules.
     *
     * @var \LdapRecord\Laravel\Auth\Rule[]
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
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool
    {
        foreach ($this->rules as $rule) {
            if (! $rule->passes($user, $model)) {
                event(new RuleFailed($rule, $user, $model));

                return false;
            }

            event(new RulePassed($rule, $user, $model));
        }

        return true;
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
