<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTaskAttachmentTable1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_attachments', function($table)
        {
            $table->tinyInteger('is_image')->default(0);
            $table->tinyInteger('is_locked')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task_attachments', function($table)
        {
            $table->dropColumn('is_image');
            $table->dropColumn('is_locked');
        });
    }
}
