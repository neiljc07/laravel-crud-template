<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\CrudController;
use App\Models\Client;
use App\Models\ClientPinBucket;
use App\Models\Location;
use App\Models\User;
use App\Models\UserType;
use App\Models\ModuleAccess;
use App\Models\UserPinBucket;
use Validator;
use DB;
use Carbon\Carbon;

class ClientController extends CrudController
{
  public function __construct() {
    parent::__construct('\App\Models\Client');
  }

  public function retrieveByCode($code) {
    return Client::where('code', $code)->first();
  }

  public function add_modules($modules, $client_id = 0) {
    ModuleAccess::where('client_id', $client_id)->delete();

    foreach(explode(',', $modules) as $module_id) {
      $module_access = new ModuleAccess();
      $module_access->client_id = $client_id;
      $module_access->module_id = $module_id;
      $module_access->created_at = date('Y-m-d H:i:s');
      $module_access->updated_at = date('Y-m-d H:i:s');
      $module_access->save();
    }
  }

  public function create(Request $request) {
    $validator = Validator::make($request->all(), $this->_model_object->getValidators());

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $paths = [];

    try {
      DB::beginTransaction();

      $data = $request->all();

      if($request->has('module_access')) {
        $modules = $data['module_access'];
        unset($data['module_access']);
      }
      
      if( ! empty($request->picture)) {
        $path = $request->file('picture')->store('public/client_pictures');
        $data['picture'] = $path;

        $save_path = 'storage/client_pictures_thumbs';

        if ( ! file_exists($save_path)) {
          mkdir($save_path, 0755, true);
        }

        // Create Thumbnail
        \Image::make($request->file('picture')->getRealPath())->fit(150, 150)->save(($save_path . '/' . basename($path)));
        $thumbnail = str_replace('storage/', 'public/', $save_path) . '/' . basename($path);
        $data['thumbnail'] = $thumbnail;

        $paths[] = $path;
        $paths[] = $thumbnail;
      }

      $data['created_at'] = date('Y-m-d H:i:s');
      $data['updated_at'] = date('Y-m-d H:i:s');

      foreach($data as $key => $value) {
        if($value === 'null') {
          $value = null;
        }

        $data[$key] = $value;
      }

      $model = $this->model::create($data);

      if($request->has('module_access')) {
        $this->add_modules($modules, $model->id);
      }

      $model->post_process();

      DB::commit();

      return response()->json($model);
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function create_by_user(Request $request) {
    $validator = Validator::make($request->all(), $this->_model_object->getValidators());

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $paths = [];

    try {
      DB::beginTransaction();

      $data = $request->all();

      if( ! empty($request->picture)) {
        $path = $request->file('picture')->store('public/client_pictures');
        $data['picture'] = $path;

        $save_path = 'storage/client_pictures_thumbs';

        if ( ! file_exists($save_path)) {
          mkdir($save_path, 0755, true);
        }

        // Create Thumbnail
        \Image::make($request->file('picture')->getRealPath())->fit(150, 150)->save(($save_path . '/' . basename($path)));
        $thumbnail = str_replace('storage/', 'public/', $save_path) . '/' . basename($path);
        $data['thumbnail'] = $thumbnail;

        $paths[] = $path;
        $paths[] = $thumbnail;
      }

      $data['created_at'] = date('Y-m-d H:i:s');
      $data['updated_at'] = date('Y-m-d H:i:s');

      foreach($data as $key => $value) {
        if($value === 'null') {
          $value = null;
        }

        $data[$key] = $value;
      }

      $model = $this->model::create($data);
      $model->post_process();

      // Set Client of id of creator
      $user = auth()->user();
      $user->client_id = $model->id;
      $user->last_profile_update = date('Y-m-d H:i:s');
      $user->user_type_id = UserType::where('code', 'MANAGER')->first()->id;
      $user->save();


      DB::commit();

      return response()->json($model);
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function update(Request $request, $id) {
    $validator = Validator::make($request->all(), $this->_model_object->getValidators('U'));
    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $old_data = $this->model
              ::where('id', $id)
              ->where('updated_at', $request->updated_at)
              ->first();

    if(empty($old_data)) {
      return response()->json(['message' => 'Record not found. It was either recently updated by another user or deleted. Please try again.'], 400);
    }

    $paths = [];

    $old_picture = $old_data->picture;
    $old_thumbnail = $old_data->thumbnail;

    try {
      DB::beginTransaction();

      $data = $request->all();

      if( ! empty($request->picture)) {
        $path = $request->file('picture')->store('public/client_pictures');
        $data['picture'] = $path;

        $save_path = 'storage/client_pictures_thumbs';

        if ( ! file_exists($save_path)) {
          mkdir($save_path, 0755, true);
        }

        // Create Thumbnail
        \Image::make($request->file('picture')->getRealPath())->fit(150, 150)->save(($save_path . '/' . basename($path)));
        $thumbnail = str_replace('storage/', 'public/', $save_path) . '/' . basename($path);
        $data['thumbnail'] = $thumbnail;

        $paths[] = $path;
        $paths[] = $thumbnail;
      }

      if($request->has('module_access')) {
        $modules = $data['module_access'];
        unset($data['module_access']);
      }


      $pin_buckets = [];
      $removed_buckets = [];
      if($request->has('pin_buckets')) {
        $pin_buckets = $data['pin_buckets'];
        unset($data['pin_buckets']);
      }

      if($request->has('removed_pin_buckets')) {
        $removed_buckets = $data['removed_pin_buckets'];
        unset($data['removed_pin_buckets']);
      }

      $data['updated_at'] = date('Y-m-d H:i:s');

      foreach($this->_model_object->not_updatable as $column) {
        unset($data[$column]);
      }

      foreach($data as $key => $value) {
        if($value === 'null') {
          $value = null;
        }

        $old_data->$key = $value;
      }

      $old_data->save();

      // Module Access (removed)
      if($request->has('module_access')) {
        $this->add_modules($modules, $old_data->id);
      }

      // Pin Buckets
      ClientPinBucket::whereIn('id', $removed_buckets)->delete();

      foreach($pin_buckets as $bucket) {
        if(isset($bucket['id'])) {
          $_bucket = ClientPinBucket::find($bucket['id']);
        } else {
          $_bucket = new ClientPinBucket;
          $_bucket->created_at = date('Y-m-d H:i:s');
        }

        $_bucket->client_id = $id;
        $_bucket->updated_at = date('Y-m-d H:i:s');
        $_bucket->quantity = $bucket['quantity'];
        $_bucket->save();
      }

      if($request->has('picture')) {
        \Storage::delete(
          [
            $old_picture,
            $old_thumbnail
          ]
        );
      }

      DB::commit();

      $old_data->post_process();

      return response()->json($old_data);
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();

    

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function check_in(Request $request) {
    $validator = Validator::make($request->all(), [
      'lat'     => 'required',
      'lng'     => 'required',
      'address' => 'required|max:255',
      'remarks' => 'required|max:255',
      'picture' => 'required|image',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $user = auth()->user();

    $user->post_process();

    if($user->out_of_pins) {
      return response()->json(['message' => 'Out of Pins'], 400);
    }

    // Get Last Check In to determine type
    $last_location = Location
      ::where('user_id', $user->id)
      ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") = ?', [date('Y-m-d')])
      ->orderBy('created_at', 'DESC')
      ->first();

    $paths = [];

    try {
      DB::beginTransaction();

      $image = $request->file('picture')->store('public/location_pictures');

      $save_path = 'storage/location_pictures_thumbs';

      if ( ! file_exists($save_path)) {
        mkdir($save_path, 0755, true);
      }

      // Create Thumbnail
      \Image::make($request->file('picture')->getRealPath())->fit(150, 150)->save(($save_path . '/' . basename($image)));
      $thumbnail = str_replace('storage/', 'public/', $save_path) . '/' . basename($image);

      $paths[] = $image;
      $paths[] = $thumbnail;

      $data = $request->all();
      $data['type'] = 'IN';
      $data['user_id'] = $user->id;
      $data['picture'] = $image;
      $data['thumbnail'] = $thumbnail;
      $data['device_type'] = $request->device_type;


      if( ! empty($last_location) && $last_location->type == 'IN') {
        $data['type'] = 'OUT';
      }

      $location = Location::create($data);

      $user = User::find($user->id);

      $user->last_check_in = $location->created_at;
      $user->last_lat = $location->lat;
      $user->last_lng = $location->lng;
      $user->last_address = $location->address;
      $user->save();

      DB::commit();

      $user->post_process();

      // Get Deviation
      $location = $this->get_deviation($location, $last_location);
      $location->full_name = $user->full_name;

      return $location;
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollback();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  private function get_deviation($location, $last_location) {
    $location->deviation = '0 minute';
    if($last_location != null && date('Y-m-d', strtotime($last_location->created_at)) == date('Y-m-d', strtotime($location->created_at))) {
      $deviation = round((strtotime($location->created_at) - strtotime($last_location->created_at)) / 60);

      if($deviation < 60) {
        $location->deviation = $deviation;

        if($deviation == 1) {
          $location->deviation .= ' minute';
        } else {
          $location->deviation .= ' minutes';
        }
      } else if ($deviation >= 60) {
        $location->deviation = number_format($deviation / 60, 1);

        if($deviation == 1) {
          $location->deviation .= ' hour';
        } else {
          $location->deviation .= ' hours';
        }
      }
    }

    return $location;
  }

  public function staff_check_in(Request $request) {
    $user = User::find(auth()->user()->id);
    
    if($request->has('user_id')) {
      $user = User::find($request->user_id);

      if(empty($user)) {
        return response()->json(['message' => 'User Not Found'], 404);
      }
    }

    // Limit to last seven days
    $last_week = Carbon::now()->addDays(-7)->format('Y-m-d');

    if($request->has('date')) {
      $locations = Location
        ::join('users', 'users.id', '=', 'locations.user_id')
        ->where('locations.user_id', $user->id)
        ->whereRaw('DATE_FORMAT(locations.created_at, "%Y-%m-%d") = ?', [date('Y-m-d', strtotime($request->date))])
        ->orderBy('locations.created_at', 'ASC')
        ->selectRaw('locations.*, CONCAT(users.first_name, " ", users.last_name) AS full_name')
        ->get();
    } else {
      $locations = Location
        ::join('users', 'users.id', '=', 'locations.user_id')
        ->where('locations.user_id', $user->id)
        ->whereRaw('DATE_FORMAT(locations.created_at, "%Y-%m-%d") BETWEEN ? AND ?', [$last_week, date('Y-m-d')])
        ->orderBy('locations.created_at', 'ASC')
        ->selectRaw('locations.*, CONCAT(users.first_name, " ", users.last_name) AS full_name')
        ->get();
    }

    // $dates = [];
    $previous = null;
    // $previous_date = null;
    // $previous_time = null;
    foreach($locations as &$location) {
      if(empty($location->thumbnail)) {
        $location->thumbnail = $location->picture;
      }

      $location = $this->get_deviation($location, $previous);

      // $location->deviation = '0 minute';
      
      // if($previous != null && $previous_date == date('Y-m-d', strtotime($location->created_at))) {
      //   $deviation = round((strtotime($location->created_at) - strtotime($previous_time)) / 60);

      //   if($deviation < 60) {
      //     $location->deviation = $deviation;

      //     if($deviation == 1) {
      //       $location->deviation .= ' minute';
      //     } else {
      //       $location->deviation .= ' minutes';
      //     }
      //   } else if ($deviation >= 60) {
      //     $location->deviation = number_format($deviation / 60, 1);

      //     if($deviation == 1) {
      //       $location->deviation .= ' hour';
      //     } else {
      //       $location->deviation .= ' hours';
      //     }
      //   }
      // }

      // $dates[date('Y-m-d', strtotime($location->created_at))][] = $location;

      $previous = $location;
      //$previous_time = $location->created_at;
      //$previous_date = date('Y-m-d', strtotime($location->created_at));
    }

    return $locations;
  }

  public function staff_check_in_single($id) {
    $location = Location::find($id);

    if(empty($location)) {
      return response()->json(['message' => 'Record Not Found'], 404);
    }

    return $location;
  }

  public function latest_locations(Request $request) {
    $date = date('Y-m-d');

    if($request->has('date')) {
      $date = $request->date;
    }
      
    if($request->has('user_id')) {
      $no_locations = [];

      $locations = Location
                    ::join('users', 'users.id', '=', 'locations.user_id')
                    ->where('user_id', $request->user_id)
                    ->where('users.is_enabled', 1)
                    ->whereRaw('DATE_FORMAT(locations.created_at, "%Y-%m-%d") = ?', [$date])
                    ->selectRaw('
                      locations.*, users.picture AS profile_picture,
                      "neutral" AS status,
                      CONCAT(users.first_name, " ", users.last_name) AS full_name
                    ')
                    ->orderBy('locations.created_at', 'ASC')
                    ->get();

      $previous = null;
      $previous_date = null;
      $previous_time = null;
      foreach($locations as &$location) {
        $location->deviation = '0 minute';

        if($previous != null && $previous_date == date('Y-m-d', strtotime($location->created_at))) {
          $deviation = round((strtotime($location->created_at) - strtotime($previous_time)) / 60);

          if($deviation < 60) {
            $location->deviation = $deviation;

            if($deviation == 1) {
              $location->deviation .= ' minute';
            } else {
              $location->deviation .= ' minutes';
            }
          } else if ($deviation >= 60) {
            $location->deviation = number_format($deviation / 60, 1);

            if($deviation == 1) {
              $location->deviation .= ' hour';
            } else {
              $location->deviation .= ' hours';
            }
          }
        }

        if( ! $location->profile_picture) {
          $location->profile_picture = 'public/default-picture.png';
        }

        $previous = $location;
        $previous_time = $location->created_at;
        $previous_date = date('Y-m-d', strtotime($location->created_at));
      }
    } else {
      $locations = Location
                    ::join('users', 'users.id', '=', 'locations.user_id')
                    ->join('clients', 'clients.id', '=', 'users.client_id')
                    ->join('vw_latest_locations', 'vw_latest_locations.id', 'locations.id')
                    ->where('clients.id', $request->client_id)
                    ->where('users.team_id', $request->team_id)
                    ->where('users.is_enabled', 1)
                    ->whereRaw('DATE_FORMAT(vw_latest_locations.date, "%Y-%m-%d") = ?', [$date])
                    ->selectRaw('
                      locations.*, users.picture AS profile_picture, CONCAT(users.first_name, " ", users.last_name) AS full_name,
                      "active" AS status
                    ')
                    ->orderBy('locations.created_at', 'ASC')
                    ->get();
                    
      $no_locations = DB::select(DB::raw('
          SELECT
              users.*,
              CONCAT(
                  users.first_name,
                  " ",
                  users.last_name
              ) AS full_name
          FROM
              `users`
          LEFT JOIN `vw_latest_locations` ON `vw_latest_locations`.`user_id` = `users`.`id` and DATE_FORMAT(
                  vw_latest_locations.date,
                  "%Y-%m-%d"
              ) = ?
          LEFT JOIN `user_types` ON `user_types`.`id` = `users`.`user_type_id`
          WHERE
              `users`.`client_id` = ? AND (`user_types`.`code` = ? OR `user_types`.`code` = ?) AND `vw_latest_locations`.`id` IS NULL AND `users`.`team_id` = ? AND users.is_enabled = ?
      '), [$date, $request->client_id, 'STAFF', 'SUPERVISOR', $request->team_id, 1]);

      foreach($no_locations as &$users) {
        if( ! $users->picture) {
          $users->picture = 'public/default-picture.png';
        }
      }

      foreach($locations as $location) {
        if( ! $location->profile_picture) {
          $location->profile_picture = 'public/default-picture.png';
        }
      }
    }

    return compact('locations', 'no_locations');
  }

  public function assign_pin_bucket(Request $request, $id) {
    $client = Client::find($id);
    $client->post_process();

    // Check if total is equal to submitted;
    $buckets = $request->all();
    $total = 0;

    foreach($buckets as $bucket) {
      if( ! isset($bucket['id'])) {
        $total += $bucket['quantity'];
      }
    }

    if($client->remaining_pin_buckets == 0) {
      return response()->json(['message' => 'You have spent all your buckets. Contact the support to purchase more.'], 400);
    }

    if($total > $client->remaining_pin_buckets) {
      return response()->json(['message' => 'Assigned buckets (' . $total . ') exceeds the remaining buckets (' . $client->remaining_pin_buckets . ')'], 400);
    }

    foreach($buckets as $bucket) {
      if(isset($bucket['id'])) {
        continue;
      }
      
      $bucket = (object) $bucket;
      $user_bucket = new UserPinBucket;
      $user_bucket->user_id = $bucket->user_id;
      $user_bucket->quantity = $bucket->quantity;
      $user_bucket->client_id = $id;
      $user_bucket->created_at = date('Y-m-d H:i:s');
      $user_bucket->updated_at = date('Y-m-d H:i:s');
      $user_bucket->save();
    }

    $client->post_process();

    return $client;
  }
}

