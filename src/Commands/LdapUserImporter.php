<?php

namespace LdapRecord\Laravel\Commands;

use Closure;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Facades\Event;
use LdapRecord\Laravel\Events\Import\Deleted;
use LdapRecord\Laravel\Events\Import\Restored;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\Import\Importer;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Attributes\AccountControl;
use LdapRecord\Models\Model as LdapRecord;
use LdapRecord\Models\Types\ActiveDirectory;

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
        Event::listen(Saved::class, function (Saved $event) {
            if (! $event->object instanceof ActiveDirectory) {
                return;
            }

            if (! $this->isUsingSoftDeletes($event->eloquent)) {
                return;
            }

            if ($this->trashDisabledUsers) {
                $this->delete($event->object, $event->eloquent);
            }

            if ($this->restoreEnabledUsers) {
                $this->restore($event->object, $event->eloquent);
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
     * Load the import's objects from the LDAP repository via chunking.
     *
     * @param Closure $callback
     * @param int     $perChunk
     *
     * @return void
     */
    public function chunkObjectsFromRepository(Closure $callback, $perChunk = 500)
    {
        $query = $this->applyLdapQueryConstraints(
            $this->repository->query()
        );

        $query->chunk($perChunk, function ($objects) use ($callback) {
            $callback($this->objects = $objects);
        });
    }

    /**
     * Soft deletes the specified model if their LDAP account is disabled.
     *
     * @param LdapRecord $object
     * @param Eloquent   $eloquent
     *
     * @return void
     */
    protected function delete(LdapRecord $object, Eloquent $eloquent)
    {
        if ($eloquent->trashed()) {
            return;
        }

        if (! $this->userIsDisabled($object)) {
            return;
        }

        $eloquent->delete();

        event(new Deleted($object, $eloquent));
    }

    /**
     * Restores soft-deleted models if their LDAP account is enabled.
     *
     * @param LdapRecord $object
     * @param Eloquent   $eloquent
     *
     * @return void
     */
    protected function restore(LdapRecord $object, Eloquent $eloquent)
    {
        if (! $eloquent->trashed()) {
            return;
        }

        if (! $this->userIsEnabled($object)) {
            return;
        }

        $eloquent->restore();

        event(new Restored($object, $eloquent));
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
