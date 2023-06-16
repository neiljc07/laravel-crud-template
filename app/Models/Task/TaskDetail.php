<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Model;

class TaskDetail extends Model
{
    protected $casts = [
        'is_completed_by_user' => 'boolean',
        'is_completed_by_checker' => 'boolean',
        'is_extended' => 'boolean',
    ];
}
