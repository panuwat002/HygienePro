<script>
    document.addEventListener('DOMContentLoaded', function() {
        var hasPreviousReclean = {{ $previousRecleanCount ?? 0 }} > 0;
        
        if (hasPreviousReclean) {
            Swal.fire({
                title: '⚠️ พบรายการค้างพิจารณา!',
                html: "พนักงานรายนี้มีรายการกักตัว (Re-clean) จากรอบก่อนหน้าที่ยังไม่ผ่าน<br>ต้องการ <b>'แก้ไขงานเก่า'</b> หรือ <b>'เริ่มตรวจรอบใหม่'</b> ครับ?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107', // Warning Yellow
                cancelButtonColor: '#0d6efd', // Primary Blue
                confirmButtonText: '<i class="bi bi-tools"></i> แก้ไขงานเก่า',
                cancelButtonText: '<i class="bi bi-plus-circle"></i> เริ่มตรวจรอบใหม่',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) {
                    // Start New Round -> Reload with ignore_reclean=1
                    const url = new URL(window.location.href);
                    url.searchParams.set('ignore_reclean', '1');
                    window.location.href = url.toString();
                } else {
                    // Fix Old -> Stay here (re-cleans are already loaded)
                    // Optional: Scroll to first re-clean?
                    const firstReclean = document.querySelector('.border-warning');
                    if(firstReclean) firstReclean.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    });
</script>
