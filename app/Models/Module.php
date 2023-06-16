<?php

namespace App\Models;

use App\Models\Base\MasterFileModel;

class Module extends MasterFileModel
{
    //
  protected $table = 'modules';

  protected $fillable = ['name', 'code', 'pages', 'created_at', 'updated_at'];

  public $files = [];

  public function getValidators($type = 'C') {
    switch($type) {
      case 'C':
        return [
          'name' => 'required|max:255',
          'code' => 'required|unique:' . $this->table . '|max:50',
					'pages' => 'required|max:500',
        ];
        
      case 'U':
        return [
          'name' => 'required|max:255',
					'pages' => 'required|max:500',
          'updated_at' => 'required'
        ];
    }
  }

  public function scopeSearch($query, $params) {
    if(isset($params['code'])) {
      $query->where('code', 'LIKE', '%' . $params['code'] . '%');
    }

    if(isset($params['name'])) {
      $query->orWhere('name', 'LIKE', '%' . $params['name'] . '%');
      $query->orWhere('pages', 'LIKE', '%' . $params['name'] . '%');
    }

  }
}
