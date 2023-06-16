<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Models\Client;
use App\Models\Location;
use App\Models\ModuleAccess;
use App\Models\AssignedLocation;
use App\Models\Task\Task;
use App\Models\Task\TaskDetail;
use App\Models\Team;
use App\Models\SubscriptionType;

class User extends Authenticatable
{
  use HasApiTokens, Notifiable;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'email', 'password',
  ];

  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = [
    'password', 'remember_token', 'email_verified_at'
  ];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'email_verified_at' => 'datetime',
    'user_type_id' => 'integer',
    'is_enabled' => 'boolean',
    'team_id' => 'integer',
    'last_notification_id' => 'integer',
    'is_activated' => 'boolean',
    'no_org' => 'boolean',
    'will_expire' => 'boolean',
    'expired' => 'boolean',
    'expiration_date' => 'string',
    'subscription_type_id' => 'integer',
  ];

  private function get_picture($picture)
  {
    if (empty($picture)) {
      return 'public/default-picture.png';
    } else {
      return $picture;
    }
  }

  private function get_thumbnail($picture)
  {
    if (empty($picture)) {
      return 'public/default-picture.png';
    } else {
      return $picture;
    }
  }

  public function load_summary()
  {
    // User Type
    $this->user_type = \DB::table('user_types')->where('id', $this->user_type_id)->first();

    $this->is_admin = $this->user_type->code == 'ADMIN';
    $this->is_manager = $this->user_type->code == 'MANAGER';
    $this->is_staff = $this->user_type->code == 'STAFF';
    $this->is_managing_director = $this->user_type->code == 'MD';
    $this->is_supervisor = $this->user_type->code == 'SUPERVISOR';

    $this->can_approve = $this->is_manager || $this->is_supervisor;

    // Pictures
    $this->picture = $this->get_picture($this->picture);
    $this->thumbnail = $this->get_thumbnail($this->thumbnail);

    $this->full_name = $this->first_name . ' ' . $this->last_name;

    $this->client = Client::find($this->client_id);
    $this->no_org = empty($this->client);
    if (empty($this->client)) {
      $this->client = [
        'code' => '',
        'id' => 0,
        'name' => 'N/A'
      ];
    }

    $this->subscription_type = SubscriptionType::where('id', $this->subscription_type_id)->first();
    if ($this->subscription_type) {
      $this->subscription_type->post_process();
    } else {
      $this->subscription_type = SubscriptionType::where('code', 'FREE')->first();
      $this->subscription_type->post_process();
    }

    $this->has_pin = false;
    $this->has_incident_report = false;
    $this->has_task_list = false;
    $this->has_site_visit = false;

    $this->pin_limit = 0;

    foreach ($this->subscription_type->settings as $setting) {
      if ($setting->code === 'pin_module' && $setting->value == 1) {
        $this->has_pin = true;
      }

      if ($setting->code === 'task_module' && $setting->value == 1) {
        $this->has_task_list = true;
      }

      if ($setting->code === 'number_of_pins') {
        $this->pin_limit = intval($setting->value);
      }
    }

    // Check for buckets
    $buckets = UserPinBucket::where('user_id', $this->id)->get();
    foreach($buckets as $bucket) {
      $this->pin_limit += $bucket->quantity;
    }

    $this->out_of_pins = false;
    $this->check_ins_for_today = Location
      ::where('user_id', $this->id)
      ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") = ?', [date('Y-m-d')])
      ->count();

    $this->remaining_pins = $this->pin_limit - $this->check_ins_for_today;

    if ($this->pin_limit < 0) {
      $this->out_of_pins = false;
    } else {
      $this->out_of_pins = $this->check_ins_for_today >= $this->pin_limit;
    }

    $this->subscription_type = SubscriptionType::where('id', $this->subscription_type_id)->first();
    if ($this->subscription_type) {
      $this->subscription_type->post_process();
    } else {
      $this->subscription_type = SubscriptionType::where('CODE', 'FREE')->first();
      $this->subscription_type_id = $this->subscription_type->id;
      $this->subscription_type->post_process();
    }

    // Get Expiration
    $subscription = UserSubscription::where('user_id', $this->id)->orderBy('id', 'DESC')->first();

    if( ! empty($subscription)) {
      $this->expired = time() > strtotime($subscription->expiration_date);

      $date1 = date_create($subscription->expiration_date);
      $date2 = date_create(date('Y-m-d H:i:s'));
      $diff = date_diff($date1, $date2);
      
      // Notify week
      $this->will_expire = $diff->d <= 7;
      $this->expiration_date = date('F d, Y h:i a', strtotime($subscription->expiration_date));
    }
  }

  public function post_process()
  {
    $this->load_summary();

    $last_location = Location
      ::where('user_id', $this->id)
      ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") = ?', [date('Y-m-d')])
      ->orderBy('created_at', 'DESC')
      ->first();

    $this->pin_label = 'Check In';

    if (!empty($last_location) && $last_location->type == 'IN') {
      $this->pin_label = 'Check Out';
    }

    if (empty($this->last_address)) {
      $this->last_address = 'N/A';
      $this->last_check_in = 'N/A';
    }

    $this->client = Client::find($this->client_id);

    if (empty($this->client)) {
      $this->client = [
        'id' => 0,
        'name' => 'N/A'
      ];
    }

    if (empty($this->position)) {
      $this->position = 'N/A';
    }

    $this->assigned_locations = AssignedLocation::where('user_id', $this->id)->get();

    // Current Tasks
    $this->num_current_tasks = Task
      ::where('user_id', $this->id)
      ->where('is_approved', 1)
      ->where('is_completed', 0)
      ->count();

    $this->current_tasks_preview = Task
      ::where('user_id', $this->id)
      ->where('is_approved', 1)
      ->where('is_completed', 0)
      ->selectRaw('*, NOW() > target_date AS is_late')
      ->limit(5)
      ->get();

    $this->num_for_approval = Task
      ::where('user_id', $this->id)
      ->where('is_approved', 0)
      ->count();


    // Get Teams
    $teams = UserTeam::where('user_id', $this->id)->get();

    $ids = [];
    foreach ($teams as $team) {
      $ids[] = $team->team_id;
    }
    
    $this->teams = Team::whereIn('id', $ids)->get();
    foreach ($this->teams as &$model) {
      $model->post_process();
    }

    $this->pin_buckets = UserPinBucket::where('user_id', $this->id)->get();
    $this->subscriptions = UserSubscription::where('user_id', $this->id)->get();
  }

  public function scopeSearch($query, $params)
  {

    if (isset($params['name'])) {
      $query->where(function ($query) use ($params) {
        $query->where('first_name', 'LIKE', '%' . $params['name'] . '%');
        $query->orWhere('last_name', 'LIKE', '%' . $params['name'] . '%');
        $query->orWhere('email', 'LIKE', '%' . $params['name'] . '%');
      });
    }

    if (isset($params['email'])) {
      $query->where('email', $params['email']);
    }

    if (isset($params['user_type_id'])) {
      $query->where('user_type_id', $params['user_type_id']);
    }

    if (isset($params['is_enabled'])) {
      $query->where('is_enabled', $params['is_enabled']);
    }

    if (isset($params['client_id'])) {
      $query->where('client_id', $params['client_id']);
    }

    if (isset($params['team_id']) && $params['team_id'] != 'null') {
      $query->where('team_id', $params['team_id']);
    }
  }
}
