
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Healthcare Provider Pre-Registration - NKTI CAPD System</title>
    <style>
        @page {
            margin: 15mm;
            size: A4;
        }
        
        body { 
            font-family: 'Arial', 'Helvetica', sans-serif; 
            margin: 0;
            padding: 0;
            color: #000000;
            line-height: 1.3;
            font-size: 11px;
            background: #ffffff;
        }
        
        .container {
            width: 100%;
            margin: 0 auto;
            padding: 0;
        }
        
        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #000000;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            gap: 20px;
        }
        
        .logo {
            height: 80px;
            width: auto;
            border: 1px solid #ddd;
            padding: 5px;
            background: #fff;
        }
        
        .institution-info {
            text-align: center;
        }
        
        .institution-name {
            color: #000000;
            font-size: 16px;
            font-weight: bold;
            margin: 5px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .department-name {
            color: #000000;
            font-size: 14px;
            font-weight: bold;
            margin: 3px 0;
            text-transform: uppercase;
        }
        
        .document-title {
            color: #000000;
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0 5px 0;
            text-transform: uppercase;
        }
        
        .document-subtitle {
            color: #666666;
            font-size: 12px;
            margin: 0;
            font-weight: normal;
        }
        
        /* Alert Box */
        .urgent-note {
            background: #f8f8f8;
            border: 1px solid #000000;
            border-left: 4px solid #000000;
            padding: 12px 15px;
            margin: 15px 0;
            color: #000000;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
        }
        
        /* Information Sections */
        .section {
            margin: 20px 0;
        }
        
        .section-title {
            color: #000000;
            background: #f0f0f0;
            padding: 8px 12px;
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            border-left: 4px solid #000000;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: bold;
            color: #000000;
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            width: 35%;
            vertical-align: top;
            font-size: 11px;
        }
        
        .info-value {
            display: table-cell;
            color: #000000;
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
            font-size: 11px;
        }
        
        /* Password Section */
        .password-section {
            background: #f8f8f8;
            border: 2px dashed #666;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .password-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #000000;
            text-transform: uppercase;
        }
        
        .password-value {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: bold;
            color: #000000;
            letter-spacing: 2px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ccc;
            display: inline-block;
            min-width: 200px;
        }
        
        /* Security Warning */
        .security-warning {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 12px 15px;
            margin: 15px 0;
            color: #000000;
            font-size: 11px;
            border-left: 4px solid #ff0000;
        }
        
        /* Instructions */
        .instructions {
            background: #fafafa;
            border: 1px solid #eee;
            padding: 15px;
            margin: 15px 0;
        }
        
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 8px;
            font-size: 11px;
            line-height: 1.4;
        }
        
        /* Terms and Conditions */
        .terms-section {
            margin: 25px 0;
        }
        
        .terms-content {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            font-size: 10px;
            line-height: 1.4;
        }
        
        .terms-content h4 {
            margin: 0 0 10px 0;
            color: #000000;
            font-size: 12px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        
        .terms-content p {
            margin-bottom: 10px;
        }
        
        /* Signature Section */
        .signature-section {
            margin: 30px 0 20px 0;
            padding-top: 20px;
            border-top: 2px solid #000;
        }
        
        .signature-title {
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 12px;
        }
        
        .signature-block {
            margin: 25px 0;
        }
        
        .signature-line {
            margin-bottom: 25px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        
        .signature-field {
            margin-bottom: 8px;
            font-size: 11px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            color: #666;
            font-size: 10px;
            line-height: 1.3;
        }
        
        .footer strong {
            color: #000;
        }
        
        /* Watermark */
        .watermark {
            position: fixed;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(0,0,0,0.03);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
        }
        
        /* Page Break */
        .page-break {
            page-break-before: always;
            margin-top: 30px;
        }
        
        /* Utility Classes */
        .text-center {
            text-align: center;
        }
        
        .mb-10 {
            margin-bottom: 10px;
        }
        
        .mt-20 {
            margin-top: 20px;
        }
        
        /* Print Styles */
        @media print {
            body {
                font-size: 11px;
                line-height: 1.3;
            }
            
            .container {
                width: 100%;
                margin: 0;
                padding: 0;
            }
            
            .section {
                margin: 15px 0;
            }
            
            .password-section {
                border: 2px dashed #000;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="watermark">CONFIDENTIAL</div>
    
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <div class="logo-section">
                <!-- Logo - Using base64 encoded placeholder since file path may not work in PDF -->
                <div style="width: 80px; height: 80px; border: 2px solid #000; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                    DialiEase<br>LOGO
                </div>
            </div>
            
            <div class="institution-info">
                <div class="institution-name">National Kidney and Transplant Institute</div>
                <div class="department-name">Continuous Ambulatory Peritoneal Dialysis (CAPD) Department</div>
                <div class="document-title">Healthcare Provider Pre-Registration</div>
                <div class="document-subtitle">System Access Credentials & Agreement</div>
            </div>
        </div>

        <!-- Urgent Notice -->
        <div class="urgent-note">
            IMPORTANT: Complete your registration by changing your temporary password upon first login
        </div>

        <!-- Provider Information -->
        <div class="section">
            <div class="section-title">Provider Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Full Name:</div>
                    <div class="info-value">
                        {{ $user->first_name ?? 'First Name' }} 
                        {{ $user->middle_name ? $user->middle_name . ' ' : '' }} 
                        {{ $user->last_name ?? 'Last Name' }} 
                        {{ $user->suffix ? $user->suffix : '' }}
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Employee Number:</div>
                    <div class="info-value">{{ $user->employeeNumber ?? 'N/A' }}</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Position/Role:</div>
                    <div class="info-value">{{ ucfirst($user->userLevel ?? 'User') }}</div>
                </div>
                
                @if($user->specialization ?? false)
                <div class="info-row">
                    <div class="info-label">Specialization:</div>
                    <div class="info-value">{{ $user->specialization }}</div>
                </div>
                @endif
                
                @if($user->Doc_license ?? false)
                <div class="info-row">
                    <div class="info-label">Professional License:</div>
                    <div class="info-value">{{ $user->Doc_license }}</div>
                </div>
                @endif
                
                @if($user->email ?? false)
                <div class="info-row">
                    <div class="info-label">Email Address:</div>
                    <div class="info-value">{{ $user->email }}</div>
                </div>
                @endif
                
                <div class="info-row">
                    <div class="info-label">Registration Date:</div>
                    <div class="info-value">{{ date('F j, Y', strtotime($user->created_at ?? 'now')) }}</div>
                </div>
            </div>
        </div>

        <!-- System Access Credentials -->
        <div class="section">
            <div class="section-title">System Access Credentials</div>
            
            <div class="password-section">
                <div class="password-title">Temporary Password</div>
                <div class="password-value">{{ $password ?? 'TEMP_PASSWORD' }}</div>
            </div>
            
            <div class="security-warning">
                <strong>SECURITY REQUIREMENT:</strong> You must change this temporary password immediately upon first login to complete your registration and gain full system access.
            </div>
        </div>

        <!-- Page Break for Instructions -->
        <div class="page-break"></div>

        <!-- System Access Instructions -->
        <div class="section">
            <div class="section-title">System Access Instructions</div>
            <div class="instructions">
                <ol>
                    <li><strong>Access the Portal:</strong> Navigate to the NKTI CAPD System login portal using the provided URL</li>
                    <li><strong>Enter Credentials:</strong> 
                        <ul style="margin: 5px 0 5px 20px;">
                            <li>Employee Number: <strong>{{ $user->employeeNumber ?? 'N/A' }}</strong></li>
                            <li>Temporary Password: Use the password provided above</li>
                        </ul>
                    </li>
                    <li><strong>Password Change:</strong> Follow the system prompts to set your permanent, secure password</li>
                    <li><strong>Profile Completion:</strong> Complete your professional profile information in the system</li>
                    <li><strong>Terms Acceptance:</strong> Review and accept the system terms of use and confidentiality agreement</li>
                    <li><strong>System Access:</strong> Once completed, you will have full access to the CAPD system</li>
                </ol>
            </div>
        </div>

        <!-- Page Break for Terms -->
        <div class="page-break"></div>

        <!-- Terms and Conditions -->
        <div class="section terms-section">
            <div class="section-title">Terms and Conditions</div>
            <div class="terms-content">
                <h4>NKTI CAPD Healthcare Provider Agreement</h4>
                
                <p><strong>Effective Date:</strong> {{ date('F j, Y') }}</p>
                
                <p><strong>1. Acceptance of Terms</strong><br>
                By accessing the NKTI CAPD System, you agree to comply with all institutional policies and procedures governing the use of electronic health records and patient data management systems.</p>
                
                <p><strong>2. Confidentiality Agreement</strong><br>
                You are required to maintain strict confidentiality of all patient information and system credentials. Unauthorized disclosure of patient data or system access credentials is strictly prohibited and may result in disciplinary action.</p>
                
                <p><strong>3. System Usage Guidelines</strong><br>
                The CAPD system shall be used exclusively for legitimate healthcare purposes related to patient care. All activities are subject to audit and monitoring to ensure compliance with institutional standards and regulatory requirements.</p>
                
                <p><strong>4. Password Security</strong><br>
                You are responsible for maintaining the security of your login credentials. Passwords must be changed immediately upon first login and periodically as per institutional security policies. Never share your password with anyone.</p>
                
                <p><strong>5. Compliance with Regulations</strong><br>
                All system usage must comply with the Data Privacy Act of 2012, the Hospital Information System Act, and other relevant healthcare regulations governing patient data protection and privacy.</p>
                
                <p><strong>6. Termination of Access</strong><br>
                System access may be revoked for violation of institutional policies, termination of employment, or as deemed necessary by the system administrators to maintain system security and integrity.</p>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-title">Provider Acknowledgement</div>
            <p>I acknowledge receipt of my system access credentials and confirm that I have read, understood, and agree to comply with the terms and conditions outlined above.</p>
            
            <div class="signature-block">
                <div class="signature-line">
                    <div class="signature-field">
                        <strong>Signature:</strong> ___________________________________________
                        <div style="font-size: 9px; color: #666; margin-top: 2px;">(Sign above this line)</div>
                    </div>
                </div>
                
                <div class="signature-line">
                    <div class="signature-field">
                        <strong>Printed Name:</strong> {{ $user->first_name ?? 'First Name' }} {{ $user->last_name ?? 'Last Name' }}
                    </div>
                    
                    <div class="signature-field">
                        <strong>Employee Number:</strong> {{ $user->employeeNumber ?? 'N/A' }}
                    </div>
                    
                    <div class="signature-field">
                        <strong>Date:</strong> _________________________
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>NATIONAL KIDNEY AND TRANSPLANT INSTITUTE</strong></p>
            <p>This document contains confidential information. Proper handling and secure storage are required.</p>
            <p>Document Generated: {{ date('F j, Y g:i A') }} | System Reference: CAPD-PR-{{ $user->employeeNumber ?? 'N/A' }}</p>
        </div>
    </div>
</body>
</html>