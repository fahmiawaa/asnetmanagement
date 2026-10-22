<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_code',
        'internet_package_id',
        'pppoe_account_id',
        'name',
        'whatsapp_number',
        'address',
        'latitude',
        'longitude',
        'installation_date',
        'due_day',
        'status',
        'notes',
    ];

    protected $casts = [
        'installation_date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function internetPackage()
    {
        return $this->belongsTo(InternetPackage::class)->withTrashed();
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function isolationLogs()
    {
        return $this->hasMany(IsolationLog::class);
    }

    public function pppoeAccount()
    {
        return $this->belongsTo(
            PppoeAccount::class,
            'pppoe_account_id'
        );
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function ontDevice(): HasOne
    {
        return $this->hasOne(OntDevice::class);
    }
}
