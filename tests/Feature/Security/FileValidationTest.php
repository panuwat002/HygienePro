<?php

use App\Models\User;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it rejects SVG file uploads for profile image', function () {
    $dept = Department::create(['dept_code' => 'T1', 'dept_name' => 'Test', 'visibility_type' => 'global']);
    $admin = User::factory()->create(['role' => 'admin', 'level' => 5, 'department_id' => $dept->id]);

    $this->actingAs($admin);
    
    $svgFile = UploadedFile::fake()->createWithContent('malicious.svg', '<svg><script>alert(1)</script></svg>');

    $response = $this->post(route('employees.store'), [
        'prefix' => 'Mr.',
        'fname' => 'Test',
        'lname' => 'User',
        'employee_id' => 'EMP001',
        'department_id' => $dept->id,
        'profile_image' => $svgFile
    ]);

    $response->assertSessionHasErrors(['profile_image']);
});
