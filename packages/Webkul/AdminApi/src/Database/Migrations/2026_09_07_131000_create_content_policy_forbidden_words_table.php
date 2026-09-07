<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_policy_forbidden_words', function (Blueprint $table): void {
            $table->id();
            $table->string('term', 255);
            $table->string('normalized_term', 255)->unique();
            $table->boolean('status')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'term'], 'content_policy_words_status_term_idx');
        });

        $configured = DB::table('core_config')
            ->where('code', 'general.content_policy.settings.forbidden_words')
            ->pluck('value')
            ->filter()
            ->flatMap(fn (string $value) => preg_split('/[\r\n,，]+/u', $value) ?: []);
        $words = collect(config('content_policy.default_forbidden_words', []))
            ->merge($configured)
            ->map(fn ($word): string => trim((string) $word))
            ->filter()
            ->unique(fn (string $word): string => mb_strtolower($word))
            ->values();
        $now = now();

        DB::table('content_policy_forbidden_words')->insert(
            $words->map(fn (string $word): array => [
                'term'            => $word,
                'normalized_term' => mb_strtolower($word),
                'status'          => true,
                'notes'           => '系统预置电商平台词',
                'created_at'      => $now,
                'updated_at'      => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('content_policy_forbidden_words');
    }
};
