<?php

namespace LdapRecord\Laravel\Import;

use Closure;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Facades\Event;
use LdapRecord\Laravel\Events\Import\Deleted;
use LdapRecord\Laravel\Events\Import\Restored;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Attributes\AccountControl;
use LdapRecord\Models\Model as LdapRecord;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Query\Collection;

class LdapUserImporter extends Importer
{
    /**
     * The LDAP user repository to use for importing.
     */
    protected LdapUserRepository $repository;

    /**
     * Whether to restore soft-deleted database models if the object is enabled.
     */
    protected bool $restoreEnabledUsers = false;

    /**
     * Whether to soft-delete the database model if the object is disabled.
     */
    protected bool $trashDisabledUsers = false;

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
     */
    public function setLdapUserRepository(LdapUserRepository $repository): static
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * Enable restoring enabled users.
     */
    public function restoreEnabledUsers(): static
    {
        $this->restoreEnabledUsers = true;

        return $this;
    }

    /**
     * Enable trashing disabled users.
     */
    public function trashDisabledUsers(): static
    {
        $this->trashDisabledUsers = true;

        return $this;
    }

    /**
     * Load the import's objects from the LDAP repository.
     */
    public function loadObjectsFromRepository(?string $username = null): Collection
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
     */
    public function chunkObjectsFromRepository(Closure $callback, int $perChunk = 500): void
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
     */
    protected function delete(LdapRecord $object, Eloquent $eloquent): void
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
     */
    protected function restore(LdapRecord $object, Eloquent $eloquent): void
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
     */
    protected function userIsEnabled(LdapRecord $object): bool
    {
        return $this->getUserAccountControl($object) === null ? false : ! $this->userIsDisabled($object);
    }

    /**
     * Determines whether the user is disabled.
     */
    protected function userIsDisabled(LdapRecord $object): bool
    {
        return ($this->getUserAccountControl($object) & AccountControl::ACCOUNTDISABLE) === AccountControl::ACCOUNTDISABLE;
    }

    /**
     * Get the user account control integer from the user.
     */
    protected function getUserAccountControl(LdapRecord $object): ?int
    {
        return $object->getFirstAttribute('userAccountControl');
    }
}
