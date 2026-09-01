<?php

namespace Webkul\Product\Validator;

use Webkul\Category\Models\Category;
use Webkul\Product\Validator\Abstract\ValuesValidator;

class ProductCategoriesValidator extends ValuesValidator
{
    /**
     * Validation rules to be used on the data
     */
    protected function generateRules(mixed $data, ?string $productId, array $options): array
    {
        return [
            '*' => [
                'exists:categories,code',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Single indexed lookup per submitted code — avoids materializing every
                    // root-category code in PHP on each validate call.
                    if (Category::where('code', $value)->whereNull('parent_id')->exists()) {
                        $fail(trans('admin::app.catalog.products.categories.root-not-allowed'));
                    }

                    $category = Category::where('code', $value)->first();
                    if ($category && in_array($category->taxonomy_type, ['standard', 'container', 'source', 'platform'], true)
                        && (! $category->is_assignable || $category->status !== 'active')) {
                        $fail('Only active assignable standard category leaves can be assigned to products.');
                    }
                },
            ],
        ];
    }

    /**
     * Get validation messages for the validator
     */
    protected function getMessages(): array
    {
        return [
            '*.exists' => trans('validation.exists-value'),
        ];
    }
}
