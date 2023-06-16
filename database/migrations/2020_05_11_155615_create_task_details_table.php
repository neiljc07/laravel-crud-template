<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_details', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('task_id');
            $table->foreign('task_id')
                    ->references('id')
                    ->on('tasks')
                    ->onDelete('cascade')
                    ->onUpdate('restrict');

            $table->string('task', 255);
            $table->dateTime('start_date');
            $table->dateTime('target_date');

            $table->tinyInteger('is_completed_by_user')->default(0);
            $table->dateTime('completion_by_user_date')->nullable();
            $table->tinyInteger('is_completed_by_checker')->default(0);
            $table->dateTime('completion_by_checker_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('task_details');
    }
}
