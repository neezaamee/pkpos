<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Service Suspended</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; padding: 24px; margin: 0;">
  <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 32px;">
    <h2 style="color: #e11d48; margin-top: 0;">Service Suspended</h2>
    <p>Dear {{ $owner->name }},</p>
    <p>The SaaS subscription for <strong>{{ $tenant->name }}</strong> has exceeded the permitted grace period and is currently overdue.</p>
    
    <div style="background: #fff1f2; border-left: 4px solid #f43f5e; padding: 16px; margin: 24px 0; border-radius: 4px;">
      <p style="margin: 0; font-size: 14px; color: #9f1239;"><strong>Status:</strong> Store Access & POS Terminal Sync Temporarily Suspended</p>
      <p style="margin: 8px 0 0 0; font-size: 13px; color: #be123c;">{{ $reason }}</p>
    </div>

    <p>To restore cashier sales sync and backoffice management immediately, please submit payment for your pending invoice or contact our corporate billing desk.</p>

    <p style="margin-top: 32px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 16px;">
      01POS Retail Cloud • Billing & Accounts • UAN: +92 42 111 010 101
    </p>
  </div>
</body>
</html>
