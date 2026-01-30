<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Traits;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Traits\GetCheckerTrait;
use Blc\Tests\UnitTestCase;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Traits/GetCheckerTrait

 *
 * @since       25.44.7398
 */


class GetCheckerTraitTest extends UnitTestCase
{
    protected string $class          = GetCheckerTrait::class;
    protected string $folder         = 'blc';
    protected string $element        = 'phpunit';
    protected string $context        = 'blc.phpunit';


    public function testgetChecker()
    {
        $plugin  = $this->bootTrait();
        $checker = $plugin->getChecker();
        $this->assertInstanceOf(BlcCheckerHttpCurl::class, $checker);
    }

    public function testgetCheckerClone()
    {
        $plugin  = $this->bootTrait();
        $checker = $plugin->getChecker(true); //clone
        $this->assertInstanceOf(BlcCheckerHttpCurl::class, $checker);
    }

    public function testgetCheckerSingleton()
    {
        $plugin     = $this->bootTrait();
        $checkerOne = $plugin->getChecker();
        $checkerTwo = $plugin->getChecker();
        $this->assertSame($checkerOne, $checkerTwo);
    }

    public function testgetCheckerNotSingleton()
    {
        $plugin     = $this->bootTrait();
        $checkerOne = $plugin->getChecker();
        $checkerTwo = $plugin->getChecker(true); // clone
        $this->assertNotSame($checkerOne, $checkerTwo);
    }


    protected function bootTrait()
    {
        $trait = new class () {
            use GetCheckerTrait {
                GetCheckerTrait::getChecker as private traitgetChecker;
            }

            public function getChecker(bool $clone = false): BlcCheckerHttpCurl
            {
                return $this->traitgetChecker($clone);
            }
        };

        return $trait;
    }
}
