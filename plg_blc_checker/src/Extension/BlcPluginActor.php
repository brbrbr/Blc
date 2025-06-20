<?php

/**
 * @package     BLC
 * @subpackage  blc.checker
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Checker\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\GetCheckerTrait;
use Joomla\CMS\Factory;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcCheckerInterface
{
    use BlcHelpTrait;
    use GetCheckerTrait;

    protected $autoloadLanguage = true;

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-checker';
    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcCheckerRequest' => 'onBlcCheckerRequest',
        ];
    }

    public function onBlcCheckerRequest($event): void
    {
        $lang = Factory::getApplication()->getLanguage();

        $extension = 'com_blc';
        $lang->load($extension, 'Administrator');

        $priority      = $this->params->get('priority', 55);
        $checker       = $event->getItem();
        $checker->registerChecker($this, $priority, true);
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        $http_code =   $linkItem->http_code ?? 0;

        if (
            //do not recheck internal links.
            !$linkItem->isInternal() &&
            //do not use isErrorCode, only 'real' faults.
            (($http_code > 400 && $http_code < 600) || $http_code == self::BLC_DNS_WAF_CODE)
        ) {
            return self::BLC_CHECK_TRUE;
        }
        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem): void
    {
        $linkItem->log[] = self::class;
        $http_code           =   $linkItem->http_code;
        $linkItem->http_code = self::BLC_CHECK_UNSET; //reset check state
        $checker             = $this->getChecker(clone: true);
        if ($checker->canCheckLink($linkItem)) {
            $checker->checkLink($linkItem, config: $this->params);
        } else {
            $linkItem->http_code = $http_code;
        }
    }
}
