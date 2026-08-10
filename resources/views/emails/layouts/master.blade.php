<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'HygienePro Notification')</title>
</head>
<body style="font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f7f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); max-width: 650px; margin: 0 auto;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #1e3a8a; padding: 35px 40px; text-align: center; background-image: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
                            <h2 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 0.5px;">
                                @yield('header_title')<br>
                                <span style="font-size: 18px; opacity: 0.9; font-weight: 400; margin-top: 5px; display: inline-block;">
                                    @yield('header_subtitle')
                                </span>
                            </h2>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px; color: #374151; font-size: 15px; line-height: 1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 25px 40px; text-align: center; border-top: 1px solid #f3f4f6;">
                            <p style="margin: 0; color: #9ca3af; font-size: 13px;">
                                นี่คืออีเมลอัตโนมัติจากระบบ <strong>HygienePro</strong><br>กรุณาอย่าตอบกลับอีเมลนี้
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
