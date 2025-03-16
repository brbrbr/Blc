<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Blc;

use Blc\Component\Blc\Administrator\Helper\UrlHelper;
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

#[Attributes\CoversClass(UrlHelper::class)]
class UrlHelperTest extends UnitTestCase
{
    public function setUp(): void
    {
        //  $this->initApplication();
    }

    public static function utf8hosts(): array
    {
        return [
            ['nörgler.com','xn--nrgler-wxa.com'],
            ['München.de','xn--Mnchen-3ya.de'],
            ['SomeUpper.200.inValid','someupper.200.invalid'],
            ['úùû-ÚÙÛ.com','xn----6gabbced.com'],

        ];
    }


    #[Attributes\DataProvider('utf8hosts')]
    public function testHostToPunycode($host, $expectedTo): void
    {
        $to         = UrlHelper::hostToPunnycode($host);
        $expectedTo = strtolower($expectedTo);
        $this->assertEquals(
            $to,
            $expectedTo,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }

    #[Attributes\DataProvider('utf8hosts')]
    public function testPunycodetoUrl($expectedTo, $from): void
    {
        $expectedTo = mb_strtolower($expectedTo);
        $url        = "https://$from/Bla-Bla-Bla/$from";

        $urlTo = "https://$expectedTo/Bla-Bla-Bla/$from";
        $to    = UrlHelper::urlToUTF8($url);

        $this->assertEquals(
            $urlTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $urlTo,
                $to
            )
        );
    }
}
