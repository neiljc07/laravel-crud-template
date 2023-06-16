<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['user_id', 'address', 'lat', 'lng', 'type', 'remarks', 'created_at', 'updated_at', 'picture', 'thumbnail', 'device_type'];
}
