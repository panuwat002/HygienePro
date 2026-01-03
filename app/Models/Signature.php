<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'level',
        'signature_token',
        'ip_address',
        'user_agent',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(InspectionSession::class, 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
