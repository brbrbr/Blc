<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Yootheme\Extension;

use Blc\Component\Blc\Administrator\Event\BlcInstanceDisplayEvent;
use Blc\Component\Blc\Administrator\Event\BlcParserRequestEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends CMSPlugin implements SubscriberInterface
{
    public function __construct(array $config = [])
    {
        if (version_compare(JVERSION, '5.3', '>=')) {
            parent::__construct($config);
        } else {
            $dispatcher =  Factory::getApplication()->getDispatcher();
            parent::__construct($dispatcher, $config);
        }
    }
    public static function getSubscribedEvents(): array
    {

        return [
            'onBlcParserRequest'              => 'onBlcParserRequest',
            'onBlcInstanceBeforeDisplayEvent' => 'onBlcInstanceBeforeDisplayEvent',
        ];
    }

    public function onBlcParserRequest(BlcParserRequestEvent $event): void
    {
        $parser = $event->getItem();
        $parser->registerParser(YoothemeParser::getInstance());
    }

    public function onBlcInstanceBeforeDisplayEvent(BlcInstanceDisplayEvent $event): void
    {

        $instances = $event->getSubject();
        //php 8.4 support array_any - some sites might have a polyfill
        if (\function_exists('array_any')) {
            $fn = 'array_any';
        } else {
            $fn = [$this, 'arrayAny'];
        }
        $parser = YoothemeParser::getInstance()->getName();

        $isYootheme = $fn(
            $instances,
            fn($i) => $i->parser == $parser
        );

        if ($isYootheme) {
            $instances = array_filter(
                $instances,
                fn($i) => $i->field != 'introtext'
            );
            $event->setInstances($instances);
        }
    }

    /**
     * php8.4 polyfill

     *
     * @return  $this
     *
     * @since  25.44.7562
     */
    private function arrayAny(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }

        return false;
    }
}
