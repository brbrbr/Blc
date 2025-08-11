<?php

declare(strict_types=1);

/**
 * @package     BLC
 * @subpackage  blc.hikashop
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Hikashop\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
//use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends CMSPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;
    use BlcExtractTrait;
    use DatabaseAwareTrait;

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-hikashop';

    protected $primary = 'product_id';
    protected Registry $componentConfig;
    protected $replacedUrls = [];
    private $hikaConfig;
    protected $context = 'com_hikashop.product'; //actually hikashop does not trigger save events.


    public function __construct(array $config = [])
    {
        if (version_compare(JVERSION, '5.3', '>=')) {
            parent::__construct($config);
        } else {
            $dispatcher =  Factory::getApplication()->getDispatcher();
            parent::__construct($dispatcher, $config);
        }
        $this->componentConfig = ComponentHelper::getParams('com_blc');
        include_once(rtrim(JPATH_ADMINISTRATOR, '/') . '/components/com_hikashop/helpers/helper.php');
        $this->hikaConfig ??= hikashop_config();
        /** @phpstan-ignore function.notFound */
    }
    public function __get($name)
    {
        return match ($name) {
            'context' => $this->context,
            'name'    => $this->_name,
            default   => null
        };
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcExtract' => 'onBlcExtract',
            //   'onBlcContainerChanged'   => 'onBlcContainerChanged', /hikashop does not send save events
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }
    public function getContainerTableById(int $id)
    {
        return $this->getHikaProduct($id);
    }

    private function getHikaProduct(int $id)
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select("*")
            ->from($db->quoteName(hikashop_table('product')))
            /** @phpstan-ignore function.notFound */
            ->where($db->quoteName('product_id') . ' = :id')
            ->bind(':id', $id);
        $query->setLimit(1);
        $db->setQuery($query);
        return $db->loadObject();
    }

    private function updateHikaProduct($productObject): void
    {
        $db                              = $this->getDatabase();
        $productObject->product_modified = time();
        /** @phpstan-ignore function.notFound */
        if (! $db->updateObject(hikashop_table('product'), $productObject, 'product_id')) {
            // @codeCoverageIgnoreStart
            throw new GenericDataException($db->getError(), 500);
            // @codeCoverageIgnoreEnd
        }
    }
    public function getViewLink($instance): string
    {


        return Route::link(
            'site',
            hikashop_frontendLink('index.php?option=com_hikashop&ctrl=product&task=show&cid=' . $instance->container_id, false)
            /** @phpstan-ignore function.notFound */
        );
    }


    public function getTitle($instance): string
    {
        $table = $this->getContainerTableById($instance->container_id);
        return $table->product_name ?? Text::sprintf('COM_BLC_PLUGIN_TITLE_NOT_FOUND', $instance->container_id);
    }


    public function getEditLink($instance): string
    {

        return Route::link(
            'administrator',
            "index.php?option=com_hikashop&ctrl=product&task=edit&cid[]={$instance->container_id}"
        );
    }
    private function updatefileLink($oldUrl, $newUrl, $id): bool
    {

        $uploadFolder = trim(Path::clean(html_entity_decode($this->hikaConfig->get('uploadfolder'))), '/');
        $uploadFolder .= '/';
        $uploadFolder = preg_quote($uploadFolder, '#');



        $oldFile = preg_replace("#^$uploadFolder#", '', $oldUrl);
        $newFile = preg_replace("#^$uploadFolder#", '', $newUrl);

        if (!$oldFile) {
            return false;
        }
        if (!$newFile) {
            return false;
        }
        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query->update($db->quoteName(hikashop_table('file'), 'a'))
            /** @phpstan-ignore function.notFound */
            ->where($db->quoteName("a.file_ref_id") . ' = :id')
            ->bind(':id', $id)
            ->where($db->quoteName("a.file_path") . ' = :oldfile')
            ->bind(':oldfile', $oldFile, ParameterType::STRING)
            ->set($db->quoteName("a.file_path") . ' = :newfile')
            ->bind(':newfile', $newFile, ParameterType::STRING);
        $result = $db->setQuery($query)->execute();


        return $result && ($db->getAffectedRows() > 0);
    }

    public function replaceLink(object $link, object $instance, string $newUrl): void
    {
        $messageLinks = $this->getMessageLinks($instance);
        $table        = $this->getContainerTableById($instance->container_id);

        if (!($table->product_id ?? 0)) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                'error'
            );
            return;
        }
        $update  = false;
        $reparse = false;

        $field = $instance->field;

        switch ($field) {
            case 'product_description':
                $text         = $table->{$field};
                $textParsers  =  BlcParseController::getInstance();
                $replacedText = $textParsers->replaceLinkInSourceByParser($instance->parser, $text, $link->url, $newUrl);
                if ($replacedText !== $text) {
                    $table->{$field} = $replacedText;
                    $update          = true;
                }

                break;
            case 'product_url':
                $url  = $table->{$field} ?? '';
                if ($url && ($url == $link->url) && ($url != $newUrl)) {
                    $table->{$field}  = $newUrl;
                    $update           = true;
                }

                break;

            case 'file':
                $update = $this->updatefileLink($link->url, $newUrl, $instance->container_id) || $update;
                break;
        }

        if ($update) {
            $this->updateHikaProduct($table);
            $this->replacedUrls[] = $newUrl;
            $reparse              = true;
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $messageLinks),
                'success'
            );
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                //or updated in the custom fields
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, $field, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                    'warning'
                );
            }
        }

        if ($reparse) {
            $this->parseContainer($instance->container_id);
        }



        return;
    }



    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from($db->quoteName(hikashop_table('product'), 'a'));
        /** @phpstan-ignore function.notFound */
        if (!$idOnly) {
            $query->select(
                $db->quoteName(
                    [
                        'a.product_name',
                        'a.product_description',
                        'a.product_url',
                        'a.product_modified',
                    ],
                    [
                        'title',
                        'description',
                        'product_url',
                        'product_modified',
                    ]
                )
            );
            $query->order($db->quoteName('a.product_modified') . ' DESC');
        }
        if ($this->getParamLocalGlobal('access')) {
            $query->where($db->quoteName('a.product_access') . ' = ' . $db->quote('all'));
        }

        if ($this->getParamLocalGlobal('published')) {
            $query->where($db->quoteName('a.product_published') . ' = 1');
        } else {
            $query->where($db->quoteName('a.product_published') . ' > 1'); //ignore trashed
        }

        return $query;
    }



    protected function getUnsynchedQuery(DatabaseQuery $query)
    {
        $db    = $this->getDatabase();
        $main  = $db->getQuery(true);
        $main->select('*')
            ->from($db->quoteName('#__blc_synch', 's'))
            ->where($db->quoteName('s.container_id') . ' = ' . $db->quoteName("a.{$this->primary}"))
            ->where($db->quoteName('s.plugin_name') . ' = ' . $db->quote($this->_name)); //bind fiai query used twice
        $mainString =  $main->__toString();
        //hikeshop uses php time() (UTC) to store the modified date. last_synch is  in string format and UTC.
        //FROM_UNIXTIME return  the session/system time zone. then CONVERT_TZ convert from session/system time to UTC
        $wheres[] = "EXISTS ( {$mainString} AND " .
            $db->quoteName('s.last_synch') . ' < CONVERT_TZ(FROM_UNIXTIME(' . $db->quoteName("a.product_modified") . '), @@session.time_zone,"+0:00")' . ")";

        $wheres[] = "NOT EXISTS ({$mainString})";
        $query->extendWhere('AND', $wheres, 'OR');
    }



    private function getFiles($id)
    {

        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query
            ->select($db->quoteName("a.file_path"))
            ->select($db->quoteName("a.file_id"))
            ->select($db->quoteName("a.file_type"))
            ->from($db->quoteName(hikashop_table('file'), 'a'))
            /** @phpstan-ignore function.notFound */
            ->where($db->quoteName("a.file_ref_id") . ' = :id')
            ->bind(':id', $id)
            ->whereIn($db->quoteName("a.file_type"), ['product'], ParameterType::STRING);
        $db->setQuery($query);
        return $db->loadObjectList();
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

        if (!empty($row->description) && strpos($row->description, '<', 1) > 0) {
            $fields = [
                'product_description' => $row->description,
            ];
            $this->processText($fields, 'product_description', $synchId);
        }
        if (!empty($row->product_url)) {
            $link = [
                'url'    => $row->product_url,
                'anchor' => 'Product URL',
            ];
            $this->processLinks([$link], 'product_url', $synchId);
        }

        $uploadFolder = trim(Path::clean(html_entity_decode($this->hikaConfig->get('uploadfolder'))), '/');
        $uploadFolder .= '/';



        $files = $this->getFiles($id);
        $links = [];
        foreach ($files as $file) {
            $links[] = [
                'url'    => $uploadFolder . $file->file_path,
                'anchor' => 'Product Image',
            ];
        }
        if ($links) {
            $this->processLinks($links, 'file', $synchId);
        }

        //hikeshop uses time(). And that is higly confusing.
        //set the synchtime to the timestamp of the hike-item
        $synchTable->setSynched();
    }
}
