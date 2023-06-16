<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;
use App\Models\Base\BaseModel;

class MasterFileModel extends BaseModel
{
    public function scopeSearch($query, $params) {
      if(isset($params['code'])) {
        $query->where('code', 'LIKE', '%' . $params['code'] . '%');
      }

      if(isset($params['name'])) {
        $query->orWhere('name', 'LIKE', '%' . $params['name'] . '%');
      }

      if(isset($params['is_enabled']) && $params['is_enabled'] !== 'null') {
        $query->where('is_enabled', (int) $params['is_enabled']);
      }
    }
    
}
