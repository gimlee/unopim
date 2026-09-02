<?php

namespace Webkul\Category\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Webkul\Category\Models\CategoryProxy;
use Webkul\Category\Models\ProductCategoryAssignment;
use Webkul\Category\Models\ProductPlatformCategoryAssignment;
use Webkul\Category\Observers\CategoryObserver;
use Webkul\Category\Services\CategoryAdditionalDataMapper;
use Webkul\MagicAI\Enums\AiProvider;
use Webkul\MagicAI\Repository\MagicAIPlatformRepository;

class CategoryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'category');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'category');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        CategoryProxy::observe(CategoryObserver::class);

        Event::listen('unopim.admin.catalog.product.edit.form.categories.after', function ($viewRenderEventManager): void {
            if (auth()->guard('admin')->check()) {
                $viewRenderEventManager->addTemplate('category::taxonomy.product-panel');
            }
        });

        View::composer('category::taxonomy.product-panel', function ($view): void {
            $editedProduct = $view->getData()['product'];
            $product = $editedProduct->parent_id
                ? $editedProduct->parent()->firstOrFail()
                : $editedProduct;
            $primary = ProductCategoryAssignment::query()
                ->where('product_id', $product->id)
                ->where('role', 'primary')
                ->whereIn('status', ['confirmed', 'proposed'])
                ->orderByRaw("CASE status WHEN 'confirmed' THEN 0 ELSE 1 END")
                ->first();
            $platformAssignment = ProductPlatformCategoryAssignment::with('platformCategory.taxonomy')
                ->where('product_id', $product->id)
                ->where('platform', 'tiktok')
                ->first();
            $aiPlatforms = resolve(MagicAIPlatformRepository::class)
                ->getActiveList()
                ->reject(fn ($platform) => $platform->provider === AiProvider::ZhipuCodePlan->value)
                ->map(fn ($platform): array => [
                    'id'         => $platform->id,
                    'label'      => $platform->label,
                    'provider'   => $platform->provider,
                    'models'     => $platform->model_list,
                    'is_default' => $platform->is_default,
                ])
                ->values();

            $view->with([
                'product'                  => $product,
                'selectedStandardCategory' => $primary?->category?->code
                    ?: data_get($product->values, 'categories.0', ''),
                'selectedPlatformCategory' => $platformAssignment?->platformCategory?->external_id ?: '',
                'aiPlatforms'              => $aiPlatforms,
            ]);
        });
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();

        $this->registerFacades();
    }

    /**
     * Register configuration.
     */
    public function registerConfig(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/category_field_types.php', 'category_field_types');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
    }

    /**
     * Register Bouncer as a singleton.
     */
    protected function registerFacades(): void
    {
        /**
         * Product value mapper
         */
        $this->app->singleton('category_additional_data_mapper', fn ($app): CategoryAdditionalDataMapper => new CategoryAdditionalDataMapper);
    }
}
