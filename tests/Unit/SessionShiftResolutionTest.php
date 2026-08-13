<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\InspectionSession;

class SessionShiftResolutionTest extends TestCase
{
    public function test_get_shift_label_attribute_fallback()
    {
        $session = new InspectionSession(['shift' => 'custom_11,custom_15,custom_9,night']);
        
        $label = $session->shift_label;
        $this->assertNotEmpty($label);
        $this->assertStringContainsString('custom_11', $label);
        $this->assertStringContainsString('กะดึก', $label);
    }

    public function test_get_shift_label_attribute_standard()
    {
        $session = new InspectionSession(['shift' => 'morning']);
        $label = $session->shift_label;
        $this->assertEquals('กะเช้า', $label);
    }

    public function test_smart_shift_prefix_grouping()
    {
        $session = new class extends InspectionSession {
            public function formatShiftNamesPublic(array $names): string {
                return $this->formatShiftNames($names);
            }
        };

        $rawNames = [
            'กะเช้า 07.00-16.00',
            'กะเช้า 08.00-17.00',
            'กะเช้า 10.00-19.00',
        ];

        $formatted = $session->formatShiftNamesPublic($rawNames);
        $this->assertEquals('กะเช้า (07.00-16.00, 08.00-17.00, 10.00-19.00)', $formatted);
    }
}
