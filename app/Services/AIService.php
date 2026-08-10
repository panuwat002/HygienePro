<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    /**
     * Call Python AI Service to get tags for a given text.
     * 
     * @param string $text
     * @return array
     */
    public static function getTagsFromFinding($text)
    {
        if (empty(trim($text))) {
            return [];
        }

        try {
            $response = Http::timeout(5)->post('http://localhost:8001/api/analyze-finding', [
                'text' => $text
            ]);

            if ($response->successful()) {
                return $response->json('tags', []);
            }
        } catch (\Exception $e) {
            Log::warning("AI Service Error: " . $e->getMessage());
        }

        return []; // Fail-safe: Return empty tags if AI is down
    }

    /**
     * Call Python AI Service to get smart shift prediction.
     * 
     * @param string $currentTime
     * @param int $currentHour
     * @param string|null $lastSessionShift
     * @param string|null $lastSessionTime
     * @return array|null
     */
    public static function detectShift($currentTime, $currentHour, $lastSessionShift = null, $lastSessionTime = null)
    {
        try {
            $response = Http::timeout(3)->post('http://localhost:8001/api/detect-shift', [
                'current_time' => $currentTime,
                'current_hour' => $currentHour,
                'last_session_shift' => $lastSessionShift,
                'last_session_time' => $lastSessionTime
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning("AI Shift Detection Error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Call Python AI Service to verify an image for defects (Mockup).
     * 
     * @param string $imagePath
     * @return array|null
     */
    public static function verifyImage($imagePath)
    {
        try {
            // In a real app we would attach the file. 
            // For this mockup, we just send a dummy file request.
            $response = Http::timeout(10)->attach(
                'file', file_get_contents($imagePath), 'image.jpg'
            )->post('http://localhost:8001/api/verify-image');

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning("AI Image Verification Error: " . $e->getMessage());
        }

        return null;
    }
}
