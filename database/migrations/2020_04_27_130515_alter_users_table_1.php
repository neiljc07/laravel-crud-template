<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterUsersTable1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function($table)
        {
            $table->unsignedBigInteger('user_type_id')->after('password');
            $table->foreign('user_type_id')
                    ->references('id')
                    ->on('user_types')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');

            $table->string('first_name', 50)->default('');
            $table->string('last_name', 50)->default('');
            $table->string('picture', 255)->default('');
            $table->string('position', 50)->default('');
            $table->datetime('last_check_in')->nullable();
            $table->double('last_lat', 12, 8)->nullable();
            $table->double('last_lng', 12, 8)->nullable();
            $table->string('last_address', 255)->nullable();
            $table->tinyInteger('is_enabled')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function($table)
        {
            $table->dropColumn('user_type_id');
            $table->dropColumn('client_id');
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
            $table->dropColumn('picture');
            $table->dropColumn('position');
            $table->dropColumn('last_check_in');
            $table->dropColumn('last_lat');
            $table->dropColumn('last_lng');
            $table->dropColumn('last_address');
            $table->dropColumn('is_enabled');
        });
    }
}
