<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\RsEventsEvent\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends CMSPlugin implements SubscriberInterface, BlcExtractInterface
{
    use DatabaseAwareTrait;
    use BlcExtractTrait; /* for now. This must move to implementations of blcExtractInterface */

    protected $componentConfig;
    protected $allowLegacyListeners = false;
    protected $primary              =  'id';
    protected $context              = 'com_rseventspro.event';
    protected $translatable         = ['description', 'URL'];
    private int $extensionId        = 0;

    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {

        $this->extensionId = $config['id'] ?? 0;
        parent::__construct($dispatcher, $config);
        $this->componentConfig = ComponentHelper::getParams('com_blc');
        $this->setRecheck();
    }

    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        //het is niet nodig voor elke url een eigen synchedId te maken. We doen toch altijd alles
        //omdat de kalender tabel geen modidified heeft
        //daarom misbruik ik het veld `field` in `instances` als kalelender_id

        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from($db->quoteName('#__rseventspro_events', 'a'));
        if (!$idOnly) {
            $query->select(
                [
                    $db->quoteName('a.name', 'name'),
                    $db->quoteName('a.URL', 'URL'),
                    $db->quoteName('a.description', 'description'),
                ]
            );
        }

        if ($this->getParamLocalGlobal('published')) {
            $query->where('`a`.`published` = 1');
        } else {
            $query->where('`a`.`published` > -1'); //ignore trashed
        }
        return $query;
    }

    public function getContainerTableById($id)
    {
        return $this->getContainerById($id);
    }


    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {
        $messageLinks = $this->getMessageLinks($instance);
        $oldUrl       = $link->url;
        if (!$this->checkCanReplaceLink($oldUrl, $messageLinks)) {
            return;
        }

        $table = $this->getContainerTableById($instance->container_id);

        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $oldUrl, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                'warning'
            );
            return;
        }

        $update  = false;
        $field   = $instance->field;

        switch ($field) {
            case 'URL':
                if ($table->URL == $oldUrl) {
                    $table->URL = $newUrl;
                    $update     = true;
                }
                break;
            case 'description':
                $text         = $table->{$field};
                $textParsers  =  BlcParseController::getInstance();
                $replacedText = $textParsers->replaceLinkInSourceByParser($instance->parser, $text, $oldUrl, $newUrl);

                if ($replacedText !== $text) {
                    $table->{$field} = $replacedText;
                    $update          = true;
                }
                break;
        }

        if ($update) {
            $db    = $this->getDatabase();
            if (! $db->updateObject('#__rseventspro_events', $table, 'id', false)) {
                throw new GenericDataException($db->getError(), 500);
            }
        }

        $translations = $this->getTranslations($table->id);

        foreach ($translations as $translation) {
            $transUpdate = false;
            if ($translation->property == $field) {
                switch ($field) {
                    case 'URL':
                        if ($table->URL == $link->url) {
                            $table->URL      = $newUrl;
                            $transUpdate     = true;
                        }
                        break;
                    case 'description':
                        $text         = $translation->value;
                        $textParsers  =  BlcParseController::getInstance();
                        $replacedText = $textParsers->replaceLinkInSourceByParser($instance->parser, $text, $oldUrl, $newUrl);

                        if ($replacedText !== $text) {
                            $translation->value   = $replacedText;
                            $transUpdate          = true;
                        }
                        break;
                }

                if ($transUpdate) {
                    //rsevents has al kinds of checks and includes that are not handles with class discovery. Which are not relevant to replace the links. So a loadTable->save() will not work
                    //therefor a shortcut
                    $db    = $this->getDatabase();
                    if (! $db->updateObject('#__rseventspro_translations', $translation, 'id', false)) {
                        throw new GenericDataException($db->getError(), 500);
                    }
                    $update = true;
                }
            }
        }


        if (!$update) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, $field, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                'warning'
            );
            return;
        }


        $this->parseContainer($instance->container_id);

        Factory::getApplication()->enqueueMessage(
            Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $messageLinks),
            'success'
        );
    }

    public function getTitle($instance): string
    {
        $table = $this->getContainerTableById($instance->container_id);
        return $table->name ?? Text::_('COM_BLC_PLUGIN_TITLE_NOT_FOUND');
    }

    public function getEditLink($instance): string
    {

        return Route::link(
            'administrator',
            'index.php?option=com_rseventspro&task=event.edit&id=' . (int)$instance->container_id
        );
    }


    public function getViewLink($instance): string
    {
        require_once JPATH_SITE . '/components/com_rseventspro/helpers/route.php';
        /** @phpstan-ignore class.notFound */
        $url = \RseventsproHelperRoute::getEventRoute($instance->container_id);
        return   Route::link('site', $url);
    }
    /**
     * Retrieves a container object by its ID.
     *
     * This method constructs a database query to fetch a container object
     * based on the provided ID. It binds the ID to the query and executes it
     * to load the corresponding object from the database.
     *
     * @param int $id The ID of the container to retrieve.
     * @return object|null The container object if found, null otherwise.
     */

    protected function getContainerById(int $id): ?object
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();

        $query->where($db->quoteName("a.{$this->primary}") . ' = :containerId')
            ->bind(':containerId', $id, ParameterType::INTEGER);
        $db->setQuery($query);
        return $db->loadObject();
    }

    protected function parseContainer(int $id): void
    {
        $row = $this->getContainerById($id);

        if ($row) {
            $this->parseContainerFields($row);
        } else {
            $synchTable = $this->getItemSynch($id);
            if ($synchTable->id) {
                $this->purgeInstances($synchTable->id);
            }
        }
    }

    /**
     * Parses the fields of a container row.
     *
     * @param object $row The container row object.
     * @return void
     */
    protected function parseContainerFields($row): void
    {
        $id = $row->id;
        //   unset($row['id']);
        $synchTable = $this->getItemSynch($id);
        $synchId    = $synchTable->id;

        if (!$synchId) {
            //creation failed most likely due to concurrent jobs
            //ignore next job will retry
            return;
        }
        $this->purgeInstances($synchId);
        $this->parseContainerFieldsRow($row, $synchId);

        $translations = $this->getTranslations($id);
        $name         = $row->name;
        foreach ($translations as $translation) {
            $row                           = new \StdClass();
            $row->name                     = $name;
            $row->{$translation->property} = $translation->value;
            $this->parseContainerFieldsRow($row, $synchId);
        }

        $synchTable->setSynched();
    }


    /**
     *
     * @since 24.44.7004
     *
     * @param object $row - item row
     * @param int $synchId
     *
     * @return void
     */

    protected function parseContainerFieldsRow($row, $synchId): void
    {
        if (!empty($row->URL)) {
            $this->processLinks([[
                'url'    => $row->URL,
                'anchor' => $row->name,
            ]], 'URL', $synchId);
        }


        //As soon as there is content there will be a <p> wrapped.
        //so check for a < after the start
        if (!empty($row->description) && strpos($row->description, '<', 1) > 0) {
            $fields = [
                'description' => $row->description,
            ];
            $this->processText($fields, 'content', $synchId);
        }
    }

    /**
     *
     * @since 24.44.7004
     *
     * @param int $id rsevent item to find translations for
     *
     * @return array<object>
     */

    protected function getTranslations($id): array
    {

        if (! $this->isTranslationEnabled()) {
            return [];
        }
        $db     = $this->getDatabase();
        $query  = $db->createQuery();

        [, $reference] = explode('.', $this->context);

        $query->select($db->quoteName('id'))
            ->select($db->quoteName('property'))
            ->select($db->quoteName('value'))
            ->where($db->quoteName('reference_id') . '= :reference_id')
            ->where($db->quoteName('reference') . '= :reference')
            ->bind(':reference_id', $id)
            ->bind(':reference', $reference)
            ->whereIn($db->quoteName('property'), $this->translatable, ParameterType::STRING)

            ->from($db->quoteName('#__rseventspro_translations'));
        $db->setQuery($query);
        $translations = $db->loadObjectList();
        return $translations;
    }


    /**
     *
     *
     * @return bool
     */

    protected function isTranslationEnabled(): bool
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);
        $query->select($db->quoteName('value'))
            ->where($db->quoteName('name') . '= ' . $db->quote('multilanguage'))
            ->from($db->quoteName('#__rseventspro_config'));
        $db->setQuery($query);
        $enabled = $db->loadResult();
        return \intval($enabled) === 1;
    }

    protected function getUnsynchedQuery(DatabaseQuery $query)
    {
        //lolcations don't have a modified date.

        $db     = $this->getDatabase();
        $wheres = [];
        $main   = "SELECT * FROM `#__blc_synch` `s` WHERE `s`.`container_id` = `a`.`{$this->primary}`" .
            ' AND `s`.`plugin_name` = ' . $db->quote($this->_name);
        $wheres[] = "NOT EXISTS ( {$main})";
        $wheres[] = "EXISTS ( {$main} AND `s`.`last_synch` < " . $db->quote($this->reCheckDate->toSql())  . ')';
        $query->extendWhere('AND', $wheres, 'OR');
    }
}
