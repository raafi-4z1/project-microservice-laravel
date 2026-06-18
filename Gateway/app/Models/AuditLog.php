<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'resource',
        'resource_id',
        'performed_by',
        'role',
        'ip_address',
        'payload',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];
}
