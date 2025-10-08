<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugins;

use Blc\Plugin\Blc\Checker\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
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



    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }
   public function testBootPluginService()
    {
        parent::testBootPluginService();
    }
    public function testgetSubscribedEvents()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testonBlcCheckerRequest()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testcanCheckLink()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testcheckLink()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetHelpLink()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetHelpHTML()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
