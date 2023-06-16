<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\CrudController;

class IncidentTypeController extends CrudController
{
  public function __construct() {
    parent::__construct('\App\Models\IncidentType');
  }
}
