# shareglint/laravel

Open Graph images for Laravel with [Shareglint](https://shareglint.com): signed `og:image` URLs, a Blade
component, and a model trait that renders the image in a queued job when a post is published.

```bash
composer require shareglint/laravel
php artisan vendor:publish --tag=shareglint-config   # optional
```

```dotenv
SHAREGLINT_ACCOUNT_ID=acc_…        # dashboard or GET /v1/account
SHAREGLINT_SIGNING_SECRET=sign_…   # signs og:image URLs
SHAREGLINT_KEY=sk_…                # only for the queued job / API calls
# SHAREGLINT_BASE_URL=https://shareglint.com
# SHAREGLINT_TEMPLATE=blog-og      SHAREGLINT_EXTENSION=png (jpg, webp, pdf)
# SHAREGLINT_QUEUE_CONNECTION=     SHAREGLINT_QUEUE=
```

## Signed URLs — no API call, no queue

```blade
<head>
  <x-og-image template="blog-og" :fields="['title' => $post->title, 'author' => $post->author->name]" :alt="$post->title" />
</head>
```

Prints `og:image` (+ width/height, default 1200×630), optional `og:image:alt`, `twitter:card` and
`twitter:image`. The image renders on the first crawler hit and is cached; the same values never cost
quota twice. Elsewhere:

```php
og_image('blog-og', ['title' => $post->title, 'locale' => 'en']);       // helper
Shareglint::url('blog-og', ['title' => $post->title], 'jpg');            // facade
Shareglint::autoOgUrl(url()->current());                                 // /u/ auto-OG, no secret
Shareglint::render('blog-og', ['title' => 'Hi']);                        // any SDK method (shareglint/shareglint-php)
```

`null` fields are left out, booleans become `1`/`0`, dates `Y-m-d`.

## Stored images for models

```php
use Shareglint\Laravel\Concerns\HasOgImage;
use Shareglint\Laravel\Contracts\OgImageable;

class Post extends Model implements OgImageable
{
    use HasOgImage;

    public function ogImageFields(): array
    {
        return ['title' => $this->title, 'author' => $this->author->name, 'date' => $this->published_at];
    }

    // Optional overrides:
    public function ogImageTemplate(): string { return 'blog-og'; }
    public function ogImageColumn(): ?string { return 'og_image'; }   // a nullable string column
    public function ogImageWatched(): ?array { return ['title', 'author_id']; } // null = any change
}
```

On create, and on an update that changes a watched attribute, `GenerateOgImage` is queued **after the
transaction commits**, renders through the API with an idempotency key derived from the fields (a
retry never charges twice), and writes the file URL with `saveQuietly()`. Until then
`$post->ogImageUrl()` — and `<x-og-image :model="$post" />` — return the signed URL of the same image,
so no page goes out without one. A full quota fails the job without retries; a busy API releases it
for 15 seconds.

```blade
<x-og-image :model="$post" />
<x-og-image auto />                                   {{-- auto-OG of the current URL --}}
<x-og-image page="https://yourblog.com/hello" extension="webp" />
```

## Tests

`sh test.sh` (from `packages/`: `npm run test:laravel`) — Composer and Pest with Orchestra Testbench in
Docker. The job tests run the real PHP SDK against a stand-in API under `php -S`.

MIT
