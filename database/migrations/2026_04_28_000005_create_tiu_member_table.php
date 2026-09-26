<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTiuMemberTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tiu_member', function (Blueprint $table) {
            $table->id('tiu_member_id')->comment('Primary key matching legacy');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('phone_number', 50);
            $table->string('email', 100)->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('age', 20)->nullable();
            $table->string('occupation', 50)->nullable();
            $table->string('member_role', 50)->default('Member');
            $table->string('password', 200);
            $table->string('status', 11)->default('1');
            $table->string('email_verified', 50)->default('No');
            $table->date('date_registered')->nullable();

            // Foreign key relationships
            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('community_id')->nullable();
            $table->unsignedBigInteger('church_type_id')->nullable();
            $table->unsignedBigInteger('bus_stop_id')->nullable();

            // JSON fields (stored as text in legacy, now JSON)
            $table->json('department_name')->nullable()->comment('JSON array of departments');
            $table->json('cluster')->nullable()->comment('JSON array of clusters');
            $table->json('house_fellowship')->nullable()->comment('JSON array of house fellowships');
            $table->string('subgroup', 100)->nullable();

            // Address & contact info
            $table->text('residential_address')->nullable();
            $table->string('next_of_kin_name', 50)->nullable();
            $table->string('next_of_kin_phone', 20)->nullable();
            $table->string('community_other', 100)->nullable();
            $table->string('parent_gaudian', 100)->nullable();
            $table->string('Phone', 50)->nullable()->comment('Alternate phone');
            $table->string('Relationship', 50)->nullable();

            // Oversight & accountability
            $table->json('oversight_extra1')->nullable();
            $table->string('accountability_extra3', 100)->nullable();

            // Profile
            $table->string('picture_part', 255)->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('campus_id')->references('cid')->on('campus')->onDelete('set null');
            $table->foreign('community_id')->references('id')->on('communities')->onDelete('set null');
            $table->foreign('church_type_id')->references('id')->on('church_type')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tiu_member');
    }
}
