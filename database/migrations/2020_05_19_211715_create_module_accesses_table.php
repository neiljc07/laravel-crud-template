<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModuleAccessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_access', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

            $table->unsignedBigInteger('module_id')->nullable();
            $table->foreign('module_id')
                    ->references('id')
                    ->on('modules')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

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
        Schema::dropIfExists('module_access');
    }
}
