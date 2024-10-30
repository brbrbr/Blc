<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\CfContent\Extension;

use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Language\Text;
use Joomla\Event\SubscriberInterface;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Event\BlcEvent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends CMSPlugin implements SubscriberInterface
{
    use BlcExtractTrait;
    protected $context     = 'com_content.article';
    protected $autoloadLanguage = false;

    private function ObsoleteMessage()
    {
        $this->loadLanguage('Plg_' . $this->_type . '_' . $this->_name . '.sys');
        $this->getApplication()->enqueueMessage(Text::_('PLG_BLC_CFCONTENT_OBSOLETE'), 'warning');
    }
    //this is the default Extract execution for normal database based extractors.
    public function onBlcExtract(BlcExtractEvent $event): void
    {
        $this->ObsoleteMessage();
    }

    public function onBlcContainerChanged(BlcEvent $event): void
    {
        //logging might confuse applications
        ob_start();
        $context   = $event->getContext();

        if ($context != $this->context) {
            return;
        }
        $this->ObsoleteMessage();
    }


    public function onBlcExtensionAfterSave(BlcEvent $event): void
    {

        
         //this->params holds the old config
         if (!$this->params) {
            return; //after pluging enable
        }
        $table = $event->getItem();
        $type  = $table->get('type');
        if ($type != 'plugin') {
            return;
        }

        $folder = $table->get('folder');
        if ($folder != $this->_type) {
            return;
        }

        $element = $table->get('element');
        if ($element != $this->_name) {
            return;
        }
    
      /*
        if ($context != $this->context) {
            return;
        }*/
        $this->ObsoleteMessage();
    }
}
