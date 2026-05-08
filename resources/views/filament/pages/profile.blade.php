<x-filament-panels::page>
<div style="max-width:768px; margin:0 auto; display:flex; flex-direction:column; gap:20px;">

    {{-- ── Profile Card ─────────────────────────────────────────────────── --}}
    <div style="background:#fff; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,.07); overflow:hidden;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; gap:8px; padding:14px 20px; border-bottom:1px solid #f3f4f6;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#1a56db"
                 style="width:16px;height:16px;flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            <span style="font-size:13px;font-weight:600;color:#111827;">Thông tin hồ sơ</span>
        </div>

        {{-- Avatar + Name --}}
        <div style="display:flex; align-items:center; gap:16px; padding:20px 20px 0 20px;">
            @if($avatar_url)
                <img src="{{ $avatar_url }}" alt="Avatar"
                     style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;flex-shrink:0;">
            @else
                <div style="width:60px;height:60px;border-radius:50%;background:#1a56db;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span style="color:#fff;font-size:22px;font-weight:700;">{{ strtoupper(substr($name ?: 'U', 0, 1)) }}</span>
                </div>
            @endif
            <div>
                <div style="font-size:15px;font-weight:600;color:#111827;">{{ $name }}</div>
                <div style="font-size:13px;color:#6b7280;">{{ $email }}</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                    @foreach(auth()->user()->getRoleNames() as $role)
                        <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;">
                            {{ $role }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Form --}}
        <form wire:submit.prevent="saveProfile" style="padding:20px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#374151;margin-bottom:4px;">Họ tên <span style="color:#ef4444;">*</span></label>
                    <input wire:model="name" type="text"
                           style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;outline:none;">
                    @error('name') <p style="color:#ef4444;font-size:11px;margin-top:2px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#374151;margin-bottom:4px;">Email <span style="color:#ef4444;">*</span></label>
                    <input wire:model="email" type="email"
                           style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;outline:none;">
                    @error('email') <p style="color:#ef4444;font-size:11px;margin-top:2px;">{{ $message }}</p> @enderror
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:500;color:#374151;margin-bottom:4px;">URL Ảnh đại diện</label>
                <input wire:model="avatar_url" type="url" placeholder="https://example.com/avatar.jpg"
                       style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;outline:none;">
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <button type="submit"
                        style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1a56db;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"
                         style="width:13px;height:13px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                    Lưu hồ sơ
                </button>
            </div>
        </form>
    </div>

    {{-- ── Change Password Card ─────────────────────────────────────────── --}}
    <div style="background:#fff; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,.07); overflow:hidden;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; gap:8px; padding:14px 20px; border-bottom:1px solid #f3f4f6;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#d97706"
                 style="width:16px;height:16px;flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
            <span style="font-size:13px;font-weight:600;color:#111827;">Đổi mật khẩu</span>
        </div>

        {{-- Form --}}
        <form wire:submit.prevent="changePassword" style="padding:20px;">
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:12px;font-weight:500;color:#374151;margin-bottom:4px;">Mật khẩu hiện tại <span style="color:#ef4444;">*</span></label>
                <input wire:model="current_password" type="password" autocomplete="current-password"
                       style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;outline:none;">
                @error('current_password') <p style="color:#ef4444;font-size:11px;margin-top:2px;">{{ $message }}</p> @enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#374151;margin-bottom:4px;">Mật khẩu mới <span style="color:#ef4444;">*</span></label>
                    <input wire:model="new_password" type="password" autocomplete="new-password"
                           style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;outline:none;">
                    @error('new_password') <p style="color:#ef4444;font-size:11px;margin-top:2px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:#374151;margin-bottom:4px;">Xác nhận mật khẩu <span style="color:#ef4444;">*</span></label>
                    <input wire:model="new_password_confirm" type="password" autocomplete="new-password"
                           style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;outline:none;">
                    @error('new_password_confirm') <p style="color:#ef4444;font-size:11px;margin-top:2px;">{{ $message }}</p> @enderror
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <button type="submit"
                        style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#d97706;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                         style="width:13px;height:13px;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z"/>
                    </svg>
                    Đổi mật khẩu
                </button>
            </div>
        </form>
    </div>

    {{-- Note --}}
    <p style="text-align:center;font-size:11px;color:#9ca3af;padding-bottom:8px;">
        Vai trò do admin quản lý, không thể tự thay đổi. Liên hệ <strong>super_admin</strong> để cập nhật.
    </p>

</div>
</x-filament-panels::page>
