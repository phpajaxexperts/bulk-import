<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = [
        'filename',
        'status',
        'total_rows',
        'imported',
        'updated',
        'invalid',
        'duplicates',
        'errors',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'imported' => 'integer',
        'updated' => 'integer',
        'invalid' => 'integer',
        'duplicates' => 'integer',
        'errors' => 'array',
    ];
}
