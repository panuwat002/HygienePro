<section>
    <header class="mb-4">
        <h2 class="h4 text-dark fw-bold mb-1">
            {{ __('ข้อมูลบัญชีผู้ใช้งาน (Profile Information)') }}
        </h2>

        <p class="text-muted small">
            {{ __("อัปเดตข้อมูลบัญชีผู้ใช้งานและอีเมลของคุณ") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="needs-validation">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="form-label fw-bold">{{ __('ชื่อ-นามสกุล') }}</label>
            <input id="name" name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @error('name')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="email" class="form-label fw-bold">{{ __('อีเมล (Email)') }}</label>
            <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="text-muted small mb-1">
                        {{ __('อีเมลของคุณยังไม่ได้รับการยืนยัน') }}
                        <button form="send-verification" class="btn btn-link p-0 m-0 align-baseline text-decoration-none small">
                            {{ __('คลิกที่นี่เพื่อส่งอีเมลยืนยันอีกครั้ง') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="text-success small fw-medium mt-1">
                            {{ __('ระบบได้ส่งลิงก์ยืนยันไปที่อีเมลของคุณแล้ว') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary px-4 fw-bold rounded-pill shadow-sm">{{ __('บันทึกข้อมูล') }}</button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-success small mb-0 fw-medium"
                ><i class="bi bi-check-circle-fill me-1"></i>{{ __('บันทึกสำเร็จ') }}</p>
            @endif
        </div>
    </form>
</section>
