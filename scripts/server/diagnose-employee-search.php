<?php

/**
 * Read-only: why a search for an employee finds nothing.
 *
 * Prints what is actually stored in the name columns for one employee, then
 * runs the exact clauses EmployeeController::index() searches on, one at a
 * time, so it is obvious which of them was supposed to match and did not.
 *
 * Run:  C:\PHP\php.exe scripts\server\diagnose-employee-search.php 65990
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

$term = $argv[1] ?? '';

if ($term === '') {
    echo "\nใส่คำค้นด้วย เช่น:  php scripts\\server\\diagnose-employee-search.php 65990\n\n";
    exit(1);
}

echo "\n=== แถวที่ตรงกับรหัสพนักงานเป๊ะๆ '{$term}' ===\n";

$rows = DB::table('employees')
    ->where('employee_id', $term)
    ->get(['id', 'employee_id', 'prefix', 'fname', 'lname', 'fullname', 'department_id', 'is_active']);

if ($rows->isEmpty()) {
    echo "  ไม่พบ — ลองค้นแบบหลวมๆ\n";
    $rows = DB::table('employees')
        ->where('employee_id', 'like', "%{$term}%")
        ->limit(5)
        ->get(['id', 'employee_id', 'prefix', 'fname', 'lname', 'fullname', 'department_id', 'is_active']);
}

foreach ($rows as $r) {
    echo "  id={$r->id}\n";
    // Bracketed so stray whitespace from an Excel import is visible.
    echo "    employee_id = [{$r->employee_id}] (ยาว " . strlen((string) $r->employee_id) . ")\n";
    echo "    prefix      = [" . ($r->prefix ?? 'NULL') . "]\n";
    echo "    fname       = [" . ($r->fname ?? 'NULL') . "]\n";
    echo "    lname       = [" . ($r->lname ?? 'NULL') . "]\n";
    echo "    fullname    = [" . ($r->fullname ?? 'NULL') . "]\n";
    echo "    is_active   = " . var_export((bool) $r->is_active, true) . "\n";
}

echo "\n=== แต่ละเงื่อนไขที่หน้าค้นหาใช้ จับได้กี่แถว ===\n";

$clauses = [
    "fname LIKE %term%"    => fn () => Employee::where('fname', 'like', "%{$term}%"),
    "lname LIKE %term%"    => fn () => Employee::where('lname', 'like', "%{$term}%"),
    "employee_id LIKE %term%" => fn () => Employee::where('employee_id', 'like', "%{$term}%"),
    "CONCAT(prefix,fname,lname)" => fn () => Employee::whereRaw(
        "CONCAT(prefix, ' ', fname, ' ', lname) LIKE ?", ["%{$term}%"]
    ),
    // Not searched today. The list prints this column as the employee's name.
    "fullname LIKE %term% (ยังไม่ได้ค้น)" => fn () => Employee::where('fullname', 'like', "%{$term}%"),
];

foreach ($clauses as $label => $builder) {
    printf("  %-38s %d แถว\n", $label, $builder()->count());
}

echo "\n=== ทั้งหน้าค้นหารวมกัน (เหมือนที่ controller ทำ) ===\n";

$total = Employee::where(function ($q) use ($term) {
    $q->where('fname', 'like', "%{$term}%")
      ->orWhere('lname', 'like', "%{$term}%")
      ->orWhere('employee_id', 'like', "%{$term}%")
      ->orWhereRaw("CONCAT(prefix, ' ', fname, ' ', lname) LIKE ?", ["%{$term}%"]);
})->count();

echo "  เจอ {$total} แถว → หน้าเว็บแบ่งหน้าละ 20\n";

if ($total > 0) {
    $pages = (int) ceil($total / 20);
    echo "  มีทั้งหมด {$pages} หน้า — ถ้า URL ยังติด ?page=6 อยู่ หน้านั้นจะว่าง\n";
}

echo "\n=== พนักงานที่ชื่อเก็บไว้ใน fullname อย่างเดียว (ค้นด้วยชื่อไม่เจอ) ===\n";

$nameless = DB::table('employees')
    ->where(function ($q) {
        $q->whereNull('fname')->orWhere('fname', '');
    })
    ->whereNotNull('fullname')
    ->count();

$all = DB::table('employees')->count();

echo "  {$nameless} จาก {$all} คน\n\n";
