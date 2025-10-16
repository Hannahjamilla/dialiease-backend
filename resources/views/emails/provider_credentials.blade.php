<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    /* Simple inline styles that work in most email clients */
    body { font-family: Arial, sans-serif; color: #222; line-height: 1.4; }
    .container { max-width: 680px; margin: 0 auto; padding: 20px; }
    .header { background:#0d6efd; color:#fff; padding:16px; border-radius:6px 6px 0 0; }
    .panel { background:#f8f9fa; border-left:4px solid #ffc107; padding:12px; margin:16px 0; border-radius:4px; }
    .button { display:inline-block; background:#0d6efd; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none; }
    .credentials { background:#fff; border:1px solid #e9ecef; padding:12px; border-radius:6px; }
    .muted { color:#6c757d; font-size:13px; }
    footer { font-size:13px; color:#6c757d; margin-top:20px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h2 style="margin:0">Welcome to CAPD Healthcare System</h2>
    </div>

    <p>Dear {{ $user->first_name }} {{ $user->last_name }},</p>

    <p>You have been successfully pre-registered in our healthcare provider system. Below are your login credentials:</p>

    <div class="credentials">
      <p><strong>Employee Number:</strong> {{ $user->employeeNumber }}</p>
      <p><strong>Temporary Password:</strong> {{ $password }}</p>
      <p><strong>Login URL:</strong> <a href="{{ config('app.url') }}/login">{{ config('app.url') }}/login</a></p>
    </div>

    <div class="panel">
      <strong>Important:</strong> You must change your password after your first login for security reasons.
    </div>

    <p>We've attached a PDF with your registration details and system instructions. Please review it carefully.</p>

    <p>
      <a class="button" href="{{ config('app.url') }}">Access the System</a>
    </p>

    <h4>System Guide</h4>
    <ol>
      <li>Log in using the credentials above</li>
      <li>Complete your profile information</li>
      <li>Set your availability schedule</li>
      <li>Explore the system features</li>
    </ol>

    <p>If you have any questions, please contact our support team at <a href="mailto:support@capdhealthcare.com">support@capdhealthcare.com</a>.</p>

    <p>Thanks,<br>The CAPD Healthcare Team</p>

    <footer>
      <p class="muted">This email contains confidential information intended for the recipient only.</p>
    </footer>
  </div>
</body>
</html>
