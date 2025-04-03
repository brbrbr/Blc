<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Parser;

use Blc\Component\Blc\Administrator\Parser;
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

 #[Attributes\CoversClass(Parser\SrcplayerParser::class)]
class SrcplayerParserTest extends UnitTestCase
{
    protected string $fieldContext = 'com_content.article';

    public static $src = 'https://phpunit.invalid/?v=phpunit.text';
    public function setUp(): void
    {
        $this->initApplication();
    }

    public static function videoLinks()
    {
        return [
            [
                '<p>Extra</p>{youtube src="' . self::$src . '"}<p>Extra</p>',
            ],
            [
                '<p>Extra</p>{youtube src=' . self::$src . '}<p>Extra</p>',
            ],
            [
                '<p>Extra</p>{vimeo src=' . self::$src . '}<p>Extra</p>',
            ],
            [
                '<p>Extra</p>{avsplayer src=' . self::$src . '}<p>Extra</p>',
            ],

        ];
    }



    #[Attributes\DataProvider('videoLinks')]
    public function testExtractAndReplaceInsource($text)
    {
        $this->assertReplaceInSource(Parser\SrcplayerParser::class,$text,self::$src,'youtube');
    }

}
