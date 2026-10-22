<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'payment_date',
        'amount',
        'method',
        'qris_reference',
        'qris_created_at',
        'qris_expires_at',
        'proof_path',
        'status',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'qris_created_at' => 'datetime',
        'qris_expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
