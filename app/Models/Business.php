<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
        'place_id',
        'name',
        'category',
        'address',
        'area',
        'lat',
        'lng',
        'rating',
        'review_count',
        'first_seen',
        'last_fetched',
        'indicators',
        'google_maps_url',
    ];

    protected $casts = [
        'indicators' => 'array',
        'first_seen' => 'date',
        'last_fetched' => 'datetime',
    ];
}
