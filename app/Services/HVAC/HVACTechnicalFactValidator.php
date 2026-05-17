<?php

namespace App\Services\HVAC;

use App\Models\AiContentJob;
use App\Models\Product;
use App\Services\AI\AIContentGovernance;
use App\Services\AI\Governance\HVACTechnicalFactValidator as GovernanceFactValidator;

class HVACTechnicalFactValidator
{
    public function __construct(
        private readonly AIContentGovernance $governance,
        private readonly GovernanceFactValidator $validator,
    ) {}

    public function validateProductContent(Product $product, array|string $content): array
    {
        $context = $this->governance->buildProductContext($product);

        return is_array($content)
            ? $this->governance->validatePayload($content, $context, ['excerpt', 'content_html', 'seo_title', 'meta_description'])
            : $this->validator->validateText($content, $context);
    }

    public function validateBlogContent(AiContentJob $job, array|string $content, array $btuInputs = []): array
    {
        $context = $this->governance->buildBlogContext($job, $btuInputs);

        return is_array($content)
            ? $this->governance->validatePayload($content, $context, ['excerpt', 'content_html', 'title', 'meta_description'])
            : $this->validator->validateText($content, $context);
    }
}
