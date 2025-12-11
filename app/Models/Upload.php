<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = [
        'uuid',
        'filename',
        'original_name',
        'size',
        'mime_type',
        'checksum',
        'status',
        'total_chunks',
        'uploaded_chunks',
        'metadata',
    ];

    protected $casts = [
        'size' => 'integer',
        'total_chunks' => 'integer',
        'uploaded_chunks' => 'integer',
        'metadata' => 'array',
    ];

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    public function incrementUploadedChunks(): void
    {
        $this->increment('uploaded_chunks');

        if ($this->uploaded_chunks >= $this->total_chunks && $this->status !== 'completed') {
            $this->update(['status' => 'uploading']);
        }
    }
}
