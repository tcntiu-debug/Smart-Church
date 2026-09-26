<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChurchMemberContactTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Extended contact information for church members.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('church_member_contact', function (Blueprint $table) {
            $table->id('church_member_id')->comment('Primary key matching legacy');
            $table->string('mobile_number_1', 50);
            $table->string('mobile_number_2', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('social_media', 50)->nullable();
            $table->string('lcda_of_residence', 10)->nullable();
            $table->string('house_number', 50)->nullable();
            $table->string('street_address', 50)->nullable();
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
        Schema::dropIfExists('church_member_contact');
    }
}
