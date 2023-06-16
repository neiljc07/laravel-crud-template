<?php

namespace App\Models;

use App\Models\Base\BaseModel;
use App\Models\IncidentType;
use App\Models\IncidentInvolvedParty;
use App\Models\IncidentAttachment;

class Incident extends BaseModel
{
    public function post_process() {
			$this->incident_type = IncidentType::find($this->incident_type_id);
			
			$this->involved_parties = IncidentInvolvedParty::where('incident_id', $this->id)->get();
			$this->attachments = IncidentAttachment::where('incident_id', $this->id)->get();
    }

    public function scopeSearch($query, $params) {
			if(isset($params['search'])) {
				$query->where('remarks', 'LIKE', '%' . $params['search'] . '%');
			}
	
			if(isset($params['date'])) {
				$query->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") = ?', date('Y-m-d', strtotime($params['date'])));
			}

			if(isset($params['type_id']) && ! empty($params['type_id'])) {
				$query->where('incident_type_id', $params['type_id']);
			}
	
			if(isset($params['user_id'])) {
				$query->where('user_id', $params['user_id']);
			}
		}
}
