<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\System\Blc\CliCommand;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcMutex;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ExtractCommand extends AbstractCommand
{
    /**
     * Stores the Input Object
     * @var InputInterface
     * @since 4.0.0
     */
    protected $cliInput;

    /**
     * SymfonyStyle Object
     * @var   SymfonyStyle
     * @since 4.0.0
     */
    protected $ioStyle;


    protected static $defaultName = 'blc:extract';


    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        $this->cliInput = $input;
        $this->ioStyle  = new SymfonyStyle($input, $output);
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        try {
            //only helps partially, since symfony catches fatals.
            PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
         } catch (Error)  {
             $this->getApplication()->enqueueMessage( 'unable to load BLC plugins, please ensure everything is updated','error');
         }
        $this->configureIO($input, $output);

        $this->ioStyle->title('Extractor for BLC');

        $lock = BlcMutex::getInstance()->acquire();

        if (!$lock) {
            $this->ioStyle->warning("Waiting for another running instance of broken link checker");
            $lock = BlcMutex::getInstance()->acquire(timeOut: 60);
        }

        if ($lock) {
            BlcHelper::setLastAction('CLI', 'Extract');
            $app             = Factory::getApplication();
            $componentConfig = ComponentHelper::getParams('com_blc');
            $maxExtract      = $componentConfig->get('extract_cli_limit', 100);
            $maxExtract      =  (int)($this->cliInput->getOption('limit') ?? $maxExtract);
            $model           = $app->bootComponent('com_blc')->getMVCFactory()->createModel(
                'Links',
                'Administrator',
                ['ignore_request' => true]
            );
            $event = $model->runBlcExtract($maxExtract);
            $todo  = $event->getTodo();
            if ($todo) {
                $lastExtractor = $event->getExtractor();
                $this->ioStyle->info(sprintf("Still %d container(s) to go by %s", $todo, $lastExtractor));
            }
        } else {
            $this->ioStyle->warning("Another instance of the broken link checker is running");
        }
        BlcMutex::getInstance()->release();

        //always report, so we can see the action


        $arguments =
            [
                'event'   => 'extract',
                'context' => 'CLI',
                'id'      => 'email',
            ];
        $event = new BlcEvent('onBlcReport', $arguments);
        $this->getApplication()->getDispatcher()->dispatch('onBlcReport', $event);

        $this->ioStyle->success('Extract Command Completed!');
        return 0;
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of containers to extract from');
        $this->setDescription('This command extracts links from content into BLC');
        $this->setHelp(
            "See: https://brokenlinkchecker.dev/documents/command-line-usage
    	<info>--limit</info> override the configured number of containers to extract.
        "
        );
    }
}
