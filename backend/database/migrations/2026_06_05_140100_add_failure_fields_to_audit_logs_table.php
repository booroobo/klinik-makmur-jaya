<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('status', 20)->default('success')->after('role')->index();
            $table->string('actor_email')->nullable()->after('status')->index();
            $table->unsignedSmallInteger('http_status')->nullable()->after('description')->index();
            $table->text('failure_reason')->nullable()->after('http_status');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'status',
                'actor_email',
                'http_status',
                'failure_reason',
            ]);
        });
    }
};
