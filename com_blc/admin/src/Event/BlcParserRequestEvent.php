<?php

/**
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Component\Blc\Administrator\Event;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

//for now lets use one event class.

/**
 * Base class for Model events
 *
 * @since  5.0.0
 */
class BlcParserRequestEvent extends BlcEvent
{
    /**
     * Constructor.
     *
     * @param   string  $name       The event name.
     * @param   array   $arguments  The event arguments.
     *
     * @throws  \BadMethodCallException
     *
     * @since   25.44.7398
     */
    public function __construct($name, array $arguments = [])
    {
        if (!\array_key_exists('subject', $arguments)) {
            throw new \BadMethodCallException("Argument 'subject' of event {$name} is required but has not been provided");
        }
        if (!method_exists($arguments['subject'], 'registerParser')) {
            throw new \BadMethodCallException("Argument 'subject' of event {$name} must implement : registerParser");
        }

        parent::__construct($name, $arguments);
    }
}
