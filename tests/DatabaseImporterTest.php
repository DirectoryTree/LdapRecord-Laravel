<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Str;
use LdapRecord\Laravel\Commands\Importer;
use LdapRecord\Laravel\Tests\Models\TestUser;

class DatabaseImporterTest extends DatabaseTestCase
{
    /** @test */
    public function ldap_users_are_imported()
    {
        $user = $this->makeLdapUser();

        $importer = new Importer($user, new TestUser());

        $model = $importer->run();

        $this->assertEquals($user->getCommonName(), $model->name);
        $this->assertEquals($user->getUserPrincipalName(), $model->email);
        $this->assertFalse($model->exists);
    }

    /** @test */
    public function ldap_users_are_not_duplicated_with_alternate_casing()
    {
        $firstUser = $this->makeLdapUser();

        $firstUser->setUserPrincipalName('JDOE@EMAIL.com');

        $m1 = (new Importer($firstUser, new TestUser()))->run();

        $m1->password = bcrypt(Str::random(16));

        $m1->save();

        $secondUser = $this->makeLdapUser();

        $secondUser->setUserPrincipalName('jdoe@email.com');

        $m2 = (new Importer($secondUser, new TestUser()))->run();

        $this->assertTrue($m1->is($m2));
    }

    /**
     * @test
     * @expectedException \UnexpectedValueException
     */
    public function exception_is_thrown_when_guid_is_null()
    {
        $u = $this->makeLdapUser([
            'objectguid' => null,
        ]);

        (new Importer($u, new TestUser()))->run();
    }

    /**
     * @test
     * @expectedException \UnexpectedValueException
     */
    public function exception_is_thrown_when_guid_is_empty()
    {
        $u = $this->makeLdapUser([
            'objectguid' => ' ',
        ]);

        (new Importer($u, new TestUser()))->run();
    }

    /**
     * @test
     * @expectedException \UnexpectedValueException
     */
    public function exception_is_thrown_when_username_is_null()
    {
        $u = $this->makeLdapUser([
            'userprincipalname' => null,
        ]);

        (new Importer($u, new TestUser()))->run();
    }

    /**
     * @test
     * @expectedException \UnexpectedValueException
     */
    public function exception_is_thrown_when_username_is_empty()
    {
        $u = $this->makeLdapUser([
            'userprincipalname' => ' ',
        ]);

        (new Importer($u, new TestUser()))->run();
    }
}
