<?php

/**
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Event;

use Joomla\CMS\Event\AbstractEvent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

//for now lets use one event class.

/**
 * Base class for Model events
 *
 * @since  5.0.0
 */
class BlcEvent extends AbstractEvent
{
    /**
     * Constructor.
     *
     * @param   string  $name       The event name.
     * @param   array   $arguments  The event arguments.
     *
     * @throws  \BadMethodCallException
     *
     * @since   5.0.0
     */
    public function __construct($name, array $arguments = [])
    {
        parent::__construct($name, $arguments);
    }

    /**
     * Getters

     *
     * @return  mixed
     *
     * @since  25.44.7314
     */

    public function getArgument($name, $default = null): mixed
    {
        return $this->arguments[$name] ?? $default;
    }


    public function getContext(): string
    {
        return $this->getArgument('context', '');
    }

    public function getId(): int|null
    {
        return $this->getArgument('id', null);
    }

    public function getEvent(): string
    {
        return $this->getArgument('event', '');
    }
    public function getItem(): object
    {
        return $this->arguments['item'] ?? new \stdClass();
    }

    /**
    * Get the event result.
    *
    * @return  mixed
    * @since   5.0.0
    */
    public function getReport(): mixed
    {
        return $this->getArgument('result', '');
    }


    /**
     * Update the result of the event.
     *
     * @param   mixed  $data  What to add to the result.
     *
     * @return  static
     * @since   5.0.0
     */
    public function setReport(mixed $data): static
    {
        $this->arguments['result'] = $data;
        return $this;
    }
}
