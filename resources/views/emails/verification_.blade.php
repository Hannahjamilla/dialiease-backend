<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #395886 0%, #477977 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8fafc;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .verification-code {
            background: #395886;
            color: white;
            padding: 20px;
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 5px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CAPD Healthcare System</h1>
        <h2>Email Verification</h2>
    </div>
    
    <div class="content">
        <p>Hello,</p>
        
        <p>You are receiving this email because you requested to verify your email address for the CAPD Healthcare System.</p>
        
        <p>Your verification code is:</p>
        
        <div class="verification-code">
            {{ $code }}
        </div>
        
        <p>This code will expire in 15 minutes.</p>
        
        <p>If you did not request this verification, please ignore this email.</p>
        
        <p>Best regards,<br>CAPD Healthcare System Team</p>
    </div>
    
    <div class="footer">
        <p>&copy; {{ date('Y') }} CAPD Healthcare System. All rights reserved.</p>
    </div>
</body>
</html>