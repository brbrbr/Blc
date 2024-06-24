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
     * Getter for the context argument.
     *

     *
     * @return  string
     *
     * @since  5.0.0
     */

    public function getContext(): string
    {
        return $this->arguments['context'];
    }

    /**
     * Setter for the context argument.
     *
     * @param   string  $value  The value to set
     *
     * @return  string
     *
     * @since  5.0.0
     */
    protected function onSetContext(string $value): string
    {
        return $value;
    }


    public function getId(): string
    {
        return $this->arguments['id'];
    }

    public function getEvent(): string
    {
        return $this->arguments['event'];
    }
    public function getItem(): object
    {
        return $this->arguments['item'];
    }

    /**
     * Setter for the item argument.
     *
     * @param   object  $value  The value to set
     *
     * @return  object
     *
     * @since  5.0.0
     */
    protected function onSetItem(object $value): object
    {
        return $value;
    }

    /**
     * Update the result of the event.
     *
     * @param   mixed  $data  What to add to the result.
     *
     * @return  static
     * @since   5.0.0
     */
    public function updateEventResult($data): static
    {
        $this->arguments['result'] = $data;

        return $this;
    }

    /**
     * Get the event result.
     *
     * @return  mixed
     * @since   5.0.0
     */
    public function getEventResult(): mixed
    {
        return $this->arguments['result'] ?? null;
    }
}
