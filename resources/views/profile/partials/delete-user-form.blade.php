<section class="space-y-6">
    <header class="mb-4">
        <h2 class="h4 text-danger fw-bold mb-1">
            {{ __('ลบบัญชีผู้ใช้งาน (Delete Account)') }}
        </h2>

        <p class="text-muted small">
            {{ __('เมื่อคุณลบบัญชี ข้อมูลทั้งหมดที่เกี่ยวข้องจะถูกลบอย่างถาวรและไม่สามารถกู้คืนได้') }}
        </p>
    </header>

    <button type="button" class="btn btn-outline-danger fw-bold rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#confirmUserDeletionModal">
        {{ __('ลบบัญชีผู้ใช้งาน') }}
    </button>

    <!-- Modal -->
    <div class="modal fade" id="confirmUserDeletionModal" tabindex="-1" aria-labelledby="confirmUserDeletionModalLabel" aria-hidden="true" 
         @if($errors->userDeletion->isNotEmpty()) data-bs-backdrop="static" data-bs-keyboard="false" @endif>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <form method="post" action="{{ route('profile.destroy') }}" class="needs-validation">
                    @csrf
                    @method('delete')
                    
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-danger" id="confirmUserDeletionModalLabel">{{ __('คุณแน่ใจหรือไม่ที่จะลบบัญชีผู้ใช้งาน?') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body pt-3">
                        <p class="text-muted small mb-4">
                            {{ __('เมื่อคุณลบบัญชี ข้อมูลทั้งหมดจะถูกลบอย่างถาวร กรุณากรอกรหัสผ่านของคุณเพื่อยืนยันการลบบัญชี') }}
                        </p>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-bold sr-only">{{ __('รหัสผ่าน (Password)') }}</label>
                            <input id="password" name="password" type="password" class="form-control" placeholder="{{ __('ใส่รหัสผ่านเพื่อยืนยัน') }}">
                            @error('password', 'userDeletion')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('ยกเลิก') }}</button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">{{ __('ลบบัญชีอย่างถาวร') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($errors->userDeletion->isNotEmpty())
        <!-- Auto-open modal if there are errors -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var myModal = new bootstrap.Modal(document.getElementById('confirmUserDeletionModal'), {
                    keyboard: false
                });
                myModal.show();
            });
        </script>
    @endif
</section>
