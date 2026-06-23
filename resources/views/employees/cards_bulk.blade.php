<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk ID Cards - Hygiene Pro</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');
        
        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 0;
            background: #eee;
        }

        .a4-page {
            width: 210mm;
            min-height: 297mm;
            padding: 10mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-auto-rows: max-content;
            gap: 10mm; /* Spacing between cards */
            box-sizing: border-box;
            page-break-after: always;
        }

        .id-card {
            width: 53.98mm;
            height: 85.6mm;
            background: white;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
            border: 1px solid #ddd; /* Light border */
            display: flex;
            flex-direction: column;
            margin: 0 auto; /* Center in grid cell */
        }

        /* Reusing Card Styles from Checkpoint 790 */
        .header {
            background: white;
            height: 12mm;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-top: 2mm;
            padding-right: 4mm;
        }

        .company-logo {
            height: 8mm;
            width: auto;
            object-fit: contain;
        }

        .content {
            flex: 1;
            padding: 1mm 4mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .photo-area {
            width: 28mm;
            height: 28mm;
            background: #f8f9fa;
            border: 3px solid #6B8E23;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 2mm;
        }

        .photo-area img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
        }

        .name {
            font-size: 11pt;
            font-weight: bold;
            color: #4a3c31;
            margin-bottom: 1mm;
            line-height: 1.1;
        }

        .role {
            font-size: 9pt;
            color: #6B8E23;
            font-weight: bold;
            margin-bottom: 1mm;
            text-transform: uppercase;
        }

        .id-text {
            font-size: 8pt;
            color: #888;
            margin-bottom: 2mm;
            background: #f0f0f0;
            padding: 1px 8px;
            border-radius: 10px;
        }

        .footer-line {
            height: 4mm;
            background: #4a3c31;
            width: 100%;
            margin-top: auto;
        }

        .qr-area {
            margin-top: 1mm;
            margin-bottom: 2mm;
            background: white;
            padding: 2px;
            border: 1px solid #eee;
            border-radius: 4px;
        }

        /* Print Settings */
        @media print {
            body { 
                background: white; 
                margin: 0; 
            }
            .a4-page {
                width: 210mm;
                height: 297mm;
                padding: 10mm; 
                margin: 0; 
                box-shadow: none;
                page-break-after: always;
            }
            .a4-page:last-child {
                page-break-after: auto;
            }
            .id-card {
                border: 1px dashed #ccc; /* Cut guide */
            }
            .no-print {
                display: none !important;
            }
        }

        .btn-print {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-family: inherit;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            font-size: 16px;
            z-index: 1000;
        }
        .btn-print:hover {
            background: #0b5ed7;
        }

        .selection-info {
            position: fixed;
            bottom: 80px;
            right: 20px;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>

    @php
        // Chunk employees into groups of 8 (2x4) or 10 (2x5) per page
        // A4 height is 297mm. Card height is 85.6mm.
        // With margins, 3 rows comfortably fit (85.6 * 3 = 256.8mm). 
        // 4 rows might be tight depending on gap. Let's try 8 per page (2x4).
        $chunks = $employees->chunk(8);
    @endphp

    @foreach($chunks as $chunk)
    <div class="a4-page">
        @foreach($chunk as $employee)
        <div class="id-card">
            <div class="header">
                <img src="{{ asset('images/logo_allcoco.png') }}" class="company-logo" alt="All Coco">
            </div>
            <div class="content">
                <div class="photo-area">
                    @if($employee->profile_image)
                        <img src="{{ $employee->profile_image }}" alt="Photo">
                    @else
                        <svg width="50" height="50" fill="#ccc" viewBox="0 0 16 16">
                            <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                        </svg>
                    @endif
                </div>
                
                <div class="name">{{ $employee->fullname }}</div>
                <div class="role">{{ $employee->department->dept_name ?? '-' }}</div>
                <div class="id-text">ID: {{ $employee->employee_id }}</div>

                <div class="qr-area">
                    {!! QrCode::size(60)->generate($employee->qr_code_hash) !!}
                </div>
            </div>
            <div class="footer-line"></div>
        </div>
        @endforeach
    </div>
    @endforeach

    <div class="selection-info no-print">
        Total Cards: {{ $employees->count() }}
    </div>

    <button class="btn-print no-print" onclick="window.print()">
        🖨️ Print Selected Cards
    </button>

</body>
</html>
