<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('paypal_email')->nullable()->after('currency');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('pending')->after('status');
            $table->string('paypal_transaction_id')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('paypal_email');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'paypal_transaction_id']);
        });
    }
};
