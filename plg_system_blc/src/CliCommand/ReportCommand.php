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

use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Joomla\CMS\Language\Text;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;

class ReportCommand extends AbstractCommand
{
    protected static $defaultName = 'blc:report';
    /**
     * Stores the Input Object
     * @var   InputInterface
     * @since 4.0.0
     */
    protected $cliInput;

    /**
     * SymfonyStyle Object
     * @var   SymfonyStyle
     * @since 4.0.0
     */
    protected $ioStyle;

    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        $this->cliInput = $input;
        $this->ioStyle  = new SymfonyStyle($input, $output);
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $this->configureIO($input, $output);
        $this->ioStyle->title('Reporting for BLC');
        $print     = $this->cliInput->getOption('print');
        $arguments =
            [
                'event'   => 'report',
                'context' => 'CLI',
                'id'      => $print ? 'json' : 'email',
            ];
        $event = new BlcEvent('onBlcReport', $arguments);
        $this->getApplication()->getDispatcher()->dispatch('onBlcReport', $event);
        if ($print) {
            $results = $event->getReport();
            $results = $this->formatLinkInfo($results);
            $table   = $this->ioStyle->createTable();
            $table->setHeaders(['Code', 'Broken', 'Redirect', 'Link', 'Final']);
            $table->setRows($results);
            $table->setColumnWidths([4, 5, 8]);
            $table->render();
            $this->ioStyle->newLine();
        }
        $this->ioStyle->success('Reporting Completed!');
        return 0;
    }

    /**
     * Transforms extension arrays into required form
     *
     * @param   array  $extensions  Array of extensions
     *
     * @return array
     *
     * @since 5.1.0
     */
    protected function formatLinkInfo($links): array
    {
        $terminal = new Terminal();
        $urlWidth = floor(($terminal->getWidth() - 6 - 7 - 10 - 10) / 2);
        $extInfo  = [];


        foreach ($links as $link) {
            $extInfo[] = [
                $link->http_code ?? 0,
                $link->broken,
                ($link->redirect_count > 0) ? 'Redirect' : '',
                substr($link->url, 0, $urlWidth),
                substr($link->final_url != $link->url ? $link->final_url : '', 0, $urlWidth),


            ];
        }

        return $extInfo;
    }


    protected function configure(): void
    {


        $this->addOption('print', null, InputOption::VALUE_NONE, 'Print the Links');
        $this->setDescription(Text::_('PLG_SYSTEM_BLC_CMD_REPORT_CONFIGURE_DESC'));


        $this->setHelp(
            Text::sprintf('PLG_SYSTEM_BLC_CMD_REPORT_CONFIGURE_HELP')
            . "\n\n" .
            Text::_('PLG_SYSTEM_BLC_CMD_CONFIGURE_HELP')
        );
    }
}
