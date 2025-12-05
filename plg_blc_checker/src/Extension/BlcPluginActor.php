<?php

declare(strict_types=1);

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
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcCheckerInterface
{
    use BlcHelpTrait;
    use GetCheckerTrait;

    protected $autoloadLanguage = true;

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-checker';
    public function __construct(array $config = [])
    {
        parent::__construct($config);
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

        $checker       = $event->getItem();
        $checker->registerChecker($this, 5, true);
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        $hosts      = $this->params->get('hosts', []);

        if (empty($hosts)) {
            return self::BLC_CHECK_FALSE;
        }

        $host = parse_url($linkItem->url, PHP_URL_HOST);
        foreach ($hosts as $hostConfig) {
            if (isset($hostConfig->host) && $hostConfig->host === $host) {
                return self::BLC_CHECK_TRUE;
            }
        }


        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem, ?Registry $config = null): void
    {

        $hosts      = $this->params->get('hosts', []);

        if (empty($hosts)) {
            return;
        }
        $host = parse_url($linkItem->toCheck, PHP_URL_HOST);
        foreach ($hosts as $hostConfig) {
            if (isset($hostConfig->host) && $hostConfig->host === $host) {
                foreach (get_object_vars($hostConfig) as $key => $value) {
                    if ($key === 'host') {
                        continue;
                    }
                    $config->set($key, $value);
                }
                break;
            }
        }
    }
}
