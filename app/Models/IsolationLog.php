<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IsolationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'action',
        'reason',
        'old_status',
        'new_status',
        'execution_mode',
        'approval_status',
        'approved_by',
        'approved_at',
        'executed_by',
        'executed_at',
        'attempt_count',
        'last_attempt_at',
        'next_retry_at',
        'last_error',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function executor()
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function approver()
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }
}
