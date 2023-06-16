<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\CrudController;
use Illuminate\Http\Request;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\IncidentInvolvedParty;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Validator;

class IncidentController extends CrudController
{
  public function __construct() {
    parent::__construct('\App\Models\Incident');
  }

  public function create(Request $request) 
  {
    $validator = Validator::make($request->all(), [
			'remarks' => 'required|max:255',
			'incident_type_id' => 'required|exists:incident_types,id',

			'attachments' => 'required|array',
			'attachments.*' => 'image',
			
			'involved_parties' => 'required|array'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

		$paths = [];
		
    try {
      DB::beginTransaction();

      $data = new Incident;
      $data->user_id = auth()->user()->id;
      $data->remarks = $request->remarks;
      $data->incident_type_id = $request->incident_type_id;
      $data->save();

      if($request->has('attachments')) {
        for($i = 0; $i < count($request->attachments); $i++) {
          $file = $request->attachments[$i];

          $attachment = new IncidentAttachment;
          $attachment->incident_id = $data->id;
          $attachment->original_file_name = $file->getClientOriginalName();
          $attachment->file_name = $file->store('public/site_visits');
          $attachment->created_at = date('Y-m-d H:i:s');
          $attachment->updated_at = date('Y-m-d H:i:s');
          $attachment->save();

          $paths[] = $attachment->file_name;
        }
      }

      if($request->has('involved_parties')) {
        for($i = 0; $i < count($request->involved_parties); $i++) {
          $party = (object) $request->involved_parties[$i];

          $attachment = new IncidentInvolvedParty;
          $attachment->incident_id = $data->id;

          $attachment->name = $party->name;

          $attachment->signature = $party->signature->store('public/incidents/signature');
        
          if(isset($party->license)) {
            $attachment->license_original_name = $party->license->getClientOriginalName();
            $attachment->license_file_name = $party->license->store('public/incidents/license');
            $paths[] = $attachment->license_file_name;
          }

          if(isset($party->insurance)) {
            $attachment->insurance_original_name = $party->insurance->getClientOriginalName();
            $attachment->insurance_file_name = $party->insurance->store('public/incidents/insurance');
            $paths[] = $attachment->insurance_file_name;
          }

          if(isset($party->OR)) {
            $attachment->or_original_name = $party->OR->getClientOriginalName();
            $attachment->or_file_name = $party->OR->store('public/incidents/or');
            $paths[] = $attachment->or_file_name;
          }

          if(isset($party->CR)) {
            $attachment->cr_original_name = $party->CR->getClientOriginalName();
            $attachment->cr_file_name = $party->CR->store('public/incidents/cr');
            $paths[] = $attachment->cr_file_name;
          }

          $attachment->created_at = date('Y-m-d H:i:s');
          $attachment->updated_at = date('Y-m-d H:i:s');
          $attachment->save();
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

  public function staff_incident_reports(Request $request) {
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
      $records = Incident::search($request->all());
    } else {
      $records = Incident::search($request->all())
        ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") BETWEEN ? AND ?', [$last_week, date('Y-m-d')]);
    }

    $records = $records->orderBy('created_at', 'DESC')
                        ->get();

    $dates = [];
    foreach($records as $record) {
      $record->post_process();
      
      $dates[date('Y-m-d', strtotime($record->created_at))][] = $record;
    }

    return $dates;
  }
}
