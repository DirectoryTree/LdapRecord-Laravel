<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class EloquentFactory
{
    /**
     * The underlying database connection to utilize.
     *
     * @var \Illuminate\Database\Connection
     */
    public static $connection;

    /**
     * The eloquent model to utilize for testing.
     *
     * @var string
     */
    protected static $model = LdapObject::class;

    /**
     * Whether to use an in-memory SQLite database.
     *
     * @var bool
     */
    public static $usingMemory = false;

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
     * Create the eloquent database model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function createModel()
    {
        $class = '\\'.ltrim(static::$model, '\\');

        return new $class;
    }

    /**
     * Initialize the Eloquent SQLite factory.
     *
     * @param bool $useMemory
     *
     * @return void
     */
    public static function initialize($useMemory = false)
    {
        $cachePath = static::getCacheFilePath();
        $cacheDirectory = static::getCacheDirectory();

        switch(true) {
            case $useMemory:
                static::$usingMemory = true;
                static::setSqliteConnection(':memory:');
                static::migrate();
                break;
            case file_exists($cachePath):
                static::$usingMemory = false;
                static::setSqliteConnection($cachePath);
                break;
            case file_exists($cacheDirectory) && is_writable($cacheDirectory):
                static::$usingMemory = false;
                file_put_contents($cachePath, '');
                static::setSqliteConnection($cachePath);
                static::migrate();
        }
    }

    /**
     * Run the database migrations.
     *
     * @return void
     */
    protected static function migrate()
    {
        tap(static::$connection->getSchemaBuilder(), function (Builder $builder) {
            $builder->create('ldap_objects', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamps();
                $table->string('domain')->nullable();
                $table->string('guid')->unique()->index();
                $table->string('name');
                $table->string('dn');
                $table->string('parent_dn')->nullable();
            });

            $builder->create('ldap_object_attributes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ldap_object_id');
                $table->string('name');
            });

            $builder->create('ldap_object_attribute_values', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ldap_object_attribute_id');
                $table->string('value');
            });
        });
    }

    /**
     * Rollback the database migrations.
     *
     * @return void
     */
    public static function teardown()
    {
        if (static::$connection) {
            tap(static::$connection->getSchemaBuilder(), function (Builder $builder) {
                $builder->dropIfExists('ldap_object_attribute_values');
                $builder->dropIfExists('ldap_object_attributes');
                $builder->dropIfExists('ldap_objects');
            });
        }

        if (!static::$usingMemory && file_exists(static::getCacheFilePath())) {
            unlink(static::getCacheFilePath());
        }
    }

    /**
     * Get the full cache file path.
     *
     * @return string
     */
    public static function getCacheFilePath()
    {
        return static::getCacheDirectory().DIRECTORY_SEPARATOR.static::getCacheFileName();
    }

    /**
     * Set the SQLite connection to utilize.
     *
     * @param string $database
     *
     * @return void
     */
    protected static function setSqliteConnection($database)
    {
        static::$connection = app(ConnectionFactory::class)->make([
            'driver' => 'sqlite',
            'database' => $database,
        ]);
    }

    /**
     * Get the cache file name of the SQLite database.
     *
     * @return string
     */
    protected static function getCacheFileName()
    {
        return 'ldap_directory.sqlite';
    }

    /**
     * Get the cache directory for storing the SQLite cache file.
     *
     * @return string
     */
    protected static function getCacheDirectory()
    {
        return realpath(storage_path('framework/cache'));
    }
}
