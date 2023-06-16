<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SiteVisitAttachment;

class SiteVisit extends Model
{

  public function post_process() {
    $this->attachments = SiteVisitAttachment::where('site_visit_id', $this->id)->get();
  }

  public function scopeSearch($query, $params) {
    if(isset($params['search'])) {
      $query->where('location', 'LIKE', '%' . $params['search'] . '%');
      $query->orWhere('contact_person', 'LIKE', '%' . $params['search'] . '%');
      $query->orWhere('project_name', 'LIKE', '%' . $params['search'] . '%');
    }

    if(isset($params['date'])) {
      $query->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") = ?', date('Y-m-d', strtotime($params['date'])));
    }

    if(isset($params['user_id'])) {
      $query->where('user_id', $params['user_id']);
    }
  }
}
