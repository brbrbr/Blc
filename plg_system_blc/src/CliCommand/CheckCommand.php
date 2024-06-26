<?php

/**
 * @package   Com_Blc
 * @author    Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 * @version   24.44
 */

namespace Blc\Plugin\System\Blc\CliCommand;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Blc\BlcMutex;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckCommand extends AbstractCommand
{
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

    protected static $defaultName = 'blc:check';


    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        $this->cliInput = $input;
        $this->ioStyle  = new SymfonyStyle($input, $output);
    }


    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $this->configureIO($input, $output);

        $checkLink            = BlcCheckLink::getInstance();
        $componentConfig      = ComponentHelper::getParams('com_blc');
        $app                  = Factory::getApplication();
        $mvcFactory           = $app->bootComponent('com_blc')->getMVCFactory();
        $model                = $mvcFactory->createModel('Links', 'Administrator', ['ignore_request' => true]);
        $checkLimit           = $componentConfig->get('check_cli_limit', 100);
        $checkLimit           =  (int)($this->cliInput->getOption('limit') ?? $checkLimit);
        $linkId               =  $this->cliInput->getOption('id') ?? false;
        $resetParked          =  $this->cliInput->getOption('parked') ?? false;

        $this->ioStyle->title('Running BLC Link Checker');
        $lock = BlcMutex::getInstance()->acquire(minLevel: BlcMutex::LOCK_SERVER);

        if (!$lock) {
            $this->ioStyle->warning("Waiting for another running instance of broken link checker");
            $lock = BlcMutex::getInstance()->acquire(timeOut: 60);
        }

        if ($lock) {
            if ($linkId !== false) {
                $linkId = (int)$linkId;
                if ($linkId > 0) {
                    $rows = [(int)$linkId];
                } else {
                    $this->ioStyle->error("Unable to find provided id:  {$linkId}");
                    return Command::FAILURE;
                }
            } else {
                $rows = $model->getToCheck(checkLimit: $checkLimit);
            }
            $num = \count($rows);
            //always report, so we can see the action
            BlcHelper::setLastAction('CLI', 'Check');

            foreach ($rows as $c => $linkId) {
                $link = $checkLink->checkLinkId($linkId);

                $this->ioStyle->text(($c + 1) . "/$num");
                if ($link) {
                    if ($link->broken) {
                        $this->ioStyle->error(sprintf('[%3s]', $link->http_code) .  " - {$link->url} ");
                    } elseif ($link->redirect_count && ($link->url != $link->final_url)) {
                        $this->ioStyle->warning(sprintf('[%3s]', 301) .  " - {$link->url} ");
                    } elseif ($link->http_code == 0) {
                        $this->ioStyle->error("Failed to check  - {$link->url} ");
                    } elseif ($link->http_code == HTTPCODES::BLC_THROTTLE_HTTP_CODE) {
                        $this->ioStyle->note("Skipped for domain throttling  - {$link->url} ");
                    } else {
                        $this->ioStyle->success(sprintf('[%3s]', $link->http_code) .  " - {$link->url} ");
                    }
                } else {
                    $this->ioStyle->error("Check failure unable to find and check:  {$linkId}");
                }
            }

            $model->updateParked($resetParked);
        } else {
            $this->ioStyle->warning("Another instance of the broken link checker is running");
        }
        BlcMutex::getInstance()->release();

        $arguments =
            [
                'event'   => 'check',
                'context' => 'CLI',
                'id'      => 'email',
            ];
        $event = new BlcEvent('onBlcReport', $arguments);
        $this->getApplication()->getDispatcher()->dispatch('onBlcReport', $event);

        //no lock  for the report. We might mis an report The transient takes care of sending unique reports



        $count = $model->getToCheck(true);
        if ($count) {
            $this->ioStyle->warning("Still $count unchecked Links");
        } else {
            $this->ioStyle->success('Link Checker Completed');
        }

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of items to check');
        $this->addOption('id', 'i', InputOption::VALUE_OPTIONAL, 'Specific Link Id to check');
        $this->addOption('parked', 'p', InputOption::VALUE_NONE, 'Reset Parked Links');
        $this->setDescription('This command checks links for BLC');
        $this->setHelp(
            "See: https://brokenlinkchecker.dev/documents/command-line-usage
    	<info>--limit</info> override the configured number of links to check.
        "
        );
    }
}
