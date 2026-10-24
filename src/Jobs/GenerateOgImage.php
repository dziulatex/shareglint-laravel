<?php

declare(strict_types=1);

namespace ImageApi\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ImageApi\ImageApiException;
use ImageApi\Laravel\Contracts\OgImageable;
use ImageApi\Laravel\ImageApiManager;
use RuntimeException;

/**
 * Renders a model's OG image through the API and stores the file URL.
 *
 * The idempotency key is derived from the template and the fields, so a
 * retried job (or the same values saved twice) never renders or charges
 * twice. The URL is written with saveQuietly(): a normal save would fire
 * `saved` again and queue this job in a loop.
 */
final class GenerateOgImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    /** The model may be gone by the time the job runs — then there is nothing to do. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Model&OgImageable $model)
    {
        $this->onConnection(config('imageapi.queue.connection'));
        $this->onQueue(config('imageapi.queue.name'));
    }

    public function handle(ImageApiManager $api): void
    {
        $model = $this->model;
        $column = $model->ogImageColumn();

        if ($column === null) {
            return;
        }

        $template = $model->ogImageTemplate();
        $fields = ImageApiManager::normalize($model->ogImageFields());
        $format = $api->format();

        try {
            $render = $api->client()->renderIdempotent(
                self::idempotencyKey($model, $template, $fields, $format),
                $template,
                $fields,
                $format,
            );
        } catch (ImageApiException $e) {
            // Sync mode ran out of time, but the render goes on — come back for it.
            if ($e->isOverloaded()) {
                $this->release(15);

                return;
            }

            // An empty quota does not refill within the retry window.
            if ($e->isQuotaExceeded()) {
                $this->fail($e);

                return;
            }

            throw $e;
        }

        if (! $render->isReady()) {
            throw new RuntimeException("Image API render {$render->id} finished as '{$render->status}': ".($render->error ?? 'no URL'));
        }

        $model->forceFill([$column => $render->url])->saveQuietly();
    }

    /** @param array<string, string> $fields */
    public static function idempotencyKey(Model&OgImageable $model, string $template, array $fields, string $format): string
    {
        return 'og-'.hash('sha256', implode('|', [$model->getMorphClass(), (string) $model->getKey(), $template, $format, json_encode($fields)]));
    }
}
