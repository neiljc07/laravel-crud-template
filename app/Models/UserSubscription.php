<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    protected $casts = [
        'subscription_type_id' => 'integer'
    ];
}
