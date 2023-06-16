<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContactUsController extends Controller
{
    public function create(Request $request) {
        $data = $request->all();

        if( ! file_exists('support')) {
            mkdir('support', 744);
        }
    
        file_put_contents('support/support_' . time() . '_' . $request->email . '.txt', json_encode($data));
        return response()->json('ok');
    }
}
