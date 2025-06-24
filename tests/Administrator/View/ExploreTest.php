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

use Blc\Component\Blc\Administrator\Model\ExploreModel;
use Blc\Component\Blc\Administrator\View\Explore\HtmlView;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Document\Document;
use Joomla\CMS\WebAsset\WebAssetManager;
use PHPUnit\Framework\Attributes;

/**
 * Code coverage and check that nothing really bad happens.
 * rest of the test and checking is vidual in the adminstrator.
 *
 * @package     BLC.UnitTest
 * @subpackage  View/Explore

 *
 * @since       25.44.7594_
 */

#[Attributes\CoversClass(HtmlView::class)]
class ExploreTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
        $this->setUser(action: 'core.manage', assetKey: 'com_blc.admin');
    }

    public function testdisplay()
    {
        $config              = [];
        $config['base_path'] = JPATH_ROOT . '/administrator/components/com_blc';
        if (! \defined('JPATH_COMPONENT')) {
            \define('JPATH_COMPONENT', $config['base_path']);
        }
        $config['name'] = 'explore';
        $view           = new HtmlView($config);

        $exploreModel        = new ExploreModel();
        $exploreModel->setDispatcher($this->getDispatcher());  // @since 25.44.7594 J60 https://github.com/joomla/joomla-cms/pull/45431

        $documentStub        = $this->getMockBuilder(Document::class)->getMock();
        $webAssetManagerStub = $this->getMockBuilder(WebAssetManager::class)->disableOriginalConstructor()->getMock();

        $documentStub->method('getWebAssetManager')
            ->willReturn($webAssetManagerStub);

        $webAssetManagerStub->method('__call')
            ->willReturn($webAssetManagerStub);

        $view->setLanguage($this->app->getLanguage());

        $view->setDocument($documentStub);

        $view->setModel($exploreModel, true);
        ob_start();
        $view->display();
        $resp = ob_get_clean();
        $this->assertNotEmpty($resp);
    }
}
