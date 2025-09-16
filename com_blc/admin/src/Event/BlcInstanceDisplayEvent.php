<?php

/**
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Component\Blc\Administrator\Event;

use Joomla\CMS\Event\AbstractEvent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

//for now lets use one event class.

/**
 * Base class for Model events
 *
 * @since  25.44.7562
 */
class BlcInstanceDisplayEvent extends AbstractEvent
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
        if (!\array_key_exists('subject', $arguments)) {
            throw new \BadMethodCallException("Argument 'subject' of event {$name} is required but has not been provided");
        }
    }

    /**
     * Getters

     *
     * @return  mixed
     *
     * @since  25.44.7562
     */


    public function getArgument($name, $default = null): mixed
    {
        return $this->arguments[$name] ?? $default;
    }
    /**
     * Getters

     *
     * @return  array
     *
     * @since  25.44.7562
     */

    /**
     * Add argument to event.
     *
     * @param   string  $name   Argument name.
     * @param   mixed   $value  Value.
     *
     * @return  $this
     */
    public function setArgument($name, $value)
    {
        if ($name == 'subject') {
            $this->setInstances($value);
            return $this;
        }
        if ($name == 'instances') {
            $this->setInstances($value);
            return $this;
        }
        $this->arguments[$name] = $value;
        return $this;
    }


    /**
     * Getters

     *
     * @return  array
     *
     * @since  25.44.7562
     */


    public function getSubject(): array
    {
        //subject is set otherwise the constructor would have thrown an exception
        return $this->arguments['subject'];
    }


    /**
     * setters

     *
     * @return  $this
     *
     * @since  25.44.7562
     */


    public function getInstances()
    {
        return $this->getSubject();
    }

    /**
     * setters
     * @param array $subject
     * @return  $this
     *
     * @since  25.44.7562
     */


    public function setInstances(array $subject)
    {
        return $this->setSubject($subject);
    }


    /**
     * setters
     * @param array $subject
     *
     * @return  $this
     *
     * @since  25.44.7562
     */


    public function setSubject(array $subject)
    {
        $this->arguments['subject'] = $subject;
        return $this;
    }
}
