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

    public static function setup()
    {
        static::migrate();
    }

    public static function teardown()
    {
        static::rollback();
    }

    public static function using($model)
    {
        static::$model = $model;
    }

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

    protected static function migrate()
    {
        Schema::create('ldap_objects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->softDeletes();
            $table->string('domain')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('guid')->unique()->index();
            $table->string('name');
            $table->string('dn');
            $table->string('type')->nullable();
        });

        Schema::create('ldap_object_classes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ldap_object_id');
            $table->string('name');
        });

        Schema::create('ldap_object_attributes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ldap_object_id');
            $table->string('name');
            $table->text('values')->nullable();
        });
    }

    protected static function rollback()
    {
        Schema::dropIfExists('ldap_object_attributes');
        Schema::dropIfExists('ldap_object_classes');
        Schema::dropIfExists('ldap_objects');
    }
}
