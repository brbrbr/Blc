<?php

declare(strict_types=1);

/**
 * @package     Blc.Plugin
 * @subpackage  Blc.Provider
 * @version   24.44
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Provider\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

final class BlcPluginActor extends CMSPlugin implements SubscriberInterface
{
    use BlcHelpTrait;


    protected $autoloadLanguage     = true;
    private const  HELPLINK         = 'https://brokenlinkchecker.dev/extensions/plg-blc-provider';

    /**
     * @param array<mixed> $config
     */

    public function __construct(array $config = [])
    {
        if (version_compare(JVERSION, '5.3', '>=')) {
            parent::__construct($config);
        } else {
            $dispatcher =   \Joomla\CMS\Factory::getApplication()->getDispatcher();  //@phpstan-ignore method.deprecatedInterface
            parent::__construct($dispatcher, $config);
        }
    }


    // phpcs:enable Generic.Files.LineLength
    public static function getSubscribedEvents(): array
    {

        return [
            'onBlcCheckerRequest' => 'onBlcCheckerRequest',

        ];
    }

    public function onBlcCheckerRequest($event): void
    {

        $checker       = $event->getItem();
        $OEmbedChecker = OEmbedChecker::getInstance();
        $OEmbedChecker->setParams($this->params);
        $checker->registerChecker($OEmbedChecker, 40); //before the http checker (50)

        $apiKey = $this->params->get('youapi');
        if ($apiKey) {
            $YoutubeChecker = YoutubeChecker::getInstance();
            $YoutubeChecker->setParams($this->params);
            $checker->registerChecker($YoutubeChecker, 39); //before the OEmbedChecker (40)
        }

        $facebook = $this->params->get('facebook', 1);

        if ($facebook) {
            $FacebookChecker = FacebookChecker::getInstance();
            $FacebookChecker->setParams($this->params);
            $checker->registerChecker($FacebookChecker, 55); //after  the http checker (50)
        }
    }
}
