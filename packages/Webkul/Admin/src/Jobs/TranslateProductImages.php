<?php

namespace Webkul\Admin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use Webkul\Admin\Services\ProductImageTranslationService;

class TranslateProductImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public string $translationJobId)
    {
        $this->onQueue('image-translations');
    }

    public function handle(ProductImageTranslationService $service): void
    {
        $record = DB::table('product_image_translation_jobs')->where('id', $this->translationJobId)->first();

        if (! $record || in_array($record->status, ['completed', 'failed'], true)) {
            return;
        }

        DB::table('product_image_translation_jobs')->where('id', $this->translationJobId)->update([
            'status'     => 'running',
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        $outcome = $service->translate(json_decode($record->payload, true, flags: JSON_THROW_ON_ERROR));

        if (empty($outcome['results']) && ! empty($outcome['errors'])) {
            throw new RuntimeException(implode('; ', array_slice($outcome['errors'], 0, 3)));
        }

        DB::table('product_image_translation_jobs')->where('id', $this->translationJobId)->update([
            'status'       => 'completed',
            'results'      => json_encode($outcome['results'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'errors'       => json_encode($outcome['errors'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'completed_at' => now(),
            'updated_at'   => now(),
        ]);
    }

    public function failed(?Throwable $error): void
    {
        DB::table('product_image_translation_jobs')->where('id', $this->translationJobId)->update([
            'status'       => 'failed',
            'error'        => $error?->getMessage() ?: '图片翻译任务执行失败',
            'completed_at' => now(),
            'updated_at'   => now(),
        ]);
    }
}
