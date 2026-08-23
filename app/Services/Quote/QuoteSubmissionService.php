<?php

namespace App\Services\Quote;

use App\Models\Lead;
use App\Models\Product;
use App\Models\QuoteRequest;
use Illuminate\Support\Facades\DB;

final class QuoteSubmissionService
{
    /**
     * @return array{quote: QuoteRequest, created: bool}
     */
    public function create(
        array $quoteAttributes,
        array $contactData,
        array $leadExtra,
        ?Product $product = null,
    ): array {
        return DB::transaction(function () use ($quoteAttributes, $contactData, $leadExtra, $product): array {
            $quote = QuoteRequest::query()->firstOrCreate(
                ['submission_token' => $quoteAttributes['submission_token']],
                $quoteAttributes,
            );

            if (! $quote->wasRecentlyCreated) {
                return ['quote' => $quote, 'created' => false];
            }

            $leadExtra['quote_request_id'] = $quote->id;

            if ($product) {
                Lead::createProductLead($contactData, $product, $leadExtra);
            } else {
                Lead::createGeneralLead($contactData, $leadExtra);
            }

            return ['quote' => $quote, 'created' => true];
        }, 3);
    }
}
