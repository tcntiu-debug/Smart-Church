<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChildrenChurchTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('children_church', function (Blueprint $table) {
            $table->id('child_id');
            $table->string('parent_name', 255)->nullable()->comment('Parent/Guardian Name');
            $table->string('child_name', 255)->comment('Child Name');
            $table->string('dob', 100)->nullable()->comment('Date of Birth');
            $table->string('parent_phone', 255)->nullable()->comment('Parent/Guardian Phone Number');
            $table->integer('campus_id')->comment('Campus ID');
            $table->timestamps();

            // Foreign key
            $table->foreign('campus_id')->references('cid')->on('campus')->onDelete('cascade');
            
            // Indexes for fast search
            $table->index('child_name');
            $table->index('campus_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('children_church');
    }
}
