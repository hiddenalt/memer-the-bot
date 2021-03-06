<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePicbankTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('picbank', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger("bank_id")->unsigned()->default(1);
            $table->longText("path");
            $table->enum("status", [
                "pending",
                "denied",
                "accepted"
            ])->default("accepted");
            $table->timestamps();

            $table->foreign('bank_id')->references('id')->on('banks_list')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('picbank');
    }
}
