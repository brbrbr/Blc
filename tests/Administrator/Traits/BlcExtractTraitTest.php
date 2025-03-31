<?php




declare(strict_types=1);

namespace Blc\Tests\Administrator\Traits;

use Blc\Tests\UnitTestCase;
use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;

#[Attributes\CoversClass(BlcExtractTrait::class)]
class BlcExtractTraitTest extends UnitTestCase
{
    public function testDummy()
    {
        $this->assertTrue(true);
    }
}
