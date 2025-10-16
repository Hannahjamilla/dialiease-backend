<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .header {
            background-color: #477977;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            padding: 20px;
        }
        .otp-code {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
            letter-spacing: 5px;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Email Verification Code</h2>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>Your email verification code is:</p>
            <div class="otp-code">{{ $otp }}</div>
            <p>This code will expire in 5 minutes. Please do not share this code with anyone.</p>
            <p>If you didn't request this code, you can safely ignore this email.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} DialiEase. All rights reserved.</p>
        </div>
    </div>
</body>
</html>