<?php

/**
 * Joomla! Content Management System
 *
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
class BlcExtractEvent extends AbstractEvent
{
    public function __construct(string $name, array $arguments = [])
    {
        $arguments['didExtract']  = 0;
        $arguments['todoExtract'] = 0;
        parent::__construct($name, $arguments);
    }

    public function updateDidExtract(int $count)
    {
        $this->arguments['didExtract'] += $count;
        $this->arguments['maxExtract'] -= $count;
        //do not stop propagation to get a correct count of the todo's
        if ($this->arguments['maxExtract'] <= 0) {
            // $this->stopPropagation();
        }


        return $this->arguments['didExtract'];
    }

    /* set/get last extractor*/

    public function setExtractor(string $name)
    {
        $this->arguments['extractor']  = $name;

        return $this->arguments['extractor'];
    }

    public function getExtractor()
    {
        return  $this->arguments['extractor'] ?? 'Not set';
    }

    public function updateTodo(int $count)
    {
        $this->arguments['todoExtract'] += $count;
        return $this->arguments['todoExtract'];
    }

    /**
     * setTodo
     *
     * @since       3.2
     *
     * @deprecated  24.01.1 will be removed in 24.52
     *              Use updateTodo
     */
    public function setTodo(int $count)
    {
        return $this->updateTodo($count);
    }

    public function getTodo()
    {
        return  $this->arguments['todoExtract'] ?? -1;
    }


    public function getMax()
    {

        return  max(0, $this->arguments['maxExtract']);
    }
    public function getDidExtract()
    {
        return  $this->arguments['didExtract'];
    }
}
