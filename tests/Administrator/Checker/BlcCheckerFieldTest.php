<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Checker;


use Blc\Component\Blc\Administrator\Checker\BlcCheckerField;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerUnchecked;

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Traits\CustomFieldsTrait;
use Blc\Tests\UnitTestCase;

use Joomla\Database\ParameterType;

use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes;

//using constants but not implementing

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(BlcCheckerField::class)]
class BlcCheckerFieldTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]

    public function setUp(): void
    {
        $this->initApplication();
    }


    public static function canCheckLinkProvider(): array
    {
        return   [
            ['url' => 'sqlfield', 'ret' => HTTPCODES::BLC_CHECK_FALSE],
            ['url' => 'sqlfield://123/123', 'ret' => HTTPCODES::BLC_CHECK_TRUE],
            ['url' => 'https://mail.fiets4daagsen.nl', 'ret' => HTTPCODES::BLC_CHECK_FALSE], //cname
            ['url' => 'dummyfield://123/123', 'ret' => HTTPCODES::BLC_CHECK_FALSE],

        ];
    }


    protected function bootInstance(bool $singleTon = false)
    {
        $checker = BlcCheckerField::getInstance($singleTon);
        $checker->setDatabase($this->getDatabase());
        return $checker;
    }

    public function testCanBoot()
    {
        $checker = $this->bootInstance(true);
        $this->assertInstanceOf(BlcCheckerField::class, $checker);
        $this->isSingeTon($checker);
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanCheckHost($url, $ret)
    {
        $checker = $this->bootInstance();

        $linkItem = $this->loadLinkItem($url);

        $result = $checker->canCheckLink($linkItem);
        $this->assertSame($result, $ret);
        $this->assertMessageQueue();
        if ($ret == HTTPCODES::BLC_CHECK_TRUE) {
            $linkItem->http_code = 200;
            $result = $checker->canCheckLink($linkItem);
            $this->assertSame($result, HTTPCODES::BLC_CHECK_FALSE);
        }
    }



    private function getFieldsByType(string $type)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from('#__fields AS a');
        $query->select($db->quoteName(['a.id', 'a.context', 'a.title', 'a.type', 'a.fieldparams']));
        $query->join('LEFT', '#__fields_values AS g', 'g.field_id = a.id');
        $query->select($db->quoteName(['g.item_id']));
        $query->where($db->quoteName('a.type') . ' = :type')
            ->bind(':type', $type, ParameterType::STRING);
        $query->group($db->quoteName(['a.id'])); //need just one
        $db->setQuery($query);

        return $db->loadObjectList();
    }
    protected function  buildPseudoFieldLink(string $type, int $id, mixed $value): string
    {
        return (new class {
            use CustomFieldsTrait;
            public function __construct() {}
        })->buildPseudoFieldLink($type, $id, $value);
    }

    /**
     * 
     * @since __DEPLOY_VERSION__
     */

    public function testparsePseudoFieldLinkObject()
    {


        $type = 'dummy';
        $id = 999;
        $value = new \stdClass();
        $value->dummy = uniqid();


        $buildUrl = $this->buildPseudoFieldLink($type, $id, $value);

        $checker = $this->bootInstance();
        ['fieldId' => $idResult, 'fieldType' => $typeResult, 'fieldValues' => $valueResult] = $checker->parsePseudoFieldLink($buildUrl);

        $this->assertSame($id, $idResult);
        $this->assertSame($type, $typeResult);
        $this->assertSame((array)$value, $valueResult);
    }
    /**
     * 
     * @since __DEPLOY_VERSION__
     */
    public function testparsePseudoFieldLinkString()
    {

        $type = 'dummy';
        $id = 999;
        $value = uniqid();


        $buildUrl = $this->buildPseudoFieldLink($type, $id, $value);

        $checker = $this->bootInstance();
        ['fieldId' => $idResult, 'fieldType' => $typeResult, 'fieldValues' => $valueResult] = $checker->parsePseudoFieldLink($buildUrl);

        $this->assertSame($id, $idResult);
        $this->assertSame($type, $typeResult);
        $this->assertSame((array)$value, $valueResult);
    }

    public function testCheckSqlfield()
    {
        $urls = $this->getSqlFieldUrls();
        $this->checkUrlsChecker($urls);
        $this->checkUrlsBlcChecker($urls);
    }
    private function getSqlFieldUrls()
    {
        $urls = [];
        $fields = $this->getFieldsByType('sql');
        foreach ($fields as $field) {
            $params = new Registry($field->fieldparams);
            $query = $params->get('query');
            $multiple =  $params->get('multiple');
            $db = $this->getDatabase();
            $db->setQuery($query);
            $results = $db->loadAssocList();
            $values = array_column($results, 'value');
            shuffle($values);
            if ($multiple) {
                $selected = array_slice($values, 0, 3);
            } else {
                $selected = $values[0];
            }
            $url = $this->buildPseudoFieldLink('sql', $field->id, $selected);


            $urls[] =
                [
                    'url' => $url,
                    'can_check' => HTTPCODES::BLC_CHECK_TRUE,
                    'http_code' => HTTPCODES::BLC_VALID_FIELD_HTTP_CODE,
                    'broken' => HTTPCODES::BLC_BROKEN_FALSE
                ];

            $url = $this->buildPseudoFieldLink('dummy', $field->id, $selected);
            $urls['dummy-valid'] =
                [
                    'url' => $url,
                    'can_check' => HTTPCODES::BLC_CHECK_FALSE,

                    'http_code' => HTTPCODES::BLC_CHECK_UNSET,
                    'broken' => HTTPCODES::BLC_BROKEN_FALSE
                ];

            $invalidId = max($values) + 1;

            if ($multiple) {
                $selected[] = $invalidId;
            } else {
                $selected =  $invalidId;
            }

            $url = $this->buildPseudoFieldLink('sql', $field->id, $selected);

            $urls[] =
                [
                    'url' => $url,
                    'can_check' => HTTPCODES::BLC_CHECK_TRUE,
                    'http_code' => HTTPCODES::BLC_INVALID_FIELD_HTTP_CODE,
                    'broken' => HTTPCODES::BLC_BROKEN_TRUE
                ];

            $url = $this->buildPseudoFieldLink('dummy', $field->id, $selected);
            $urls['dummy-invalid'] =
                [
                    'url' => $url,
                    'can_check' => HTTPCODES::BLC_CHECK_FALSE,

                    'http_code' => HTTPCODES::BLC_CHECK_UNSET,
                    'broken' => HTTPCODES::BLC_BROKEN_FALSE
                ];
        }
        return $urls;
    }

    private function checkUrlsChecker($urls)
    {
        $checker = $this->bootInstance();
        foreach ($urls as $urlData) {
            ['url' => $url, 'can_check' => $can_check, 'http_code' => $http_code, 'broken' => $broken] = $urlData;

            $linkItem = $this->loadLinkItem($url);
            $result = $checker->canCheckLink($linkItem);
            $this->assertSame($result, $can_check, json_encode($urlData, JSON_PRETTY_PRINT));
            $checker->checkLink($linkItem);
            $this->assertSame($linkItem->http_code, $http_code, json_encode($urlData, JSON_PRETTY_PRINT));
            $this->assertSame($linkItem->broken, $broken, json_encode($urlData, JSON_PRETTY_PRINT));
        }
        $this->assertMessageQueue();
    }

    public function checkUrlsBlcChecker($urls)
    {

        $this->setComponentOption('com_blc', 'field_checker', 1);
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->getChecker(BlcCheckerUnchecked::class)->instance->setConfigOption('unkownprotocols', 0);
        foreach ($urls as $urlData) {
            ['url' => $url,  'http_code' => $http_code, 'broken' => $broken] = $urlData;

            $linkItem = $this->loadLinkItem($url);


            $BlcCheckLink->checkLink($linkItem);
            $this->assertSame($linkItem->http_code, $http_code, json_encode($urlData, JSON_PRETTY_PRINT));
            $this->assertSame($linkItem->broken, $broken, json_encode($urlData, JSON_PRETTY_PRINT));
        }
        $this->assertMessageQueue();
    }
}
