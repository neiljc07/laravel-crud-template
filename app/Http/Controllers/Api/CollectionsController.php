<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserType;
use App\Models\Client;
use App\Models\SubscriptionType;
use App\Models\Team;

class CollectionsController extends Controller
{
    public function user_dropdowns(Request $request) {
        $user_types = UserType::where('is_enabled', 1)->where('code', '<>', 'ADMIN')->get();
        $client = Client::where('code', $request->code)->first();

        $teams = Team::where('client_id', $client->id)->get();
        $subscription_types = SubscriptionType::where('is_enabled', 1)->get();

        // Check if Users has exceeded
        $users = User::where('client_id', $client->id)->get();

        if(count($users) >= $client->num_of_users && ! $request->has('user_id')) {
            return response()->json(['message' => 'Number of Users has already reached the maximum number (' . count($users) . '/' . $client->num_of_users . ')'], 400);
        }

        if($request->has('user_id')) {
            $user = User::find($request->user_id);

            if(empty($user)) {
                return response()->json(['message' => 'Record Not Found'], 404);
            }

            $user->post_process();

            return compact('client', 'user_types', 'user', 'teams', 'subscription_types');
        }

        return compact('client', 'user_types', 'teams', 'subscription_types');
    }

    public function user_dropdowns_without_teams(Request $request) {
        $user_types = UserType::where('is_enabled', 1)->where('code', '<>', 'ADMIN')->get();
        $subscription_types = SubscriptionType::where('is_enabled', 1)->get();

        $client = null;

        if($request->has('code')) {
            $client = Client::where('code', $request->code)->first();
        }
        

        if($request->has('user_id')) {
            $user = User::find($request->user_id);

            if(empty($user)) {
                return response()->json(['message' => 'Record Not Found'], 404);
            }

            $user->post_process();

            return compact('user_types', 'user', 'subscription_types', 'client');
        }

        return compact('user_types', 'subscription_types', 'client');
    }
}
