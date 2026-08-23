<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Services\Mail\MailDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class QuoteFormWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_has_truthful_three_step_contract(): void
    {
        $this->get(route('quote.index'))
            ->assertOk()
            ->assertSee('3 bước ngắn')
            ->assertSee('totalSteps: 3', false)
            ->assertDontSee('Quy trình 5 bước')
            ->assertDontSee('chỉ mất 2 phút');
    }

    public function test_minimal_direct_request_creates_one_linked_quote_and_lead(): void
    {
        $token = (string) Str::uuid();

        $response = $this->post(route('quote.store'), [
            'submission_token' => $token,
            'entry_context' => 'direct',
            'full_name' => 'Nguyễn An',
            'phone' => '+84 901 234 567',
        ]);

        $response->assertRedirect(route('quote.index'));
        $this->assertDatabaseHas('quote_requests', [
            'submission_token' => $token,
            'entry_context' => 'direct',
            'phone' => '0901234567',
            'area_m2' => null,
            'budget_range' => null,
        ]);

        $quote = QuoteRequest::query()->sole();
        $this->assertDatabaseHas('leads', [
            'quote_request_id' => $quote->id,
            'phone' => '0901234567',
        ]);
    }

    public function test_repeated_submission_token_is_idempotent(): void
    {
        $payload = [
            'submission_token' => (string) Str::uuid(),
            'entry_context' => 'direct',
            'full_name' => 'Khách thử nghiệm',
            'phone' => '0901234567',
        ];

        $this->post(route('quote.store'), $payload)->assertRedirect(route('quote.index'));
        $this->post(route('quote.store'), $payload)->assertRedirect(route('quote.index'));

        $this->assertSame(1, QuoteRequest::count());
        $this->assertSame(1, Lead::count());
    }

    public function test_product_origin_uses_server_resolved_product_context(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->get(route('quote.index', ['product' => $product->slug]))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('value="product"', false);

        $this->post(route('quote.store'), [
            'submission_token' => (string) Str::uuid(),
            'entry_context' => 'product',
            'lead_type' => 'product',
            'product_id' => $product->id,
            'full_name' => 'Khách sản phẩm',
            'phone' => '0912345678',
        ])->assertRedirect(route('quote.index'));

        $this->assertDatabaseHas('quote_requests', [
            'entry_context' => 'product',
            'product_id' => $product->id,
            'product_name' => $product->name,
        ]);
        $this->assertDatabaseHas('leads', [
            'interested_product_id' => $product->id,
            'quote_request_id' => QuoteRequest::query()->value('id'),
        ]);
    }

    public function test_calculator_context_is_reused_without_retyping_or_url_payload(): void
    {
        $this->post(route('btu-calculator.calculate'), [
            'method' => 'volume',
            'area_m2' => 30,
            'ceiling_height' => 4,
            'space_type' => 'nha_o',
            'people_count' => 2,
            'direct_sunlight' => 1,
            'heat_equipment' => 0,
        ])->assertRedirect(route('btu-calculator.index'));

        $context = session('quote_calculator_context');
        $this->assertIsArray($context);

        $this->get(route('quote.index', ['source' => 'calculator']))
            ->assertOk()
            ->assertSee('Đã dùng kết quả từ công cụ tính BTU')
            ->assertSee(number_format($context['recommended_btu']).' BTU');

        $this->post(route('quote.store'), [
            'submission_token' => (string) Str::uuid(),
            'entry_context' => 'calculator',
            'full_name' => 'Khách calculator',
            'phone' => '0909876543',
        ])->assertRedirect(route('quote.index'));

        $quote = QuoteRequest::query()->sole();
        $this->assertSame('calculator', $quote->entry_context);
        $this->assertSame('volume', $quote->calculator_context['method']);
        $this->assertSame(30.0, (float) $quote->area_m2);
        $this->assertSame($context['recommended_btu'], $quote->calculated_btu);
        $this->assertNull(session('quote_calculator_context'));
    }

    public function test_invalid_phone_preserves_input_and_does_not_create_records(): void
    {
        $this->from(route('quote.index'))->post(route('quote.store'), [
            'submission_token' => (string) Str::uuid(),
            'entry_context' => 'direct',
            'full_name' => 'Khách lỗi',
            'phone' => 'not-a-phone',
        ])->assertRedirect(route('quote.index'))->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('quote_requests', 0);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_landing_quote_uses_same_idempotent_quote_and_lead_persistence(): void
    {
        $token = (string) Str::uuid();
        $payload = [
            'submission_token' => $token,
            'name' => 'Khách landing',
            'phone' => '0912 345 678',
            'room_area' => 50,
            'source_page' => route('landing'),
        ];

        $this->post(route('landing.lead'), $payload)->assertRedirect(route('landing'));
        $this->post(route('landing.lead'), $payload)->assertRedirect(route('landing'));

        $this->assertDatabaseCount('quote_requests', 1);
        $this->assertDatabaseCount('leads', 1);
        $quote = QuoteRequest::query()->sole();
        $this->assertSame('0912345678', $quote->phone);
        $this->assertSame(50.0, (float) $quote->area_m2);
        $this->assertDatabaseHas('leads', ['quote_request_id' => $quote->id]);
    }

    public function test_mail_failure_does_not_lose_persisted_quote_or_lead(): void
    {
        $mail = Mockery::mock(MailDispatchService::class);
        $mail->shouldReceive('sendEvent')->andThrow(new \RuntimeException('mail unavailable'));
        $mail->shouldReceive('sendCustomerEvent')->never();
        $this->app->instance(MailDispatchService::class, $mail);

        $this->post(route('quote.store'), [
            'submission_token' => (string) Str::uuid(),
            'entry_context' => 'direct',
            'full_name' => 'Khách mail lỗi',
            'phone' => '0901234567',
        ])->assertRedirect(route('quote.index'));

        $this->assertDatabaseCount('quote_requests', 1);
        $this->assertDatabaseCount('leads', 1);
    }
}
