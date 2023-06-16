<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('task_id');
            $table->foreign('task_id')
                    ->references('id')
                    ->on('tasks')
                    ->onDelete('cascade')
                    ->onUpdate('restrict');

            $table->unsignedBigInteger('attached_by_id');
            $table->foreign('attached_by_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

            $table->unsignedBigInteger('task_detail_id')->nullable();
            $table->foreign('task_detail_id')
                    ->references('id')
                    ->on('task_details')
                    ->onDelete('cascade')
                    ->onUpdate('restrict');
            
            $table->string('file_name', 255);
            $table->string('original_file_name', 255);

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
        Schema::dropIfExists('task_attachments');
    }
}
