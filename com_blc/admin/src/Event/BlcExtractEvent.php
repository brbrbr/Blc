<?php

/**
 * Joomla! Content Management System
 *
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
 * @since  5.0.0
 */
class BlcExtractEvent extends AbstractEvent
{
    public function __construct(string $name, array $arguments = [])
    {
        $arguments['didExtract']  = 0;
        $arguments['todoExtract'] = 0;
        $arguments['maxExtract'] ??= 0;
        parent::__construct($name, $arguments);
    }

    public function updateDidExtract(int $count): int
    {
        $this->arguments['didExtract'] += $count;
        $this->arguments['maxExtract'] -= $count;
        //do not stop propagation to get a correct count of the todo's



        return $this->arguments['didExtract'];
    }

    /* set/get last extractor*/

    public function setExtractor(string $name): string
    {
        $this->arguments['extractor']  = $name;

        return $this->arguments['extractor'];
    }

    public function getExtractor(): string
    {
        return  $this->arguments['extractor'] ?? 'Not set';
    }

    public function updateTodo(int $count): int
    {
        $this->arguments['todoExtract'] += $count;
        return $this->arguments['todoExtract'];
    }


    public function getTodo(): int
    {
        return  $this->arguments['todoExtract'] ?? -1;
    }


    public function getMax(): int
    {
        return  max(0, $this->arguments['maxExtract']);
    }
    public function getDidExtract(): int
    {
        return  $this->arguments['didExtract'];
    }
}
