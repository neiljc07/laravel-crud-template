<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPinBucket extends Model
{
    protected $casts = [
        'quantity' => 'integer',
        'client_id' => 'integer',
        'user_id' => 'integer'
    ];
}
