<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'invoice_number',
        'period_start',
        'period_end',
        'due_date',
        'total_amount',
        'status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function isolationLogs()
    {
        return $this->hasMany(IsolationLog::class);
    }
}
