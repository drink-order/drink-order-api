<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSessionFieldsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('session_id')->nullable()->after('user_id');
            $table->string('customer_name')->nullable()->after('session_id');
            $table->string('order_number')->unique()->nullable()->after('customer_name');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['session_id', 'customer_name', 'order_number']);
        });
    }
}