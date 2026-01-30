<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * stupid Joomla handles the messageQueue diffenty for the Web and Cli
 *
 */

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;


use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;

// phpcs:enable PSR1.Files.SideEffects


class BlcMessages extends BlcModule
{
    public const MSG_EMERGENCY = 'emergency';
    public const MSG_ALERT     = 'alert';
    public const MSG_CRITICAL  = 'critical';
    public const MSG_ERROR     = 'error';
    public const MSG_WARNING   = 'warning';
    public const MSG_NOTICE    = 'notice';
    public const MSG_INFO      = 'info';
    public const MSG_DEBUG     = 'debug';

    private array $messageQueue       = [];
    private ?InputFilter $inputFilter = null;

    /**
     * Enqueue a system message. Adapted from CMSApplication
     *
     * @param   string  $msg   The message to enqueue.
     * @param   string  $type  The message type. Default is message.
     *
     * @return  void
     *
     * @since   24.44.6882
     */
    public function enqueueMessage(string $msg, string $type = self::MSG_INFO): array
    {
        $this->inputFilter ??= InputFilter::getInstance(
            [],
            [],
            InputFilter::ONLY_BLOCK_DEFINED_TAGS,
            InputFilter::ONLY_BLOCK_DEFINED_ATTRIBUTES
        );
        // Don't add empty messages.
        if (trim($msg) === '') {
            return  [
                'message' => '',
                'type'    => $this->inputFilter->clean(strtolower($type), 'cmd'),
            ];
        }
        // Build the message array and apply the HTML InputFilter with the default blacklist to the message
        $message = [
            'message' => $this->inputFilter->clean($msg, 'html'),
            'type'    => $this->inputFilter->clean(strtolower($type), 'cmd'),
        ];
        if (!\in_array($message, $this->messageQueue)) {
            // Enqueue the message.
            $this->messageQueue[] = $message;
        }
        return $message;
    }

    /**
     * Get the system message queue.  Adapted from CMSApplication
     *
     * @param   bool  $clear  Clear the messages currently attached to the application object
     *
     * @return  array  The system message queue.
     *
     * @since    24.44.6882
     */
    public function getMessageQueue($clear = false): array
    {

        $messageQueue = $this->messageQueue;
        if ($clear) {
            $this->messageQueue = [];
        }

        return $messageQueue;
    }
    /**
     * Move all queued messages to the application message queue
     *
     * @param   CMSApplicationInterface|null  $app  The application instance
     *
     * @return  void
     *
     * @since   24.44.6882
     */


    public function moveToApplication(?CMSApplicationInterface $app = null): void
    {

        $app ??= Factory::getApplication();
        $messages = $this->getMessageQueue(true);
        foreach ($messages as $message) {
            $app->enqueueMessage($message['message'], $message['type']);
        }
    }
}
