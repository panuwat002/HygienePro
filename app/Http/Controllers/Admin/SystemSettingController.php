<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SystemSettingController extends Controller
{
    /**
     * Show the form for editing email settings.
     */
    public function editEmail()
    {
        // Read current .env values
        $env = [
            'MAIL_MAILER' => env('MAIL_MAILER', 'smtp'),
            'MAIL_HOST' => env('MAIL_HOST', '127.0.0.1'),
            'MAIL_PORT' => env('MAIL_PORT', '2525'),
            'MAIL_USERNAME' => env('MAIL_USERNAME', ''),
            'MAIL_PASSWORD' => env('MAIL_PASSWORD', ''),
            'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION', 'tls'),
            'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'MAIL_FROM_NAME' => env('MAIL_FROM_NAME', config('app.name')),
        ];

        return view('admin.settings.email', compact('env'));
    }

    /**
     * Update the email settings in .env file.
     */
    public function updateEmail(Request $request)
    {
        $request->validate([
            'MAIL_MAILER' => 'required|string',
            'MAIL_HOST' => 'required|string',
            'MAIL_PORT' => 'required|numeric',
            'MAIL_USERNAME' => 'nullable|string',
            'MAIL_PASSWORD' => 'nullable|string',
            'MAIL_ENCRYPTION' => 'nullable|string',
            'MAIL_FROM_ADDRESS' => 'required|email',
            'MAIL_FROM_NAME' => 'required|string',
        ]);

        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);

        $keys = [
            'MAIL_MAILER',
            'MAIL_HOST',
            'MAIL_PORT',
            'MAIL_USERNAME',
            'MAIL_PASSWORD',
            'MAIL_ENCRYPTION',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME',
        ];

        foreach ($keys as $key) {
            $value = $request->input($key);
            
            // CRITICAL SECURITY: Sanitize value to prevent .env injection
            // Strip newlines, carriage returns, and null bytes that could inject new .env keys
            if ($value !== null) {
                $value = str_replace(["\n", "\r", "\0", "\x0B"], '', $value);
            }
            
            // Format value safely
            if (empty($value) && $value !== '0') {
                $value = 'null';
            } elseif (preg_match('/[\s#"\']/', $value)) {
                // Quote values containing spaces, hash signs, or quotes
                $value = '"' . addcslashes($value, '"') . '"';
            }

            // Replace existing key or append
            $pattern = "/^" . preg_quote($key, '/') . "=.*/m";
            if (preg_match($pattern, $str)) {
                $str = preg_replace($pattern, "{$key}={$value}", $str);
            } else {
                $str .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envFile, $str);

        // Clear config cache so Laravel uses the new env variables
        Artisan::call('config:clear');

        // Log the activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'update_email_settings',
            'model_type' => 'System',
            'model_id' => 0,
            'description' => 'Updated system email (SMTP) configuration',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.settings.email')->with('success', 'บันทึกการตั้งค่าอีเมลและรีเซ็ตระบบเรียบร้อยแล้ว (Email settings updated successfully)');
    }

    /**
     * Send a test email.
     */
    public function testEmail(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            \Illuminate\Support\Facades\Mail::raw('This is a test email from HygienePro system to verify your SMTP configuration.', function ($message) use ($request) {
                $message->to($request->test_email)
                        ->subject('✅ HygienePro: Test Email Verification');
            });

            return redirect()->route('admin.settings.email')->with('success', 'ส่งอีเมลทดสอบสำเร็จ! กรุณาตรวจสอบกล่องจดหมายของคุณ (Test email sent successfully)');
        } catch (\Exception $e) {
            return redirect()->route('admin.settings.email')->with('error', 'ไม่สามารถส่งอีเมลได้: ' . $e->getMessage());
        }
    }
}
