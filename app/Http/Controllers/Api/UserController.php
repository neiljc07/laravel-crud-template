<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ForgotPassword;
use App\Mail\UserActivation;
use App\Models\ActivationCode;
use App\Models\ForgotPasswordCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\UserType;
use App\Models\AssignedLocation;
use App\Models\Client;
use App\Models\Task\Task;
use App\Models\Notification;
use App\Models\Location;
use App\Models\SubscriptionType;
use App\Models\UserPinBucket;
use App\Models\UserSubscription;
use Validator;
use DB;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
  public function index(Request $request) 
  {
    $params = $request->all();

    if(isset($params['code']) && $params['code'] != 'user') {
      $user_type = \App\Models\UserType::where('code', $request->code)->first();

      if($user_type !== null) {
        $params['user_type_id'] = $user_type->id;
      }
    }

    if(isset($params['client_code'])) {
      $client = \App\Models\Client::where('code', $request->client_code)->first();

      if($client !== null) {
        $params['client_id'] = $client->id;      
      }
    }


    $users = User::search($params);

    if(isset($request->order_by)) {
      $orders = explode(',', $request->order_by);

      foreach($orders as $order) {
        $param = explode(':', $order);

        $users->orderBy($param[0], $param[1]);
      }
    }

    if(isset($request->pager)) {
      $users = $users->paginate($request->items_per_page);
    } else {
      $users = $users->get();
    }

    // DB::enableQueryLog();
    // $users->get();

    // return response()->json(DB::getQueryLog(), 500);

    foreach($users as &$user) {
      $user->post_process();
    }
    
    return $users;
  }

  public function login(Request $request) 
  {
    $validator = Validator::make($request->all(), [
      'email' => 'required',
      'password' => 'required'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    if ( ! auth()->attempt(['email' => $request->email, 'password' => $request->password])) {
      return response(['message' => 'Incorrect Email/Password combination.'], 401);
    }

    $data = User::where('email', $request->email)
                  ->where('is_enabled', 1)
                  ->first();

    if(empty($data)) {
      return response(['message' => 'User is temporarily suspended. Please contact the administrator.'], 401);
    }

    $user = auth()->user();
    $user->load_summary();

    if($user->expired) {
      return response(['message' => 'Subscription has expired.'], 401);
    }

    $token = $user->createToken('authToken');

    $accessToken = $token->accessToken;
    $user->accessToken = $accessToken;

    return $user;
  }

  public function logout() {
    $user = auth()->user();
    $user->fcm_key = '';
    $user->save();
      
    auth()->user()->token()->revoke();
    return response()->json(true);
  }

  private function _generate_code($length) {
    $code = strtoupper(Str::random($length));
    $valid = ActivationCode::where('code', $code)->first() == null;

    while ( ! $valid) {
      $code = strtoupper(Str::random($length));
      $valid = ActivationCode::where('code', $code)->first() == null;
    }

    return $code;
  }

  private function _generate_forgot_code($length) {
    $code = strtoupper(Str::random($length));
    $valid = ForgotPasswordCode::where('code', $code)->first() == null;

    while ( ! $valid) {
      $code = strtoupper(Str::random($length));
      $valid = ForgotPasswordCode::where('code', $code)->first() == null;
    }

    return $code;
  }

  public function register(Request $request) 
  {
    $validator = Validator::make($request->all(), [
      'client_code' => 'nullable|exists:clients,code',
      'first_name' => 'required|regex:/^[A-Za-z0-9\.\_]+(?:[ -][A-Za-z0-9]+)*$/|min:1|max:50',
      'last_name' => 'required|regex:/^[A-Za-z0-9\.\_]+(?:[ -][A-Za-z0-9]+)*$/|min:1|max:50',
      'email' => 'required|email|unique:users|max:255',
      'password' => 'required|confirmed|min:6|max:50',
      'user_type_id' => 'required|exists:user_types,id'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $paths = [];

    try {
      DB::beginTransaction();

      if( ! empty($request->client_code)) {
        $client_id = Client::where('code', $request->client_code)->first()->id;

        // Check Limit
        $subscription_type = SubscriptionType::where('code', 'FREE')->first();
        $subscription_type->post_process();
    
        $staffs = User
                    ::join('user_types', 'user_types.id', '=', 'users.user_type_id')
                    ->where('users.client_id', $client_id)
                    ->where('user_types.code', 'STAFF')
                    ->get();
    
        $supervisors = User
                    ::join('user_types', 'user_types.id', '=', 'users.user_type_id')
                    ->where('users.client_id', $client_id)
                    ->where('user_types.code', 'SUPERVISOR')
                    ->get();
    
        $user_type = UserType::find($request->user_type_id);
    
        // Check for Staff
        if($user_type->code === 'STAFF') {
          $num_of_users = count($staffs);
          $limit = $subscription_type->get_settings('number_of_staff');
        }
    
        // Check for Supervisor
        if($user_type->code === 'SUPERVISOR') {
          $num_of_users = count($supervisors);
          $limit = $subscription_type->get_settings('number_of_supervisor');
        }
    
        if($num_of_users >= $limit) {
          return response()->json(['message' => 'Organization have reached the limit for number of ' . $user_type->name . ' (' . $limit . ')'], 400);
        }
      }

      $data = new User;
      $data->first_name = $request->first_name;
      $data->last_name = $request->last_name;
      $data->email = $request->email;
      $data->password = \Hash::make($request->password);
      $data->user_type_id = $request->user_type_id;
      $data->is_enabled = 1;
      $data->is_activated = 0;
      $data->created_at = date('Y-m-d H:i:s');
      $data->updated_at = date('Y-m-d H:i:s');
      
      if( ! empty($request->client_code)) {
        $data->client_id = Client::where('code', $request->client_code)->first()->id;
      }
      
      $data->position = 'N/A';
      $data->team_id = null;
      
      $data->save();

      // Create Activation Code
      $code = $this->_generate_code(5);
      $activation_code = new ActivationCode();
      $activation_code->code = $code;
      $activation_code->is_valid = 1;
      $activation_code->user_id = $data->id;
      $activation_code->created_at = date('Y-m-d H:i:s');
      $activation_code->updated_at = date('Y-m-d H:i:s');
      $activation_code->save();

      $activation_code->post_process();

      // Send Email
      Mail::to($data->email)->send(new UserActivation($activation_code));

      $user = User::find($data->id);
      $user->load_summary();

      $token = $user->createToken('authToken');

      $accessToken = $token->accessToken;
      $user->accessToken = $accessToken;

      DB::commit();

      return $user;
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function activate(Request $request, $id) {
    try {
      DB::beginTransaction();

      $model = User::find($id);

      if(empty($model)) {
        return response()->json(['message' => 'Record Not Found'], 404);
      }

      $activation_code = ActivationCode
                          ::where('code', $request->code)
                          ->where('is_valid', 1)
                          ->where('user_id', $id)
                          ->first();

      if(empty($activation_code)) {
        return response()->json(['message' => 'Invalid Code. Please try again.'], 404);
      }

      // Set Subscription to Free
      // Subscription Type
      $free = SubscriptionType::where('code', 'FREE')->first();
      $free->post_process();

      $model->subscription_type_id = $free->id;
      $model->is_activated = 1;
      $model->save();

      $duration = $free->get_settings('expiration');
      $expiration_date = date('Y-m-d H:i:s', strtotime('+' . $duration . ' days', time()));

      // Create Subscription
      $subscription = new UserSubscription();
      $subscription->user_id = $model->id;
      $subscription->subscription_type_id = $free->id;
      $subscription->expiration_date = $expiration_date;
      $subscription->created_at = date('Y-m-d H:i:s');
      $subscription->updated_at = date('Y-m-d H:i:s');
      $subscription->save();

      $user = User::find($id);
      $user->load_summary();

      $token = $user->createToken('authToken');

      $accessToken = $token->accessToken;
      $user->accessToken = $accessToken;
      
      ActivationCode
        ::where('user_id', $id)
        ->update(['is_valid' => 0]);

      DB::commit();

      return $user;
    } catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function resend_code($id) {
    $model = User::find($id);

    if(empty($model)) {
      return response()->json(['message' => 'Record Not Found'], 404);
    }

    // Create Activation Code
    $code = $this->_generate_code(5);
    $activation_code = new ActivationCode();
    $activation_code->code = $code;
    $activation_code->is_valid = 1;
    $activation_code->user_id = $id;
    $activation_code->created_at = date('Y-m-d H:i:s');
    $activation_code->updated_at = date('Y-m-d H:i:s');
    $activation_code->save();

    $activation_code->post_process();

    // Send Email
    Mail::to($activation_code->user->email)->send(new UserActivation($activation_code));

    return response()->json(['message' => 'Resend Success. Please check your email.']);
  }

  public function forgot_password(Request $request) {
    $model = User::where('email', $request->email)->first();

    if(empty($model)) {
      return response()->json(['message' => 'User Not Found'], 404);
    }

    // Create Forgot Password Code
    $code = $this->_generate_forgot_code(5);
    $forgot_code = new ForgotPasswordCode();
    $forgot_code->code = $code;
    $forgot_code->is_valid = 1;
    $forgot_code->user_id = $model->id;
    $forgot_code->created_at = date('Y-m-d H:i:s');
    $forgot_code->updated_at = date('Y-m-d H:i:s');
    $forgot_code->save();

    $forgot_code->post_process();

    // Send Email
    Mail::to($request->email)->send(new ForgotPassword($forgot_code));
    return response()->json(['message' => 'Please check your email for the change password code.']);
  }

  public function change_password(Request $request) {
    $validator = Validator::make($request->all(), [
      'code' => 'required',
      'password' => 'required|confirmed|min:6|max:50',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $forgot_code = ForgotPasswordCode
                        ::where('code', $request->code)
                        ->where('is_valid', 1)
                        ->first();

    if(empty($forgot_code)) {
      return response()->json(['message' => 'Invalid Code. Please try again.'], 404);
    }

    $user = User::find($forgot_code->user_id);
    $user->password = \Hash::make($request->password);
    $user->updated_at = date('Y-m-d H:i:s');
    $user->save();

    ForgotPasswordCode
      ::where('user_id', $forgot_code->user_id)
      ->update(['is_valid' => 0]);

    return response()->json(['message' => 'Password Changed. Please Login Again.']);
  }

  public function create(Request $request) 
  {
    $validator = Validator::make($request->all(), [
      'first_name' => 'required|regex:/^[A-Za-z0-9\.\_]+(?:[ -][A-Za-z0-9]+)*$/|min:1|max:50',
      'last_name' => 'required|regex:/^[A-Za-z0-9\.\_]+(?:[ -][A-Za-z0-9]+)*$/|min:1|max:50',
      'email' => 'required|email|unique:users|max:255',
      'password' => 'required|confirmed|min:6|max:50',
      'user_code' => 'required|exists:user_types,code',
      'picture' => 'sometimes|required|image',
      'is_enabled' => 'required',

      'client_id' => 'sometimes|exists:clients,id',
      'position' => 'sometimes|required|max:50',
      'team_id' => 'sometimes',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $paths = [];

    try {
      DB::beginTransaction();

      $data = new User;
      if( ! empty($request->picture)) {
        $path = $request->file('picture')->store('public/profile_pictures');
        $data->picture = $path;

        $save_path = 'storage/profile_pictures_thumbs';

        if ( ! file_exists($save_path)) {
          mkdir($save_path, 0755, true);
        }

        // Create Thumbnail
        \Image::make($request->file('picture')->getRealPath())->fit(150, 150)->save(($save_path . '/' . basename($path)));
        $thumbnail = str_replace('storage/', 'public/', $save_path) . '/' . basename($path);
        $data->thumbnail = $thumbnail;

        $paths[] = $path;
        $paths[] = $thumbnail;
      }

      $data->first_name = $request->first_name;
      $data->last_name = $request->last_name;
      $data->email = $request->email;
      $data->password = \Hash::make($request->password);
      $data->user_type_id = UserType::where('code', $request->user_code)->first()->id;
      $data->is_enabled = $request->is_enabled;
      $data->created_at = date('Y-m-d H:i:s');
      $data->updated_at = date('Y-m-d H:i:s');
      
      if($request->has('client_id')) {
        $data->client_id = $request->client_id;
        $data->position = $request->position;
      }

      if($request->has('team_id') && ! empty($request->team_id) && $request->team_id != 'null') {
        $data->team_id = $request->team_id;
      } else {
        $data->team_id = null;
      }

      
      $data->save();

      if($request->has('location_address')) {
        for($i = 0; $i < count($request->location_address); $i++) {
          $location = new AssignedLocation;
          $location->user_id = $data->id;
          $location->lat = $request->location_lat[$i];
          $location->lng = $request->location_lng[$i];
          $location->address = $request->location_address[$i];
          $location->landmark = $request->location_landmark[$i];

          $location->created_at = date('Y-m-d H:i:s');
          $location->updated_at = date('Y-m-d H:i:s');
          $location->save();
        }
      }

      $user = User::find($data->id);
      $user->post_process();

      DB::commit();

      return $user;
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function create_with_subscription_type(Request $request) 
  {
    $validator = Validator::make($request->all(), [
      'first_name' => 'required|regex:/^[A-Za-z0-9\.\_]+(?:[ -][A-Za-z0-9]+)*$/|min:1|max:50',
      'last_name' => 'required|regex:/^[A-Za-z0-9\.\_]+(?:[ -][A-Za-z0-9]+)*$/|min:1|max:50',
      'email' => 'required|email|unique:users|max:255',
      'password' => 'required|confirmed|min:6|max:50',
      'user_code' => 'required|exists:user_types,code',
      'picture' => 'sometimes|required|image',
      'is_enabled' => 'required',

      'client_id' => 'sometimes|exists:clients,id',
      'position' => 'sometimes|required|max:50',
      'team_id' => 'sometimes',

      'subscription_type_id' => 'sometimes|exists:subscription_types,id'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $paths = [];

    try {
      DB::beginTransaction();

      $data = new User;
      if( ! empty($request->picture)) {
        $path = $request->file('picture')->store('public/profile_pictures');
        $data->picture = $path;

        $save_path = 'storage/profile_pictures_thumbs';

        if ( ! file_exists($save_path)) {
          mkdir($save_path, 0755, true);
        }

        // Create Thumbnail
        \Image::make($request->file('picture')->getRealPath())->fit(150, 150)->save(($save_path . '/' . basename($path)));
        $thumbnail = str_replace('storage/', 'public/', $save_path) . '/' . basename($path);
        $data->thumbnail = $thumbnail;

        $paths[] = $path;
        $paths[] = $thumbnail;
      }

      $data->first_name = $request->first_name;
      $data->last_name = $request->last_name;
      $data->email = $request->email;
      $data->password = \Hash::make($request->password);
      $data->user_type_id = UserType::where('code', $request->user_code)->first()->id;
      $data->is_enabled = $request->is_enabled;
      $data->created_at = date('Y-m-d H:i:s');
      $data->updated_at = date('Y-m-d H:i:s');
      
      if($request->has('client_id')) {
        $data->client_id = $request->client_id;
        $data->position = $request->position;
      }

      if($request->has('team_id') && ! empty($request->team_id) && $request->team_id != 'null') {
        $data->team_id = $request->team_id;
      } else {
        $data->team_id = null;
      }

      if($request->has('subscription_type_id')) {
        $data->subscription_type_id = $request->subscription_type_id;
      }

      $data->save();

      if($request->has('location_address')) {
        for($i = 0; $i < count($request->location_address); $i++) {
          $location = new AssignedLocation;
          $location->user_id = $data->id;
          $location->lat = $request->location_lat[$i];
          $location->lng = $request->location_lng[$i];
          $location->address = $request->location_address[$i];
          $location->landmark = $request->location_landmark[$i];

          $location->created_at = date('Y-m-d H:i:s');
          $location->updated_at = date('Y-m-d H:i:s');
          $location->save();
        }
      }

      if($request->has('pin_buckets')) {
        for($i = 0; $i < count($request->pin_buckets); $i++) {
          $bucket = new UserPinBucket();
          $bucket->quantity = $request->pin_buckets[$i];
          $bucket->user_id = $data->id;

          $bucket->created_at = date('Y-m-d H:i:s');
          $bucket->updated_at = date('Y-m-d H:i:s');
          $bucket->save();
        }
      }

      $user = User::find($data->id);
      $user->post_process();

      DB::commit();

      return $user;
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }
  
  public function update(Request $request, $id) 
  {
    $validator = Validator::make($request->all(), [
      'first_name'     => 'sometimes|min:1|max:50',
      'last_name'     => 'sometimes|min:1|max:50',
      'password' => 'sometimes|confirmed|min:6|max:50',
      'user_code' => 'sometimes',
      'picture' => 'sometimes|required|image',
      'is_enabled' => 'required',

      'client_id' => 'sometimes|exists:clients,id',
      'position' => 'sometimes|required|max:50',
      'team_id' => 'sometimes',      
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $data = User::where('id', $id)
                  ->where('updated_at', $request->updated_at)
                  ->first();

    if(empty($data)) {
      return response()->json(['message' => 'Record not found. It was either recently updated by another user or deleted. Please try again.'], 400);
    }

    $path = '';
    $old_picture = $data->picture;
    $old_thumbnail = $data->thumbnail;

    $paths = [];

    try {
      DB::beginTransaction();

      if( ! empty($request->picture)) {
        $path = $request->file('picture')->store('public/profile_pictures');
        $data->picture = $path;

        $save_path = 'storage/profile_pictures_thumbs';

        if ( ! file_exists($save_path)) {
          mkdir($save_path, 0755, true);
        }

        // Create Thumbnail
        \Image::make($request->file('picture')->getRealPath())->fit(150, 150)->save(($save_path . '/' . basename($path)));
        $thumbnail = str_replace('storage/', 'public/', $save_path) . '/' . basename($path);
        $data->thumbnail = $thumbnail;

        $paths[] = $path;
        $paths[] = $thumbnail;
      }
      
      if ($request->has('password')) {
        $data->password = \Hash::make($request->password);
      }

      $data->first_name = $request->first_name;
      $data->last_name = $request->last_name;

      if($request->has('user_code')) {
        $data->user_type_id = UserType::where('code', $request->user_code)->first()->id;
      }

      if($request->has('is_enabled')) {
        $data->is_enabled = (int) $request->is_enabled;
      }

      if($request->has('position')) {
        $data->position = $request->position;
      }
      
      $data->updated_at = date('Y-m-d H:i:s');

      if($request->has('client_id')) {
        $data->client_id = $request->client_id;
        $data->position = $request->position;
      }


      if( ! empty($request->team_id) && $request->team_id != 'null') {
        $data->team_id = $request->team_id;
      }

      if($request->has('removed_locations')) {
        AssignedLocation::whereIn('id', $request->removed_locations)->delete();
      }

      if($request->has('location_address')) {
        for($i = 0; $i < count($request->location_address); $i++) {
          $location = new AssignedLocation;
          $location->user_id = $data->id;
          $location->lat = $request->location_lat[$i];
          $location->lng = $request->location_lng[$i];
          $location->address = $request->location_address[$i];
          $location->landmark = $request->location_landmark[$i];

          $location->created_at = date('Y-m-d H:i:s');
          $location->updated_at = date('Y-m-d H:i:s');
          $location->save();
        }
      }

      if(auth()->user()->id == $data->id) {
        $data->last_profile_update = date('Y-m-d H:i:s');
      }

      $data->save();

      $data->post_process();

      // Delete Old Pictures
      if($request->has('picture')) {
        \Storage::delete(
          [
            $old_picture,
            $old_thumbnail
          ]
        );
      }
      
      DB::commit();

      return $data;
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollback();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function update_with_subscription_type(Request $request, $id) 
  {
    $validator = Validator::make($request->all(), [
      'first_name'     => 'sometimes|min:1|max:50',
      'last_name'     => 'sometimes|min:1|max:50',
      'password' => 'sometimes|confirmed|min:6|max:50',
      'user_code' => 'sometimes|exists:user_types,code',
      'picture' => 'sometimes|required|image',
      'is_enabled' => 'required',

      'client_id' => 'sometimes|exists:clients,id',
      'position' => 'sometimes|required|max:50',
      'team_id' => 'sometimes',
      
      'subscription_type_id' => 'sometimes|exists:subscription_types,id'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $data = User::where('id', $id)
                  ->where('updated_at', $request->updated_at)
                  ->first();

    if(empty($data)) {
      return response()->json(['message' => 'Record not found. It was either recently updated by another user or deleted. Please try again.'], 400);
    }

    $path = '';
    $old_picture = $data->picture;
    $old_thumbnail = $data->thumbnail;

    $paths = [];

    try {
      DB::beginTransaction();

      if( ! empty($request->picture)) {
        $path = $request->file('picture')->store('public/profile_pictures');
        $data->picture = $path;

        $save_path = 'storage/profile_pictures_thumbs';

        if ( ! file_exists($save_path)) {
          mkdir($save_path, 0755, true);
        }

        // Create Thumbnail
        \Image::make($request->file('picture')->getRealPath())->fit(150, 150)->save(($save_path . '/' . basename($path)));
        $thumbnail = str_replace('storage/', 'public/', $save_path) . '/' . basename($path);
        $data->thumbnail = $thumbnail;

        $paths[] = $path;
        $paths[] = $thumbnail;
      }
      
      if ($request->has('password')) {
        $data->password = \Hash::make($request->password);
      }

      $data->first_name = $request->first_name;
      $data->last_name = $request->last_name;

      if($request->has('user_code')) {
        $data->user_type_id = UserType::where('code', $request->user_code)->first()->id;
      }

      if($request->has('is_enabled')) {
        $data->is_enabled = (int) $request->is_enabled;
      }

      if($request->has('position')) {
        $data->position = $request->position;
      }
      
      $data->updated_at = date('Y-m-d H:i:s');

      if($request->has('client_id')) {
        $data->client_id = $request->client_id;
        $data->position = $request->position;
      }

      if( ! empty($request->team_id) && $request->team_id != 'null') {
        $data->team_id = $request->team_id;
      }

      if($request->has('subscription_type_id')) {
        $data->subscription_type_id = $request->subscription_type_id;
      }

      if($request->has('removed_locations')) {
        AssignedLocation::whereIn('id', $request->removed_locations)->delete();
      }

      if($request->has('location_address')) {
        for($i = 0; $i < count($request->location_address); $i++) {
          $location = new AssignedLocation;
          $location->user_id = $data->id;
          $location->lat = $request->location_lat[$i];
          $location->lng = $request->location_lng[$i];
          $location->address = $request->location_address[$i];
          $location->landmark = $request->location_landmark[$i];

          $location->created_at = date('Y-m-d H:i:s');
          $location->updated_at = date('Y-m-d H:i:s');
          $location->save();
        }
      }

      if($request->has('removed_pin_buckets')) {
        UserPinBucket::whereIn('id', $request->removed_pin_buckets)->delete();
      }

      if($request->has('pin_buckets')) {
        for($i = 0; $i < count($request->pin_buckets); $i++) {
          $bucket = new UserPinBucket();
          $bucket->quantity = $request->pin_buckets[$i];
          $bucket->user_id = $id;

          $bucket->created_at = date('Y-m-d H:i:s');
          $bucket->updated_at = date('Y-m-d H:i:s');
          $bucket->save();
        }
      }

      if($request->has('subscription_id')) {
        // Create Subscription
        $subscription = new UserSubscription();
        $subscription->user_id = $data->id;
        $subscription->subscription_type_id = $request->subscription_id;
        $subscription->expiration_date = date('Y-m-d', strtotime($request->subscription_expiration)) . date(' H:i:s');
        $subscription->created_at = date('Y-m-d H:i:s');
        $subscription->updated_at = date('Y-m-d H:i:s');
        $subscription->save();

        $data->subscription_type_id = $request->subscription_id;
      }

      if(auth()->user()->id == $data->id) {
        $data->last_profile_update = date('Y-m-d H:i:s');
      }

      $data->save();

      $data->post_process();

      // Delete Old Pictures
      if($request->has('picture')) {
        \Storage::delete(
          [
            $old_picture,
            $old_thumbnail
          ]
        );
      }
      
      DB::commit();

      return $data;
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollback();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }
  
  public function toggle_status(Request $request, $id) 
  {
    $data = User::where('id', $id)
                  ->where('updated_at', $request->updated_at)
                  ->first();

    if(empty($data)) {
      return response()->json(['message' => 'Record not found. It was either recently updated by another user or deleted. Please try again.'], 400);
    }

    try { 
      $data->is_enabled = ! $data->is_enabled;
      $data->updated_at = date('Y-m-d H:i:s');
      $data->save();

      $data->post_process();

      return $data;
    } catch(\Illuminate\Database\QueryException $ex){ 
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function retrieve($id) {
    $model = User::find($id);

    if(empty($model)) {
      return response()->json(['message' => 'Record Not Found'], 404);
    }

    $model->post_process();

    return $model;
  }

  public function user(Request $request) {
    $user = $request->user();
    $user->load_summary();

    return $user;
  }

  public function task_stats($id) {
    $user = User::find($id);

    $user->num_current = Task
                                    ::where('user_id', $id)
                                    ->where('is_approved', 1)
                                    ->where('is_completed', 0)
                                    ->count();

    $user->num_for_approval = Task
                                    ::where('user_id', $id)
                                    ->where('is_approved', 0)
                                    ->count();

    $user->num_completed = Task
                                    ::where('user_id', $id)
                                    ->where('is_completed', 1)
                                    ->count();

    if(empty($user->thumbnail)) {
      $user->thumbnail = 'public/default-picture.png';
    }

    $user->full_name = $user->first_name . ' ' . $user->last_name;

    return $user;
  }

  private function _get_notification($last_update, $id, $initial) {
    DB::enableQueryLog();
    $notifications = Notification
        ::leftJoin('users', 'users.id', '=', 'notifications.sender_id')
        ->join('tasks', 'tasks.id', '=', 'notifications.task_id')
        ->where('notifications.recipient_id', $id);

    if($initial) {
      $notifications = $notifications->where('notifications.is_read', 0);
    } else {
      $notifications = $notifications->where('notifications.id', '>', $last_update);
    }
        
    $notifications = $notifications->selectRaw('
          notifications.*, 
          CONCAT(users.first_name, " ", users.last_name) AS full_name, 
          users.thumbnail AS picture')
        ->orderBy('created_at', 'DESC')
        ->get();


    $new = 0;
    foreach($notifications as &$notif) {
      if(empty($notif->full_name)) {
        $notif->full_name = 'System Notification';
      }

      if(empty($notif->picture)) {
        $notif->picture = 'public/default-picture.png';
      }

      if( ! $notif->is_read) {
        $new++;
      }
    }

    return ['notifications' => $notifications, 'new' => $new];
  }

  public function notifications(Request $request, $id) {
    $user = auth()->user();

    if(empty($user->last_notification_id)) {
      $notif_id = 0;
    } else {
      $notif_id = $user->last_notification_id;
    }
    
    $notifications = $this->_get_notification($notif_id, $id, $request->initial);

    if(count($notifications['notifications']) > 0 && ! $request->initial) {
      $user->last_notification_id = $notifications['notifications'][0]->id;
      $user->save();
    }

    return $notifications;
  }

  public function read_notification(Request $request) {
    Notification::where('id', $request->id)
      ->update(['is_read' => 1]);

    return $request->id;
  }

  public function read_all_notification() {
    Notification::where('recipient_id', auth()->user()->id)
      ->update(['is_read' => 1]);
    
    return response()->json('ok');
  }

  public function update_fcm(Request $request, $id) {
    // User
    //   ::where('fcm_key', $request->fcm_key)
    //   ->update(['fcm_key' => '']);
      
    $user = User::find(auth()->user()->id);
     
    if( ! empty($request->fcm_key)) {
        User
          ::where('id', $user->id)
          ->update(['fcm_key' => $request->fcm_key]);    
    }
    

    return $user;
  }

  public function pin_label($id) {
    $last_location = Location
      ::where('user_id', $id)
      ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") = ?', [date('Y-m-d')])
      ->orderBy('created_at', 'DESC')
      ->first();

    if( ! empty($last_location) && $last_location->type == 'IN') {
      return response()->json(['label' => 'Check-Out']);
    } else {
      return response()->json(['label' => 'Check-In']);
    }
  }

}
