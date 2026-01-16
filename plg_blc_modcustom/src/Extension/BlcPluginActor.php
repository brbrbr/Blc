<?php

declare(strict_types=1);

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\ModCustom\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Module;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-modcustom';
    protected $catids      = [];
    protected $context     = 'com_modules.module';
    //some contexes behave like com_modules.module but have a different name.
    private $useForContext = ['com_modules.module', 'com_advancedmodules.module'];
    private $replacedUrls  = [];


    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->setRecheck();
    }

    public static function getSubscribedEvents(): array
    {

        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }


    public function onBlcExtensionAfterSave(BlcEvent $event): void
    {

        parent::onBlcExtensionAfterSave($event);

        //the save is from extension but it is more ore less content
        $context = $event->getContext();


        if (!\in_array($context, $this->useForContext)) {
            return;
        }

        $table   = $event->getItem();


        $id = $table->get('id');
        // generate and empty object

        $arguments =
            [
                'context' => $this->context, // not context since we might have an 'alias'
                'id'      => $id,
                'event'   => 'onsave',
            ];

        $event = new BlcEvent('onBlcContainerChanged', $arguments);
        $this->onBlcContainerChanged($event);
    }





    protected function getContainerTable()
    {
        try {
            $db    = $this->getDatabase();
            $table = new Module($db);
        } catch (\Error) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_GETCONTAINERTABLE_ERROR'),
                'warning'
            );
            return false;
        }

        return $table;
    }


    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {

        $messageLinks = $this->getMessageLinks($instance);

        if (!$instance->parser) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_PARSER_NOT_SET')),
                'warning'
            );
            return;
        }

        $table        = $this->getContainerTableById($instance->container_id);

        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                'warning'
            );
            return;
        }

        //Actually it is not to bad if someone is editing. The replaced link is simply overwritten again.
        if ($table->checked_out) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_CHECKED_OUT_ERROR')),
                'warning'
            );
            return;
        }

        $update = false;
        $field  = $instance->field;

        switch ($field) {
            case 'content':
                $text         = $table->{$field};
                $textParsers  =  BlcParseController::getInstance();

                $replacedText = $textParsers->replaceLinkInSourceByParser($instance->parser, $text, $link->url, $newUrl);

                if ($replacedText !== $text) {
                    $table->{$field} = $replacedText;
                    $update          = true;
                }
                break;
            case 'backgroundimage':
                if (isset($table->params)) {
                    $params =  json_decode($table->params);

                    $url    = $params->backgroundimage ?? '';
                    if ($url && $url == $link->url && $url != $newUrl) {
                        $params->backgroundimage = $newUrl;
                        $update                  = true;
                        $table->params           = json_encode($params);
                    }
                }
                break;
        }
        $field = $instance->field; //just to be consitent
        if ($update) {
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            }

            if (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }
            $this->replacedUrls[] = $newUrl;
            $this->parseContainer($instance->container_id);
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $messageLinks),
                'success'
            );
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, $field, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                    'warning'
                );
            }
        }
    }

    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {
        $db    = $this->getDatabase();

        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from($db->quoteName('#__modules', 'a'))
            ->where('COALESCE(' .   $db->quoteName('a.content') . ",'') != ''");

        if (!$idOnly) {
            $query->select('`a`.`title`,`a`.`content`,`a`.`params`');
        }
        if ($this->getParamLocalGlobal('access')) {
            $query->where('`a`.`access` IN (1)');
        }

        if ($this->params->get('administrator', 1) == 0) {
            $query->where('`a`.`client` IN (0)');
        }

        if ($this->getParamLocalGlobal('published')) {
            $nowQouted         = $db->quote(Factory::getDate()->toSql());
            $nullDateQuoted    = $db->quote($db->getNullDate());
            $query->where('`a`.`published` = 1')
                ->where("(`a`.`publish_up` IS NULL OR  `a`.`publish_up` = $nullDateQuoted OR `a`.`publish_up` <= $nowQouted)")
                ->where("( `a`.`publish_down` IS NULL OR `a`.`publish_down` = $nullDateQuoted OR  `a`.`publish_down` >= $nowQouted)");
        } else {
            $query->where('`a`.`published` > -1'); //ignore trashed
        }
        return $query;
    }

    public function getEditLink($instance): string
    {
        return Route::link(
            'administrator',
            'index.php?option=com_modules&task=module.edit&id=' . (int)$instance->container_id
        );
    }

    public function getViewLink($instance): string
    {
        return $this->getEditLink($instance);
    }


    protected function parseContainerFields($row): void
    {

        $id         = $row->id;
        $synchTable = $this->getItemSynch($id);
        $synchId    = $synchTable->id;
        if (!$synchId) {
            //creation failed most likely due to concurrent jobs
            //ignore next job will retry
            return;
        }
        $this->purgeInstances($synchId);
        $fields = [
            'content' => $row->content,

        ];

        $this->processText($fields, 'content', $synchId);
        if (isset($row->params)) {
            $params =  json_decode($row->params);
            if (!empty($params->backgroundimage)) {
                $this->processLink($params->backgroundimage, 'backgroundimage', $synchId);
            }
        }

        $synchTable->setSynched();
    }

    protected function getUnsynchedQuery(DatabaseQuery $query)
    {
        //modules don't have a modified date.
        //TOD resync after x days option
        $db     = $this->getDatabase();
        $wheres = [];
        $main   = "SELECT * FROM `#__blc_synch` `s` WHERE `s`.`container_id` = `a`.`{$this->primary}`" .
            ' AND `s`.`plugin_name` = ' . $db->quote($this->_name);
        $wheres[] = "NOT EXISTS ( {$main})";
        $wheres[] = "EXISTS ( {$main} AND `s`.`last_synch` < " . $db->quote($this->synchStillValidDate->toSql())  . ')';
        $query->extendWhere('AND', $wheres, 'OR');
    }
}
