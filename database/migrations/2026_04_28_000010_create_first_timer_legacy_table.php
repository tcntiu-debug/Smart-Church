<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFirstTimerLegacyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * This is the LEGACY first_timer (singular) table from the TIU system.
     * Note: There's a separate first_timers (plural) table created by a previous migration.
     * Both are kept for backwards compatibility until full migration is complete.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('first_timer', function (Blueprint $table) {
            $table->id('first_timer_id')->comment('Primary key matching legacy');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('phone_number', 200);
            $table->string('email', 50)->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('age', 50)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('occupation', 50)->nullable();
            $table->string('attendant_type', 50)->nullable();
            $table->integer('attendance_count')->default(0);
            $table->string('status', 50)->default('New');
            $table->date('register_date')->nullable();
            $table->date('status_change_date')->nullable();
            $table->text('address')->nullable();
            $table->string('how_did_you_hear', 100)->nullable();
            $table->string('born_again', 20)->nullable()->default('');
            $table->string('water_baptism', 20)->nullable()->default('');
            $table->string('holy_ghost_baptism', 20)->nullable()->default('');

            // Foreign keys
            $table->unsignedBigInteger('church_type_id')->nullable();
            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('community_id')->nullable();

            // Registration info
            $table->string('registered_by', 200)->nullable();
            $table->string('parent_name', 200)->nullable();
            $table->string('parent_phone', 100)->nullable();
            $table->string('relationship', 50)->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('church_type_id')->references('id')->on('church_type')->onDelete('set null');
            $table->foreign('campus_id')->references('cid')->on('campus')->onDelete('set null');
            $table->foreign('community_id')->references('id')->on('communities')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('first_timer');
    }
}
