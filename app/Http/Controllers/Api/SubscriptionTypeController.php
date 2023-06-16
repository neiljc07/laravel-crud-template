<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\CrudController;
use App\Models\SubscriptionTypeSetting;
use Illuminate\Http\Request;
use Validator;
use DB;

class SubscriptionTypeController extends CrudController
{
  public function __construct() {
    parent::__construct('\App\Models\SubscriptionType');
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

      $settings = $data['settings'];
      unset($data['settings']);

      $data['created_at'] = date('Y-m-d H:i:s');
      $data['updated_at'] = date('Y-m-d H:i:s');

      foreach($data as $key => $value) {
        if($value === 'null') {
          $value = null;
        }

        $data[$key] = $value;
      }

      $model = $this->model::create($data);

      $this->add_settings($settings, $model->id);

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

    try {
      DB::beginTransaction();

      $data = $request->all();

      $settings = $data['settings'];
      unset($data['settings']);

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

      $this->add_settings($settings, $old_data->id);

      DB::commit();

      $old_data->post_process();

      return response()->json($old_data);
    } catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function add_settings($settings, $id = 0) {
    SubscriptionTypeSetting::where('subscription_type_id', $id)->delete();

    foreach($settings as $setting) {
      $s = new SubscriptionTypeSetting;
      $s->subscription_type_id = $id;
      $s->code = $setting['code'];
      $s->value = $setting['value'];
      $s->created_at = date('Y-m-d H:i:s');
      $s->updated_at = date('Y-m-d H:i:s');
      $s->save();
    }
  }
}
