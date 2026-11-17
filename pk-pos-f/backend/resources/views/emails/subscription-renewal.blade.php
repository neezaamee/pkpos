<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Subscription Renewal Notice</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; padding: 24px; margin: 0;">
  <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 32px;">
    <h2 style="color: #0f172a; margin-top: 0;">Subscription Renewal Reminder</h2>
    <p>Dear {{ $owner->name }},</p>
    <p>Your subscription for <strong>{{ $tenant->name }}</strong> on the <strong>{{ $subscription->plan?->name ?? 'Standard' }}</strong> plan is scheduled to expire in <strong>{{ $daysRemaining }} day(s)</strong>.</p>
    
    <div style="background: #f1f5f9; border-left: 4px solid #6366f1; padding: 16px; margin: 24px 0; border-radius: 4px;">
      <p style="margin: 0 0 8px 0; font-size: 14px;"><strong>Expiration Date:</strong> {{ $subscription->ends_at?->format('F d, Y') ?? 'Soon' }}</p>
      <p style="margin: 0; font-size: 14px;"><strong>Amount Due:</strong> Rs. {{ number_format(($subscription->plan?->price_monthly ?? 0) / 100, 2) }}</p>
    </div>

    <p>To ensure uninterrupted POS terminal checkout and FBR automated sync at your store counters, please settle your subscription invoice through online banking, JazzCash, EasyPaisa, or 1Bill.</p>

    <p style="margin-top: 32px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 16px;">
      01POS Retail Cloud • Automated Billing System • Lahore, Pakistan
    </p>
  </div>
</body>
</html>
