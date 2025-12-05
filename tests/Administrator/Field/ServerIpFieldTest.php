<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Field;

use Blc\Component\Blc\Administrator\Field\ServerIpField;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Form\Form;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/ServerIpField

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(ServerIpField::class)]
class ServerIpFieldTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testcanBoot()
    {
        $this->expectNotToPerformAssertions();
        $form = $this->createStub(Form::class);
        new ServerIpField($form);
    }
}
