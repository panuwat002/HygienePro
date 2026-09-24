<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * Display the user's settings form.
     */
    public function index()
    {
        $user = Auth::user();
        
        // Define all available email preferences
        $preferences = [
            'email_session_started' => $user->wantsEmailFor('email_session_started'),
            'email_session_finished_pass' => $user->wantsEmailFor('email_session_finished_pass'),
            'email_session_finished_fail' => $user->wantsEmailFor('email_session_finished_fail'),
            'email_order_reclean' => $user->wantsEmailFor('email_order_reclean'),
            'email_session_verified' => $user->wantsEmailFor('email_session_verified'),
            'email_car_new' => $user->wantsEmailFor('email_car_new'),
            'email_car_resolved' => $user->wantsEmailFor('email_car_resolved'),
            'email_car_closed' => $user->wantsEmailFor('email_car_closed'),
            'email_car_overdue' => $user->wantsEmailFor('email_car_overdue'),
            'email_awaiting_approval' => $user->wantsEmailFor('email_awaiting_approval'),
        ];

        return view('settings.index', compact('preferences'));
    }

    /**
     * Update the user's settings.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // Extract checkboxes (they will be missing from request if unchecked)
        $preferences = [
            'email_session_started' => $request->has('email_session_started'),
            'email_session_finished_pass' => $request->has('email_session_finished_pass'),
            'email_session_finished_fail' => $request->has('email_session_finished_fail'),
            'email_order_reclean' => $request->has('email_order_reclean'),
            'email_session_verified' => $request->has('email_session_verified'),
            'email_car_new' => $request->has('email_car_new'),
            'email_car_resolved' => $request->has('email_car_resolved'),
            'email_car_closed' => $request->has('email_car_closed'),
            'email_car_overdue' => $request->has('email_car_overdue'),
            'email_awaiting_approval' => $request->has('email_awaiting_approval'),
        ];

        $user->update([
            'notification_preferences' => $preferences
        ]);

        return redirect()->route('settings.index')->with('success', 'บันทึกการตั้งค่าการรับอีเมลเรียบร้อยแล้ว');
    }
}
