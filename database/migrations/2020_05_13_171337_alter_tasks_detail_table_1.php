<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTasksDetailTable1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_details', function($table)
        {
            $table->unsignedBigInteger('rated_by_id')->nullable();
            $table->foreign('rated_by_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

            $table->tinyInteger('rating')->nullable();
            $table->dateTime('rating_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task_details', function($table)
        {
            $table->dropColumn('rated_by_id');
            $table->dropColumn('rating');
            $table->dropColumn('rating_date');
        });
    }
}
