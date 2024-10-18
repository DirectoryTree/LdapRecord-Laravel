<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class LdapDatabaseManager
{
    /**
     * The underlying database manager instance.
     */
    protected DatabaseManager $db;

    /**
     * The resolved LDAP database connections.
     *
     * @var Connection[]
     */
    protected array $connections = [];

    /**
     * The eloquent model to utilize.
     */
    protected static string $model = LdapObject::class;

    /**
     * Set the Eloquent model to use.
     */
    public static function using(string $model): void
    {
        static::$model = $model;
    }

    /**
     * Returns the configured models class name.
     */
    public static function model(): string
    {
        return static::$model;
    }

    /**
     * Constructor.
     */
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    /**
     * Create the eloquent database model.
     */
    public function createModel(?string $connection = null): Model
    {
        $class = '\\'.ltrim(static::$model, '\\');

        return tap(new $class)->setConnection($connection);
    }

    /**
     * Get the LDAP database connection.
     */
    public function connection(?string $name = null, array $config = []): Connection
    {
        $name = $name ?? Config::get('ldap.default', 'default');

        $this->connections[$name] = $this->makeConnection(
            $this->makeDatabaseConnectionName($name),
            Arr::get($config, 'database', ':memory:')
        );

        return $this->connections[$name];
    }

    /**
     * Make the database connection.
     */
    protected function makeConnection(string $name, string $database): Connection
    {
        // If we're not working with an in-memory database,
        // we'll assume a file path has been given and
        // create it before we run the migrations.
        if ($database !== ':memory:' && ! file_exists($database)) {
            file_put_contents($database, '');
        }

        app('config')->set("database.connections.$name", [
            'driver' => 'sqlite',
            'database' => $database,
        ]);

        return tap($this->db->connection($name), function (Connection $connection) {
            $this->migrate($connection);
        });
    }

    /**
     * Tear down the LDAP database connections.
     */
    public function teardown(): void
    {
        foreach ($this->connections as $name => $connection) {
            $this->rollback($connection);

            unset($this->connections[$name]);
        }
    }

    /**
     * Return the created connections.
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Make the unique LDAP database connection name.
     */
    protected function makeDatabaseConnectionName(string $name): string
    {
        return Str::start($name, 'ldap_');
    }

    /**
     * Run the database migrations on the connection.
     */
    protected function migrate(Connection $connection): void
    {
        $builder = $connection->getSchemaBuilder();

        if (! $builder->hasTable('ldap_objects')) {
            $builder->create('ldap_objects', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamps();
                $table->string('domain')->nullable();
                $table->string('guid')->unique()->index();
                $table->string('guid_key')->nullable();
                $table->string('name')->nullable();
                $table->string('dn')->nullable();
                $table->string('parent_dn')->nullable();
            });
        }

        if (! $builder->hasTable('ldap_object_attributes')) {
            $builder->create('ldap_object_attributes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ldap_object_id');
                $table->string('name');
            });
        }

        if (! $builder->hasTable('ldap_object_attribute_values')) {
            $builder->create('ldap_object_attribute_values', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ldap_object_attribute_id');
                $table->string('value');
            });
        }
    }

    /**
     * Rollback the database migrations on the connection.
     */
    protected function rollback(Connection $connection): void
    {
        if ($connection->getDatabaseName() === ':memory:') {
            tap($connection->getSchemaBuilder(), function (Builder $builder) {
                $builder->dropIfExists('ldap_object_attribute_values');
                $builder->dropIfExists('ldap_object_attributes');
                $builder->dropIfExists('ldap_objects');
            });
        } elseif (file_exists($dbFilePath = $connection->getDatabaseName())) {
            unlink($dbFilePath);
        }
    }
}
