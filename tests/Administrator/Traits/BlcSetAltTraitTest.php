<?php

declare(strict_types=1);

namespace Blc\Tests\Administrator\Traits;

use Blc\Component\Blc\Administrator\Interface\BlcSetAltInterface as ALT_CODES;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcSetAltTrait;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;

class BlcSetAltTraitTest extends UnitTestCase
{
    protected string $class          = BlcSetAltTrait::class;
    protected string $folder         = 'blc';
    protected string $element        = 'phpunit';
    protected string $context        = 'blc.phpunit';
    protected $canSetAltFields       = [
        'fieldyes'    => ALT_CODES::BLC_REPLACE_ALT_YES,
        'fieldno'     => ALT_CODES::BLC_REPLACE_ALT_NO,
        'fieldparser' => ALT_CODES::BLC_REPLACE_ALT_PARSER,
    ];
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testbootPlugin()
    {
        $plugin = $this->bootPlugin();
        $this->assertInstanceOf(CMSPlugin::class, $plugin);
    }

    public function testSetAlt()
    {
        $this->expectException(\RuntimeException::class);
        $plugin   = $this->bootPlugin();
        $instance = new \StdClass();
        $link     = new LinkTable($this->getDatabase());

        $plugin->setAlt($link, $instance, '');


        // Test with a field that can be replaced
    }

    public function testcanSetAltEmpty()
    {
        $this->expectException(\RuntimeException::class);
        $plugin   = $this->bootPlugin();
        $instance = new \StdClass();
        $plugin->canSetAlt($instance);
    }

    public function testcanSetAltFieldEmpty()
    {
        $plugin                  = $this->bootPlugin();
        $plugin->canSetAltFields = $this->canSetAltFields;
        $instance                = new \StdClass();
        $result                  = $plugin->canSetAlt($instance);
        $this->assertFalse($result, 'Expected canSetAlt to return false when field is empty');
    }

    public function testcanSetAltFieldNo()
    {
        $plugin                  = $this->bootPlugin();
        $plugin->canSetAltFields = $this->canSetAltFields;
        $instance                = new \StdClass();
        $instance->field         = 'fieldno'; // This field is set to ALT_CODES::BLC_REPLACE_ALT_NO
        $result                  = $plugin->canSetAlt($instance);
        $this->assertFalse($result, 'Expected canSetAlt to return false when field is empty');
    }

    public function testcanSetAltFieldYes()
    {
        $plugin                  = $this->bootPlugin();
        $plugin->canSetAltFields = $this->canSetAltFields;
        $instance                = new \StdClass();
        $instance->field         = 'fieldyes'; // This field is set to ALT_CODES::BLC_REPLACE_ALT_NO
        $result                  = $plugin->canSetAlt($instance);
        $this->assertTrue($result, 'Expected canSetAlt to return false when field is empty');
    }

    public function testcanSetAltFieldParserParserEmpty()
    {
        $plugin                  = $this->bootPlugin();
        $plugin->canSetAltFields = $this->canSetAltFields;
        $instance                = new \StdClass();
        $instance->field         = 'fieldparser'; // This field is set to ALT_CODES::BLC_REPLACE_ALT_NO
        $result                  = $plugin->canSetAlt($instance);
        $this->assertFalse($result, 'Expected canSetAlt to return false when field is empty');
    }

    public function testcanSetAltFieldParserParserNotExists()
    {
        $plugin                  = $this->bootPlugin();
        $plugin->canSetAltFields = $this->canSetAltFields;
        $instance                = new \StdClass();
        $instance->field         = 'fieldparser'; // This field is set to ALT_CODES::BLC_REPLACE_ALT_NO
        $instance->parser        = 'dummy';
        $result                  = $plugin->canSetAlt($instance);
        $this->assertFalse($result, 'Expected canSetAlt to return false when field is empty');
    }

    public function testcanSetAltFieldInvalidCode()
    {
        $plugin                           = $this->bootPlugin();
        $plugin->canSetAltFields          = $this->canSetAltFields;
        $plugin->canSetAltFields['dummy'] = 'invalid_code'; // This field is set to an invalid code
        $instance                         = new \StdClass();
        $instance->field                  = 'dummy'; // This field is set to ALT_CODES::BLC_REPLACE_ALT_NO
        $instance->parser                 = 'dummy';
        $result                           = $plugin->canSetAlt($instance);
        $this->assertFalse($result, 'Expected canSetAlt to return false when field is empty');
    }


    public function testcanSetAltFieldParserParserCanNot()
    {
        $plugin                  = $this->bootPlugin();
        $plugin->canSetAltFields = $this->canSetAltFields;
        $instance                = new \StdClass();
        $instance->field         = 'fieldparser'; // This field is set to ALT_CODES::BLC_REPLACE_ALT_NO
        $instance->parser        = 'href';
        $result                  = $plugin->canSetAlt($instance);
        $this->assertFalse($result, 'Expected canSetAlt to return false when field is empty');
    }
    public function testcanSetAltFieldParserParserCan()
    {
        $plugin                  = $this->bootPlugin();
        $plugin->canSetAltFields = $this->canSetAltFields;
        $instance                = new \StdClass();
        $instance->field         = 'fieldparser'; // This field is set to ALT_CODES::BLC_REPLACE_ALT_NO
        $instance->parser        = 'img';
        $result                  = $plugin->canSetAlt($instance);
        $this->assertTrue($result, 'Expected canSetAlt to return false when field is empty');
    }

    public function testgetFieldsSetAlt()
    {
        $plugin                  = $this->bootPlugin();
        $plugin->canSetAltFields = $this->canSetAltFields;

        $result = $plugin->getFieldsSetAlt();
        $this->assertSame($result, $this->canSetAltFields, 'Expected canSetAlt to return false when field is empty');
    }




    protected function bootPlugin(?string $class = null, ?array $config = null, bool $assert = false)
    {

        $class ??= $this->class;
        if ($class !== $this->class) {
            parent::bootPlugin($class, $config, $assert);
        }


        $config = [
            'type'   => $this->folder,
            'name'   => $this->element,
            'params' => '{"check_catid":1,"article_alias":2,"category_alias":2,"check_lang":1,"enablecf":1,"cf":{"editor":2,"textarea":2,"text":2,"url":2,"extraurl":["14"],"media":2,"mediajce":2,"subform":2},"access":-1,"published":-1,"onsave":"-1","ondelete":"-1","deleteonsavepugin":1}',
            'id'     => 10285,
        ];

        $plugin = new class ($config) extends CMSPlugin {
            use DatabaseAwareTrait;
            use BlcSetAltTrait;

            protected string $context        = 'blc.phpunit';
            public $componentConfig;
            public $canSetAltFields;

            public function __construct(array $config = []) //wrong way around J4 workaround
            {
                if (version_compare(JVERSION, '5.3', '<')) {
                    $subject =  Factory::getApplication()->getDispatcher();
                    parent::__construct($subject, $config);
                } else {
                    parent::__construct($config);
                }
                $this->params          = new Registry();
                $this->componentConfig =  ComponentHelper::getParams('com_blc');
            }
        };
        $plugin->setApplication($this->app);
        $plugin->setDatabase($this->getDatabase());

        return $plugin;
    }
}
