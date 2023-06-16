<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
  protected $fillable = ['code', 'name', 'is_enabled', 'created_at', 'updated_at'];

  public $not_updatable = ['id', 'code', 'created_at'];

  public $files = [];

  public function getValidators($type = 'C') {
    switch($type) {
      case 'C':
        return [
          'code' => 'required|unique:' . $this->table . '|max:50',
          'name' => 'required|max:255',
          'is_enabled' => 'required|boolean'
        ];
        
      case 'U':
        return [
          'name' => 'required|max:255',
          'is_enabled' => 'required|boolean',
          'updated_at' => 'required'
        ];
    }
  }

  public function withFiles() {
    return count($this->files) > 0;
  }

  public function getFiles() {
    $paths = [];
    foreach($this->files as $k => $v) {
      if( ! empty($this->$k)) {
        $paths[] = $this->$k;
      }
    }

    return $paths;
  }

  protected $casts = [
    'is_enabled' => 'boolean',
  ];

  public function post_process() {
    
  }

  public function scopeSearch($query, $params) {
    
  }
}
