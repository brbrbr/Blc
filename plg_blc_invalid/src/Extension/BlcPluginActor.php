<?php

/**
 * @package     Brambring.Plugin
 * @subpackage  Blc.Invalid
 * @version    24.02.01
 * @copyright  2024 Bram Brambring
 * @license    GNU General Public License version 3 or later;
 */

namespace Brambring\Plugin\Blc\Invalid\Extension;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpBase;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 *  SefPlus Plugin.
 *
 * @since 24.52.6877
 */
final class BlcPluginActor extends CMSPlugin implements SubscriberInterface, BlcCheckerInterface
{
    protected $allowLegacyListeners = false;


    /**
     * @since   24.52.6877
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcCheckerRequest'     => 'onBlcCheckerRequest',
        ];
    }

    public function onBlcCheckerRequest(BlcEvent $event): void
    {
        $checker = $event->getItem();
        $checker->registerChecker($this, 5);
    }
    /**
     * @since   24.52.6877
     */
    public function canCheckLink(LinkTable $linkItem): int
    {
        // $linkItem->_toCheck  is not set here
        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_FALSE;
        }
        $host = parse_url($linkItem->url, PHP_URL_HOST);
        return str_ends_with($host, '.invalid') ?    self::BLC_CHECK_TRUE :    self::BLC_CHECK_FALSE;
    }
    /**
     * @since   24.52.6877
     */
    public function checkLink(LinkTable &$linkItem, $results = []): array
    {
        // $linkItem->url is the exact url found 
        // $linkItem->_toCheck is prepared with urlencoding en punycode changes and might be altered by checkers
        //
        $host = parse_url($linkItem->_toCheck, PHP_URL_HOST);
        $parts = explode('.', $host);
        array_pop($parts);
        $part = array_pop($parts);
        if (strlen($part) == 3) {
            $httpCode = intval($part);
        } else {
            $httpCode = 206;
        }
        if ($httpCode >= 300 && $httpCode < 340) {
            $results['final_url'] = $linkItem->_toCheck . '-pseude-redirect-' . $httpCode;
            $results['redirect_count'] = 1;
        } else {
            $results['redirect_count'] = 0;
        }

        $results['http_code'] = $httpCode;
        $results['broken'] =  BlcCheckerHttpBase::getInstance()->isErrorCode($httpCode);
        $results['mime'] = 'text/html';
        return $results;
    }
}
