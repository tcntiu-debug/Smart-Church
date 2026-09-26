<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveUnusedColumnsFromChildCeremonies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('child_ceremonies', function (Blueprint $table) {
            $table->dropColumn(['house_fellowship', 'parent_department', 'cluster_hod', 'church_serving_unit']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('child_ceremonies', function (Blueprint $table) {
            $table->string('house_fellowship')->nullable()->after('parent_background');
            $table->string('parent_department')->nullable()->after('house_fellowship');
            $table->string('cluster_hod')->nullable()->after('parent_department');
            $table->string('church_serving_unit')->nullable()->after('dedication_date');
        });
    }
}
