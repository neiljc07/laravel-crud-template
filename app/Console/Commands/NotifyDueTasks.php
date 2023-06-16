<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task\Task;
use App\Models\User;
use App\Models\Notification;
use DB;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;

class NotifyDueTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:due_tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify Due Tasks';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("Cron is working fine!");

        try {
            DB::beginTransaction();

            $tasks = Task
                ::join('task_details', 'task_details.task_id', '=', 'tasks.id')
                ->where('tasks.is_completed', 0)
                ->where('tasks.is_approved', 1)
                ->where('task_details.is_completed_by_user', 0)
                ->where('task_details.notified', 0)
				->whereRaw('? >= task_details.target_date', [date('Y-m-d H:i:s', strtotime('+1 hour'))])
                ->select('task_details.*', 'tasks.user_id')
                ->get();

            $task_ids = [];

            foreach ($tasks as $task) {
                $recipient = User::find($task->user_id);
                $recipient->load_summary();

                // Send Notif
                $notif = new Notification;
                if (strtotime($task->target_date) <= time()) {
                    $notif->message = 'Task: ' . $task->task . ' is already past its due date';
                } else {
                    $notif->message = 'Task: ' . $task->task . ' is due on ' . date('Y-m-d h:i A', strtotime($task->target_date));
                }
                $notif->sender_id = null;
                $notif->created_at = date('Y-m-d H:i:s');
                $notif->updated_at = date('Y-m-d H:i:s');
                $notif->task_id = $task->task_id;
                $notif->recipient_id = $task->user_id;
                $notif->save();

                if (!empty($recipient->fcm_key)) {
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

            echo 'Ok: ' . count($task_ids);

            \Log::info('Ok: ' . count($task_ids));

            // return $tasks;
        } catch (\Illuminate\Database\QueryException $ex) {
            DB::rollBack();
            // return response()->json(['message' => $ex->errorInfo[2]], 400);

            // $this->info('Not Ok: ' . $ex->errorInfo[2]);
            \Log::info('Not Ok: ' . $ex->errorInfo[2]);
        }
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
	}
}
