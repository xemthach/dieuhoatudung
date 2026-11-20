@if($brands->isNotEmpty())
    <section class="border-y border-surface-200 bg-surface-50 py-12 lg:py-16">
        <div class="container-main">
            <x-section-heading
                title="Thương Hiệu Sản Phẩm"
                subtitle="Đại lý ủy quyền các thương hiệu điều hòa hàng đầu thế giới"
            />
            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-{{ min($brands->count(), 6) }}">
                @foreach($brands as $brand)
                    <x-brand-card :brand="$brand" />
                @endforeach
            </div>
        </div>
    </section>
    @endif
