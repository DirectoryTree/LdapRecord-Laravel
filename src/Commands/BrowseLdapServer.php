<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use LdapRecord\Container;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;

class BrowseLdapServer extends Command
{
    const OPERATION_INSPECT_OBJECT = 'inspect';

    const OPERATION_NAVIGATE_DOWN = 'down';

    const OPERATION_NAVIGATE_UP = 'up';

    const OPERATION_NAVIGATE_TO = 'to';

    const OPERATION_NAVIGATE_TO_ROOT = 'root';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:browse {connection=default : The LDAP connection to browse.}';

    /**
     * The LDAP connections base DN (root).
     */
    protected ?string $baseDn = null;

    /**
     * The currently selected DN.
     */
    protected ?string $selectedDn = null;

    /**
     * The operations and their tasks.
     */
    protected array $operations = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->operations = $this->getOperationTasks();
    }

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        $connection = $this->argument('connection');

        $this->info("Connecting to [$connection]...");

        Container::getConnection($connection)->connect();

        $this->info('Successfully connected.');

        $this->baseDn = $this->newLdapQuery()->getBaseDn();

        $this->selectedDn = $this->baseDn;

        $this->askForOperation();

        return static::SUCCESS;
    }

    /**
     * Ask the developer for an operation to perform.
     */
    protected function askForOperation(string $prompt = 'Select operation'): void
    {
        $this->info("Viewing object [$this->selectedDn]");

        $operations = $this->getAvailableOperations();

        // If the base DN is equal to the currently selected DN, the
        // developer cannot navigate up any further. We'll remove
        // the operation from selection to prevent this.
        if ($this->selectedDn === $this->baseDn) {
            unset($operations[static::OPERATION_NAVIGATE_UP]);
        }

        $this->performOperation($this->choice($prompt, $operations));
    }

    /**
     * Perform the selected operation.
     *
     * @throws InvalidArgumentException
     */
    protected function performOperation(string $operation): void
    {
        throw_if(
            ! array_key_exists($operation, $this->operations),
            new InvalidArgumentException("Operation [$operation] does not exist.")
        );

        $this->operations[$operation]();
    }

    /**
     * Display the nested objects.
     */
    protected function displayNestedObjects(): void
    {
        $dns = $this->getSelectedNestedDns();

        if (empty($dns)) {
            $this->askForOperation('This object contains no nested objects. Select operation');

            return;
        }

        $dns[static::OPERATION_NAVIGATE_UP] = $this->getAvailableOperations()[static::OPERATION_NAVIGATE_UP];

        $selected = $this->choice('Select an object to inspect', $dns);

        if ($selected !== static::OPERATION_NAVIGATE_UP) {
            $this->selectedDn = $dns[$selected];
        }

        $this->askForOperation();
    }

    /**
     * Display the currently selected objects attributes.
     */
    protected function displayAttributes(): void
    {
        $object = $this->newLdapQuery()->find($this->selectedDn);

        $attributes = $object->getAttributes();

        $attributeNames = array_keys($attributes);

        $attribute = $this->choice('Which attribute would you like to view?', $attributeNames);

        $wrapped = array_map([$this, 'wrapAttributeValuesInArray'], $attributes);

        $this->table([$attribute], $wrapped[$attribute]);

        $this->askForOperation();
    }

    /**
     * Wrap attribute values in an array for tabular display.
     *
     * @return array[]
     */
    protected function wrapAttributeValuesInArray(array $values): array
    {
        return array_map(function ($value) {
            return [$value];
        }, $values);
    }

    /**
     * Get a listing of the nested object DNs inside the currently selected DN.
     */
    protected function getSelectedNestedDns(): array
    {
        return $this->newLdapQuery()
            ->in($this->selectedDn)
            ->list()
            ->paginate()
            ->sortBy(function (Model $object) {
                return $object->getName();
            })->map(function (Model $object) {
                return $object->getDn();
            })->values()->all();
    }

    /**
     * Get the operations tasks.
     */
    protected function getOperationTasks(): array
    {
        return [
            static::OPERATION_INSPECT_OBJECT => function () {
                $this->displayAttributes();
            },
            static::OPERATION_NAVIGATE_UP => function () {
                $this->selectedDn = (new DistinguishedName($this->selectedDn))->parent();

                $this->askForOperation();
            },
            static::OPERATION_NAVIGATE_DOWN => function () {
                $this->displayNestedObjects();

                $this->askForOperation();
            },
            static::OPERATION_NAVIGATE_TO_ROOT => function () {
                $this->selectedDn = $this->baseDn;

                $this->askForOperation();
            },
            static::OPERATION_NAVIGATE_TO => function () {
                $this->selectedDn = $this->ask('Enter the objects distinguished name you would like to navigate to.');

                $this->displayNestedObjects();

                $this->askForOperation();
            },
        ];
    }

    /**
     * Get the available command operations.
     */
    protected function getAvailableOperations(): array
    {
        return [
            static::OPERATION_INSPECT_OBJECT => 'View the selected objects attributes',
            static::OPERATION_NAVIGATE_UP => 'Navigate up a level',
            static::OPERATION_NAVIGATE_DOWN => 'Navigate down a level',
            static::OPERATION_NAVIGATE_TO_ROOT => 'Navigate to root',
            static::OPERATION_NAVIGATE_TO => 'Navigate to specific object',
        ];
    }

    /**
     * Create a new LDAP query on the connection.
     */
    protected function newLdapQuery(): Builder
    {
        return Entry::on($this->argument('connection'));
    }
}
