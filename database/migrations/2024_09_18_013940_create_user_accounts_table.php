<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAccountsTable extends Migration
{
    public function up()
    {
        Schema::create('user_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('middleinital', 2)->nullable();
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('usertype'); // Use string for referential integrity
            $table->string('office_college'); // Use string for referential integrity
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_accounts');
    }
}
