<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterIncidentInvolvedPartiesTable1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('incident_involved_parties', function($table)
        {
            $table->string('insurance_file_name', 255)->nullable();
            $table->string('insurance_original_name', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('incident_involved_parties', function($table)
        {
            $table->dropColumn('insurance_file_name');
            $table->dropColumn('insurance_original_name');
        });
    }
}
