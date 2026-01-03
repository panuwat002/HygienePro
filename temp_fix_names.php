<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$employees = App\Models\Employee::all();
echo "Found " . $employees->count() . " employees.\n";

foreach($employees as $e) {
    echo "Processing ID: " . $e->id . " Name: " . $e->fullname . "\n";
    // Normalize spaces
    $fullname = preg_replace('/\s+/', ' ', trim($e->fullname));
    
    $parts = explode(' ', $fullname);
    $count = count($parts);
    echo "Parts count: " . $count . "\n";
    
    if($count >= 3) {
        $e->prefix = $parts[0];
        $e->fname = $parts[1];
        $e->lname = implode(' ', array_slice($parts, 2));
    } elseif($count == 2) {
        $e->prefix = 'คุณ'; // Default prefix
        $e->fname = $parts[0];
        $e->lname = $parts[1];
    } else {
        $e->fname = $e->fullname; // Fallback
    }
    
    $e->save();
    echo "Saved: Prefix=" . $e->prefix . ", Fname=" . $e->fname . ", Lname=" . $e->lname . "\n";
}
echo "Done.";
