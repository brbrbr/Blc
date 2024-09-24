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
use Joomla\CMS\Language\Text;
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
        } catch (Error) {
            $this->getApplication()->enqueueMessage(Text::_("PLG_SYSTEM_BLC_ERROR_IMPORTPLUGIN_BLC"), 'error');
        }
        $app = Factory::getApplication();
        $this->ioStyle->title(Text::_("PLG_SYSTEM_BLC_CMD_PURGE_TITLE"));
        $mvcFactory = $app->bootComponent('com_blc')->getMVCFactory();
        $model      = $mvcFactory->createModel('Link', 'Administrator', ['ignore_request' => true]);


        $purgeType   =  $this->cliInput->getOption('type');
        $purgePlugin =  $this->cliInput->getOption('plugin') ?? '';


        if ($purgeType === null) {
            $this->ioStyle->error(Text::_("PLG_SYSTEM_BLC_CMD_PURGE_ERROR_MISSING_TYPE_OPTION"));
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
                $this->ioStyle->error(Text::_("PLG_SYSTEM_BLC_CMD_PURGE_ERROR_UNKNOWN_TYPE_OPTION"));
                return Command::INVALID;
        }

        $this->ioStyle->success(Text::_("PLG_SYSTEM_BLC_CMD_PURGE_SUCCESS_COMPLETED"));
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, Text::_('PLG_SYSTEM_BLC_CMD_PURGE_OPTION_TYPE'));
        $this->addOption('plugin', null, InputOption::VALUE_OPTIONAL, Text::_('PLG_SYSTEM_BLC_CMD_PURGE_OPTION_PLUGIN'));
        $app          = Factory::getApplication();
        $mvcFactory   = $app->bootComponent('com_blc')->getMVCFactory();
        $model        = $mvcFactory->createModel('Setup', 'Administrator', ['ignore_request' => true]);
        $stats        = $model->getCountSynch();
        $plugins      = array_keys($stats);
        $pluginHelp   = match (\count($plugins)) {
            0 => '',
            1 => Text::sprintf("PLG_SYSTEM_BLC_CMD_PURGE_CONFIGURE_HELP_PLUGIN_1", $plugins),
            // phpcs:disable Generic.Files.LineLength
            default => Text::sprintf("PLG_SYSTEM_BLC_CMD_PURGE_CONFIGURE_HELP_PLUGIN_MORE", join(', ', \array_slice($plugins, 0, -1)), end($plugins))
            // phpcs:enable Generic.Files.LineLength
        };
        $this->setDescription(Text::_('PLG_SYSTEM_BLC_CMD_PURGE_CONFIGURE_DESC'));
        $this->setHelp(
            Text::sprintf('PLG_SYSTEM_BLC_CMD_PURGE_CONFIGURE_HELP_TYPE') .
            "\n\n".
            $pluginHelp.
            "\n\n".
            Text::_('PLG_SYSTEM_BLC_CMD_CONFIGURE_HELP')
        );

    }
}
