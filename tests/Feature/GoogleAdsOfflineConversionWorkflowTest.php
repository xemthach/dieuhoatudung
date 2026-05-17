<?php

namespace Tests\Feature;

use App\Models\GoogleAdsConversionImport;
use App\Models\QuoteRequest;
use App\Services\Marketing\GoogleAdsOfflineConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAdsOfflineConversionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_quote_records_pending_google_ads_offline_conversion(): void
    {
        config([
            'marketing_integrations.google_ads.customer_id' => '123-456-7890',
            'marketing_integrations.google_ads.conversion_action_id' => '987654321',
        ]);

        $response = $this->postJson(route('quote.quick'), [
            'full_name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'email' => 'lead@example.com',
            'source_page' => 'https://example.com/san-pham/a',
            'gclid' => 'test-gclid',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $quote = QuoteRequest::firstOrFail();
        $conversion = GoogleAdsConversionImport::firstOrFail();

        $this->assertSame('test-gclid', $quote->gclid);
        $this->assertSame(QuoteRequest::class, $conversion->source_type);
        $this->assertSame($quote->id, $conversion->source_id);
        $this->assertSame('quick_quote', $conversion->event_name);
        $this->assertSame('pending', $conversion->status);
        $this->assertSame('test-gclid', $conversion->gclid);
        $this->assertSame('customers/1234567890/conversionActions/987654321', $conversion->conversion_action_resource_name);
        $this->assertSame('quote-'.$quote->id, $conversion->order_id);
        $this->assertNotSame('lead@example.com', $conversion->user_identifiers_json[0]['hashedEmail']);
    }

    public function test_upload_pending_posts_click_conversion_to_google_ads_api(): void
    {
        config([
            'marketing_integrations.google_ads.api_version' => 'v20',
            'marketing_integrations.google_ads.access_token' => 'access-token',
            'marketing_integrations.google_ads.developer_token' => 'developer-token',
            'marketing_integrations.google_ads.customer_id' => '1234567890',
        ]);

        Http::fake([
            'googleads.googleapis.com/v20/customers/1234567890:uploadClickConversions' => Http::response([
                'results' => [
                    [
                        'gclid' => 'test-gclid',
                        'conversionAction' => 'customers/1234567890/conversionActions/987654321',
                    ],
                ],
                'jobId' => '42',
            ]),
        ]);

        $conversion = GoogleAdsConversionImport::create([
            'source_type' => QuoteRequest::class,
            'source_id' => 1,
            'event_name' => 'submit_quote',
            'status' => 'pending',
            'customer_id' => '1234567890',
            'conversion_action_resource_name' => 'customers/1234567890/conversionActions/987654321',
            'gclid' => 'test-gclid',
            'conversion_date_time' => now(),
            'conversion_value' => 1000000,
            'currency_code' => 'VND',
            'order_id' => 'quote-1',
            'user_identifiers_json' => [
                ['hashedPhoneNumber' => hash('sha256', '+84901234567')],
            ],
        ]);

        $summary = app(GoogleAdsOfflineConversionService::class)->uploadPending();

        $this->assertSame(1, $summary['checked']);
        $this->assertSame(1, $summary['uploaded']);
        $this->assertSame('synced', $conversion->fresh()->status);
        $this->assertNotNull($conversion->fresh()->uploaded_at);

        Http::assertSent(fn ($request) => $request->url() === 'https://googleads.googleapis.com/v20/customers/1234567890:uploadClickConversions'
            && $request->hasHeader('Authorization', 'Bearer access-token')
            && $request->hasHeader('developer-token', 'developer-token')
            && $request['partialFailure'] === true
            && $request['conversions'][0]['gclid'] === 'test-gclid'
            && $request['conversions'][0]['conversionAction'] === 'customers/1234567890/conversionActions/987654321');
    }

    public function test_upload_pending_marks_partial_failure_as_failed(): void
    {
        config([
            'marketing_integrations.google_ads.access_token' => 'access-token',
            'marketing_integrations.google_ads.developer_token' => 'developer-token',
            'marketing_integrations.google_ads.customer_id' => '1234567890',
        ]);

        Http::fake([
            'googleads.googleapis.com/*' => Http::response([
                'partialFailureError' => [
                    'message' => 'CLICK_NOT_FOUND',
                ],
                'results' => [],
            ]),
        ]);

        $conversion = GoogleAdsConversionImport::create([
            'source_type' => QuoteRequest::class,
            'source_id' => 2,
            'status' => 'pending',
            'conversion_action_resource_name' => 'customers/1234567890/conversionActions/987654321',
            'gclid' => 'missing-click',
            'conversion_date_time' => now(),
            'order_id' => 'quote-2',
        ]);

        $summary = app(GoogleAdsOfflineConversionService::class)->uploadPending();

        $this->assertSame(1, $summary['failed']);
        $this->assertSame('failed', $conversion->fresh()->status);
        $this->assertSame('partial_failure', $conversion->fresh()->failed_reason);
        $this->assertSame('CLICK_NOT_FOUND', $conversion->fresh()->last_error_message);
    }
}
