<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrayerSuggestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prayer_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tiu_member_id')->nullable()->comment('The member who submitted');
            $table->unsignedBigInteger('campus_id')->nullable()->comment('Campus identifier (for future use)');
            $table->enum('type', ['prayer', 'suggestion'])->comment('Type of submission');
            $table->text('message')->comment('The prayer request or suggestion content');
            $table->string('status', 20)->default('pending')->comment('pending, reviewed, approved');
            $table->timestamps();

            // Index only (no foreign key constraint due to possible engine mismatch)
            $table->index('tiu_member_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prayer_suggestions');
    }
}
