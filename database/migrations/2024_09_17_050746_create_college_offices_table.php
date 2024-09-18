<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCollegeOfficesTable extends Migration
{
    public function up()
    {
        Schema::create('college_offices', function (Blueprint $table) {
            $table->id();
            $table->string('officename');
            $table->string('abbreviation');
            $table->enum('officetype', ['Academic', 'Non-Academic']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('college_offices');
    }
}
