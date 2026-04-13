<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 * @since 24.44.6670
 */

namespace Blc\Component\Blc\Administrator\Traits;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcMessages;

/**
 * Trait for standardized message handling in BLC components
 *
 * Provides consistent message enqueueing to either BLC-specific message queue
 * or the application's general message queue.
 *
 * @since 24.44.6670
 */
trait BlcMessageTrait
{
    /**
     * Enqueue an informational message
     *
     * @param string $message The message to display
     * @param bool   $isBlc   Whether to use BLC message queue (true) or application queue (false)
     *
     * @return void
     */
    protected function messageInfo(string $message, bool $isBlc = true): void
    {
        $this->enqueueMessage($message, 'info', $isBlc);
    }


    /**
     * Enqueue an alert message
     *
     * @param string $message The message to display
     * @param bool   $isBlc   Whether to use BLC message queue (true) or application queue (false)
     *
     * @return void
     */
    protected function messageAlert(string $message, bool $isBlc = true): void
    {
        $this->enqueueMessage($message, 'alert', $isBlc);
    }

    /**
     * Enqueue a warning message
     *
     * @param string $message The message to display
     * @param bool   $isBlc   Whether to use BLC message queue (true) or application queue (false)
     *
     * @return void
     */
    protected function messageWarning(string $message, bool $isBlc = true): void
    {
        $this->enqueueMessage($message, 'warning', $isBlc);
    }

    /**
     * Enqueue an error message
     *
     * @param string $message The message to display
     * @param bool   $isBlc   Whether to use BLC message queue (true) or application queue (false)
     *
     * @return void
     */
    protected function messageError(string $message, bool $isBlc = true): void
    {
        $this->enqueueMessage($message, 'error', $isBlc);
    }

    /**
     * Enqueue a success message
     *
     * @param string $message The message to display
     * @param bool   $isBlc   Whether to use BLC message queue (true) or application queue (false)
     *
     * @return void
     */
    protected function messageSuccess(string $message, bool $isBlc = true): void
    {
        $this->enqueueMessage($message, 'success', $isBlc);
    }

    /**
     * Enqueue a message with specified type
     *
     * Routes the message to either BLC's message queue or the application's
     * message queue based on the $isBlc parameter.
     *
     * @param string $message The message to display
     * @param string $type    Message type: 'info', 'warning', 'error', 'success', 'notice', 'message'
     * @param bool   $isBlc   Whether to use BLC message queue (true) or application queue (false)
     *
     * @return void
     */
    protected function enqueueMessage(string $message, string $type = 'info', bool $isBlc = true): void
    {
     
        if ($isBlc) {
            BlcMessages::getInstance()->enqueueMessage($message, $type);
        } else {
            $this->getApplication()->enqueueMessage($message, $type);
        }
    }
}
