<?php

namespace App\Models;

use App\Models\Base\MasterFileModel;
use App\Models\ModuleAccess;
use App\Models\ClientPinBucket;
use App\Models\UserPinBucket;

class Client extends MasterFileModel
{
  //
  protected $table = 'clients';

  protected $casts = [
    'is_enabled' => 'boolean',
    'total_pin_bucket' => 'integer'
  ];

  protected $fillable = ['code', 'name', 'is_enabled', 'created_at', 'updated_at', 'num_of_pins', 'num_of_users', 'picture'];

  public $files = [
    'picture' => [
      'path' => 'public/client_pictures'
    ]
  ];

  public function getValidators($type = 'C')
  {
    switch ($type) {
      case 'C':
        return [
          'code' => 'required|unique:' . $this->table . '|max:50',
          'name' => 'required|max:255',
          'is_enabled' => 'required|boolean',
          'num_of_pins' => 'nullable|numeric',
          'num_of_users' => 'nullable|numeric',
          'picture' => 'sometimes|image'
        ];

      case 'U':
        return [
          'name' => 'required|max:255',
          'is_enabled' => 'required|boolean',
          'updated_at' => 'required',
          'num_of_pins' => 'nullable|numeric',
          'num_of_users' => 'nullable|numeric',
          'picture' => 'sometimes|image'
        ];
    }
  }

  public function post_process()
  {
    if (empty($this->picture)) {
      $this->picture = 'public/default-thumbnail.jpg';
    }

    if (empty($this->thumbnail)) {
      $this->thumbnail = 'public/default-thumbnail.jpg';
    }

    $this->module_access = ModuleAccess::where('client_id', $this->id)->get();

    $this->module_access_ids = [];
    foreach ($this->module_access as $access) {
      $this->module_access_ids = array_merge($this->module_access_ids, [intval($access->module_id)]);
    }

    $this->pin_buckets = ClientPinBucket::where('client_id', $this->id)->get();
    $this->user_pin_buckets = UserPinBucket::where('client_id', $this->id)->get();

    $this->num_of_users = User::where('client_id', $this->id)->count();

    $this->remaining_pin_buckets = 0;
    $this->total_pin_buckets = 0;
    $this->assigned_pin_buckets = 0;


    foreach($this->pin_buckets as $pin_bucket) {
      $this->total_pin_buckets += $pin_bucket->quantity;
    }

    $this->remaining_pin_buckets = $this->total_pin_buckets;

    foreach($this->user_pin_buckets as $pin_bucket) {
      $this->remaining_pin_buckets -= $pin_bucket->quantity;
      $this->assigned_pin_buckets += $pin_bucket->quantity;
    }
  }

  public function scopeSearch($query, $params)
  {
    if (isset($params['code'])) {
      $query->where('code', 'LIKE', '%' . $params['code'] . '%');
    }

    if (isset($params['code_exact'])) {
      $query->where('code', $params['code_exact']);
    }

    if (isset($params['name'])) {
      $query->orWhere('name', 'LIKE', '%' . $params['name'] . '%');
    }

    if (isset($params['is_enabled']) && $params['is_enabled'] !== 'null') {
      $query->where('is_enabled', (int) $params['is_enabled']);
    }
  }
}
