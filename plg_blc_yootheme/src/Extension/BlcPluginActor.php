<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Yootheme\Extension;

use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {

        return [
            'onBlcParserRequest' => 'onBlcParserRequest',
        ];
    }

    public function onBlcParserRequest(BlcEvent $event): void
    {
        $parser = $event->getItem();
        $parser->registerParser(YoothemeParser::getInstance());
    }
}
