<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Model;
use App\Models\Base\BaseModel;
use App\Models\Task\TaskDetail;
use App\Models\Task\TaskComment;
use App\Models\Task\TaskAttachment;

class Task extends BaseModel
{
	protected $fillable = ['user_id', 'name', 'description', 'created_at', 'updated_at', 'start_date', 'target_date'];

	protected $casts = [
		'is_approved' => 'boolean',
		'for_verification' => 'boolean',
		'is_completed' => 'boolean',
		'is_late' => 'boolean'
  ];

	public function scopeSearch($query, $params) {
		if(isset($params['user_id'])) {
			$query->where('user_id', $params['user_id']);
		}

		if(isset($params['is_completed'])) {
			$query->where('is_completed', $params['is_completed']);
		}

		if(isset($params['date_from']) && isset($params['date_to'])) {
			$query->whereBetween('start_date', [$params['date_from'], $params['date_to']]);
		}
	}

	public function post_process() {
		$this->tasks = TaskDetail::where('task_id', $this->id)->get();

		$this->comments = [];

		// $this->comments = TaskComment
		// 										::join('users', 'users.id', '=', 'task_comments.commented_by_id')
		// 										->where('task_comments.task_id', $this->id)
		// 										->selectRaw('task_comments.*, users.picture, CONCAT(users.first_name, " ", users.last_name) AS full_name')
		// 										->orderBy('task_comments.created_at', 'DESC')
		// 										->paginate(5);

		// foreach($this->comments as &$comment) {
		// 	if( ! $comment->picture) {
		// 		$comment->picture = 'public/default-picture.png';
		// 	}
		// }

		$this->attachments = TaskAttachment
													::leftJoin('task_details', 'task_details.id', '=', 'task_attachments.task_detail_id')
													->where('task_attachments.task_id', $this->id)
													->select('task_attachments.*', 'task_details.task')
													->get();

		$this->thumbnails = [];
		$this->other_attachments = [];

		foreach($this->attachments as &$attachment) {
			// if($attachment->is_image) {
			// 	$this->thumbnails = array_merge($this->thumbnails, [$attachment->file_name]);
			// } else {
			// 	$this->other_attachments = array_merge($this->other_attachments, [pathinfo($attachment->file_name)['extension']]);
			// }

			if(empty($attachment->thumbnail)) {
				$attachment->thumbnail = 'public/generic-file.svg';
			}
		}

		$this->percentage = 0;

		$done = 0;
		$this->is_late = false;
		$this->is_extended = false;

		$this->rating = 0;

		foreach($this->tasks as &$task) {
			if($task->is_completed_by_user) {
				$done++;
			}

			if($this->is_approved && 
				(
					$task->is_completed_by_user && strtotime($task->completion_by_user_date) > strtotime($task->original_target_date) || 
					$task->is_completed_by_user == false && strtotime($task->original_target_date) < time()
				)
			) {
				$this->is_late = true;
				$task->is_late = true;
			} else {
				$task->is_late = false;
			}

			$task->start = date('F d, Y h:i A', strtotime($task->start_date));
			$task->end = date('F d, Y h:i A', strtotime($task->target_date));

			$task->task_end_time = date('h:i', strtotime($task->target_date));
			$task->task_end_period = date('A', strtotime($task->target_date));

			if($task->is_extended) {
				$this->is_extended = true;
			}

			if($task->rating) {
				$this->rating += $task->rating;
			}
		}

		$this->rating = $this->rating / count($this->tasks);

		$this->percentage = number_format(($done / count($this->tasks)) * 100, 0) . '%';

		$this->period_label = '';

		if(date('Y-m-d', strtotime($this->start_date)) == date('Y-m-d', strtotime($this->target_date))) {
			$this->period_label = date('M d, Y', strtotime($this->start_date));
		} else {
			// Same Month and Year, different date
			if( (strtotime(date('Y-m', strtotime($this->start_date))) == strtotime(date('Y-m', strtotime($this->target_date)))) &&
					(strtotime(date('Y-m-d', strtotime($this->start_date))) < strtotime(date('Y-m-d', strtotime($this->target_date))))) {
				$this->period_label = date('M d', strtotime($this->start_date)) . ' - ' . date('d, Y', strtotime($this->target_date));
			}

			// Same Year, different month
			if( (strtotime(date('Y', strtotime($this->start_date))) == strtotime(date('Y', strtotime($this->target_date)))) &&
					(strtotime(date('Y-m', strtotime($this->start_date))) < strtotime(date('Y-m', strtotime($this->target_date))))) {
				$this->period_label = date('M d', strtotime($this->start_date)) . ' - ' . date('M d, Y', strtotime($this->target_date));
			}

			// Different Year
			if(date('Y', strtotime($this->start_date)) != date('Y', strtotime($this->target_date))) {
				$this->period_label = date('M d, Y', strtotime($this->start_date)) . ' - ' . date('M d, Y', strtotime($this->target_date));
			}
		}
	}
}
