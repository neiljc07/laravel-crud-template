<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVwLatestLocationsView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            CREATE OR REPLACE VIEW vw_latest_locations
            as
            select 
                max(a.id) AS id, a.user_id, DATE_FORMAT(a.created_at, '%Y-%m-%d') as date
            from locations a
            group by a.user_id, DATE_FORMAT(a.created_at, '%Y-%m-%d')
        ");
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    DB::statement("DROP VIEW IF EXISTS vw_latest_locations");
  }
}
