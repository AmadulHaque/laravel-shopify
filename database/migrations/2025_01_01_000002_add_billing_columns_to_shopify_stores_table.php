<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table): void {
            $table->string('subscription_id')->nullable()->index()->after('plan');
            $table->string('subscription_status')->nullable()->after('subscription_id');
            $table->timestamp('plan_activated_at')->nullable()->after('subscription_status');
            $table->timestamp('trial_ends_at')->nullable()->after('plan_activated_at');
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table): void {
            $table->dropColumn([
                'subscription_id',
                'subscription_status',
                'plan_activated_at',
                'trial_ends_at',
            ]);
        });
    }

    private function table(): string
    {
        return config('shopify.store.table', 'shopify_stores');
    }
};
