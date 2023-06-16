<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientPinBucketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('client_pin_buckets', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

            $table->integer('quantity');

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
        Schema::dropIfExists('client_pin_buckets');
    }
}
