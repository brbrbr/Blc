<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\GVS\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Gvs\Component\Gvs\Site\Helper\RouteHelper as GvsLinks;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcCheckerInterface, BlcExtractInterface
{
    protected $primary =  'kalender_id';
    protected $context = 'com_gvs.kalender';
    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */
    public static function getSubscribedEvents(): array
    {

        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcCheckerRequest'     => 'onBlcCheckerRequest',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }

    public function onBlcCheckerRequest($event): void
    {
        $checker = $event->getItem();
        $checker->registerChecker($this, 10);
    }
    //Check for [ROOT] replace pattern
    public function canCheckLink(LinkTable $linkItem): int
    {
        if ($linkItem->isInternal()) {
            $path = $linkItem->url;
            if (strpos($path, '[root]') !== false) {
                return self::BLC_CHECK_IGNORE;
            }
        }
        return self::BLC_CHECK_FALSE;
    }
    public function checkLink(LinkTable &$linkItem, $results = []): array
    {
        //this link should never be checked or imported
        //however lets return sensible data

        $results['http_code']           = self::BLC_UNCHECKED_IGNORELINK;
        $results['broken']              = false;
        $linkItem->log['Configuration'] = 'Special link for GVS';
        return $results;
    }

    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        //het is niet nodig voor elke url een eigen synchedId te maken. We doen toch altijd alles
        //omdat de kalender tabel geen modidified heeft
        //daarom misbruik ik het veld `field` in `instances` als kalelender_id

        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from('`kalender` `a`');
        if (!$idOnly) {
            $query->select('`a`.`titel` `anchor`,`a`.`url` `url`');
        }

        if ($this->params->get('active', 1)) {
            $query->where("(`a`.`datum` > current_timestamp())");
        } else {
            $query->where("1"); //otherwise the extend where will fail
        }
        return $query;
    }

    #[\Override]
    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {

        $db    = $this->getDatabase();
        $field = 'kalender';
        //for the plugin it's simply replacing the old url.
        $object = (object)[
            'url'         => $newUrl,
            'modified'    => Factory::getDate()->toSql(), //utc tijd als in joomla
            'kalender_id' => $instance->container_id,
        ];
        $db->updateObject('kalender', $object, 'kalender_id', false);
        $count    = $db->getAffectedRows();
        $viewHtml = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), 'replaced');
        if ($count > 0) { // should be 1 or 0
            $this->parseContainer($instance->container_id);
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $viewHtml),
                'succcess'
            );
        } else {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, $field, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                'warning'
            );
        }
    }

    public function getTitle($data): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.titel", 'titel'))
            ->from('`kalender` `a`')
            ->where($db->quoteName("a.{$this->primary}") . ' = :containerId')
            ->bind(':containerId', $data->container_id);
        $db->setQuery($query);
        return $db->loadResult() ?? '';
    }
    public function getEditLink($data): string
    {
        return GvsLinks::getMenuLink('activiteitenkalender') . '/bewerk/' . (int)$data->container_id;
    }
    public function getViewLink($data): string
    {
        return GvsLinks::getMenuLink('activiteitenkalender') . '/' . (int)$data->container_id;
    }

    protected function parseContainer(int $id): void
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $query->where($db->quoteName("a.{$this->primary}") . ' = :containerId')
        ->bind(':containerId', $id, ParameterType::INTEGER);
        $db->setQuery($query);
        $row = $db->loadObject();
        if ($row) {
            $this->parseContainerFields($row);
        } else {
            $synchTable = $this->getItemSynch($id);
            if ($synchTable->id) {
                $this->purgeInstances($synchTable->id);
            }
        }
    }

    protected function parseContainerFields($row): void
    {
        $id = $row->id;
        //   unset($row['id']);
        $synchTable = $this->getItemSynch($id);
        $synchedId  = $synchTable->id;
        $this->purgeInstances($synchedId);
        $this->processLink((array)$row, 'kalender', $synchedId);
        $synchTable->setSynched();
    }

}
