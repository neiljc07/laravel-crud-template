<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIncidentInvolvedPartiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('incident_involved_parties', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('incident_id');
            $table->foreign('incident_id')
                    ->references('id')
                    ->on('incidents')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');


            $table->string('name', 255)->default('');
            $table->string('license_file_name', 255)->nullable();
            $table->string('license_original_name', 255)->nullable();

            $table->string('or_file_name', 255)->nullable();
            $table->string('or_original_name', 255)->nullable();

            $table->string('cr_file_name', 255)->nullable();
            $table->string('cr_original_name', 255)->nullable();

            $table->string('signature', 255)->nullable();

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
        Schema::dropIfExists('incident_involved_parties');
    }
}
