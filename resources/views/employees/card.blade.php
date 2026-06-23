<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $employee->fullname }} - ID Card</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');
        
        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 0;
            background: #eee;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .id-card {
            width: 53.98mm;
            height: 85.6mm;
            background: white;
            border-radius: 8px; /* More rounded */
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            border: 1px solid #ddd;
            display: flex;
            flex-direction: column;
        }

        /* Print Settings */
        @media print {
            body { 
                background: white; 
                display: block; 
                margin: 0; 
            }
            .id-card {
                box-shadow: none;
                border: 1px solid #000;
                page-break-inside: avoid;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
        }

        .header {
            background: white;
            height: 12mm; /* Reduced header height */
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
            padding: 1mm 4mm; /* Reduced padding */
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .photo-area {
            width: 28mm; /* Slightly smaller photo */
            height: 28mm;
            background: #f8f9fa;
            border: 3px solid #6B8E23;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 2mm;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .photo-area img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center; /* Prevents cutting off top of head */
        }

        .name {
            font-size: 11pt; /* Slightly smaller font */
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
            margin-top: auto; /* Push footer to bottom */
        }

        .qr-area {
            margin-top: 1mm;
            margin-bottom: 2mm;
            background: white;
            padding: 2px;
            border: 1px solid #eee;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    
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
                <!-- Compact QR Code Size -->
                {!! QrCode::size(60)->generate($employee->qr_code_hash) !!}
            </div>
        </div>
        <div class="footer-line"></div>
    </div>

    <button class="btn-print no-print" onclick="window.print()">
        🖨️ Print ID Card
    </button>

</body>
</html>
