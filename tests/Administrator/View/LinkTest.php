<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\View;

use Blc\Component\Blc\Administrator\View\Link\HtmlView;
use Blc\Component\Blc\Administrator\Model\LinkModel;
use Blc\Component\Blc\Administrator\Service\Html\Blc;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Document\Document;
use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes;
use Joomla\CMS\WebAsset\WebAssetManager;


/**
 * Code coverage and check that nothing really bad happens.
 * rest of the test and checking is vidual in the adminstrator.
 *
 * @package     BLC.UnitTest
 * @subpackage  View/Link

 *
 * @since       __DEPLOY_VERSION___
 */
#[Attributes\CoversClass(Blc::class)]
#[Attributes\CoversClass(HtmlView::class)]
class LinkTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
        $this->setUser(action: 'core.manage', assetKey: 'com_blc.admin');
    }

    public function testdisplay()
    {
        $config = [];
        $config['base_path'] = JPATH_ROOT . '/administrator/components/com_blc';
        if (! defined('JPATH_COMPONENT')) {
            define('JPATH_COMPONENT', $config['base_path']);
        }
        $config['name'] = 'link';
        $view = new HtmlView($config);

        $linkModel = new LinkModel();

        $linkItem = $this->getSomeLinkId(); //this should be a link with instances
        Factory::getApplication()->getInput()->set('id', $linkItem->link_id);
       
        $documentStub = $this->getMockBuilder(Document::class)->getMock();
        $webAssetManagerStub = $this->getMockBuilder(WebAssetManager::class)->disableOriginalConstructor()->getMock();

        $documentStub->method('getWebAssetManager')
            ->willReturn($webAssetManagerStub);

        $webAssetManagerStub->method('__call')
            ->willReturn($webAssetManagerStub);

        $view->setLanguage($this->app->getLanguage());

        $view->setDocument($documentStub);

        $view->setModel($linkModel, true);
        ob_start();
        $view->display();
        $resp = ob_get_clean();
       
        $this->assertNotEmpty($resp);
        $this->assertStringContainsString("<input type=\"hidden\" name=\"jform[id]\" value=\"{$linkItem->link_id}\" />",$resp);
    }
}
