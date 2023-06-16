<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskDetailHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_detail_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            $table->unsignedBigInteger('task_detail_id');
            $table->foreign('task_detail_id')
                    ->references('id')
                    ->on('task_details')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

            $table->dateTime('target_date');
            $table->unsignedBigInteger('extended_by_id');
            $table->foreign('extended_by_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

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
        Schema::dropIfExists('task_detail_histories');
    }
}
