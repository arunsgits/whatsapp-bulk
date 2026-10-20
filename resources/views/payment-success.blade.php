<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="3;url={{ $dashboardUrl }}">
    <title>Subscription Renewal Successful</title>
    <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
    <link rel="shortcut icon" type="image/png" href="{{ $faviconUrl }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0b1329; color: #f8fafc; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 36px 32px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        .icon { width: 68px; height: 68px; background: rgba(34, 197, 94, 0.15); color: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; }
        h1 { font-size: 24px; font-weight: 700; margin: 0 0 8px; color: #ffffff; }
        p { color: #94a3b8; font-size: 14px; margin: 0 0 20px; line-height: 1.5; }
        .details { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; padding: 14px 18px; text-align: left; margin-bottom: 22px; font-size: 14px; }
        .row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #1e293b; color: #cbd5e1; }
        .row.total-row { border-bottom: none; font-weight: 700; color: #22c55e; font-size: 15px; padding-top: 10px; margin-top: 2px; }
        .btn { display: inline-block; width: 100%; box-sizing: border-box; background: #3b82f6; color: white; padding: 13px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; transition: background 0.2s; }
        .btn:hover { background: #2563eb; }
        .redirect-notice { font-size: 13px; color: #94a3b8; margin-top: 14px; }
        .countdown-badge { font-weight: 700; color: #38bdf8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#10003;</div>
        <h1>Subscription Renewed!</h1>
        <p>Thank you, {{ $userName }}. Your subscription for <strong>{{ $packageName }}</strong> has been successfully renewed and activated.</p>
        <div class="details">
            {!! $detailsHtml !!}
        </div>
        <a href="{{ $dashboardUrl }}" class="btn">Return to Dashboard</a>
        <div class="redirect-notice">
            Redirecting automatically in <span id="countdown" class="countdown-badge">3</span> seconds...
        </div>
    </div>
    <script>
        var seconds = 3;
        var countdownEl = document.getElementById('countdown');
        var timer = setInterval(function() {
            seconds--;
            if (countdownEl) countdownEl.innerText = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = "{{ $dashboardUrl }}";
            }
        }, 1000);
        setTimeout(function() {
            window.location.href = "{{ $dashboardUrl }}";
        }, 3000);
    </script>
</body>
</html>
