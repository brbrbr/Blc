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

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\BlcSplitOptionTrait;
use Blc\Component\Blc\Administrator\Traits\GetCheckerTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface, BlcCheckerInterface
{
    use BlcHelpTrait;

    use GetCheckerTrait;
    use DatabaseAwareTrait;
    use BlcExtractTrait;
    use BlcSplitOptionTrait;

    protected $autoloadLanguage = true;
    private array $matchCache   = [];

    private const HELPLINK          = 'https://brokenlinkchecker.dev/extensions/plg-blc-checker';
    protected $allowLegacyListeners = false;
    protected $componentConfig;

    public function __construct(array $config = [])
    {
        if (version_compare(JVERSION, '5.3', '>=')) {
            parent::__construct($config);
        } else {
            parent::__construct(Factory::getApplication()->getDispatcher(), $config);
        }

        $this->componentConfig = ComponentHelper::getParams('com_blc');
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
        $linkItemhost = parse_url((string) $linkItem->toCheck, PHP_URL_HOST);
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
