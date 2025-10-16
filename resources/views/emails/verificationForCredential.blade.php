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
            background-color: #f8fafc;
        }
        .header {
            background: linear-gradient(135deg, #395886 0%, #477977 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            font-family: 'Courier New', monospace;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
        .info-box {
            background: #e8f4fd;
            border-left: 4px solid #395886;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
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
        
        <div class="info-box">
            <p><strong>Important:</strong> This code will expire in 15 minutes.</p>
        </div>
        
        <p>If you did not request this verification, please ignore this email.</p>
        
        <p>Best regards,<br>CAPD Healthcare System Team</p>
    </div>
    
    <div class="footer">
        <p>&copy; {{ date('Y') }} CAPD Healthcare System. All rights reserved.</p>
    </div>
</body>
</html>