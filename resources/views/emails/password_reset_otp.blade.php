<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset OTP</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f2f4f7;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border-top: 6px solid #477977;
        }

        .email-header {
            background-color: #395886;
            color: #ffffff;
            padding: 24px 32px;
            text-align: center;
        }

        .email-header .icon {
            font-size: 36px;
            margin-bottom: 8px;
        }

        .email-header h1 {
            font-size: 20px;
            margin: 0;
        }

        .email-body {
            padding: 32px;
            color: #333;
        }

        .email-body h2 {
            font-size: 22px;
            margin-bottom: 15px;
            color: #395886;
        }

        .email-body p {
            font-size: 16px;
            margin: 10px 0;
            line-height: 1.6;
        }

        .otp-code {
            font-size: 30px;
            font-weight: 700;
            letter-spacing: 6px;
            color: #477977;
            background: #e0f1f0;
            padding: 16px 28px;
            display: inline-block;
            border-radius: 10px;
            margin: 24px 0;
        }

        .email-footer {
            background-color: #f4f6fa;
            text-align: center;
            font-size: 13px;
            color: #888;
            padding: 20px;
            border-top: 1px solid #e0e0e0;
        }

        @media (max-width: 600px) {
            .email-body, .email-header, .email-footer {
                padding: 20px;
            }

            .otp-code {
                font-size: 24px;
                letter-spacing: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <div class="icon">🔐</div>
            <h1>Password Reset</h1>
        </div>

        <div class="email-body">
            <h2>Hello,</h2>
            <p>You recently requested to reset your password. Please use the verification code below to proceed:</p>

            <div class="otp-code">{{ $otp }}</div>

            <p>This OTP will expire in <strong>10 minutes</strong>. Do not share this code with anyone.</p>

            <p>If you did not request this, please disregard this message or contact support if you have concerns.</p>

            <p>Regards,<br><strong>The Dialiease Team</strong></p>
        </div>

        <div class="email-footer">
            &copy; 2025 Dialiease. All rights reserved.<br>
        </div>
    </div>
</body>
</html>
 