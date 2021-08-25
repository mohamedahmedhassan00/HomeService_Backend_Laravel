<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePromptBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prompt_bookings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('booking_key')->nullable();
            $table->longText('options')->nullable();
            $table->smallInteger('quantity')->nullable()->default(1);
            $table->bigInteger('user_id')->nullable()->unsigned();
            $table->integer('category_id')->nullable()->unsigned();
            $table->string('price_range')->nullable();
            $table->integer('booking_status_id')->nullable()->unsigned();
            $table->longText('address');
            $table->integer('payment_id')->nullable()->unsigned();
            $table->longText('coupon')->nullable();
            $table->dateTime('booking_at')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->text('hint')->nullable();
            $table->boolean('cancel')->nullable()->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null')->onUpdate('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null')->onUpdate('set null');
            $table->foreign('booking_status_id')->references('id')->on('booking_statuses')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null')->onUpdate('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prompt_bookings');
    }
}
