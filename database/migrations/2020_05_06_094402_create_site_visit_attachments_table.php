<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiteVisitAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('site_visit_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('site_visit_id');
            $table->foreign('site_visit_id')
                    ->references('id')
                    ->on('site_visits')
                    ->onDelete('restrict')
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
        Schema::dropIfExists('site_visit_attachments');
    }
}
