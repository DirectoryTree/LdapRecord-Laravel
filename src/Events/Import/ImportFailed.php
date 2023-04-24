<?php

namespace LdapRecord\Laravel\Events\Import;

use Exception;
use Illuminate\Database\Eloquent\Model as Eloquent;
use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Models\Model as LdapModel;

class ImportFailed extends Event implements LoggableEvent
{
    use Loggable;

    /**
     * The exception that was thrown during import.
     */
    public Exception $exception;

    /**
     * Constructor.
     */
    public function __construct(LdapModel $object, Eloquent $eloquent, Exception $exception)
    {
        parent::__construct($object, $eloquent);

        $this->exception = $exception;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogLevel(): string
    {
        return 'error';
    }

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): string
    {
        return "Failed importing object [{$this->object->getName()}]. {$this->exception->getMessage()}";
    }
}
