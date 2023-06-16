<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskTemplateDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_template_details', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('task_template_id');
            $table->foreign('task_template_id')
                    ->references('id')
                    ->on('task_templates')
                    ->onDelete('cascade')
                    ->onUpdate('restrict');

            $table->string('task', 255);
            $table->dateTime('start_date');
            $table->dateTime('target_date');
            
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
        Schema::dropIfExists('task_template_details');
    }
}
