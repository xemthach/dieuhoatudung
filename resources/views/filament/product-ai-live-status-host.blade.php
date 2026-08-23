@if(!empty($productId))
    <livewire:ai-product-live-status :product-id="(int) $productId" :key="'ai-product-live-status-'.$productId" />
@endif
