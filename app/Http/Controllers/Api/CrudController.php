<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use DB;

class CrudController extends Controller
{
  protected $table;
  protected $model;
  protected $_model_object;

  public function __construct($model) 
  {
    $this->model = $model;
    $this->_model_object = new $this->model();
    $this->table = $this->_model_object->getTable();
  }

  public function index(Request $request)
  {
    $models = $this->model::search($request->all());

    if(isset($request->order_by)) {
      $orders = explode(',', $request->order_by);

      foreach($orders as $order) {
        $param = explode(':', $order);

        $models->orderBy($param[0], $param[1]);
      }
    }

    if(isset($request->pager)) {
      $models = $models->paginate($request->items_per_page);
    } else {
      $models = $models->get();
    }

    foreach($models as &$model) {
      $model->post_process();
    }

    return $models;
  }

  public function retrieve($id) {
    $model = $this->model::find($id);

    if(empty($model)) {
      return response()->json(['message' => 'Record Not Found'], 404);
    }

    $model->post_process();
    return $model;
  }

  protected function process_uploads(Request $request, &$data, &$paths) {
    foreach ($this->_model_object->files as $key => $value) {
      if($request->has($key)) {
        if(isset($value['name_format'])) {
          $name = $value['name_format'];

          foreach($request->all() as $k => $v) {
            $name = str_replace('{' . $k . '}', $v, $name);
          }

          $name .= '.' . $request->$key->getClientOriginalExtension();

          $data[$key] = $request->file($key)->storeAs($value['path'], $name);
        } else {
          $data[$key] = $request->file($key)->store($value['path']);
        }

        $paths[] = $data[$key];
      }
    }

    return $data;
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

      if($this->_model_object->withFiles()) {
        $this->process_uploads($request, $data, $paths);
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

    $old_files = $old_data->getFiles();
    $paths = [];

    try {
      DB::beginTransaction();

      $data = $request->all();

      if($this->_model_object->withFiles()) {
        $this->process_uploads($request, $data, $paths);
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

      if( ! empty($old_files) && ! empty($paths)) {
        \Storage::delete($old_files);
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

  public function delete(Request $request, $id) {
    $data = $this->model
              ::where('id', $id)
              ->where('updated_at', $request->updated_at)
              ->first();

    if(empty($data)) {
      return response()->json(['message' => 'Record not found. It was either recently updated by another user or deleted. Please try again.'], 400);
    }

    $data->delete();
        
    return response()->json(['success' => 'success'], 200);
  }

  public function toggle_status(Request $request, $id) 
  {
    $data = $this->model::where('id', $id)
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
}
