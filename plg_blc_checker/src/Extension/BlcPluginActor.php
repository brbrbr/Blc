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
use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Factory;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcCheckerInterface
{
    use BlcHelpTrait;

    protected $autoloadLanguage = true;
    private BlcCheckerInterface $checker;
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
        $this->checker = clone BlcCheckerHttpCurl::getInstance();
        //    $this->checker->initConfig($this->params);
        $checker = $event->getItem();
        $checker->registerChecker($this, $priority, true);
    }

    public function initConfig(Registry $config): void
    {
        $this->params->set('name', 'Secondary Checker');
        $this->checker->initConfig($this->params);
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        return $this->checker->canCheckLink($linkItem) ? self::BLC_CHECK_ALWAYS : self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem, $results = [], object|array $options = []): array
    {
        $code = $results['http_code'] ?? 0;
        //do not use isErrorCode, only 'real' faults.
        if (($code > 400 && $code < 600) || $code == self::BLC_DNS_WAF_CODE) {
            $results = $this->checker->checkLink($linkItem);
        }

        return $results;
    }
}
