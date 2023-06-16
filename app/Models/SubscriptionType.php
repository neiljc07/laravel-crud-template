<?php

namespace App\Models;

use App\Models\Base\MasterFileModel;
use App\Models\SubscriptionTypeSetting;

class SubscriptionType extends MasterFileModel
{
  protected $table = 'subscription_types';

  private $_settings = [];

  public function getValidators($type = 'C') {
    switch($type) {
      case 'C':
        return [
          'name' => 'required|max:255',
          'code' => 'required|unique:' . $this->table . '|max:50',
					'settings' => 'required|array',
        ];
        
      case 'U':
        return [
          'name' => 'required|max:255',
					'settings' => 'required|array',
          'updated_at' => 'required'
        ];
    }
  }

  public function post_process()
  {
    $this->settings = SubscriptionTypeSetting::where('subscription_type_id', $this->id)->get();

    foreach($this->settings as $setting) {
      $this->_settings[$setting->code] = $setting->value;
    }
  }

  public function get_settings($code) {
    if ( ! isset($this->_settings[$code])) {
      return null;
    }

    return $this->_settings[$code];
  }

}
