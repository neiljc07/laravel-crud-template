<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Response;
use App\Models\Location;
use App\Models\User;
use App\Models\UserType;
use App\Models\Task\Task;
use App\Models\Team;
use DB;

class ReportController extends Controller
{
    public function check_ins(Request $request) {
        $locations = Location
            ::join('users', 'users.id', '=', 'locations.user_id')
            ->where('users.is_enabled', 1)
            ->where('client_id', $request->client_id);

        if($request->has('date')) {
            $locations->whereRaw('DATE_FORMAT(locations.created_at, "%Y-%m-%d") = ?', [$request->date]);
        }

        if($request->has('date_from') && $request->has('date_to')) {
            $locations->whereRaw('DATE_FORMAT(locations.created_at, "%Y-%m-%d") BETWEEN ? AND ?', [$request->date_from, $request->date_to]);
        }

        if($request->has('user_id')) {
            $locations->where('users.id', $request->user_id);
        }

        $locations->where('users.team_id', $request->team_id);

        $locations->selectRaw('
            locations.id, users.id AS user_id, users.first_name, users.last_name, DATE_FORMAT(locations.created_at, "%Y-%m-%d") AS date,
            DATE_FORMAT(locations.created_at, "%h:%i:%s %p") AS time, locations.lat, locations.lng, locations.address,
            locations.remarks, locations.picture, locations.created_at');
        
        $locations->orderBy('users.last_name', 'ASC');    
        $locations->orderBy('users.first_name', 'ASC');
        $locations->orderBy('locations.created_at', 'ASC');

        $locations = $locations->get();

        $previous_date = null;
        $previous_user = null;
        $previous_time = null;
        foreach($locations as &$location) {
            if(($previous_date == null && $previous_user == null) ||
                ($previous_date != $location->date) ||
                ($previous_user != $location->user_id)) {
                $location->deviation = 0;
            } else {
                $deviation = round((strtotime($location->created_at) - strtotime($previous_time)) / 60);

                if($deviation < 60) {
                    $location->deviation = $deviation;

                    if($deviation == 1) {
                        $location->deviation .= ' minute';
                    } else {
                        $location->deviation .= ' minutes';
                    }   
                } else if ($deviation >= 60) {
                    $location->deviation = number_format(($deviation / 60), 1);

                    if($deviation == 1) {
                        $location->deviation .= ' hour';
                    } else {
                        $location->deviation .= ' hours';
                    }
                }
            }

            $previous_date = $location->date;
            $previous_user = $location->user_id;
            $previous_time = $location->created_at;
            
            $location->picture = asset(str_replace('public/', 'storage/', $location->picture));
        }

        $columns = [
            'id', 'first_name', 'last_name', 'date', 'time', 'deviation', 'lat', 'lng', 'address', 'remarks', 'picture'
        ];

        $filepath = 'reports/report_' . date('Y-m-d') . '_' . $request->client_id . '.csv';
      
        $fp = fopen($filepath, 'w');

        fputcsv($fp, $columns);
        foreach($locations as $location) {
            fputcsv($fp, [$location->id, $location->first_name, $location->last_name, $location->date, $location->time, $location->deviation, $location->lat, $location->lng, $location->address, $location->remarks, $location->picture]);
        }
        
        fclose($fp);

        if(file_exists($filepath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            flush(); // Flush system output buffer
            readfile($filepath);
            exit;
        }
    }

    public function manager_dashboard(Request $request) {
        $staff = UserType::where('code', 'STAFF')->first();
        $manager_type = UserType::where('code', 'MANAGER')->first();
        $supervisor_type = UserType::where('code', 'SUPERVISOR')->first();

        $team_id = $request->team_id;
        $manager = User::where('team_id', $team_id)
                    ->where('user_type_id', $manager_type->id)
                    ->where('is_enabled', 1)
                    ->first();

        $team = Team::find($team_id);
        
        if($manager && ! $manager->picture) {
            $manager->picture = 'public/default-picture.png';
        }

        $staffs = User::search($request)
                            ->where('team_id', $team_id)
                            ->where('user_type_id', $staff->id)
                            ->where('is_enabled', 1)
                            ->get();
        
        $supervisors = User::search($request)
                            ->where('team_id', $team_id)
                            ->where('user_type_id', $supervisor_type->id)
                            ->where('is_enabled', 1)
                            ->get();

        $team_members = [];

        foreach($supervisors as $user) {
            $user->post_process();
            $user->current_tasks = $this->current_tasks($user->id);
            $user->total_check_ins = $this->total_check_ins($user->id);
            $user->check_in_for_today = $this->check_in_for_today($user->id);
            $user->completed_tasks = $this->completed_tasks($user->id);
            $user->for_verification_tasks = $this->for_verification_tasks($user->id);
            $user->for_approval_tasks = $this->for_approval_tasks($user->id);
            $user->late_tasks = $this->late_tasks($user->id);
            $user->rating = $this->rating($user->id);

            $team_members = array_merge($team_members, [$user]);
        }

        // Get Active Tasks
        foreach($staffs as $user) {
            $user->post_process();
            $user->current_tasks = $this->current_tasks($user->id);
            $user->total_check_ins = $this->total_check_ins($user->id);
            $user->check_in_for_today = $this->check_in_for_today($user->id);
            $user->completed_tasks = $this->completed_tasks($user->id);
            $user->for_verification_tasks = $this->for_verification_tasks($user->id);
            $user->for_approval_tasks = $this->for_approval_tasks($user->id);
            $user->late_tasks = $this->late_tasks($user->id);
            $user->rating = $this->rating($user->id);

            $team_members = array_merge($team_members, [$user]);
        }

        return compact('team_members', 'manager', 'team');
    }

    public function manager_dashboard_with_date(Request $request) {
        $staff = UserType::where('code', 'STAFF')->first();
        $manager_type = UserType::where('code', 'MANAGER')->first();
        $supervisor_type = UserType::where('code', 'SUPERVISOR')->first();

        $team_id = $request->team_id;
        $manager = User::where('team_id', $team_id)
                    ->where('user_type_id', $manager_type->id)
                    ->where('is_enabled', 1)
                    ->first();

        $team = Team::find($team_id);
        
        if($manager && ! $manager->picture) {
            $manager->picture = 'public/default-picture.png';
        }

        $staffs = User::search($request)
                            ->where('team_id', $team_id)
                            ->where('user_type_id', $staff->id)
                            ->where('is_enabled', 1)
                            ->get();
        
        $supervisors = User::search($request)
                            ->where('team_id', $team_id)
                            ->where('user_type_id', $supervisor_type->id)
                            ->where('is_enabled', 1)
                            ->get();

        $team_members = [];

        foreach($supervisors as $user) {
            $user->post_process();
            $user->current_tasks = $this->current_tasks($user->id);
            // $user->total_check_ins = $this->total_check_ins($user->id);
            $user->check_in_for_today = $this->check_in_for_today($user->id);
            $user->completed_tasks = $this->completed_tasks_with_date($user->id, $request->date_from, $request->date_to);
            $user->for_verification_tasks = $this->for_verification_tasks($user->id);
            $user->for_approval_tasks = $this->for_approval_tasks($user->id);
            $user->late_tasks = $this->late_tasks_with_date($user->id, $request->date_from, $request->date_to);
            $user->rating = $this->rating($user->id);

            $team_members = array_merge($team_members, [$user]);
        }

        // Get Active Tasks
        foreach($staffs as $user) {
            $user->post_process();
            $user->current_tasks = $this->current_tasks($user->id);
            // $user->total_check_ins = $this->total_check_ins($user->id);
            $user->check_in_for_today = $this->check_in_for_today($user->id);
            $user->completed_tasks = $this->completed_tasks_with_date($user->id, $request->date_from, $request->date_to);
            $user->for_verification_tasks = $this->for_verification_tasks($user->id);
            $user->for_approval_tasks = $this->for_approval_tasks($user->id);
            $user->late_tasks = $this->late_tasks_with_date($user->id, $request->date_from, $request->date_to);
            $user->rating = $this->rating($user->id);

            $team_members = array_merge($team_members, [$user]);
        }

        return compact('team_members', 'manager', 'team');
    }

    public function managing_director_dashboard(Request $request) {
        $teams = Team::search($request)->get();
        
        $staff = UserType::where('code', 'STAFF')->first();
        $manager = UserType::where('code', 'MANAGER')->first();

        foreach($teams as &$team) {
            // Get Manager 
            $team->manager = User::where('team_id', $team->id)
                                    ->where('user_type_id', $manager->id)
                                    ->where('is_enabled', 1)
                                    ->first();

            if($team->manager && ! $team->manager->picture) {
                $team->manager->picture = 'public/default-picture.png';
            }

            $team->team_members = User::search($request)
                                            ->where('team_id', $team->id)
                                            ->where('user_type_id', '<>', $manager->id)
                                            ->where('is_enabled', 1)
                                            ->get();

            $team->current_tasks = 0;
            $team->completed_tasks = 0;
            $team->late_tasks = 0;
            
            $team->num_of_members = count($team->team_members);

            if($team->manager) {
                $team->num_of_members++;
            }

            foreach($team->team_members as &$user) {
                if( ! $user->picture) {
                    $user->picture = 'public/default-picture.png';
                }

                $user->current_tasks = $this->current_tasks($user->id);
                $user->rating = $this->rating($user->id);
                $user->completed_tasks = $this->completed_tasks($user->id);
                $user->late_tasks = $this->late_tasks($user->id);

                $team->current_tasks += count($user->current_tasks);
                $team->completed_tasks += count($user->completed_tasks);
                $team->late_tasks += count($user->late_tasks);

                
                $user->is_checked_in = date('Y-m-d', strtotime($user->last_check_in)) == date('Y-m-d');
            }
        }


        return compact('teams');
    }

    public function managing_director_dashboard_with_date(Request $request) {
        $teams = Team::search($request)->get();
        
        $staff = UserType::where('code', 'STAFF')->first();
        $manager = UserType::where('code', 'MANAGER')->first();

        foreach($teams as &$team) {
            // Get Manager 
            $team->manager = User::where('team_id', $team->id)
                                    ->where('user_type_id', $manager->id)
                                    ->where('is_enabled', 1)
                                    ->first();

            if($team->manager && ! $team->manager->picture) {
                $team->manager->picture = 'public/default-picture.png';
            }

            $team->team_members = User::search($request)
                                            ->where('team_id', $team->id)
                                            ->where('user_type_id', '<>', $manager->id)
                                            ->where('is_enabled', 1)
                                            ->get();

            $team->current_tasks = 0;
            $team->completed_tasks = 0;
            $team->late_tasks = 0;
            
            $team->num_of_members = count($team->team_members);

            if($team->manager) {
                $team->num_of_members++;
            }

            foreach($team->team_members as &$user) {
                if( ! $user->picture) {
                    $user->picture = 'public/default-picture.png';
                }

                $user->current_tasks = $this->current_tasks($user->id);
                $user->rating = $this->rating($user->id);
                $user->completed_tasks = $this->completed_tasks_with_date($user->id, $request->date_from, $request->date_to);
                $user->late_tasks = $this->late_tasks_with_date($user->id, $request->date_from, $request->date_to);

                $team->current_tasks += count($user->current_tasks);
                $team->completed_tasks += count($user->completed_tasks);
                $team->late_tasks += count($user->late_tasks);

                
                $user->is_checked_in = date('Y-m-d', strtotime($user->last_check_in)) == date('Y-m-d');
            }
        }


        return compact('teams');
    }

    private function current_tasks($user_id) {
        $result = DB::select(DB::raw('SELECT 
                                    x.id, 
                                    x.name, 
                                    x.description, 
                                    x.target_date, 
                                    x.is_approved, 
                                    x.for_verification,
                                    x.is_completed,
                                    (x.task_complete / (x.task_complete + x.task_incomplete)) * 100 as progress,
                                    x.user_id
                                
                                FROM (SELECT 
                                    a.id, 
                                    a.name, 
                                    a.description, 
                                    a.target_date, 
                                    a.is_approved, 
                                    a.for_verification, 
                                    a.is_completed,
                                    COUNT(b.id) as task_complete,
                                    0 as task_incomplete,
                                    a.user_id
                                FROM tasks a
                                INNER JOIN task_details b ON
                                    b.task_id = a.id
                                    
                                WHERE b.is_completed_by_user = 1 AND
                                    a.is_approved = 1
                                
                                GROUP BY 
                                    a.id, 
                                    a.name, 
                                    a.description, 
                                    a.target_date, 
                                    a.is_approved, 
                                    a.for_verification, 
                                    a.is_completed,
                                    a.user_id
                                    
                                UNION ALL
                                
                                SELECT 
                                    a.id, 
                                    a.name, 
                                    a.description, 
                                    a.target_date, 
                                    a.is_approved, 
                                    a.for_verification, 
                                    a.is_completed,
                                    0 as task_complete,
                                    COUNT(b.id) as task_incomplete,
                                    a.user_id
                                FROM tasks a
                                INNER JOIN task_details b ON
                                    b.task_id = a.id
                                    
                                WHERE b.is_completed_by_user = 0 AND
                                    a.is_approved = 1
                                    
                                GROUP BY 
                                    a.id, 
                                    a.name, 
                                    a.description, 
                                    a.target_date, 
                                    a.is_approved, 
                                    a.for_verification, 
                                    a.is_completed,
                                    a.user_id) as x
                                WHERE x.user_id = ? AND x.is_completed = ?'), [$user_id, 0]);

        return $result;
    }

    private function completed_tasks($user_id) {
        return Task::where('user_id', $user_id)
                    ->where('is_completed', 1)
                    ->get();
    }

    private function completed_tasks_with_date($user_id, $date_from, $date_to) {
        return Task::where('user_id', $user_id)
                    ->where('is_completed', 1)
                    ->whereBetween('start_date', [date('Y-m-d', strtotime($date_from)), date('Y-m-d', strtotime($date_to))])
                    ->get();
    }

    private function for_verification_tasks($user_id) {
        return Task::where('user_id', $user_id)->where('for_verification', 1)->where('is_completed', 0)->get();
    }

    private function for_approval_tasks($user_id) {
        return Task::where('user_id', $user_id)->where('is_approved', 0)->get();
    }

    private function rating($user_id) {
        return  Task
                    ::join('task_details', 'tasks.id', '=', 'task_details.task_id')
                    ->where('tasks.is_completed', 1)
                    ->where('task_details.is_completed_by_checker', 1)
                    ->where('tasks.user_id', $user_id)
                    ->selectRaw('ROUND(SUM(IFNULL(rating, 0)) / COUNT(tasks.id)) as rating')
                    ->get()
                    ->first()->rating;
    }

    private function late_tasks($user_id) {
        return  Task
                    ::join('task_details', 'tasks.id', '=', 'task_details.task_id')
                    ->whereRaw('task_details.original_target_date < IFNULL(task_details.completion_by_user_date, NOW())')
                    ->where('tasks.user_id', $user_id)
                    ->where('tasks.is_approved', 1)
                    ->selectRaw('MAX(task_details.id), task_details.task_id')
                    ->groupBy('task_details.task_id')
                    ->get();
    }

    private function late_tasks_with_date($user_id, $date_from, $date_to) {
        return  Task
                    ::join('task_details', 'tasks.id', '=', 'task_details.task_id')
                    ->whereRaw('task_details.original_target_date < IFNULL(task_details.completion_by_user_date, NOW())')
                    ->where('tasks.user_id', $user_id)
                    ->where('tasks.is_approved', 1)
                    ->whereBetween('tasks.start_date', [date('Y-m-d', strtotime($date_from)), date('Y-m-d', strtotime($date_to))])
                    ->selectRaw('MAX(task_details.id), task_details.task_id')
                    ->groupBy('task_details.task_id')
                    ->get();
    }
    
    private function total_check_ins($user_id) {
        return Location::where('user_id', $user_id)->count();
    }

    private function check_in_for_today($user_id) {
        return Location::where('user_id', $user_id)->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d") = ?', [date('Y-m-d')])->count();
    }

    public function tasks_by_date(Request $request) {
        $sql = "SELECT
                    d.name as department,
                    CONCAT(c.first_name, ' ', c.last_name) as employee,
                    a.name as parent_task,
                    b.task as task_name,
                    b.start_date,
                    b.original_target_date as target_date,
                    CASE WHEN b.is_completed_by_user THEN 'Done'
                    ELSE 'Ongoing' END as status,
                    b.completion_by_user_date as completion_date,
                    CASE WHEN b.is_extended THEN b.target_date
                    ELSE '' END as extension_date
                FROM tasks a
                INNER JOIN task_details b ON
                    b.task_id = a.id
                INNER JOIN users c ON
                    c.id = a.user_id
                INNER JOIN teams d ON
                    d.id = c.team_id
                
                WHERE 
                    a.is_approved = 1 AND
                    (DATE_FORMAT(b.original_target_date, '%Y-%m-%d') BETWEEN ? AND ?) AND c.team_id = ?
                
                ORDER BY 
                    CONCAT(c.first_name, ' ', c.last_name),
                    a.id,
                    b.start_date";

        $start_date = date('Y-m-d', strtotime($request->start_date));
        $end_date = date('Y-m-d', strtotime($request->target_date));

        $query = DB::select(DB::raw($sql), [$start_date, $end_date, $request->team_id]);

        $columns = [
            'Department', 'Employee Name', 'Parent Task', 'Task Name', 'Start Date/Time', 'Target Date/Time', 'Status',	'Completion Date/Time', 'Extension Date/Time'
        ];

        $filepath = 'reports/report_tasks_' . date('Y-m-d') . '_' . $request->team_id . '.csv';
      
        $fp = fopen($filepath, 'w');

        fputcsv($fp, $columns);
        foreach($query as $data) {
            fputcsv($fp, [
                $data->department, 
                $data->employee, 
                $data->parent_task,
                $data->task_name,
                $data->start_date,
                $data->target_date,
                $data->status,
                $data->completion_date,
                $data->extension_date
            ]);
        }
        
        fclose($fp);

        if(file_exists($filepath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            flush(); // Flush system output buffer
            readfile($filepath);
            exit;
        }
    }
}
