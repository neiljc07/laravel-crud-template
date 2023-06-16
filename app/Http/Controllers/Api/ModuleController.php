<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\CrudController;

class ModuleController extends CrudController
{
  public function __construct() {
    parent::__construct('\App\Models\Module');
  }
}
