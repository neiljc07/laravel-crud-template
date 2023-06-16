<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_comments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('task_id');
            $table->foreign('task_id')
                    ->references('id')
                    ->on('tasks')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

            $table->unsignedBigInteger('commented_by_id');
            $table->foreign('commented_by_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

            $table->string('comment', 500);

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
        Schema::dropIfExists('task_comments');
    }
}
