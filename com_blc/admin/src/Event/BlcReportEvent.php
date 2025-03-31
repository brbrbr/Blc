<?php

declare(strict_types=1);
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
class BlcReportEvent  extends AbstractEvent
{

    private $must = [
        'action',
        'client',
        'format',
    ];
    /**
     * Constructor.
     *
     * @param   string  $name       The event name.
     * @param   array   $arguments  The event arguments.
     *
     * @throws  \BadMethodCallException
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct($name, array $arguments = [])
    {
        foreach ($this->must as $key) {
            if (!\array_key_exists($key, $arguments)) {
                throw new \BadMethodCallException("Argument '$key' of event {$name} is required but has not been provided");
            }
        }


        parent::__construct($name, $arguments);
    }


    /**
     * Get an event argument value.
     *
     * @param   string  $name     The argument name.
     * @param   mixed   $default  The default value if not found.
     *
     * @return  mixed  The argument value or the default value.
     *
     * @since   1.0
     */
    public function getArgument($name, $default = null)
    {
        return $this->arguments[$name] ?? $default;
    }


    /**
     * Add argument to event.
     * It will use a pre-processing method if one exists. The method has the signature:
     *
     * onSet<ArgumentName>($value): mixed
     *
     * where:
     *
     * $value  is the value being set by the user
     * It returns the value to return to set in the $arguments array of the event.
     *
     * @param   string  $name   Argument name.
     * @param   mixed   $value  Value.
     *
     * @return  $this
     *
     * @since   4.0.0
     */
    public function setArgument($name, $value)
    {




        // Look for the method for the value pre-processing/validation
        $ucfirst     = ucfirst($name);
        $methodName1 = 'onSet' . $ucfirst;


        if (method_exists($this, $methodName1)) {

            $value = $this->{$methodName1}($value);
        }

        $this->arguments[$name] = $value;

        return $this;
    }

    protected function onSetAction(string $value): string
    {

        return $value;
    }

    protected function onSetFormat(string $value): string
    {

        return $value;
    }

    protected function onSetClient(string $value): string
    {

        return $value;
    }

    public function getFormat(): string
    {
        return $this->getArgument('format', '');
    }

    public function getAction(): string
    {
        return $this->getArgument('action', '');
    }

    public function getClient(): string
    {
        return $this->getArgument('client', '');
    }


    /**
     * Get the event result.
     *
     * @return  mixed
     * @since   5.0.0
     */
    public function getReport(): mixed
    {
        return $this->getArgument('report', '');
    }

    protected function onSetReport(mixed $data): static
    {
        return $this->setReport($data);
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
        $this->arguments['report'] = $data;
        return $this;
    }
}
