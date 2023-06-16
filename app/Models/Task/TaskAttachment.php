<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Model;

class TaskAttachment extends Model
{
    protected $casts = [
        'is_locked' => 'boolean',
    ];
}
