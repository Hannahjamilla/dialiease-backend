<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #444;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8fafc;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
            padding: 40px;
            border-top: 5px solid #4361ee;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 200px;
            margin-bottom: 20px;
        }
        h2 {
            color: #2b2d42;
            margin: 0 0 15px 0;
            font-size: 26px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        p {
            margin-bottom: 16px;
            font-weight: 400;
            color: #4a5568;
        }
        .otp-container {
            margin: 30px 0;
            text-align: center;
        }
        .otp-code {
            display: inline-block;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 5px;
            padding: 20px 30px;
            background: linear-gradient(135deg, #f0f4ff 0%, #e6edff 100%);
            color: #4361ee;
            border-radius: 10px;
            border: 1px dashed #4361ee;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.1);
        }
        .highlight {
            color: #4361ee;
            font-weight: 600;
        }
        .warning {
            background-color: #fff8f1;
            border-left: 4px solid #ff9f1c;
            padding: 15px;
            margin: 25px 0;
            border-radius: 0 6px 6px 0;
        }
        .footer {
            margin-top: 40px;
            font-size: 13px;
            color: #718096;
            border-top: 1px solid #edf2f7;
            padding-top: 20px;
            text-align: center;
        }
        .footer p {
            margin-bottom: 8px;
            color: #718096;
        }
        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background-color: #4361ee;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            box-shadow: 0 4px 8px rgba(67, 97, 238, 0.2);
        }
        .support {
            font-weight: 500;
            color: #4361ee;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Email Verification Code</h2>
        </div>
        
        <p>Hello {{ $user->first_name }},</p>
        
        <p>To verify your email address, please use the following One-Time Password (OTP):</p>
        
        <div class="otp-container">
            <div class="otp-code">
                {{ $otp }}
            </div>
        </div>
        
        <div class="warning">
            <p><span class="highlight">⏳ Expires in 15 minutes.</span> For security, please do not share this code with anyone.</p>
        </div>
        
        <p>If you didn't request this verification, please <span class="highlight">ignore this email</span> or contact our support team immediately at <a href="mailto:support@healthcare.com" class="support">support@healthcare.com</a>.</p>
        
        <div class="footer">
            <p>Thank you for choosing our healthcare services.</p>
            <p>© {{ date('Y') }} DialiEase. All rights reserved.</p>
            <p><small>This is an automated message, please do not reply directly.</small></p>
        </div>
    </div>
</body>
</html>