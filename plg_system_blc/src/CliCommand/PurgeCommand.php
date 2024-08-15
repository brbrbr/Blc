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
// phpcs:enable PSR1.Files.SideEffects;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PurgeCommand extends AbstractCommand
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

    protected static $defaultName = 'blc:purge';
    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        $this->cliInput = $input;
        $this->ioStyle  = new SymfonyStyle($input, $output);
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $this->configureIO($input, $output);
        try {
            //only helps partially, since symfony catches fatals.
            PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
         } catch (Error)  {
             $this->getApplication()->enqueueMessage( 'unable to load BLC plugins, please ensure everything is updated','error');
         }
        $app = Factory::getApplication();
        $this->ioStyle->title('Purge for BLC');
        $mvcFactory = $app->bootComponent('com_blc')->getMVCFactory();
        $model      = $mvcFactory->createModel('Link', 'Administrator', ['ignore_request' => true]);


        $purgeType   =  $this->cliInput->getOption('type');
        $purgePlugin =  $this->cliInput->getOption('plugin') ?? '';


        if ($purgeType === null) {
            $this->ioStyle->error('Missing Purge type option');
            return Command::FAILURE;
        }

        switch ($purgeType) {
            case 'orphans':
                $model->trashit('orphans', 'links');
                break;
            case 'links':
                $model->trashit('truncate', 'links');
                break;
            case 'extracted':
                $model->trashit('delete', 'synch', $purgePlugin);
                break;
            case 'checks':
                $model->trashit('reset', 'links');
                break;
            default:
                $this->ioStyle->error('unkown Purge type option');
                return Command::INVALID;
        }



        $this->ioStyle->success('Purge Completed!');
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Purge action');
        $this->addOption('plugin', null, InputOption::VALUE_OPTIONAL, 'Content plugin to purge');
        $app          = Factory::getApplication();
        $mvcFactory   = $app->bootComponent('com_blc')->getMVCFactory();
        $model        = $mvcFactory->createModel('Setup', 'Administrator', ['ignore_request' => true]);
        $stats        = $model->getCountSynch();
        $plugins      = array_keys($stats);
        $pluginString = match (\count($plugins)) {
            0 => '',
            1 => "Found content type: <info>{$plugins[0]}</info>",
            // phpcs:disable Generic.Files.LineLength
            default => "Found content types:  <info>" . join(', ', \array_slice($plugins, 1)) .  " and {$plugins[0]}</info>"
            // phpcs:enable Generic.Files.LineLength
        };

        $this->setHelp(
            "
			<info>--type</info> can be 'links','orphans','extracted' or 'checks', 
			'links' will purge 'extracted' as well. 
			'checks' will reset urls to be rechecked.\n
			<info>--plugin</info> can be one of the content types.
			 $pluginString.
			Use 'Transient' to purge the transients.\n
			See also : https://brokenlinkchecker.dev/documents/command-line-usage
		
		"
        );
        $this->setDescription("This command  purges data from BLC:");
    }
}
