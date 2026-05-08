{{-- filament/mail-template-variables-panel.blade.php --}}
{{-- Compact card-based layout for sidebar (4/12 column) --}}
@php
    use App\Services\Mail\MailTemplateRenderer;

    $renderer = app(MailTemplateRenderer::class);
    $allGroups = $renderer->getAllVariableGroups();

    $formRecord = $formRecord ?? null;
    if (is_numeric($formRecord)) {
        $formRecord = \App\Models\MailTemplate::find($formRecord);
    }
    $templateKey = (is_object($formRecord) && isset($formRecord->key)) ? $formRecord->key : null;

    $relevantVarNames = [];
    if ($templateKey) {
        $relevantVarNames = array_column($renderer->getAvailableVariables($templateKey), 'name');
    }

    $groupMeta = [
        'site' => ['icon' => '', 'accent' => '#2563eb', 'bg' => '#eff6ff', 'border' => '#bfdbfe'],
        'customer' => ['icon' => '', 'accent' => '#16a34a', 'bg' => '#f0fdf4', 'border' => '#bbf7d0'],
        'product' => ['icon' => '', 'accent' => '#9333ea', 'bg' => '#faf5ff', 'border' => '#e9d5ff'],
        'lead' => ['icon' => '', 'accent' => '#ea580c', 'bg' => '#fff7ed', 'border' => '#fed7aa'],
        'review' => ['icon' => '', 'accent' => '#ca8a04', 'bg' => '#fefce8', 'border' => '#fde68a'],
        'qa' => ['icon' => '', 'accent' => '#db2777', 'bg' => '#fdf2f8', 'border' => '#fbcfe8'],
    ];
@endphp

<div
    x-data="{
        search: '',
        copied: '',
        openGroups: @js(array_fill_keys(array_keys($allGroups), true)),
        copyVar(name) {
            const ob = String.fromCharCode(123);
            const cb = String.fromCharCode(125);
            const toCopy = ob + ob + name + cb + cb;
            navigator.clipboard.writeText(toCopy).then(() => {
                this.copied = name;
                setTimeout(() => this.copied = '', 2000);
            }).catch(() => {
                const el = document.createElement('textarea');
                el.value = toCopy;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
                this.copied = name;
                setTimeout(() => this.copied = '', 2000);
            });
        },
        matchesSearch(name, desc) {
            if (!this.search.trim()) return true;
            const q = this.search.toLowerCase();
            return name.toLowerCase().includes(q) || desc.toLowerCase().includes(q);
        }
    }"
    style="max-height:72vh; overflow-y:auto; display:flex; flex-direction:column; gap:0;"
>

    {{-- ── Search ─────────────────────────────────────────────────────── --}}
    <div style="position:sticky; top:0; z-index:10; background:#fff; padding:0 0 8px 0;">
        <div style="position:relative;">
            <svg style="position:absolute; left:10px; top:50%; transform:translateY(-50%); pointer-events:none;" width="14" height="14" fill="none" stroke="#9ca3af" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                x-model.debounce.200ms="search"
                type="text"
                placeholder="Tìm biến..."
                style="width:100%; padding:7px 12px 7px 32px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; color:#111827; background:#fff; outline:none; box-sizing:border-box;"
            >
            <button
                x-show="search"
                @click="search = ''"
                style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:2px; color:#9ca3af; display:flex;"
            >
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @if($templateKey)
        <div style="margin-top:6px; font-size:11px; color:#6b7280;">
            Template: <code style="color:#2563eb; font-family:monospace; font-size:11px;">{{ $templateKey }}</code>
        </div>
        @endif
    </div>

    {{-- ── Variable groups ─────────────────────────────────────────────── --}}
    @foreach($allGroups as $groupKey => $group)
    @php
        $meta = $groupMeta[$groupKey] ?? ['icon' => '', 'accent' => '#374151', 'bg' => '#f9fafb', 'border' => '#e5e7eb'];
        $hasRelevant = !empty($relevantVarNames);
    @endphp

    <div style="margin-bottom:4px;">
        {{-- Group header --}}
        <button
            @click="openGroups['{{ $groupKey }}'] = !openGroups['{{ $groupKey }}']"
            style="width:100%; display:flex; align-items:center; gap:6px; padding:6px 10px; background:{{ $meta['bg'] }}; border:1px solid {{ $meta['border'] }}; border-radius:8px; cursor:pointer; text-align:left;"
        >
            <span style="font-size:14px; line-height:1;">{{ $meta['icon'] }}</span>
            <span style="flex:1; font-size:12px; font-weight:600; color:#111827;">{{ $group['label'] }}</span>
            <span style="background:{{ $meta['accent'] }}; color:#fff; font-size:10px; font-weight:600; padding:1px 6px; border-radius:8px;">{{ count($group['variables']) }}</span>
            <svg
                width="12" height="12" fill="none" stroke="{{ $meta['accent'] }}" viewBox="0 0 24 24"
                :style="openGroups['{{ $groupKey }}'] ? 'transform:rotate(90deg); transition:transform 0.15s;' : 'transform:rotate(0deg); transition:transform 0.15s;'"
                style="flex-shrink:0;"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        {{-- Variable cards --}}
        <div x-show="openGroups['{{ $groupKey }}']" x-collapse style="padding:4px 0 0 0;">
            @foreach($group['variables'] as $var)
            @php
                $isRelevant = empty($relevantVarNames) || in_array($var['name'], $relevantVarNames);
                $varName = $var['name'];
                $varDesc = $var['description'];
                $varExample = $var['example'];
            @endphp
            <div
                x-show="matchesSearch('{{ $varName }}', '{{ addslashes($varDesc) }}')"
                style="display:flex; align-items:center; gap:6px; padding:5px 8px; border-bottom:1px solid #f3f4f6; {{ $isRelevant ? '' : 'opacity:0.4;' }}"
                @mouseenter="$el.style.background='#f9fafb'" @mouseleave="$el.style.background=''"
            >
                {{-- Relevance dot --}}
                @if($hasRelevant)
                <span style="width:6px; height:6px; border-radius:50%; background:{{ $isRelevant ? '#22c55e' : '#d1d5db' }}; flex-shrink:0;" title="{{ $isRelevant ? 'Dùng trong template' : 'Không dùng' }}"></span>
                @endif

                {{-- Variable info --}}
                <div style="flex:1; min-width:0;">
                    <code style="background:{{ $meta['bg'] }}; color:{{ $meta['accent'] }}; border:1px solid {{ $meta['border'] }}; padding:1px 5px; border-radius:4px; font-family:monospace; font-size:11px; font-weight:500; display:inline-block;">{{ '{' . '{' . $varName . '}' . '}' }}</code>
                    <div style="font-size:11px; color:#6b7280; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $varDesc }} — Ví dụ: {{ $varExample }}">
                        {{ $varDesc }}
                        @if($varExample)
                        <span style="color:#9ca3af;">· {{ $varExample }}</span>
                        @endif
                    </div>
                </div>

                {{-- Copy button --}}
                <button
                    @click="copyVar('{{ $varName }}')"
                    :style="copied === '{{ $varName }}'
                        ? 'background:#f0fdf4; border:1px solid #86efac; color:#15803d; cursor:pointer; display:inline-flex; align-items:center; padding:3px 8px; border-radius:5px; font-size:10px; font-weight:500; flex-shrink:0;'
                        : 'background:#fff; border:1px solid #e5e7eb; color:#374151; cursor:pointer; display:inline-flex; align-items:center; padding:3px 8px; border-radius:5px; font-size:10px; font-weight:500; flex-shrink:0;'"
                >
                    <span x-text="copied === '{{ $varName }}' ? '' : 'Copy'"></span>
                </button>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    {{-- ── Legend ──────────────────────────────────────────────────────── --}}
    @if(!empty($relevantVarNames))
    <div style="padding:8px 0; border-top:1px solid #e5e7eb; margin-top:4px;">
        <div style="display:flex; flex-wrap:wrap; gap:12px; font-size:10px; color:#6b7280;">
            <span style="display:flex; align-items:center; gap:4px;">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#22c55e;"></span>
                Dùng trong template
            </span>
            <span style="display:flex; align-items:center; gap:4px;">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#d1d5db;"></span>
                Không dùng
            </span>
        </div>
    </div>
    @endif

    {{-- ── Quick guide ────────────────────────────────────────────────── --}}
    <div style="padding:8px; background:#f9fafb; border-radius:8px; margin-top:4px; border:1px solid #e5e7eb;">
        <div style="font-size:11px; color:#6b7280; line-height:1.5;">
            <div style="font-weight:600; color:#374151; margin-bottom:2px; font-size:11px;"> Hướng dẫn</div>
            <div>• Dùng <code style="font-size:10px; background:#eff6ff; padding:1px 4px; border-radius:3px; color:#2563eb;">{{ '{' . '{' . 'biến' . '}' . '}' }}</code> để chèn dữ liệu</div>
            <div>• Click <strong>Copy</strong> để copy biến</div>
            <div>• Preview trước khi gửi test</div>
        </div>
    </div>
</div>
