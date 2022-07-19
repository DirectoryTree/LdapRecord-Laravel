<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class LdapDatabaseManager
{
    /**
     * The eloquent model to utilize.
     *
     * @var string
     */
    protected static $model = LdapObject::class;

    /**
     * The underlying database manager instance.
     *
     * @var DatabaseManager
     */
    protected $db;

    /**
     * The resolved LDAP database connections.
     *
     * @var Connection[]
     */
    protected $connections = [];

    /**
     * Set the Eloquent model to use.
     *
     * @param string $model
     */
    public static function using($model)
    {
        static::$model = $model;
    }

    /**
     * Returns the configured models class name.
     *
     * @return string
     */
    public static function model()
    {
        return static::$model;
    }

    /**
     * Constructor.
     *
     * @param DatabaseManager $db
     */
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    /**
     * Create the eloquent database model.
     *
     * @param string|null $connection
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createModel($connection = null)
    {
        $class = '\\'.ltrim(static::$model, '\\');

        return tap(new $class)->setConnection($connection);
    }

    /**
     * Get the LDAP database connection.
     *
     * @param string|null $name
     * @param array       $config
     *
     * @return Connection
     */
    public function connection($name = null, $config = [])
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
     *
     * @param string $name
     * @param string $database
     *
     * @return Connection
     */
    protected function makeConnection($name, $database)
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
     *
     * @return void
     */
    public function teardown()
    {
        foreach ($this->connections as $name => $connection) {
            if ($connection->getDatabaseName() === ':memory:') {
                tap($connection->getSchemaBuilder(), function (Builder $builder) {
                    $builder->dropIfExists('ldap_object_attribute_values');
                    $builder->dropIfExists('ldap_object_attributes');
                    $builder->dropIfExists('ldap_objects');
                });
            } elseif (file_exists($dbFilePath = $connection->getDatabaseName())) {
                unlink($dbFilePath);
            }

            unset($this->connections[$name]);
        }
    }

    /**
     * Return all of the created connections.
     *
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * Make the unique LDAP database connection name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function makeDatabaseConnectionName($name)
    {
        return Str::start($name, 'ldap_');
    }

    /**
     * Run the database migrations on the connection.
     *
     * @param Connection $connection
     *
     * @return void
     */
    protected function migrate(Connection $connection)
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
}
