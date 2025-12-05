<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Plugins;

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTP_CODES;
use Blc\Plugin\Blc\Checker\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(BlcPluginActor::class)]
class PlgBlcCheckerTest extends UnitTestCase
{
    protected string $folder  = 'blc';
    protected string $element = 'checker';
    protected string $class   = BlcPluginActor::class;
    protected string $context = 'checker';


    protected function customConfig(): array
    {
        $config           =  (array)PluginHelper::getPlugin($this->folder, $this->element) ?? [];
        $config['params'] =  [
            'hosts' => [
                'hosts0' => [
                    'host'            => 'cascadedesigns.com',
                    'timeout_http'    => 1,
                    'timeout_cli'     => 1,
                    'head'            => 1,
                    'range'           => 1,
                    'follow'          => 1,
                    'maxredirs'       => 5,
                    'response'        => 0,
                    'language'        => 1,
                    'accept-language' => '-',
                    'cookies'         => 1,
                    'signature'       => 'chrome',
                    'dynamicSecFetch' => 1,
                    'valid_ssl'       => 2,
                    'sslversion'      => 'CURL_SSLVERSION_DEFAULT',
                ],
            ],
        ];
        return $config;
    }
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }
    public function testBootPluginService()
    {
        parent::testBootPluginService();
    }

    public function testCanBoot()
    {
        $this->bootPlugin(assert: true);
    }


    public function testcanCheckLink()
    {
        $url      = 'https://cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig());
        $canCheck =  $checker->canCheckLink($linkItem);
        $this->assertSame($canCheck, HTTP_CODES::BLC_CHECK_TRUE);
    }

    public function testcheckLink()
    {
        $url      = 'https://cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig());
        $options  =  clone ComponentHelper::getParams('com_blc');
        $options->set('accept-language', uniqid());
        $checker->checkLink($linkItem, $options);
        $this->assertSame($options->get('accept-language'), '-');
    }


    /***
     *
     * if accept language is set to '-' (empty) cascasedesigns should not redirect to localized page
     */
    public function testCascadedesigns()
    {


        $linkChecker  = $this->getBlcCheckLink();

        $checker = $this->bootPlugin(config: $this->customConfig());
        $linkChecker->unregisterChecker($this->class);
        $linkChecker->registerChecker($checker, 5, true);

        $url = 'https://cascadedesigns.com/products/elixir-2-backpacking-tent';

        $linkItem = $this->loadLinkItem($url);

        $linkChecker->checkLink($linkItem);
        $this->assertEmpty($linkItem->final_url, "Final URL should be empty\n" . $linkItem->final_url);

        //test reset of options

        $linkChecker->unregisterChecker($this->class);

        $linkChecker->checkLink($linkItem);
        $this->assertNotEmpty($linkItem->final_url, "Final URL should not be empty\n" . $linkItem->final_url);
    }
}
