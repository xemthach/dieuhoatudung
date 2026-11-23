<x-filament-panels::page>
    @php($health = app(\App\Services\Product\ProductGovernanceHealth::class)->inspect())
    @if(!$health['ready'])
        <x-filament::section heading="Product Data Governance chưa sẵn sàng">
            <p role="status">Cần bổ sung schema hoặc evidence đã kiểm chứng trước khi duyệt dữ liệu.</p>
            <ul class="mt-3 list-disc pl-5">@foreach($health['issues'] as $issue)<li>{{ $issue['code'] }}: {{ $issue['resource'] }}</li>@endforeach</ul>
            <p class="mt-3">Chạy <code>php artisan products:governance-health --json</code> trên server để chẩn đoán. Không thay đổi dữ liệu sản phẩm để bỏ qua bước này.</p>
        </x-filament::section>
    @else
    @php($taxonomy = $this->taxonomy())
    @php($marketing = $this->marketing())

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach(['keep' => 'Verified/Keep', 'propose_update' => 'Proposed', 'ambiguous' => 'Ambiguous', 'no_evidence' => 'No evidence', 'conflict' => 'Conflict'] as $key => $label)
            <x-filament::section><div class="text-sm text-gray-500">{{ $label }}</div><div class="text-2xl font-bold">{{ $taxonomy['stats'][$key] }}</div></x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Taxonomy Review">
        <div class="mb-4 text-sm">Ledger: <code>{{ $taxonomy['ledger_digest'] }}</code></div>
        @if($taxonomy['approved'])
            <x-filament::badge color="success">APPROVED — exact digest</x-filament::badge>
        @elseif(auth()->user()?->isSuperAdmin() || auth()->user()?->can('product_taxonomy.approve'))
            <div class="flex max-w-3xl gap-3">
                <input wire:model="taxonomyReason" class="flex-1 rounded-lg border-gray-300" placeholder="Required approval reason">
                <x-filament::button wire:click="approveTaxonomy" wire:confirm="Approve every deterministic KEEP/PROPOSE row bound to this exact digest?">Approve deterministic proposals</x-filament::button>
                <x-filament::button color="gray" wire:click="exportTaxonomyLedger">Export ledger</x-filament::button>
            </div>
        @endif
        <div class="mt-4 max-h-[34rem] overflow-auto">
            <table class="w-full text-sm">
                <thead><tr><th>Product</th><th>Brand</th><th>Current</th><th>Proposed</th><th>Source</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($taxonomy['rows'] as $row)
                    <tr>
                        <td>#{{ $row['product_id'] }}<br>{{ $row['sku'] }}<br><span class="text-xs">{{ $row['model_code'] }}</span></td>
                        <td>{{ $row['brand'] }}</td><td>{{ $row['current_category_slug'] }}</td><td>{{ $row['proposed_category_slug'] ?? '—' }}</td>
                        <td>{{ $row['evidence_source'] }}<br><span class="text-xs">{{ $row['evidence_location'] }}</span></td>
                        <td><x-filament::badge>{{ $row['classification'] }}</x-filament::badge></td>
                        <td>
                            @if(auth()->user()?->isSuperAdmin() || auth()->user()?->can('product_taxonomy.approve'))
                                @if(in_array($row['classification'], ['KEEP','PROPOSE_UPDATE']))
                                    <button wire:click="rejectTaxonomyProposal({{ $row['product_id'] }})" wire:confirm="Reject this proposal from the approved batch? A reason is required." class="text-danger-600">Reject</button>
                                @else
                                    <button wire:click="markTaxonomyForReview({{ $row['product_id'] }})" class="text-primary-600">Review</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Marketing Capacity Review">
        <div class="grid gap-3 md:grid-cols-5">
            @foreach($marketing['stats'] as $key => $value)<div><div class="text-xs text-gray-500">{{ str_replace('_', ' ', $key) }}</div><div class="font-bold">{{ $value }}</div></div>@endforeach
        </div>
        <p class="mt-3 text-sm text-gray-600">No tier is auto-selected from technical BTU, kW, HP, model suffix or nearest bucket. Every group lists all affected models and requires an explicit reason.</p>
        <div class="mt-4 space-y-3">
            @foreach($marketing['groups'] as $group)
                <details class="rounded-lg border p-3" data-marketing-group="{{ $group['group_key'] }}">
                    <summary class="cursor-pointer font-semibold">{{ $group['brand'] }} · {{ $group['taxonomy'] }} · technical {{ $group['technical_capacity_btu'] ?? '—' }} BTU · {{ $group['capacity_kw'] ?? '—' }} kW · {{ $group['affected_count'] }} Products</summary>
                    <div class="mt-2 text-xs">Source: {{ $group['source'] }} · digest {{ $group['source_digest'] }}</div>
                    <ul class="my-3 max-h-40 overflow-auto text-sm">@foreach($group['products'] as $product)<li>#{{ $product['id'] }} {{ $product['model_code'] }} — current {{ $product['current_marketing_capacity_btu'] ?? 'missing' }} ({{ $product['status'] }})</li>@endforeach</ul>
                    @if(auth()->user()?->isSuperAdmin() || auth()->user()?->can('marketing_capacity.approve'))
                        <div class="grid gap-2 md:grid-cols-3">
                            <input type="number" wire:model="mappingTiers.{{ $group['group_key'] }}" class="rounded-lg border-gray-300" placeholder="Approved marketing BTU">
                            <input wire:model="mappingReasons.{{ $group['group_key'] }}" class="rounded-lg border-gray-300" placeholder="Required source-backed business reason">
                            <x-filament::button wire:click="approveMarketingGroup('{{ $group['group_key'] }}')" wire:confirm="High-risk bulk approval: apply this selected tier to every displayed Product in the exact source group?">Approve mapping</x-filament::button>
                            <x-filament::button color="danger" wire:click="rejectMarketingGroup('{{ $group['group_key'] }}')" wire:confirm="Reject this exact mapping group? A reason is required.">Reject</x-filament::button>
                            <x-filament::button color="gray" wire:click="markMarketingGroupForReview('{{ $group['group_key'] }}')">Mark unresolved</x-filament::button>
                        </div>
                    @endif
                </details>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Append-only approval audit trail">
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Domain</th><th>Action</th><th>Ledger</th><th>Operator</th><th>Time</th><th>Reason</th></tr></thead><tbody>
        @foreach($this->recentEvents() as $event)<tr><td>{{ $event->domain }}</td><td>{{ $event->action }}</td><td><code>{{ $event->ledger_digest }}</code></td><td>{{ $event->operator?->name ?? '#'.$event->operator_id }}</td><td>{{ $event->occurred_at }}</td><td>{{ $event->reason }}</td></tr>@endforeach
        </tbody></table></div>
    </x-filament::section>
    @endif
</x-filament-panels::page>
