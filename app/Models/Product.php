<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'category',
        'stock',
        'primary_image_id',
        'pending_image_name',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function primaryImage()
    {
        return $this->belongsTo(Image::class, 'primary_image_id');
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'entity');
    }
}
