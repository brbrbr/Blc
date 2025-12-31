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
use Blc\Component\Blc\Administrator\Traits\BlcSplitOptionTrait;
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
    use BlcSplitOptionTrait;

    protected $autoloadLanguage = true;
    private array $matchCache   = [];

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

        if ($this->getHostConfig($linkItem) !== false) {
            return self::BLC_CHECK_TRUE;
        }


        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem, ?Registry $config = null): void
    {

        $hostConfig = $this->getHostConfig($linkItem);

        if ($hostConfig !== false) {
            foreach (get_object_vars($hostConfig) as $key => $value) {
                if ($key === 'host') {
                    continue;
                }

                $config->set($key, $value);
            }
        }
    }
    private function gethostConfig(LinkTable &$linkItem): bool|object
    {

        $hostLists      = $this->params->get('hosts', []);

        if (empty($hostLists)) {
            return false;
        }
        $linkItemhost = parse_url($linkItem->toCheck, PHP_URL_HOST);
        if (!$linkItemhost) { //internal links and special like mailto:
            return false;
        }

        if (isset($this->matchCache[$linkItemhost])) {
            return $this->matchCache[$linkItemhost];
        }
        foreach ($hostLists as $hostConfig) {
            if (!empty($hostConfig->host)) {
                $match = $hostConfig->match ?? '';
                $hosts = array_filter($this->splitOption($hostConfig->host));
                foreach ($hosts as $host) {
                    switch ($match) {
                        default:
                        case '':
                            if ($host === $linkItemhost) {
                                return $this->storeConfig($linkItemhost, $hostConfig);
                            }
                            break;

                        case 'www':
                            if (("www.{$host}" == $linkItemhost) || ($host === $linkItemhost)) {
                                return $this->storeConfig($linkItemhost, $hostConfig);
                            }
                            break;

                        case 'ends':
                            if (str_ends_with($linkItemhost, ".{$host}")) {
                                return $this->storeConfig($linkItemhost, $hostConfig);
                            }
                            break;
                    }
                }
            }
        }
        return false;
    }
    private function storeConfig(string $linkItemhost, object $hostConfig): object
    {

        if (!empty($hostConfig->cookiestring)) {
            $hostConfig->cookies = $hostConfig->cookiestring;
            unset($hostConfig->cookiestring);
        }
        $this->matchCache[$linkItemhost] = $hostConfig;

        return $hostConfig;
    }
}
