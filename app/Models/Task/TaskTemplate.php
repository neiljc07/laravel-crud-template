<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Model;
use App\Models\Task\TaskTemplateDetail;

class TaskTemplate extends Model
{
    public function post_process() {
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

        $this->tasks = TaskTemplateDetail::where('task_template_id', $this->id)->get();
    }
}
