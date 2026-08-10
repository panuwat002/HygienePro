<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Update the user's electronic signature.
     */
    public function updateSignature(Request $request): RedirectResponse
    {
        $request->validate([
            'signature_data' => ['nullable', 'string', 'starts_with:data:image/png;base64,'],
            'signature_file' => ['nullable', 'file', 'mimes:png', 'max:2048'],
        ]);

        if (!$request->input('signature_data') && !$request->hasFile('signature_file')) {
            return back()->withErrors(['signature_data' => 'กรุณาวาดลายเซ็นต์ หรืออัปโหลดไฟล์ลายเซ็นต์ (.png)']);
        }

        $user = $request->user();
        
        // Delete old signature if exists
        if ($user->signature_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->signature_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->signature_path);
        }

        if ($request->hasFile('signature_file')) {
            $file = $request->file('signature_file');
            $fileName = 'signature_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('signatures', $fileName, 'public');
        } else {
            $base64Image = $request->input('signature_data');
            // Remove the data URI scheme prefix
            $imageParts = explode(';base64,', $base64Image);
            $imageTypeAux = explode('image/', $imageParts[0]);
            $imageType = $imageTypeAux[1];
            $imageBase64 = base64_decode($imageParts[1]);

            // Generate a unique filename
            $fileName = 'signature_' . $user->id . '_' . time() . '.' . $imageType;
            $path = 'signatures/' . $fileName;

            // Save new signature
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $imageBase64);
        }

        $user->signature_path = $path;
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'signature-updated');
    }
}
