<?php

/**
 * @package     Brambring.Plugin
 * @subpackage  Blc.Invalid
 * @version    24.02.01
 * @copyright  2024 Bram Brambring
 * @license    GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Invalid\Extension;

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
    protected $start_time;


    public function __construct(array $config = [])
    {
        if (version_compare(JVERSION, '5.3', '>=')) {
            parent::__construct($config);
        } else {
            $dispatcher =  Factory::getApplication()->getDispatcher();
            parent::__construct($dispatcher, $config);
        }
    }
    /**
     * @since   24.52.6877
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcCheckerRequest' => 'onBlcCheckerRequest',
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
        $this->start_time                 = hrtime(true);

        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_FALSE;
        }
        $host = parse_url($linkItem->url, PHP_URL_HOST);

        return ($host && str_ends_with($host, '.invalid')) ? self::BLC_CHECK_TRUE : self::BLC_CHECK_FALSE;
    }
    /**
     * @since   24.52.6877
     */
    public function checkLink(LinkTable &$linkItem): void
    {
        // $linkItem->url is the exact url found
        // $linkItem->toCheck is prepared with urlencoding en punycode changes and might be altered by checkers
        //
        $linkItem->log[] = self::class;
        $host            = parse_url($linkItem->toCheck, PHP_URL_HOST);
        $parts           = explode('.', $host);
        array_pop($parts);
        $part = array_pop($parts);
        if (\strlen($part) == 3) {
            $httpCode = \intval($part);
        } else {
            $httpCode = 206;
        }
        if ($httpCode >= 300 && $httpCode < 340) {
            $linkItem->final_url      = $linkItem->toCheck . '-pseude-redirect-' . $httpCode;
            $linkItem->redirect_count = 1;
        } else {
            $linkItem->redirect_count = 0;
            $linkItem->final_url      = $linkItem->url;
        }

        $linkItem->http_code          = $httpCode;
        $linkItem->broken             =  BlcCheckerHttpBase::getInstance()->isErrorCode($httpCode);
        $linkItem->mime               = 'text/html';
        $linkItem->request_duration   = (hrtime(true) - $this->start_time) / 1e+9; //nanoseconds to seconds
    }
}
