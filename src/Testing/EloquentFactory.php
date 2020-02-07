<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EloquentFactory
{
    /**
     * The eloquent model to utilize for testing.
     *
     * @var string
     */
    protected static $model = LdapObject::class;

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
     * Run the database migrations.
     *
     * @return void
     */
    public static function migrate()
    {
        Schema::create('ldap_objects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->string('domain')->nullable();
            $table->string('guid')->unique()->index();
            $table->string('name');
            $table->string('dn');
            $table->string('parent_dn')->nullable();
        });

        Schema::create('ldap_object_attributes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ldap_object_id');
            $table->string('name');
        });

        Schema::create('ldap_object_attribute_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ldap_object_attribute_id');
            $table->string('value');
        });
    }

    /**
     * Rollback the database migrations.
     *
     * @return void
     */
    public static function rollback()
    {
        Schema::dropIfExists('ldap_object_attribute_values');
        Schema::dropIfExists('ldap_object_attributes');
        Schema::dropIfExists('ldap_objects');
    }
}
