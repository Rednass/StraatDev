<?php

declare(strict_types=1);

use App\Student;
use App\Tips\Models\Tip;
use App\Tips\Services\TipManager;

class TipTest extends \Tests\TestCase
{
    public function testTipLike(): void
    {
        $tip = factory(Tip::class)->create();
        $student = factory(Student::class)->create();

        $tipService = new TipManager();
        $result = $tipService->likeTip($tip, 1, $student);

        $this->assertTrue($result);

        $result = $tipService->likeTip($tip, 1, $student);

        $this->assertFalse($result);
    }
}
