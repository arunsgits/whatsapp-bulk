<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Plan Renewal Payment</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 36px; max-width: 440px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        h2 { margin: 0 0 8px; font-size: 22px; font-weight: 700; color: #fff; }
        .sub { color: #94a3b8; font-size: 14px; margin-bottom: 24px; }
        .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #334155; font-size: 14px; }
        .total { font-weight: 700; font-size: 18px; color: #38bdf8; border-bottom: none; padding-top: 14px; }
        .btn-pay { width: 100%; background: #2563eb; color: #fff; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 24px; transition: background 0.2s; }
        .btn-pay:hover { background: #1d4ed8; }
        .btn-back:hover { background: #475569 !important; }
        .btn-cancel:hover { background: #ef4444 !important; color: #fff !important; }
        .note { font-size: 12px; color: #64748b; margin-top: 14px; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Renew {{ $planName }}</h2>
        <div class="sub">Secure payment gateway powered by Razorpay</div>
        <div class="row"><span>Subscriber</span><span>{{ $userName }}</span></div>
        <div class="row"><span>Billing Cycle</span><span>{{ $periodLabel }}</span></div>
        <div class="row"><span>Base Renewal Price</span><span>₹{{ $order->base_amount }}</span></div>
        {!! $taxRowsHtml !!}
        <div class="row total"><span>Total Payable</span><span>₹{{ $totalFormatted }}</span></div>
        <button class="btn-pay" id="payBtn" onclick="payNow()">&#9889; Pay ₹{{ $totalFormatted }} & Renew</button>
        <div style="display: flex; gap: 12px; margin-top: 14px;">
            <button type="button" class="btn-back" onclick="goBack()" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #475569; background: #334155; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                &#8592; Back
            </button>
            <button type="button" class="btn-cancel" onclick="cancelOrder()" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #ef4444; background: transparent; color: #ef4444; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                &#10005; Cancel
            </button>
        </div>
        <div class="note">Encrypted & Secure 256-Bit SSL Payment</div>
    </div>
    <script>
        var backUrl = "{{ $backUrl }}";
        function goBack() {
            if (backUrl) {
                window.location.href = backUrl;
            } else if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/';
            }
        }
        function cancelOrder() {
            if (confirm("Are you sure you want to cancel this payment link?")) {
                fetch('/api/plan/payment-link/cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ receipt: '{{ $receipt }}' })
                }).finally(function() {
                    if (backUrl) {
                        window.location.href = backUrl;
                    } else if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = '/';
                    }
                });
            }
        }
        function payNow() {
            var key = "{{ $keyId }}";
            var orderId = "{{ $orderId }}";
            if (!key) {
                // If in dev/test mode without live keys, prompt or simulate
                if (confirm("Razorpay key is not configured in settings. Simulate payment completion for testing?")) {
                    var mockPayId = "pay_mock_" + Date.now();
                    window.location.href = "{{ $callbackUrl }}?razorpay_payment_id=" + mockPayId + "&razorpay_payment_link_id={{ $receipt }}&razorpay_payment_link_status=paid";
                }
                return;
            }
            var options = {
                "key": key,
                "amount": {{ $amountPaise }},
                "currency": "INR",
                "name": "{{ config('app.name', 'ZapReach') }}",
                "description": "Renewal: {{ $planName }} ({{ $periodLabel }})",
                "order_id": orderId,
                "prefill": {
                    "name": "{{ $userName }}",
                    "email": "{{ $userEmail }}",
                    "contact": "{{ $userPhone }}"
                },
                "theme": { "color": "#2563eb" },
                "modal": {
                    "handleback": true,
                    "confirm_close": true,
                    "ondismiss": function() {
                        console.log("Razorpay checkout modal closed");
                    }
                },
                "handler": function (response) {
                    fetch("{{ $verifyUrl }}", {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "Accept": "application/json" },
                        body: JSON.stringify({
                            razorpay_order_id: response.razorpay_order_id || orderId,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature: response.razorpay_signature,
                            receipt: "{{ $receipt }}"
                        })
                    }).then(function(res) { return res.json(); }).then(function(data) {
                        window.location.href = "{{ $callbackUrl }}?razorpay_payment_id=" + response.razorpay_payment_id + "&razorpay_payment_link_id={{ $receipt }}&razorpay_payment_link_status=paid";
                    }).catch(function(err) {
                        alert("Verification error: " + err.message);
                    });
                }
            };
            var rzp = new Razorpay(options);
            rzp.open();
        }
    </script>
</body>
</html>
