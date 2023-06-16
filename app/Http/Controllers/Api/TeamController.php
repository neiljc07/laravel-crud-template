<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\CrudController;
use Illuminate\Http\Request;
use Validator;
use DB;
use App\Models\Client;
use App\Models\Team;
use App\Models\UserTeam;
use App\Models\User;
use App\Models\UserType;
use App\Models\Task\Task;
use App\Models\Location;
use App\Models\SubscriptionType;

class TeamController extends CrudController
{
  public function __construct()
  {
    parent::__construct('\App\Models\Team');
  }

  public function index(Request $request)
  {
    $params = $request->all();

    if (isset($request->client_code)) {
      $client = Client::where('code', $request->client_code)->first();
      if ($client) {
        $params['client_id'] = $client->id;
      }

      unset($params['client_code']);
    }

    $models = $this->model::search($params);

    if (isset($request->order_by)) {
      $orders = explode(',', $request->order_by);

      foreach ($orders as $order) {
        $param = explode(':', $order);

        $models->orderBy($param[0], $param[1]);
      }
    }

    if (isset($request->pager)) {
      $models = $models->paginate($request->items_per_page);
    } else {
      $models = $models->get();
    }

    foreach ($models as &$model) {
      $model->post_process();
    }

    return $models;
  }

  public function create(Request $request)
  {
    $validator = Validator::make($request->all(), $this->_model_object->getValidators());

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $paths = [];

    try {
      DB::beginTransaction();

      $data = $request->all();

      $data['client_id'] = Client::where('code', $data['client_code'])->first()->id;
      unset($data['client_code']);

      $data['created_at'] = date('Y-m-d H:i:s');
      $data['updated_at'] = date('Y-m-d H:i:s');

      foreach ($data as $key => $value) {
        if ($value === 'null') {
          $value = null;
        }

        $data[$key] = $value;
      }

      $model = $this->model::create($data);

      $model->post_process();

      DB::commit();

      return response()->json($model);
    } catch (\Illuminate\Database\QueryException $ex) {
      // Delete Uploaded file on error
      if (!empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function create_by_user(Request $request)
  {
    $validator = Validator::make($request->all(), $this->_model_object->getValidators());

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $paths = [];

    try {
      DB::beginTransaction();

      $data = $request->all();

      $data['client_id'] = Client::where('code', $data['client_code'])->first()->id;
      unset($data['client_code']);

      $data['created_at'] = date('Y-m-d H:i:s');
      $data['updated_at'] = date('Y-m-d H:i:s');

      foreach ($data as $key => $value) {
        if ($value === 'null') {
          $value = null;
        }

        $data[$key] = $value;
      }

      $model = $this->model::create($data);
      $model->post_process();

      // Create User Team
      $user = auth()->user();
      $user_team = new UserTeam();
      $user_team->user_id = $user->id;
      $user_team->team_id = $model->id;
      $user_team->created_at = date('Y-m-d H:i:s');
      $user_team->updated_at = date('Y-m-d H:i:s');
      $user_team->save();

      DB::commit();

      return response()->json($model);
    } catch (\Illuminate\Database\QueryException $ex) {
      // Delete Uploaded file on error
      if (!empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function update(Request $request, $id)
  {
    $validator = Validator::make($request->all(), $this->_model_object->getValidators('U'));
    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $old_data = $this->model
      ::where('id', $id)
      ->where('updated_at', $request->updated_at)
      ->first();

    if (empty($old_data)) {
      return response()->json(['message' => 'Record not found. It was either recently updated by another user or deleted. Please try again.'], 400);
    }

    $paths = [];

    try {
      DB::beginTransaction();

      $data = $request->all();

      $data['updated_at'] = date('Y-m-d H:i:s');

      foreach ($this->_model_object->not_updatable as $column) {
        unset($data[$column]);
      }

      foreach ($data as $key => $value) {
        if ($value === 'null') {
          $value = null;
        }

        $old_data->$key = $value;
      }

      $old_data->save();

      DB::commit();

      $old_data->post_process();

      return response()->json($old_data);
    } catch (\Illuminate\Database\QueryException $ex) {
      // Delete Uploaded file on error
      if (!empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();



      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function get_teams_by_user(Request $request)
  {
    // Get Teams of user
    $teams = UserTeam::where('user_id', $request->user_id)->get();

    $ids = [];
    foreach ($teams as $team) {
      $ids[] = $team->team_id;
    }

    $params = $request->all();

    $models = $this->model::search($params);

    $models = $models->whereIn('id', $ids);

    if (isset($request->order_by)) {
      $orders = explode(',', $request->order_by);

      foreach ($orders as $order) {
        $param = explode(':', $order);

        $models->orderBy($param[0], $param[1]);
      }
    }

    if (isset($request->pager)) {
      $models = $models->paginate($request->items_per_page);
    } else {
      $models = $models->get();
    }

    foreach ($models as &$model) {
      $model->post_process();
    }

    return $models;
  }

  public function confirm_add_member(Request $request, $id)
  {
    $manager = auth()->user();

    // Check if user exist
    $user = User::where('email', $request->email)->where('is_activated', 1)->first();

    if (empty($user)) {
      return response()->json(['message' => 'User does not exist'], 404);
    }

    // Check if user is already in your team
    $in_team = UserTeam::where('user_id', $user->id)->where('team_id', $id)->first();
    if (!empty($in_team)) {
      return response()->json(['message' => 'User is already in this team.'], 400);
    }

    // Check if does not belong in your organization
    if ( ! empty($user->client_id) && $user->client_id != $manager->client_id) {
      return response()->json(['message' => 'User does not belong to your organization.'], 400);
    }

    // Check if in an organization
    if (empty($user->client_id)) {
      // Check if reached the limit
      // Check if user type is valid based on subscription
      $subscription_type = SubscriptionType::find($manager->subscription_type_id);

      if(empty($subscription_type)) {
        $subscription_type = SubscriptionType::where('code', 'FREE')->first();
      }

      $subscription_type->post_process();

      $staffs = User
                ::join('user_types', 'user_types.id', '=', 'users.user_type_id')
                ->where('users.client_id', $manager->client_id)
                ->where('user_types.code', 'STAFF')
                ->get();

      $supervisors = User
                ::join('user_types', 'user_types.id', '=', 'users.user_type_id')
                ->where('users.client_id', $manager->client_id)
                ->where('user_types.code', 'SUPERVISOR')
                ->get();

      $user_type = UserType::where('code', $request->user_type_code)->first();

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
        return response()->json(['message' => 'You have reached the limit for number of ' . $user_type->name . ' (' . $limit . ')'], 400);
      }

      return response()->json(['message' => 'User does not belong in an organization. Confirm to also add in your organization.']);
    }

    return response()->json(['message' => 'Confirm to add user to your team']);
  }

  public function add_member(Request $request, $id)
  {
    $manager = auth()->user();

    // Check if user type is valid based on subscription
    // $subscription_type = SubscriptionType::find($manager->subscription_type_id);

    // if(empty($subscription_type)) {
    //   $subscription_type = SubscriptionType::where('code', 'FREE')->first();
    // }

    // $subscription_type->post_process();

    $user_type = UserType::where('code', $request->user_type_code)->first();

    // $staffs = User
    //           ::join('user_types', 'user_types.id', '=', 'users.user_type_id')
    //           ->where('users.client_id', $manager->client_id)
    //           ->where('user_types.code', 'STAFF')
    //           ->get();

    // $supervisors = User
    //           ::join('user_types', 'user_types.id', '=', 'users.user_type_id')
    //           ->where('users.client_id', $manager->client_id)
    //           ->where('user_types.code', 'SUPERVISOR')
    //           ->get();

    // 

    // // Check for Staff
    // if($user_type->code === 'STAFF') {
    //   $num_of_users = count($staffs);
    //   $limit = $subscription_type->get_settings('number_of_staff');
    // }

    // // Check for Supervisor
    // if($user_type->code === 'SUPERVISOR') {
    //   $num_of_users = count($supervisors);
    //   $limit = $subscription_type->get_settings('number_of_supervisor');
    // }

    // if($num_of_users >= $limit) {
    //   return response()->json(['message' => 'You have reached the limit for number of ' . $user_type->name . ' (' . $limit . ')'], 400);
    // }

    $user = User::where('email', $request->email)->where('is_activated', 1)->first();

    $user->client_id = $manager->client_id;
    $user->user_type_id = $user_type->id;
    $user->save();

    $user_team = new UserTeam();
    $user_team->user_id = $user->id;
    $user_team->team_id = $id;
    $user_team->created_at = date('Y-m-d H:i:s');
    $user_team->updated_at = date('Y-m-d H:i:s');
    $user_team->save();

    return response()->json(['message' => 'User Successfully Added.']);
  }

  public function update_member(Request $request, $id, $user_id) 
  {
    $validator = Validator::make($request->all(), [
      'id' => 'required',
      'first_name'     => 'sometimes|min:1|max:50',
      'last_name'     => 'sometimes|min:1|max:50',
      'password' => 'sometimes|confirmed|min:6|max:50',
      'picture' => 'sometimes|required|image',
      'is_enabled' => 'required',
      'position' => 'sometimes|required|max:50',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $data = User::where('id', $user_id)
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

      if($request->has('is_enabled')) {
        $data->is_enabled = (int) $request->is_enabled;
      }
      
      $data->updated_at = date('Y-m-d H:i:s');

      if($request->has('position')) {
        $data->position = $request->position;
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

  public function dashboard(Request $request, $id)
  {
    $staff = UserType::where('code', 'STAFF')->first();
    $manager_type = UserType::where('code', 'MANAGER')->first();
    $supervisor_type = UserType::where('code', 'SUPERVISOR')->first();

    $team_id = $id;

    // Get Manager
    $manager = UserTeam
      ::join('users', 'users.id', '=', 'user_teams.user_id')
      ->join('user_types', 'user_types.id', '=', 'users.user_type_id')
      ->where('user_teams.team_id', $id)
      ->where('user_types.code', 'MANAGER')
      ->where('users.is_enabled', 1)
      ->first();

    $team = Team::find($team_id);

    if ($manager && !$manager->picture) {
      $manager->picture = 'public/default-picture.png';
    }

    // Get Members
    $members = UserTeam
      ::where('user_teams.team_id', $id)
      ->get();

    $member_ids = [];
    foreach ($members as $member) {
      $member_ids[] = $member->user_id;
    }

    $staffs = User::search($request)
      ->whereIn('id', $member_ids)
      ->where('user_type_id', $staff->id)
      ->where('is_enabled', 1)
      ->where('is_activated', 1)
      ->get();

    $supervisors = User::search($request)
      ->whereIn('id', $member_ids)
      ->where('user_type_id', $supervisor_type->id)
      ->where('is_enabled', 1)
      ->where('is_activated', 1)
      ->get();

    $team_members = [];

    foreach ($supervisors as $user) {
      $user->post_process();
      $user->current_tasks = $this->current_tasks($user->id);
      $user->check_in_for_today = $this->check_in_for_today($user->id);
      $user->completed_tasks = $this->completed_tasks_with_date($user->id, $request->date_from, $request->date_to);
      $user->for_verification_tasks = $this->for_verification_tasks($user->id);
      $user->for_approval_tasks = $this->for_approval_tasks($user->id);
      $user->late_tasks = $this->late_tasks_with_date($user->id, $request->date_from, $request->date_to);
      $user->rating = $this->rating($user->id);

      $team_members = array_merge($team_members, [$user]);
    }

    // Get Active Tasks
    foreach ($staffs as $user) {
      $user->post_process();
      $user->current_tasks = $this->current_tasks($user->id);
      $user->check_in_for_today = $this->check_in_for_today($user->id);
      $user->completed_tasks = $this->completed_tasks_with_date($user->id, $request->date_from, $request->date_to);
      $user->for_verification_tasks = $this->for_verification_tasks($user->id);
      $user->for_approval_tasks = $this->for_approval_tasks($user->id);
      $user->late_tasks = $this->late_tasks_with_date($user->id, $request->date_from, $request->date_to);
      $user->rating = $this->rating($user->id);

      $team_members = array_merge($team_members, [$user]);
    }

    return compact('team_members', 'manager', 'team');
  }

  public function get_members(Request $request, $id) {
    $manager_type = UserType::where('code', 'MANAGER')->first();
    $params = $request->all();

    $members = User
                ::search($params)
                ->join('user_teams', 'user_teams.user_id', '=', 'users.id')
                ->where('users.is_activated', 1)
                ->where('users.is_enabled', 1)
                ->where('user_teams.team_id', $id)
                ->select('users.*');

    if(isset($request->order_by)) {
      $orders = explode(',', $request->order_by);

      foreach($orders as $order) {
        $param = explode(':', $order);

        $members->orderBy($param[0], $param[1]);
      }
    }

    if(isset($request->pager)) {
      $members = $members->paginate($request->items_per_page);
    } else {
      $members = $members->get();
    }

    foreach($members as &$member) {
      $member->load_summary();
    }

    return $members;
  }

  public function get_latest_locations(Request $request, $id) {
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
                    ->where('users.is_activated', 1)
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
      // Get Member Ids
      $members = UserTeam::where('team_id', $id)->get();
      $member_ids = [];

      if(count($members) === 0) {
        $locations = [];
        $no_locations = [];
        return compact('locations', 'no_locations');
      }

      foreach($members as $member) {
        $member_ids[] = $member->user_id;
      }

      $locations = Location
                    ::join('users', 'users.id', '=', 'locations.user_id')
                    ->join('clients', 'clients.id', '=', 'users.client_id')
                    ->join('vw_latest_locations', 'vw_latest_locations.id', 'locations.id')
                    ->where('clients.id', $request->client_id)
                    ->whereIn('users.id', $member_ids)
                    ->where('users.is_enabled', 1)
                    ->where('users.is_activated', 1)
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
              user_types.code IN(?, ?) AND `vw_latest_locations`.`id` IS NULL AND `users`.`id` IN (' . implode(',', $member_ids) . ') AND users.is_enabled = ?
      '), [$date, 'STAFF', 'SUPERVISOR', 1]);

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

  private function current_tasks($user_id)
  {
    $result = DB::select(DB::raw('SELECT 
                                    x.id, 
                                    x.name, 
                                    x.description, 
                                    x.target_date, 
                                    x.is_approved, 
                                    x.for_verification,
                                    x.is_completed,
                                    (x.task_complete / (x.task_complete + x.task_incomplete)) * 100 as progress,
                                    x.user_id
                                
                                FROM (SELECT 
                                    a.id, 
                                    a.name, 
                                    a.description, 
                                    a.target_date, 
                                    a.is_approved, 
                                    a.for_verification, 
                                    a.is_completed,
                                    COUNT(b.id) as task_complete,
                                    0 as task_incomplete,
                                    a.user_id
                                FROM tasks a
                                INNER JOIN task_details b ON
                                    b.task_id = a.id
                                    
                                WHERE b.is_completed_by_user = 1 AND
                                    a.is_approved = 1
                                
                                GROUP BY 
                                    a.id, 
                                    a.name, 
                                    a.description, 
                                    a.target_date, 
                                    a.is_approved, 
                                    a.for_verification, 
                                    a.is_completed,
                                    a.user_id
                                    
                                UNION ALL
                                
                                SELECT 
                                    a.id, 
                                    a.name, 
                                    a.description, 
                                    a.target_date, 
                                    a.is_approved, 
                                    a.for_verification, 
                                    a.is_completed,
                                    0 as task_complete,
                                    COUNT(b.id) as task_incomplete,
                                    a.user_id
                                FROM tasks a
                                INNER JOIN task_details b ON
                                    b.task_id = a.id
                                    
                                WHERE b.is_completed_by_user = 0 AND
                                    a.is_approved = 1
                                    
                                GROUP BY 
                                    a.id, 
                                    a.name, 
                                    a.description, 
                                    a.target_date, 
                                    a.is_approved, 
                                    a.for_verification, 
                                    a.is_completed,
                                    a.user_id) as x
                                WHERE x.user_id = ? AND x.is_completed = ?'), [$user_id, 0]);

    return $result;
  }

  private function completed_tasks($user_id)
  {
    return Task::where('user_id', $user_id)
      ->where('is_completed', 1)
      ->get();
  }

  private function completed_tasks_with_date($user_id, $date_from, $date_to)
  {
    return Task::where('user_id', $user_id)
      ->where('is_completed', 1)
      ->whereBetween('start_date', [date('Y-m-d', strtotime($date_from)), date('Y-m-d', strtotime($date_to))])
      ->get();
  }

  private function for_verification_tasks($user_id)
  {
    return Task::where('user_id', $user_id)->where('for_verification', 1)->where('is_completed', 0)->get();
  }

  private function for_approval_tasks($user_id)
  {
    return Task::where('user_id', $user_id)->where('is_approved', 0)->get();
  }

  private function rating($user_id)
  {
    return  Task
      ::join('task_details', 'tasks.id', '=', 'task_details.task_id')
      ->where('tasks.is_completed', 1)
      ->where('task_details.is_completed_by_checker', 1)
      ->where('tasks.user_id', $user_id)
      ->selectRaw('ROUND(SUM(IFNULL(rating, 0)) / COUNT(tasks.id)) as rating')
      ->get()
      ->first()->rating;
  }

  private function late_tasks($user_id)
  {
    return  Task
      ::join('task_details', 'tasks.id', '=', 'task_details.task_id')
      ->whereRaw('task_details.original_target_date < IFNULL(task_details.completion_by_user_date, NOW())')
      ->where('tasks.user_id', $user_id)
      ->where('tasks.is_approved', 1)
      ->selectRaw('MAX(task_details.id), task_details.task_id')
      ->groupBy('task_details.task_id')
      ->get();
  }

  private function late_tasks_with_date($user_id, $date_from, $date_to)
  {
    return  Task
      ::join('task_details', 'tasks.id', '=', 'task_details.task_id')
      ->whereRaw('task_details.original_target_date < IFNULL(task_details.completion_by_user_date, NOW())')
      ->where('tasks.user_id', $user_id)
      ->where('tasks.is_approved', 1)
      ->whereBetween('tasks.start_date', [date('Y-m-d', strtotime($date_from)), date('Y-m-d', strtotime($date_to))])
      ->selectRaw('MAX(task_details.id), task_details.task_id')
      ->groupBy('task_details.task_id')
      ->get();
  }

  private function total_check_ins($user_id)
  {
    return Location::where('user_id', $user_id)->count();
  }

  private function check_in_for_today($user_id)
  {
    return Location::where('user_id', $user_id)->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") = ?', [date('Y-m-d')])->count();
  }
}
