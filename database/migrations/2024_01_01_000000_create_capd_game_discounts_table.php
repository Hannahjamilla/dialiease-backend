<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCapdGameDiscountsTable extends Migration
{
    public function up()
    {
        Schema::create('capd_game_discounts', function (Blueprint $table) {
            $table->id('discount_id');
            $table->unsignedBigInteger('userID');
            $table->integer('score');
            $table->integer('total_questions');
            $table->decimal('percentage', 5, 2);
            $table->decimal('discount_earned', 5, 2);
            $table->boolean('is_used')->default(false);
            $table->timestamp('valid_until');
            $table->timestamps();

            $table->foreign('userID')->references('userID')->on('users')->onDelete('cascade');
            $table->index(['userID', 'is_used', 'valid_until']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('capd_game_discounts');
    }
}