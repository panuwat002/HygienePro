<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use App\Models\Checkpoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Departments
        $deptQA = Department::create([
            'dept_name' => 'Quality Assurance',
            'dept_code' => 'QA',
            'visibility_type' => 'global'
        ]);

        $deptPd = Department::create([
            'dept_name' => 'Production',
            'dept_code' => 'PD',
            'visibility_type' => 'isolated'
        ]);

        $deptHR = Department::create([
            'dept_name' => 'Human Resource',
            'dept_code' => 'HR',
            'visibility_type' => 'isolated'
        ]);

        // 2. Create Users (Matrix Roles)

        // IT Admin (Global, Level 6)
        User::create([
            'name' => 'panuwat sakutem',
            'email' => 'panuwat.sa@allcoco.co.th',
            'password' => Hash::make('088286@p'),
            'role' => 'admin',
            'level' => 6,
            'department_id' => $deptQA->id, // Admin usually in QA or IT
        ]);

        // QA Manager (Global, Level 5-6)
        User::create([
            'name' => 'QA Manager',
            'email' => 'manager.qa@example.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'level' => 6,
            'department_id' => $deptQA->id,
        ]);

        // QA Supervisor (Global, Level 4)
        User::create([
            'name' => 'QA Supervisor',
            'email' => 'sup.qa@example.com',
            'password' => Hash::make('password'),
            'role' => 'supervisor',
            'level' => 4,
            'department_id' => $deptQA->id,
        ]);

        // QA Staff (Global, Level 2-3)
        User::create([
            'name' => 'QA Staff 01',
            'email' => 'staff.qa@example.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'level' => 2,
            'department_id' => $deptQA->id,
        ]);

        // Production Manager (isolated, Level 5-6)
        User::create([
            'name' => 'Production Manager',
            'email' => 'manager.pd@example.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'level' => 6,
            'department_id' => $deptPd->id,
        ]);

        // 3. Create Checkpoints (Version 1)
        $checkpoints = [
            ['title' => 'เล็บสั้น ตะไบเรียบร้อย', 'description' => 'เล็บต้องไม่ยาวเกินปลายนิ้ว'],
            ['title' => 'ไม่สวมเครื่องประดับ', 'description' => 'ห้ามใส่แหวน นาฬิกา ต่างหู'],
            ['title' => 'สวมหมวกเก็บผมเรียบร้อย', 'description' => 'ไรผมต้องไม่โผล่ออกมานอกหมวก'],
            ['title' => 'สวมหน้ากากอนามัยถูกต้อง', 'description' => 'ปิดจมูกและปากให้มิดชิด'],
            ['title' => 'ไม่มีบาดแผลที่มือ', 'description' => 'หากมีแผลต้องปิดพลาสเตอร์กันน้ำ'],
        ];

        foreach ($checkpoints as $cp) {
            Checkpoint::create($cp);
        }

        // 4. Create Employees
        \App\Models\Employee::create([
            'employee_id' => 'EMP001',
            'fullname' => 'Somchai Jaidee',
            'department_id' => $deptPd->id,
            'qr_code_hash' => 'hash_somchai_001',
            'is_active' => true
        ]);

        \App\Models\Employee::create([
            'employee_id' => 'EMP002',
            'fullname' => 'Somsri Rakngan',
            'department_id' => $deptPd->id,
            'qr_code_hash' => 'hash_somsri_002',
            'is_active' => true
        ]);
    }
}
