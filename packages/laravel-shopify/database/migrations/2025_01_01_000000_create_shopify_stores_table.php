<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->string('shop_domain')->unique();
            $table->text('access_token')->nullable();
            $table->string('token_type')->default('offline');
            $table->text('scopes')->nullable();
            $table->string('plan')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('currency')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('shopify.store.table', 'shopify_stores');
    }
};
