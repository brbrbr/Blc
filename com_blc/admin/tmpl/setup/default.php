<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Model\SetupModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use  Joomla\CMS\Router\Route;

$params = ComponentHelper::getParams('com_blc');
// phpcs:disable Generic.Files.LineLength
?>
<div class="item_fields">
    <?= $this->get('StatsHtml'); ?>
    <ul class="d-none list-group blcstatus Unable">
        <li class="list-group-item ">
            <span class="blcresponse long">Waiting for first response</span>
        </li>
    </ul>
    <h2>Cron Links and Paths</h2>
    <h3 class="mt-4">HTTP Cron Links</h3>
    <?php
    $optionsUrl = Route::link('administrator', 'index.php?option=com_config&view=component&component=com_blc');
    $mustToken      = $params->get('token', null);
    $totalLinks     = $this->get('CountLinks')->links ?? 0;
    $checkThreshold = BlcHelper::intervalTohours(
        (int)$params->get('check_threshold', 168),
        $params->get('check_thresholdUnit', 'hours')
    );
    if ($mustToken == '') {
        print   "<p stye=\"padding:50px;background-color:red\">" . Text::sprintf("Please set a security token in the <a href=\"%s\">configuration</a> first", $optionsUrl) . "</p>";
    } else {
        $query =
        [
        'option' => 'com_ajax',
        'plugin' => 'blcExtract',
        'format' => 'raw',

        'token'  => $mustToken,
        'report' => 1,
        ];

        ?>
        <div class="list-group">
            <div class="list-group-item list-group-item-primary text-center ">
                <a class=" btn btn-primary m-1 w-75" target="_blank" href="<?= Route::link('site', $query, absolute: true); ?>">Extract</a>
            </div>
            <?php
            $query['plugin'] = 'blcCheck';
            $checkUrl            = Route::link('site', $query, absolute: true)
            ?>
            <div class="list-group-item list-group-item-primary text-center ">
                <a class=" btn btn-primary m-1 w-75" target="_blank" href="<?= $checkUrl; ?>">Check</a>
            </div>
            <?php
            $query['plugin'] = 'blcReport';
            ?>
            <div class="list-group-item list-group-item-primary text-center ">
                <a class=" btn btn-primary m-1 w-75" target="_blank" href="<?= Route::link('site', $query, absolute: true); ?>">(Email) Report</a>
            </div>
            <p class="list-group-item m-0 mt-2">
                <?php
                $throttle = $params->get('throttle', 60);
                ?>You can remove the &report=1 when running as unattended cron jobs. This will free up some resources on the webserver.
                Cron request are time throttled. Currrenty you can execute a cron every <?= $throttle ?> second(s). You can change the interval in the <a href="<?= $optionsUrl ?>" -->Options</a>.
            </p>
        <?php

        print '<h4 class="list-group-item list-group-item-action m-0">Frequency estimate (HTTP)</h4>';

        $checkLimit = $params->get('check_http_limit', 10);



        SetupModel::cronEstimate('Links', $totalLinks, $checkLimit, $checkThreshold, "wget -q -O /dev/null $checkUrl");
        $maxExecutionTime = \ini_get('max_execution_time');
        $timeoutHttp      = (int)$params->get('timeout_http', 1);

        if ($maxExecutionTime && $maxExecutionTime > 0 && $timeoutHttp > 0) {
            $batch = max(1, floor($maxExecutionTime / $timeoutHttp));
            print '<div class="list-group-item">';
            print Text::sprintf("COM_BLC_SETUP_BATCH_ESTIMATE", $maxExecutionTime, $timeoutHttp, $batch);
            print "</div>";
        }
        print '</div>';
    }
    ?>
        <h3 class="mt-4">Information Command Line Crons</h3>
        <?php
        $liveSite = BlcHelper::root();
        ?>
        <div class="list-group">
            <h4 class="list-group-item list-group-item-action m-0">Getting to the CLI folder</h4>
            <div class="list-group-item">
                <code>
                    cd <?= JPATH_ROOT; ?>/cli<br>
                </code>
            </div>
            <h4 class="list-group-item list-group-item-action m-0">Commands to run the extract and check operations:</h4>
            <div class="list-group-item">
                <code>
                    php joomla.php blc:extract --live-site=<?= $liveSite; ?><br>
                    php joomla.php blc:check --live-site=<?= $liveSite; ?><br>
                    php joomla.php blc:report --live-site=<?= $liveSite; ?><br>
                </code>
                <p class="m-0 mt-2">
                    You might need to combine the cd and the command when setting a cron job:
                </p>
                <code>
                    <?php
                    $checkCLI = "cd " . JPATH_ROOT . "/cli;php joomla.php blc:check --live-site={$liveSite}";
                    echo "{$checkCLI}<br>";
                    ?>
                </code>
            </div>
            <div class="list-group-item">
                <p class="m-0 mt-2">instead of using <code>--live-site=<?= $liveSite; ?></code> you can also add <code>public $live_site = '<?= $liveSite; ?>';</code>
                    to your configuration.php</p>
            </div>
            <h4 class="list-group-item list-group-item-action m-0">Maintenance Commands</h4>
            <div class="list-group-item">
                <code>
                    php joomla.php blc:purge --type checks # This will recheck al links. <em>working</em> and <em>ignore</em> settings are preserved. Same as <strong>Reset Checks</strong> above<br>
                    php joomla.php blc:purge --type extracted # This will purge al extracted data but will keep the links. Same as <strong>Purge Extracted</strong> above<br>
                    php joomla.php blc:purge --type extracted --plugin &lt;name&gt; # as above per BLC plugin. Same as the <strong>Purge</strong> buttons above<br>
                    php joomla.php blc:purge --type links # This will purge al the links and the extracted data. Same as <strong>Purge links</strong> above<br>
                    php joomla.php blc:purge --type orphans # This will purge links that are not found on the site anymore.
                </code>
                <p>Ensure your command line php is using at least version 8.1 You can check the default version using: <code>php -v</code>. Selecting a different php version on the command line depends on your Operating system.</p>
            </div>
        </div>
        <?php
        print '<div class="list-group">';
        print '<h4 class="list-group-item list-group-item-action m-0">Frequency estimate (CLI)</h4>';
        $checkLimit = $params->get('check_cli_limit', 10);


        SetupModel::cronEstimate('Links', $totalLinks, $checkLimit, $checkThreshold, "($checkCLI 2>&1 > /dev/null)");
        print '</div>';
        ?>
        <ul class="list-group">
            <li class="list-group-item list-group-item-action">
                <h3 class="m-0">Last crons</h3>
            </li>

            <?php

            foreach (['Extract', 'Check', 'Report'] as $event) {
                print '<li  class="list-group-item">' . SetupModel::lastAction($event) . "</li>";
            }

            ?>
        </ul>
        <?php
        foreach (
            [

                'lastListeners:onBlcExtract'        => 'Extractors',
                'lastListeners:onBlcParserRequest'  => 'Parsers',
                'lastListeners:onBlcCheckerRequest' => 'Checkers',


            ] as $transient => $h4
        ) {
            $last =  BlcTransientManager::getInstance()->get($transient);
            if ($last) {
                ?>
                <ul class="list-group">
                    <h3 class="list-group-item m-0 list-group-item-action">Active <?= $h4 ?> (Last Run)</h3>

                    <?php
                    foreach ($last as $class => $priority) {
                        $classString = preg_replace('#^Blc\\\\#', '', $class);
                        $classString = str_replace('Component\Blc\Administrator', 'admin', $classString);
                        $classString = str_replace('\Extension\BlcPluginActor', '', $classString);
                        $classString = str_replace('Plugin', 'plugin', $classString);
                        $classString = method_exists($class, 'getHelpHTML') ? $class::getHelpHTML($classString) : $classString;
                        if ($priority == 0) {
                            $priority = '     ';
                        } else {
                            $priority = sprintf("% 4s:", $priority);
                        }
                        print '<li   class="list-group-item" style="white-space:pre;font-family:monospace"><span>' . "$priority $classString</span></li>";
                    }

                    ?>
                </ul>
                <?php
            }
        }
        echo BlcHelper::footer();
// phpcs:enable Generic.Files.LineLength
