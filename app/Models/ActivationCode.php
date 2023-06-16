<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ActivationCode extends Model
{
    //

    public function post_process() {
        $this->user = User::find($this->user_id);
    }
}
