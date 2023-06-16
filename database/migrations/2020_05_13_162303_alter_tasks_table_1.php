<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTasksTable1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tasks', function($table)
        {
            $table->tinyInteger('for_verification')->nullable();
            $table->dateTime('for_verification_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tasks', function($table)
        {
            $table->dropColumn('for_verification');
            $table->dropColumn('for_verification_date');
        });
    }
}
