<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChildCeremoniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('child_ceremonies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id'); // FK to tiu_member
            $table->enum('ceremony_type', ['naming', 'dedication']);
            
            // Fields for NAMING
            $table->text('naming_address')->nullable();
            $table->string('naming_landmarks')->nullable();
            $table->string('child_gender')->nullable(); // Male, Female, Twins (Same Sex), Twins (Opposite Sex)
            $table->string('child_position')->nullable();
            $table->date('date_of_delivery')->nullable();
            $table->date('proposed_naming_date')->nullable();
            $table->string('proposed_naming_time')->nullable();
            $table->text('proposed_child_names')->nullable();
            $table->text('parent_background')->nullable();
            $table->string('house_fellowship')->nullable();
            $table->string('parent_department')->nullable();
            $table->string('cluster_hod')->nullable();
            
            // Fields for DEDICATION
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('dedication_child_name')->nullable();
            $table->date('dedication_date')->nullable();
            $table->string('church_serving_unit')->nullable();
            
            $table->timestamps();
            
            // Index
            $table->index('member_id');
            $table->index('ceremony_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('child_ceremonies');
    }
}
