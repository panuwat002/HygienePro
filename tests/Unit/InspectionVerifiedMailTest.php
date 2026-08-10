<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\InspectionSession;
use App\Models\User;
use App\Mail\InspectionVerified;

class InspectionVerifiedMailTest extends TestCase
{
    public function test_mail_envelope_handles_null_type()
    {
        $session = new InspectionSession(['type' => null]);
        $user = new User(['name' => 'Test Supervisor']);
        
        $mail = new InspectionVerified($session, $user);
        
        $envelope = $mail->envelope();
        
        $this->assertStringContainsString('Unknown', $envelope->subject);
    }
}
