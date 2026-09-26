<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChurchTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('church_type', function (Blueprint $table) {
            $table->id();
            $table->string('church_type_name', 100)->comment('e.g., Main Service, Youth, Children');
            $table->string('church_type_lead_id', 11)->comment('References tiu_member.tiu_member_id');
            $table->timestamps(); // Added for Laravel best practice
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('church_type');
    }
}
