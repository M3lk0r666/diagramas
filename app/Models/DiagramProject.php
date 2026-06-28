<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagramProject extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'type',
        'canvas_json',
    ];

    protected $casts = [
        'canvas_json' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
