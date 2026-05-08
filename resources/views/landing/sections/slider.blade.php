{{-- Slider Section (placeholder — managed via admin settings_json) --}}
@if($section->content)
<section class="bg-white py-12 lg:py-16" id="landing-slider">
    <div class="container-main">
        <div class="prose max-w-none">
            {!! $section->content !!}
        </div>
    </div>
</section>
@endif
