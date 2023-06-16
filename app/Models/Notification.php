<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $casts = [
        'id' => 'integer',
        'is_read' => 'boolean'
    ];
}
