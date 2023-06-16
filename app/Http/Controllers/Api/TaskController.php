<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\CrudController;
use Illuminate\Http\Request;
use App\Models\Task\Task;
use App\Models\Task\TaskDetail;
use App\Models\Task\TaskTemplate;
use App\Models\Task\TaskTemplateDetail;
use App\Models\Task\TaskComment;
use App\Models\Task\TaskAttachment;
use App\Models\Task\TaskDetailHistory;
use App\Models\User;
use App\Models\UserType;
use App\Models\Notification;
use Validator;
use DB;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;

class TaskController extends CrudController
{

	public function __construct()
	{
		parent::__construct('App\Models\Task\Task');
	}

	public function index(Request $request) {
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

	// v1
	public function create(Request $request) {
		$validator = Validator::make($request->all(), [
			'name'     		=> 'required|max:255',
			'description' => 'required|max:255',
			'start_date'  => 'required|date',
			'target_date'	=> 'required|date',
			'tasks'				=> 'required|array'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
		}
		
		try {
			DB::beginTransaction();

			$params = $request->all();
			$params['user_id'] = auth()->user()->id;

			$tasks = $params['tasks'];
			unset($params['tasks']);

			$params['start_date'] = date('Y-m-d 00:00:00', strtotime($params['start_date']));
			$params['target_date'] = date('Y-m-d 23:59:59', strtotime($params['target_date']));
			$params['created_at'] = date('Y-m-d H:i:s');
			$params['updated_at'] = date('Y-m-d H:i:s');

			$model = Task::create($params);

			foreach($tasks as $task) {
				$task = (object) $task;
				$detail = new TaskDetail();
				$detail->task_id = $model->id;
				$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
				$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));
				$detail->original_target_date = $detail->target_date;
				$detail->task = $task->task;

				$detail->created_at = date('Y-m-d H:i:s');
				$detail->updated_at = date('Y-m-d H:i:s');

				$detail->save();
			}

			$model = Task::find($model->id);
			$model->post_process();

			DB::commit();

			return $model;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
		
	}

	// v2 with notif
	public function create_with_notif(Request $request) {
		$validator = Validator::make($request->all(), [
			'name'     		=> 'required|max:255',
			'description' => 'required|max:255',
			'start_date'  => 'required|date',
			'target_date'	=> 'required|date',
			'tasks'				=> 'required|array'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
		}
		
		try {
			DB::beginTransaction();

			$params = $request->all();
			$params['user_id'] = auth()->user()->id;

			$tasks = $params['tasks'];
			unset($params['tasks']);

			$params['start_date'] = date('Y-m-d 00:00:00', strtotime($params['start_date']));
			$params['target_date'] = date('Y-m-d 23:59:59', strtotime($params['target_date']));
			$params['created_at'] = date('Y-m-d H:i:s');
			$params['updated_at'] = date('Y-m-d H:i:s');

			$model = Task::create($params);

			foreach($tasks as $task) {
				$task = (object) $task;
				$detail = new TaskDetail();
				$detail->task_id = $model->id;
				$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
				$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));
				$detail->original_target_date = $detail->target_date;
				$detail->task = $task->task;

				if($task->reference_no == 'null') {
					$task->reference_no = null;
				}

				$detail->reference_no = $task->reference_no;

				$detail->created_at = date('Y-m-d H:i:s');
				$detail->updated_at = date('Y-m-d H:i:s');

				$detail->save();
			}

			$model = Task::find($model->id);
			$model->post_process();

			// Add Notification
			// Find Manager/Supervisor
			$current_user = auth()->user();
			$current_user->load_summary();

			if($current_user->is_staff) {
				$approvers = User
										::join('user_types', 'user_types.id', '=', 'users.user_type_id')
										->where('users.team_id', $current_user->team_id)
										->whereIn('user_types.code', ['MANAGER', 'SUPERVISOR'])
										->where('users.is_enabled', 1)
										->select('users.*')
										->get();
			} else {
				$approvers = User
										::join('user_types', 'user_types.id', '=', 'users.user_type_id')
										->where('users.team_id', $current_user->team_id)
										->whereIn('user_types.code', ['MANAGER'])
										->where('users.is_enabled', 1)
										->select('users.*')
										->get();
			}

			// Create Notif
			foreach($approvers as $manager) {
				$notif = new Notification;
				$notif->message = 'Task: ' . $model->name . ' has been submitted for approval.';
				$notif->sender_id = $current_user->id;
				$notif->created_at = date('Y-m-d H:i:s');
				$notif->updated_at = date('Y-m-d H:i:s');
				$notif->task_id = $model->id;
				$notif->recipient_id = $manager->id;
				$notif->save();

				$recipient = User::find($manager->id);
				if( ! empty($recipient->fcm_key)) {
					$sender = auth()->user();
					$sender->load_summary();
					$recipient->load_summary();

					$fcm_data = [
						'id' => $notif->id,
						'task_id' => $model->id,
						'full_name' => $sender->full_name,
						'picture' => $sender->thumbnail,
						'message' => $notif->message,
						'is_read' => 0,
						'created_at' => $notif->created_at
					];
						
					$result = $this->send_notification($model->name, $notif->message, $fcm_data, $recipient->fcm_key);
				}
			}

			DB::commit();

			return $model;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
		
	}

	// v3 with option to save as template
	public function create_with_template(Request $request) {
		$validator = Validator::make($request->all(), [
			'name'     		=> 'required|max:255',
			'description' => 'required|max:255',
			'start_date'  => 'required|date',
			'target_date'	=> 'required|date',
			'tasks'				=> 'required|array'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
		}
		
		try {
			DB::beginTransaction();

			$params = $request->all();
			$params['user_id'] = auth()->user()->id;

			$tasks = $params['tasks'];
			unset($params['tasks']);

			$save_as_template = isset($params['save_as_template']);
			$template_name = '';
			if($save_as_template) {
				$template_name = $params['template_name'];

				unset($params['save_as_template']);
				unset($params['template_name']);
			}


			$params['start_date'] = date('Y-m-d 00:00:00', strtotime($params['start_date']));
			$params['target_date'] = date('Y-m-d 23:59:59', strtotime($params['target_date']));
			$params['created_at'] = date('Y-m-d H:i:s');
			$params['updated_at'] = date('Y-m-d H:i:s');

			$model = Task::create($params);

			foreach($tasks as $task) {
				$task = (object) $task;
				$detail = new TaskDetail();
				$detail->task_id = $model->id;
				$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
				$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));
				$detail->original_target_date = $detail->target_date;
				$detail->task = $task->task;

				if($task->reference_no == 'null') {
					$task->reference_no = null;
				}

				$detail->reference_no = $task->reference_no;

				$detail->created_at = date('Y-m-d H:i:s');
				$detail->updated_at = date('Y-m-d H:i:s');

				$detail->save();
			}

			$model = Task::find($model->id);
			$model->post_process();

			// Add Notification
			// Find Manager/Supervisor
			$current_user = auth()->user();
			$current_user->load_summary();

			if($current_user->is_staff) {
				$approvers = User
										::join('user_types', 'user_types.id', '=', 'users.user_type_id')
										->where('users.team_id', $current_user->team_id)
										->whereIn('user_types.code', ['MANAGER', 'SUPERVISOR'])
										->where('users.is_enabled', 1)
										->select('users.*')
										->get();
			} else {
				$approvers = User
										::join('user_types', 'user_types.id', '=', 'users.user_type_id')
										->where('users.team_id', $current_user->team_id)
										->whereIn('user_types.code', ['MANAGER'])
										->where('users.is_enabled', 1)
										->select('users.*')
										->get();
			}

			// Create Notif
			foreach($approvers as $manager) {
				$notif = new Notification;
				$notif->message = 'Task: ' . $model->name . ' has been submitted for approval.';
				$notif->sender_id = $current_user->id;
				$notif->created_at = date('Y-m-d H:i:s');
				$notif->updated_at = date('Y-m-d H:i:s');
				$notif->task_id = $model->id;
				$notif->recipient_id = $manager->id;
				$notif->save();

				$recipient = User::find($manager->id);
				if( ! empty($recipient->fcm_key)) {
					$sender = auth()->user();
					$sender->load_summary();
					$recipient->load_summary();

					$fcm_data = [
						'id' => $notif->id,
						'task_id' => $model->id,
						'full_name' => $sender->full_name,
						'picture' => $sender->thumbnail,
						'message' => $notif->message,
						'is_read' => 0,
						'created_at' => $notif->created_at
					];
						
					$result = $this->send_notification($model->name, $notif->message, $fcm_data, $recipient->fcm_key);
				}
			}

			// Create Template
			if($save_as_template) {
				$template = new TaskTemplate();
				$template->name = $template_name;
				$template->task_name = $model->name;
				$template->description = $model->description;
				$template->user_id = $model->user_id;
				$template->start_date = $model->start_date;
				$template->target_date = $model->target_date;
				$template->created_at = $model->created_at;
				$template->updated_at = $model->updated_at;
				$template->save();

				foreach($tasks as $task) {
					$task = (object) $task;
					$detail = new TaskTemplateDetail();
					$detail->task_template_id = $template->id;
					$detail->task = $task->task;

					$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
					$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));

					$detail->created_at = date('Y-m-d H:i:s');
					$detail->updated_at = date('Y-m-d H:i:s');
	
					$detail->save();
				}
			}

			DB::commit();

			return $model;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
		
	}

	public function update(Request $request, $id) {
		$validator = Validator::make($request->all(), [
			'name'     		=> 'required|max:255',
			'description' => 'required|max:255',
			'start_date'  => 'required|date',
			'target_date'	=> 'required|date',
			'tasks'				=> 'sometimes|array'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
		}

		$model = Task::find($id);
		
		try {
			DB::beginTransaction();

			$model->name = $request->name;
			$model->start_date = date('Y-m-d 00:00:00', strtotime($request->start_date));
			$model->target_date = date('Y-m-d 00:00:00', strtotime($request->target_date));
			$model->updated_at = date('Y-m-d H:i:s');

			$model->save();

			if($request->has('deleted_tasks')) {
				TaskDetail::whereIn('id', $request->deleted_tasks)->delete();
			}

			if($request->has('updated_tasks')) {
				foreach($request->updated_tasks as $task) {
					$task = (object) $task;
					$detail = TaskDetail::find($task->id);

					$previous_target_date = $detail->target_date;

					$detail->task = $task->task;
					$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
					$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));
					$detail->updated_at = date('Y-m-d H:i:s');

					if(strtotime($previous_target_date) != strtotime($task->end) && $model->is_approved) {
						$detail->is_extended = 1;
					}
					
					$detail->save();


					if($detail->is_extended) {
						// Create History
						$history = new TaskDetailHistory();
						$history->task_detail_id = $detail->id;
						$history->target_date = $previous_target_date;
						$history->extended_by_id = auth()->user()->id;
						$history->created_at = date('Y-m-d H:i:s');
						$history->updated_at = date('Y-m-d H:i:s');
						$history->save();
					}
				}
			}

			if($request->has('tasks')) {
				foreach($request->tasks as $task) {
					$task = (object) $task;
					$detail = new TaskDetail();
					$detail->task_id = $model->id;
					$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
					$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));
					$detail->original_target_date = $detail->target_date;
					$detail->task = $task->task;
					$detail->created_at = date('Y-m-d H:i:s');
					$detail->updated_at = date('Y-m-d H:i:s');
	
					$detail->save();
				}
			}

			DB::commit();

			$model = Task::find($model->id);
			$model->post_process();

			return $model;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
	}

	public function extend(Request $request, $id) {
		try {
			DB::beginTransaction();

			$detail = TaskDetail::find($id);

			$previous_target_date = $detail->target_date;

			$detail->target_date = date('Y-m-d H:i:s', strtotime($request->target_date));
			$detail->updated_at = date('Y-m-d H:i:s');

			if(strtotime($previous_target_date) < strtotime($request->target_date)) {
				$detail->is_extended = 1;
			}
			
			$detail->save();

			// Create History
			$history = new TaskDetailHistory();
			$history->task_detail_id = $detail->id;
			$history->target_date = $previous_target_date;
			$history->extended_by_id = auth()->user()->id;
			$history->created_at = date('Y-m-d H:i:s');
			$history->updated_at = date('Y-m-d H:i:s');
			$history->save();

			DB::commit();

			return $this->retrieve($detail->task_id);;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
		
	}

	public function extend_reset_notify(Request $request, $id) {
		try {
			DB::beginTransaction();

			$detail = TaskDetail::find($id);

			$previous_target_date = $detail->target_date;

			$detail->target_date = date('Y-m-d H:i:s', strtotime($request->target_date));
			$detail->updated_at = date('Y-m-d H:i:s');

			if(strtotime($previous_target_date) < strtotime($request->target_date)) {
				$detail->is_extended = 1;
				$detail->notified = 0;
			}
			
			$detail->save();

			// Create History
			$history = new TaskDetailHistory();
			$history->task_detail_id = $detail->id;
			$history->target_date = $previous_target_date;
			$history->extended_by_id = auth()->user()->id;
			$history->created_at = date('Y-m-d H:i:s');
			$history->updated_at = date('Y-m-d H:i:s');
			$history->save();

			DB::commit();

			return $this->retrieve($detail->task_id);;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
		
	}

	public function update_with_ref_no(Request $request, $id) {
		$validator = Validator::make($request->all(), [
			'name'     		=> 'required|max:255',
			'description' => 'required|max:255',
			'start_date'  => 'required|date',
			'target_date'	=> 'required|date',
			'tasks'				=> 'sometimes|array'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
		}

		$model = Task::find($id);
		
		try {
			DB::beginTransaction();

			$model->name = $request->name;
			$model->description = $request->description;
			$model->start_date = date('Y-m-d 00:00:00', strtotime($request->start_date));
			$model->target_date = date('Y-m-d 00:00:00', strtotime($request->target_date));
			$model->updated_at = date('Y-m-d H:i:s');

			$model->save();

			if($request->has('deleted_tasks')) {
				TaskDetail::whereIn('id', $request->deleted_tasks)->delete();
			}

			if($request->has('updated_tasks')) {
				foreach($request->updated_tasks as $task) {
					$task = (object) $task;
					$detail = TaskDetail::find($task->id);

					$previous_target_date = $detail->target_date;

					$detail->task = $task->task;

					if($task->reference_no == 'null') {
						$task->reference_no = null;
					}

					$detail->reference_no = $task->reference_no;
					$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
					$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));
					$detail->updated_at = date('Y-m-d H:i:s');


					if(strtotime($previous_target_date) != strtotime($task->end) && $model->is_approved) {
						$detail->is_extended = 1;
					}
					
					$detail->save();


					if($detail->is_extended) {
						// Create History
						$history = new TaskDetailHistory();
						$history->task_detail_id = $detail->id;
						$history->target_date = $previous_target_date;
						$history->extended_by_id = auth()->user()->id;
						$history->created_at = date('Y-m-d H:i:s');
						$history->updated_at = date('Y-m-d H:i:s');
						$history->save();
					}
				}
			}

			if($request->has('tasks')) {
				foreach($request->tasks as $task) {
					$task = (object) $task;
					$detail = new TaskDetail();
					$detail->task_id = $model->id;
					$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
					$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));
					$detail->original_target_date = $detail->target_date;
					$detail->task = $task->task;

					if($task->reference_no == 'null') {
						$task->reference_no = null;
					}

					$detail->reference_no = $task->reference_no;
					$detail->created_at = date('Y-m-d H:i:s');
					$detail->updated_at = date('Y-m-d H:i:s');
	
					$detail->save();
				}
			}

			DB::commit();

			$model = Task::find($model->id);
			$model->post_process();

			return $model;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
	}

	public function approve($id) {

		$task = Task::find($id);

		if($task === null) {
			return response()->json(['message' => 'Task not found'], 404);
		}

		$task->is_approved = 1;
		$task->approval_date = date('Y-m-d H:i:s');
		$task->updated_at = date('Y-m-d H:i:s');
		$task->approved_by_id = auth()->user()->id;
		$task->save();
		$task->post_process();
		
		return $task;
	}
	
	public function approve_with_notif($id) {
		try {
			DB::beginTransaction();

			$task = Task::find($id);

			if($task === null) {
				return response()->json(['message' => 'Task not found'], 404);
			}

			$task->is_approved = 1;
			$task->approval_date = date('Y-m-d H:i:s');
			$task->updated_at = date('Y-m-d H:i:s');
			$task->approved_by_id = auth()->user()->id;
			$task->save();
			$task->post_process();

			// Send Notif
			$notif = new Notification;
			$notif->message = 'Task: ' . $task->name . ' has been approved';
			$notif->sender_id = auth()->user()->id;
			$notif->created_at = date('Y-m-d H:i:s');
			$notif->updated_at = date('Y-m-d H:i:s');
			$notif->task_id = $task->id;
			$notif->recipient_id = $task->user_id;
			$notif->save();

			$recipient = User::find($task->user_id);

			if( ! empty($recipient->fcm_key)) {
				$sender = auth()->user();
				$sender->load_summary();
				$recipient->load_summary();

				$fcm_data = [
					'id' => $notif->id,
					'task_id' => $task->id,
					'full_name' => $sender->full_name,
					'picture' => $sender->thumbnail,
					'message' => $notif->message,
					'is_read' => 0,
					'created_at' => $notif->created_at
				];
			    
				$result = $this->send_notification($task->name, $notif->message, $fcm_data, $recipient->fcm_key);
			}
			
			DB::commit();
			return $task;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
		}
	}

	public function task_detail_toggle_complete($id) {
		$task = TaskDetail::find($id);

		if($task->is_completed_by_user) {
			$task->is_completed_by_user = 0;
			$task->completion_by_user_date = null;
			$task->updated_at = date('Y-m-d H:i:s');
		} else {
			$task->is_completed_by_user = 1;
			$task->completion_by_user_date = date('Y-m-d H:i:s');
			$task->updated_at = date('Y-m-d H:i:s');
		}

		$task->save();

		return $this->retrieve($task->task_id);
	}

	public function comment(Request $request, $id) {
		$task = $this->retrieve($id);

		$user = auth()->user();

		$comment = new TaskComment();
		$comment->task_id = $id;
		$comment->commented_by_id = $user->id;
		$comment->comment = $request->comment;
		$comment->created_at = date('Y-m-d H:i:s');
		$comment->updated_at = date('Y-m-d H:i:s');
		$comment->save();

		$comment->full_name = $user->first_name . ' ' . $user->last_name;
		$comment->picture = $user->picture;
		
		if(empty($comment->picture)) {
			$comment->picture = 'public/default-picture.png';
		}
	
		return $comment;
	}

	public function comment_with_notif(Request $request, $id) {
		try {
			DB::beginTransaction();
			$task = $this->retrieve($id);

			$user = auth()->user();

			$comment = new TaskComment();
			$comment->task_id = $id;
			$comment->commented_by_id = $user->id;
			$comment->comment = $request->comment;
			$comment->created_at = date('Y-m-d H:i:s');
			$comment->updated_at = date('Y-m-d H:i:s');
			$comment->save();

			$comment->full_name = $user->first_name . ' ' . $user->last_name;
			$comment->picture = $user->picture;
			
			if(empty($comment->picture)) {
				$comment->picture = 'public/default-picture.png';
			}

			$user->load_summary();

			$recipients = [];

			$owner = $user->id == $task->user_id;

			// Get Manager and Supervisor
			if($user->is_staff) {
				$users = User
										::join('user_types', 'user_types.id', '=', 'users.user_type_id')
										->where('users.team_id', $user->team_id)
										->whereIn('user_types.code', ['MANAGER', 'SUPERVISOR'])
										->where('users.is_enabled', 1)
										->select('users.*')
										->get();

				foreach($users as $a) {
					$recipients[] = $a->id;
				}
			} 
			// Get Supervisor and Owner
			else if ($user->is_manager) {
				$supervisors = User
										::join('user_types', 'user_types.id', '=', 'users.user_type_id')
										->where('users.team_id', $user->team_id)
										->whereIn('user_types.code', ['SUPERVISOR'])
										->where('users.is_enabled', 1)
										->select('users.*')
										->get();

				foreach($supervisors as $a) {
					$recipients[] = $a->id;
				}

				// Add Owner
				$recipients[] = $task->user_id;
			}
			else if ($user->is_supervisor) {
				// Get The manager
				$managers = User
										::join('user_types', 'user_types.id', '=', 'users.user_type_id')
										->where('users.team_id', $user->team_id)
										->whereIn('user_types.code', ['MANAGER'])
										->where('users.is_enabled', 1)
										->select('users.*')
										->get();

				foreach($managers as $a) {
					$recipients[] = $a->id;
				}

				// If not commenting on own tasks
				if( ! $owner) {
					$recipients[] = $task->user_id;
				}
			}
			
			foreach($recipients as $recipient_id) {
				// Send Notif
				$notif = new Notification;
				$notif->message = $comment->full_name . ' has submitted a comment on Task: ' . $task->name;
				$notif->sender_id = $user->id;
				$notif->created_at = date('Y-m-d H:i:s');
				$notif->updated_at = date('Y-m-d H:i:s');
				$notif->task_id = $task->id;
				$notif->recipient_id = $recipient_id;
				$notif->save();

				$recipient = User::find($recipient_id);

				if( ! empty($recipient->fcm_key)) {
					$recipient->load_summary();

					$fcm_data = [
						'id' => $notif->id,
						'task_id' => $task->id,
						'full_name' => $user->full_name,
						'picture' => $user->thumbnail,
						'message' => $notif->message,
						'is_read' => 0,
						'created_at' => $notif->created_at
					];
						
					$result = $this->send_notification($task->name, $notif->message, $fcm_data, $recipient->fcm_key);
				}
			}

			
			DB::commit();
			return $comment;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
	}

	public function attach(Request $request, $id) {
		$task = $this->retrieve($id);

		$validator = Validator::make($request->all(), [
			'attachments'	=> 'array',
		]);
		
		if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
		}

		$paths = [];

		try {
			DB::beginTransaction();

			foreach($request->attachments as $attach) {
				$file = $attach['file'];

				$is_image = substr($file->getMimeType(), 0, 5) == 'image';
				$attachment_path = $file->store('public/task_attachments');

				// Create Path
				$save_path = 'storage/task_attachments_thumbs';
				if ( ! file_exists($save_path)) {
					mkdir($save_path, 0755, true);
				}
	
				$paths[] = $attachment_path;
				$thumbnail = '';
				if($is_image) {
					// Create Thumbnail
					\Image::make($file->getRealPath())->fit(150, 150)->save(($save_path . '/' . basename($attachment_path)));
					$thumbnail = str_replace('storage/', 'public/', $save_path) . '/' . basename($attachment_path);

					$paths[] = $thumbnail;
				}


				$attachment = new TaskAttachment();
				$attachment->task_id = $id;
				$attachment->attached_by_id = auth()->user()->id;

				if($attach['task_id'] != 'null') {
					$attachment->task_detail_id = $attach['task_id'];
				}

				$attachment->is_image = $is_image;
				$attachment->original_file_name = $file->getClientOriginalName();
				$attachment->file_name = $attachment_path;
				$attachment->thumbnail = $thumbnail;
				$attachment->created_at = date('Y-m-d H:i:s');
				$attachment->updated_at = date('Y-m-d H:i:s');
				$attachment->is_locked = 1;

				$attachment->save();
			}

			DB::commit();

			$task->post_process();
			return $task;
		} catch(\Illuminate\Database\QueryException $ex){ 
			DB::rollBack();
			
			if ( ! empty($paths)) {
        \Storage::delete($paths);
      }

      return response()->json(['message' => $ex->errorInfo[2]], 400);
		}
	}

	public function remove_attachment(Request $request, $id) {
		$task = $this->retrieve($id);

		$task_attachment = TaskAttachment::find($request->attachment_id);
		\Storage::delete($task_attachment->file_name);
		\Storage::delete($task_attachment->thumbnail);

		$task_attachment->delete();

		$task->post_process();
		return $task;
	}

	public function toggle_lock_attachment(Request $request, $id) {
		$task = $this->retrieve($id);

		$task_attachment = TaskAttachment::find($request->attachment_id);

		if($task_attachment->is_locked) {
			$task_attachment->is_locked = 0;
		} else {
			$task_attachment->is_locked = 1;
		}

		$task_attachment->save();

		return $task_attachment;
	}

	public function for_verification($id) {
		$task = $this->retrieve($id);

		$task = Task::find($id);
		$task->for_verification = 1;
		$task->for_verification_date = date('Y-m-d H:i:s');
		$task->updated_at = date('Y-m-d H:i:s');

		$task->save();

		$task->post_process();
		return $task;
	}

	public function for_verification_with_notif($id) {
		try {
			DB::beginTransaction();

			$task = $this->retrieve($id);

			$task = Task::find($id);
			$task->for_verification = 1;
			$task->for_verification_date = date('Y-m-d H:i:s');
			$task->updated_at = date('Y-m-d H:i:s');

			$task->save();

			$task->post_process();

			$current_user = auth()->user();
			$current_user->load_summary();
			// $type = UserType::where('code', 'MANAGER')->first();
			// $managers = User
			// 							::where('team_id', $current_user->team_id)
			// 							->where('user_type_id', $type->id)
			// 							->where('is_enabled', 1)
			// 							->get();

			if($current_user->is_staff) {
				$approvers = User
										::join('user_types', 'user_types.id', '=', 'users.user_type_id')
										->where('users.team_id', $current_user->team_id)
										->whereIn('user_types.code', ['MANAGER', 'SUPERVISOR'])
										->where('users.is_enabled', 1)
										->select('users.*')
										->get();
			} else {
				$approvers = User
										::join('user_types', 'user_types.id', '=', 'users.user_type_id')
										->where('users.team_id', $current_user->team_id)
										->whereIn('user_types.code', ['MANAGER'])
										->where('users.is_enabled', 1)
										->select('users.*')
										->get();
			}

			foreach($approvers as $manager) {
				// Create Notif
				$notif = new Notification;
				$notif->message = 'Task: ' . $task->name . ' has been submitted for verification.';
				$notif->sender_id = $current_user->id;
				$notif->created_at = date('Y-m-d H:i:s');
				$notif->updated_at = date('Y-m-d H:i:s');
				$notif->task_id = $task->id;
				$notif->recipient_id = $manager->id;
				$notif->save();

				$recipient = User::find($manager->id);
				if( ! empty($recipient->fcm_key)) {
					$sender = auth()->user();
					$sender->load_summary();
					$recipient->load_summary();

					$fcm_data = [
						'id' => $notif->id,
						'task_id' => $task->id,
						'full_name' => $sender->full_name,
						'picture' => $sender->thumbnail,
						'message' => $notif->message,
						'is_read' => 0,
						'created_at' => $notif->created_at
					];
						
					$result = $this->send_notification($task->name, $notif->message, $fcm_data, $recipient->fcm_key);
				}
			}
			
			DB::commit();			
			
			return $task;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
		}
	}

	public function verify_as_complete($id) {
		$task = $this->retrieve($id);

		$task = Task::find($id);
		$task->completed_by_id = auth()->user()->id;
		$task->is_completed = 1;
		$task->completion_date = date('Y-m-d H:i:s');
		$task->updated_at = date('Y-m-d H:i:s');

		$task->save();

		$task->post_process();
		return $task;
	}

	public function verify_as_complete_with_notif($id) {
		try {
			DB::beginTransaction();

			$task = Task::find($id);

			if($task === null) {
				return response()->json(['message' => 'Task not found'], 404);
			}

			$task->completed_by_id = auth()->user()->id;
			$task->is_completed = 1;
			$task->completion_date = date('Y-m-d H:i:s');
			$task->updated_at = date('Y-m-d H:i:s');
			$task->save();
			$task->post_process();

			// Send Notif
			$notif = new Notification;
			$notif->message = 'Task: ' . $task->name . ' has been completed';
			$notif->sender_id = auth()->user()->id;
			$notif->created_at = date('Y-m-d H:i:s');
			$notif->updated_at = date('Y-m-d H:i:s');
			$notif->task_id = $task->id;
			$notif->recipient_id = $task->user_id;
			$notif->save();

			$recipient = User::find($task->user_id);

			if( ! empty($recipient->fcm_key)) {
				$sender = auth()->user();
				$sender->load_summary();
				$recipient->load_summary();

				$fcm_data = [
					'id' => $notif->id,
					'task_id' => $task->id,
					'full_name' => $sender->full_name,
					'picture' => $sender->thumbnail,
					'message' => $notif->message,
					'is_read' => 0,
					'created_at' => $notif->created_at
				];
			    
				$result = $this->send_notification($task->name, $notif->message, $fcm_data, $recipient->fcm_key);
			}
			
			DB::commit();
			return $task;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
		}
	}

	public function rate_task(Request $request, $id) {
		$task = $this->retrieve($id);

		$task = TaskDetail::find($request->task_id);
		$task->rated_by_id = auth()->user()->id;
		$task->rating = $request->rating;
		$task->rating_date = date('Y-m-d H:i:s');
		$task->is_completed_by_checker = 1;
		$task->completion_by_checker_date = date('Y-m-d H:i:s');
		$task->updated_at = date('Y-m-d H:i:s');

		$task->save();

		return $this->retrieve($id);
	}

	public function retrieve_with_history(Request $request, $id) {
    $model = $this->model::find($id);

    if(empty($model)) {
      return response()->json(['message' => 'Record Not Found'], 404);
    }

		$model->post_process();
	
		foreach($model->tasks as &$task) {
			$task->history = TaskDetailHistory
												::join('users', 'users.id', '=', 'task_detail_histories.extended_by_id')
												->where('task_detail_id', $task->id)
												->selectRaw('task_detail_histories.*, CONCAT(users.first_name, " ", users.last_name) AS extended_by')
												->get();
		}

    return $model;
	}
	
	public function delete(Request $request, $id) {
    $data = $this->model
              ::where('id', $id)
							->where('updated_at', $request->updated_at)
							->where('is_approved', 0)
              ->first();

    if(empty($data)) {
      return response()->json(['message' => 'Record not found. It was either recently updated by another user or deleted. Please try again.'], 400);
    }

    $data->delete();
        
    return response()->json(['success' => 'success'], 200);
	}
	
	public function get_comments($id) {
		$comments = TaskComment
									::join('users', 'users.id', '=', 'task_comments.commented_by_id')
									->where('task_comments.task_id', $id)
									->selectRaw('task_comments.*, users.picture, CONCAT(users.first_name, " ", users.last_name) AS full_name')
									->orderBy('task_comments.created_at', 'DESC')
									->paginate(5);

		foreach($comments as &$comment) {
			if( ! $comment->picture) {
				$comment->picture = 'public/default-picture.png';
			}
		}

		return $comments;
	}

	public function task_detail_history($id) {
		$history = TaskDetailHistory
								::join('users', 'users.id', '=', 'task_detail_histories.extended_by_id')
								->where('task_detail_id', $id)
								->selectRaw('task_detail_histories.*, users.picture, CONCAT(users.first_name, " ", users.last_name) AS full_name')
								->orderBy('task_detail_histories.created_at', 'ASC')
								->get();

		foreach($history as &$data) {
			if( ! $data->picture) {
				$data->picture = 'public/default-picture.png';
			}
		}

		return $history;
	}

	private function send_notification($title, $body, $notif, $token) {
		$optionBuilder = new OptionsBuilder();
		$optionBuilder->setTimeToLive(0);

		$notificationBuilder = new PayloadNotificationBuilder($title);
		$notificationBuilder->setBody($body)
								->setSound('default');

		$dataBuilder = new PayloadDataBuilder();
		$dataBuilder->addData($notif);

		$option = $optionBuilder->build();
		$notification = $notificationBuilder->build();
		$data = $dataBuilder->build();

		$downstreamResponse = FCM::sendTo($token, $option, $notification, $data);

		return [
			'success' => $downstreamResponse->numberSuccess(),
			'fail' => $downstreamResponse->numberFailure()
		];
		
		// $downstreamResponse->numberModification();

		// // return Array - you must remove all this tokens in your database
		// $downstreamResponse->tokensToDelete();

		// // return Array (key : oldToken, value : new token - you must change the token in your database)
		// $downstreamResponse->tokensToModify();

		// // return Array - you should try to resend the message to the tokens in the array
		// $downstreamResponse->tokensToRetry();

		// // return Array (key:token, value:error) - in production you should remove from your database the tokens
		// $downstreamResponse->tokensWithError();

	}

	public function notify_expiring() {
		try {
			DB::beginTransaction();

			$tasks = Task
				::join('task_details', 'task_details.task_id', '=', 'tasks.id')
				->where('tasks.is_completed', 0)
				->where('tasks.is_approved', 1)
				->where('tasks.for_verification', 0)
				->where('task_details.is_completed_by_user', 0)
				->where('task_details.notified', 0)
				->whereRaw('DATE_ADD(NOW(), INTERVAL 1 HOUR) >= task_details.target_date')
				->select('task_details.*', 'tasks.user_id')
				->get();

			$task_ids = [];

			foreach($tasks as $task) {
				$recipient = User::find($task->user_id);
				$recipient->load_summary();

				// Send Notif
				$notif = new Notification;
				if(strtotime($task->target_date) <= time()) {
					$notif->message = 'Task: ' . $task->task . ' is already past its due date';
				} else {
					$notif->message = 'Task: ' . $task->task . ' is due at ' . date('Y-m-d h:i A', strtotime($task->target_date));
				}
				$notif->sender_id = null;
				$notif->created_at = date('Y-m-d H:i:s');
				$notif->updated_at = date('Y-m-d H:i:s');
				$notif->task_id = $task->task_id;
				$notif->recipient_id = $task->user_id;
				$notif->save();

				if( ! empty($recipient->fcm_key)) {
					$recipient->load_summary();

					$fcm_data = [
						'id' => $notif->id,
						'task_id' => $task->id,
						'full_name' => 'System Notification',
						'picture' => 'public/default-picture.png',
						'message' => $notif->message,
						'is_read' => 0,
						'created_at' => $notif->created_at
					];
						
					$result = $this->send_notification($task->name, $notif->message, $fcm_data, $recipient->fcm_key);
				}

				$task_ids[] = $task->id;
			}

			DB
				::table('task_details')
				->whereIn('id', $task_ids)
				->update([
					'notified' => 1
				]);
			
			DB::commit();

			return $tasks;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
		}
	}

	public function get_templates(Request $request) {
		$templates = TaskTemplate::where('user_id', $request->user_id)->get();

		foreach($templates as &$tmp) {
			$tmp->post_process();
		}
		
		return $templates;
	}

	public function get_template_by_id($id) {
		$template = TaskTemplate::find($id);
		$template->post_process();

		return $template;
	}

	public function update_template(Request $request, $id) {
		$validator = Validator::make($request->all(), [
			'name'     		=> 'required|max:255',
			'task_name' 	=> 'required|max:255',
			'description' => 'required|max:255',
			'start_date'  => 'required|date',
			'target_date'	=> 'required|date',
			'tasks'				=> 'sometimes|array'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
		}

		$model = TaskTemplate::find($id);

		try {
			DB::beginTransaction();

			$model->name = $request->name;
			$model->task_name = $request->task_name;
			$model->description = $request->description;
			$model->start_date = date('Y-m-d 00:00:00', strtotime($request->start_date));
			$model->target_date = date('Y-m-d 00:00:00', strtotime($request->target_date));
			$model->updated_at = date('Y-m-d H:i:s');

			$model->save();

			if($request->has('deleted_tasks')) {
				TaskTemplateDetail::whereIn('id', $request->deleted_tasks)->delete();
			}

			if($request->has('updated_tasks')) {
				foreach($request->updated_tasks as $task) {
					$task = (object) $task;
					$detail = TaskTemplateDetail::find($task->id);

					$detail->task = $task->task;
					$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
					$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));
					$detail->updated_at = date('Y-m-d H:i:s');
					
					$detail->save();
				}
			}

			if($request->has('tasks')) {
				foreach($request->tasks as $task) {
					$task = (object) $task;
					$detail = new TaskTemplateDetail();
					$detail->task_template_id = $model->id;
					$detail->start_date = date('Y-m-d H:i:s', strtotime($task->start));
					$detail->target_date = date('Y-m-d H:i:s', strtotime($task->end));
					$detail->task = $task->task;
					$detail->created_at = date('Y-m-d H:i:s');
					$detail->updated_at = date('Y-m-d H:i:s');
	
					$detail->save();
				}
			}

			DB::commit();

			$model = TaskTemplate::find($model->id);
			$model->post_process();

			return $model;
		} catch(\Illuminate\Database\QueryException $ex){ 
      DB::rollBack();
      return response()->json(['message' => $ex->errorInfo[2]], 400);
    }
	}

	public function delete_template(Request $request, $id) {
		$data = TaskTemplate
							::where('id', $id)
							->where('updated_at', $request->updated_at)
							->first();

		if(empty($data)) {
			return response()->json(['message' => 'Record not found. It was either recently updated by another user or deleted. Please try again.'], 400);
		}

		$data->delete();
				
		return response()->json(['success' => 'success'], 200);
	}
}
