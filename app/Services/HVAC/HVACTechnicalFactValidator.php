<?php

namespace App\Services\HVAC;

use App\Models\AiContentJob;
use App\Models\Product;
use App\Services\AI\AIContentGovernance;

class HVACTechnicalFactValidator
{
    public function __construct(
        private readonly AIContentGovernance $governance,
    ) {}

    public function validateProductContent(Product $product, array|string $content): array
    {
        $context = $this->governance->buildProductContext($product);

        return is_array($content)
            ? $this->governance->validatePayload($content, $context, $product->name)
            : $this->governance->validateText($content, $context);
    }

    public function validateBlogContent(AiContentJob $job, array|string $content, array $btuInputs = []): array
    {
        $context = $this->governance->buildBlogContext($job, $btuInputs);

        return is_array($content)
            ? $this->governance->validatePayload($content, $context, $job->keyword ?: $job->topic)
            : $this->governance->validateText($content, $context);
    }
}
