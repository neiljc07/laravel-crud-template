<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIncidentAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('incident_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('incident_id');
            $table->foreign('incident_id')
                    ->references('id')
                    ->on('incidents')
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
        Schema::dropIfExists('incident_attachments');
    }
}
