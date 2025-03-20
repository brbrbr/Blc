<?php
/*
public function testpluginCanReplaceLink()
    {
        $plugin                                                                = $this->bootPlugin();
        $plugin->params->set('plugin_can_replace_link' , 0);
        $canReplace = $plugin->pluginCanReplaceLink();
        $this->assertFalse($canReplace);

        $plugin->params->set('plugin_can_replace_link' , 1);
        $canReplace = $plugin->pluginCanReplaceLink();
        $this->assertTrue($canReplace);

        $plugin->params->set('plugin_can_replace_link' , -1);
        $canReplace = $plugin->pluginCanReplaceLink();
        $this->assertFalse($canReplace);
        
       
    }

    */


declare(strict_types=1);

namespace Blc\Tests\Plugin;


use Blc\Plugin\Blc\Unsef\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;

class BlcExtractTrailTest extends UnitTestCase {

   public function testDummy() {
        $this->assertTrue(true);
    }
}
