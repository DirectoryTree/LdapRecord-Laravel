<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Support\Facades\Event;
use LdapRecord\Laravel\Import\Importer;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Model as LdapRecord;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Models\Attributes\AccountControl;
use Illuminate\Database\Eloquent\Model as Eloquent;

class LdapUserImporter extends Importer
{
    /**
     * The LDAP user repository to use for importing.
     *
     * @var LdapUserRepository
     */
    protected $repository;

    /**
     * Whether to restore soft-deleted database models if the object is enabled.
     *
     * @var bool
     */
    protected $restoreEnabledUsers = false;

    /**
     * Whether to soft-delete the database model if the object is disabled.
     *
     * @var bool
     */
    protected $trashDisabledUsers = false;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        Event::listen(Imported::class, function (Imported $event) {
            if (! $event->ldap instanceof ActiveDirectory) {
                return;
            }

            if ($this->trashDisabledUsers) {
                $this->delete($event->eloquent, $event->ldap);
            }

            if ($this->restoreEnabledUsers) {
                $this->restore($event->eloquent, $event->ldap);
            }
        });
    }

    /**
     * Set the LDAP user repository to use for importing.
     *
     * @param LdapUserRepository $repository
     *
     * @return $this
     */
    public function setLdapUserRepository(LdapUserRepository $repository)
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * Enable restoring enabled users.
     *
     * @return $this
     */
    public function restoreEnabledUsers()
    {
        $this->restoreEnabledUsers = true;

        return $this;
    }

    /**
     * Enable trashing disabled users.
     *
     * @return $this
     */
    public function trashDisabledUsers()
    {
        $this->trashDisabledUsers = true;

        return $this;
    }

    /**
     * Load the import's objects from the LDAP repository.
     *
     * @param string|null $username
     *
     * @return \LdapRecord\Query\Collection
     */
    public function loadObjectsFromRepository($username = null)
    {
        $query = $this->applyLdapQueryConstraints(
            $this->repository->query()
        );

        if (! $username) {
            return $this->objects = $query->paginate();
        }

        $users = $query->getModel()->newCollection();

        return $this->objects = ($user = $query->findByAnr($username))
            ? $users->add($user)
            : $users;
    }

    /**
     * Soft deletes the specified model if their LDAP account is disabled.
     *
     * @param Eloquent   $eloquent
     * @param LdapRecord $object
     *
     * @return void
     */
    protected function delete(Eloquent $eloquent, LdapRecord $object)
    {
        // If deleting is enabled, the model supports soft deletes,
        // the model isn't already deleted, and the LDAP user is
        // disabled, we'll go ahead and delete the users model.
        if (
            $this->isUsingSoftDeletes($eloquent)
            && ! $eloquent->trashed()
            && $this->userIsDisabled($object)
        ) {
            $eloquent->delete();
        }
    }

    /**
     * Restores soft-deleted models if their LDAP account is enabled.
     *
     * @param Eloquent   $eloquent
     * @param LdapRecord $object
     *
     * @return void
     */
    protected function restore(Eloquent $eloquent, LdapRecord $object)
    {
        // If the model has soft-deletes enabled, the model is
        // currently deleted, and the LDAP user account
        // is enabled, we'll restore the users model.
        if (
            $this->isUsingSoftDeletes($eloquent)
            && $eloquent->trashed()
            && $this->userIsEnabled($object)
        ) {
            $eloquent->restore();
        }
    }

    /**
     * Determine whether the user is enabled.
     *
     * @param LdapRecord $object
     *
     * @return bool
     */
    protected function userIsEnabled(LdapRecord $object)
    {
        return $this->getUserAccountControl($object) === null ? false : ! $this->userIsDisabled($object);
    }

    /**
     * Determines whether the user is disabled.
     *
     * @param LdapRecord $object
     *
     * @return bool
     */
    protected function userIsDisabled(LdapRecord $object)
    {
        return ($this->getUserAccountControl($object) & AccountControl::ACCOUNTDISABLE) === AccountControl::ACCOUNTDISABLE;
    }

    /**
     * Get the user account control integer from the user.
     *
     * @param LdapRecord $object
     *
     * @return int|null
     */
    protected function getUserAccountControl(LdapRecord $object)
    {
        return $object->getFirstAttribute('userAccountControl');
    }
}
