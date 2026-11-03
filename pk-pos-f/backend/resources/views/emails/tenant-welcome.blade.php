<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to PK POS</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 40px 20px; color: #1e293b; }
        .container { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .header { background: #0f172a; padding: 32px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0 0 6px; font-size: 24px; font-weight: 700; letter-spacing: -0.02em; }
        .header p { margin: 0; font-size: 14px; color: #94a3b8; }
        .content { padding: 32px; }
        .badge { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 6px; background: #e0e7ff; color: #4338ca; margin-bottom: 16px; }
        .credentials-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0; }
        .credentials-row { margin-bottom: 10px; font-size: 14px; }
        .credentials-row:last-child { margin-bottom: 0; }
        .credentials-label { font-weight: 600; color: #64748b; width: 120px; display: inline-block; }
        .credentials-value { font-family: monospace; font-weight: 600; color: #0f172a; }
        .btn { display: inline-block; background: #4f46e5; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; text-align: center; }
        .btn-wrapper { text-align: center; margin: 28px 0 10px; }
        .footer { border-top: 1px solid #e2e8f0; padding: 20px 32px; font-size: 12px; color: #94a3b8; text-align: center; background: #f8fafc; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PK POS Platform</h1>
            <p>Pakistan Integrated Retail & Point of Sale System</p>
        </div>
        <div class="content">
            <span class="badge">Tenant Registration</span>
            <h2>Welcome, {{ $owner->name }}!</h2>
            <p>Your tenant organization <strong>{{ $tenant->name }}</strong> has been configured on the PK POS platform.</p>
            <p>Below are your credentials to access the administrative control panel:</p>

            <div class="credentials-box">
                <div class="credentials-row">
                    <span class="credentials-label">Tenant:</span>
                    <span class="credentials-value">{{ $tenant->name }}</span>
                </div>
                <div class="credentials-row">
                    <span class="credentials-label">Province:</span>
                    <span class="credentials-value">{{ strtoupper($tenant->province) }}</span>
                </div>
                <div class="credentials-row">
                    <span class="credentials-label">Email:</span>
                    <span class="credentials-value">{{ $owner->email }}</span>
                </div>
                <div class="credentials-row">
                    <span class="credentials-label">Temporary Password:</span>
                    <span class="credentials-value">{{ $temporaryPassword }}</span>
                </div>
            </div>

            <p style="font-size: 13px; color: #64748b;">For security, please log in and change your password upon initial sign-in.</p>

            <div class="btn-wrapper">
                <a href="{{ url('/login') }}" class="btn">Sign In to Admin Portal</a>
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} PK POS Platform. All rights reserved.
        </div>
    </div>
</body>
</html>
