<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.se
 * @since   24.44.6473
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Ini\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;

    private const HELPLINK  = 'https://brokenlinkchecker.dev/';
    protected $primary      =  'href';
    protected $context      = 'com_blc.ini';
    protected $extractCount = 0;
    private $regex          = "#href\s*=\s*(?P<quote>\\\\?[\"\'])(.*?)(?P=quote)#iu";
    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   24.44.6473
     */

    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
    }
    /**
     * @since   24.44.6473
     */

    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }


    #[\Override]
    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {


        $synchTable    = $this->getItemSynch($instance->container_id);
        $file          = $synchTable->data ?? '';
        if (!$file || !file_exists($file)) {
            Factory::getApplication()->enqueueMessage("Unable find the $file", 'warning');
        }
        //  $iniContent = file_get_contents($file);
        $iniContent = file_get_contents($file);
        $url        = preg_quote($link->url);
        $regex      = "#href\s*=\s*(?P<quote>\\\\?[\"\'])$url(?P=quote)#iu";


        $newContent = preg_replace($regex, "href='$newUrl'", $iniContent);
        if ($iniContent == $newContent) {
            Factory::getApplication()->enqueueMessage("Unable find url ($link->url) the $file", 'warning');
        }
        if (File::write($file, $newContent)) {
            Factory::getApplication()->enqueueMessage("Replaced url ($link->url) by {$newUrl} in file $file", 'success');
            $link->working = HTTPCODES::BLC_WORKING_HIDDEN;
            $link->save();
        } else {
            Factory::getApplication()->enqueueMessage("Unable find write file $file", 'error');
        }
    }
    /**
     * @since   24.44.6473
     */

    public function getTitle($data): string
    {
        return $data->field;
    }

    /**
     * @since   24.44.6473
     */

    public function getEditLink($data): string
    {
        return '';
    }

    /**
     * @since   24.44.6473
     */

    public function getViewLink($data): string
    {
        return '';
    }


    /**
     * @since   24.44.6473
     */




    //true == continue
    //false == stop
    protected function parseIniFile(string $file): void
    {
        $name          = basename($file);
        $id            = crc32($this->_name . $file);
        $synchTable    = $this->getItemSynch($id);
        $dateLastSynch = new Date($synchTable->last_synch ?? '1970-01-01 00:00:00');
        $time          = @filemtime($file);
        $fileMTime     = new Date($time);

        if ($dateLastSynch >= $fileMTime) {
            return;
        }
        $relFile = Path::removeRoot($file);
        $this->extractCount++;
        $this->purgeInstances($synchTable->id);
        $iniContent = file_get_contents($file);
        preg_match_all($this->regex, $iniContent, $m);
        $urls = array_filter($m[2], function ($url) {
            return str_starts_with($url, 'http');
        });

        $this->processLinks($urls, $name, $synchTable->id);
        $synchTable->setSynched([
            'last_synch' => $fileMTime->toSql(),
            'data'       => $file,
        ]);
    }

    protected function getUnsynchedCount(): int
    {
        $urls = (array) $this->params->get('urls', []);
        return \count($urls);
    }

    public function onBlcExtract(BlcExtractEvent $event): void
    {

        $folders = $this->params->get('folders', []);

        foreach ($folders as $folder) {
            $this->parseLimit = $event->getMax();
            if ($this->parseLimit <= 0) {
                return;
            }

            $dir = Path::resolve(JPATH_ROOT . DIRECTORY_SEPARATOR . $folder->dir);
            if (!is_dir($dir)) {
                $dir = Path::removeRoot($dir);
                Factory::getApplication()->enqueueMessage("Folder $dir not found", 'error');
                return;
            }
            Path::check($dir, JPATH_ROOT); //throws exeption

            $files = glob($dir . "/*.ini");

            $event->updateTodo(\count($files));

            foreach ($files as $file) {
                $event->updateTodo(-1);
                $this->parseIniFile($file);
                if ($this->extractCount > $this->parseLimit) {
                    break;
                }
            }

            if ($this->extractCount) {
                $event->setExtractor($this->_name);
                $event->updateDidExtract($this->extractCount);
            }
        }
    }
}
