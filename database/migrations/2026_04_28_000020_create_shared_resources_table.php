<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSharedResourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Uploaded/shared resource files for church members.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shared_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('file_name', 255);
            $table->string('file_path', 512);
            $table->string('file_type', 100)->nullable()->comment('MIME type');
            $table->integer('file_size')->nullable()->comment('Size in bytes');
            $table->unsignedBigInteger('uploader_id')->nullable()->comment('tiu_member_id of uploader');
            $table->timestamps();

            $table->foreign('uploader_id')->references('tiu_member_id')->on('tiu_member')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shared_resources');
    }
}
