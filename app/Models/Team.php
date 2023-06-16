<?php

namespace App\Models;

use App\Models\Base\MasterFileModel;
use App\Models\Client;

class Team extends MasterFileModel
{
	protected $table = 'teams';

	protected $fillable = ['code', 'name', 'client_id', 'is_enabled', 'created_at', 'updated_at'];

	public $not_updatable = ['id', 'code', 'created_at', 'client_id', 'client_code'];

	public $files = [];

	public function getValidators($type = 'C') {
		switch($type) {
			case 'C':
				return [
					'code' => 'required|max:50',
					'name' => 'required|max:255',
					'is_enabled' => 'required|boolean',
					'client_code' => 'required|exists:clients,code'
				];
					
			case 'U':
				return [
					'name' => 'required|max:255',
					'is_enabled' => 'required|boolean',
					'updated_at' => 'required',
					'client_code' => 'required|exists:clients,code'
				];
		}
	}


	public function scopeSearch($query, $params) {
		parent::scopeSearch($query, $params);

		if(isset($params['client_id'])) {
			$query->where('client_id', $params['client_id']);
		}
	}

	public function post_process() {
		$this->client = Client::find($this->client_id);
		$this->client_code = $this->client->code;

		$this->num_of_members = UserTeam::where('team_id', $this->id)->count();
	}
}
