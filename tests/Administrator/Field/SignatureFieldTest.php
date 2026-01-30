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

use Blc\Component\Blc\Administrator\Field\SignatureField;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Form\Form;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/SignatureField

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(SignatureField::class)]
class SignatureFieldTest extends UnitTestCase
{
    public function testcanBoot()
    {
        $this->expectNotToPerformAssertions();
        $form = $this->createStub(Form::class);
        new SignatureField($form);
    }
}
