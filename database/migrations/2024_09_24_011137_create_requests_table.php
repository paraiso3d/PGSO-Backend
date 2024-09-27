<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('control_no');
            $table->string('description');
            $table->string('officename');
            $table->string('location_name');
            $table->string('area');
            $table->boolean('overtime');
            $table->string('category_name');
            $table->string('file_path')->nullable();
            $table->string('status')->default('Pending');
            $table->string('fiscal_year');
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
        Schema::dropIfExists('requests');
    }
}
