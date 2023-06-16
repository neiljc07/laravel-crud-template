<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionTypeSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_type_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            $table->unsignedBigInteger('subscription_type_id');
            $table->foreign('subscription_type_id')
                    ->references('id')
                    ->on('subscription_types')
                    ->onDelete('cascade')
                    ->onUpdate('restrict');

            $table->string('code', 50);
            $table->string('value', 255);
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
        Schema::dropIfExists('subscription_type_settings');
    }
}
