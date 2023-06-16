<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\CrudController;
use Illuminate\Http\Request;
use App\Models\SiteVisit;
use App\Models\SiteVisitAttachment;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Validator;

class SiteVisitController extends CrudController
{
	public function __construct() {
		parent::__construct('\App\Models\SiteVisit');
  }
  
	public function create(Request $request) 
  {
    $validator = Validator::make($request->all(), [
			'location' => 'required|max:255',
			'contact_person' => 'required|max:255',
			'project_name' => 'required|max:255',

			'attachments' => 'required|array',
      'attachments.*' => 'image'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $paths = [];

    try {
      DB::beginTransaction();

      $data = new SiteVisit;
      $data->user_id = auth()->user()->id;
      $data->location = $request->location;
      $data->contact_person = $request->contact_person;
      $data->project_name = $request->project_name;
      $data->created_at = date('Y-m-d H:i:s');
      $data->updated_at = date('Y-m-d H:i:s');
      $data->save();

      if($request->has('attachments')) {
        for($i = 0; $i < count($request->attachments); $i++) {
          $file = $request->attachments[$i];

          $attachment = new SiteVisitAttachment;
          $attachment->site_visit_id = $data->id;
          $attachment->original_file_name = $file->getClientOriginalName();
          $attachment->file_name = $file->store('public/site_visits');
          $attachment->created_at = date('Y-m-d H:i:s');
          $attachment->updated_at = date('Y-m-d H:i:s');
          $attachment->save();

          $paths[] = $attachment->file_name;
        }
      }

      $data->post_process();

      DB::commit();

      return $data;
    } catch(\Illuminate\Database\QueryException $ex){ 
      // Delete Uploaded file on error
      if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      DB::rollBack();

      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
  }

  public function staff_visits(Request $request) {
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
      $records = SiteVisit
        ::where('user_id', $user->id)
        ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") = ?', [date('Y-m-d', strtotime($request->date))])
        ->orderBy('created_at', 'DESC')
        ->get();
    } else {
      $records = SiteVisit
        ::where('user_id', $user->id)
        ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") BETWEEN ? AND ?', [$last_week, date('Y-m-d')])
        ->orderBy('created_at', 'DESC')
        ->get();
    }

    $dates = [];
    foreach($records as $record) {
      $record->post_process();
      
      $dates[date('Y-m-d', strtotime($record->created_at))][] = $record;
    }

    return $dates;
  }
}
