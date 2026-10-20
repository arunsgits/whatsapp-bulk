<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet & Razorpay API Docs | ZapReach</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0c1220;
            --panel: #121a2b;
            --card: #182235;
            --line: #2a364d;
            --text: #e8eef8;
            --muted: #93a0b8;
            --green: #22c55e;
            --blue: #3b82f6;
            --amber: #f59e0b;
            --red: #ef4444;
            --cyan: #22d3ee;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, #1a2744 0%, var(--bg) 45%);
            color: var(--text);
            line-height: 1.55;
            min-height: 100vh;
        }
        .wrap { max-width: 980px; margin: 0 auto; padding: 32px 20px 64px; }
        .top {
            display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: flex-start;
            margin-bottom: 28px;
        }
        h1 { font-size: 1.75rem; letter-spacing: -0.03em; }
        .sub { color: var(--muted); margin-top: 6px; max-width: 720px; }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(34,211,238,.12); color: var(--cyan);
            border: 1px solid rgba(34,211,238,.35); border-radius: 999px;
            padding: 8px 14px; font-size: .85rem; font-weight: 600;
        }
        .card {
            background: var(--card); border: 1px solid var(--line); border-radius: 14px;
            padding: 18px 18px 16px; margin-bottom: 16px;
        }
        .card h2 { font-size: 1.05rem; margin-bottom: 10px; }
        .card p, .card li { color: var(--muted); font-size: .95rem; }
        .card ul { padding-left: 18px; }
        .card li { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: .9rem; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
        .endpoint {
            background: var(--panel); border: 1px solid var(--line); border-radius: 14px;
            margin-bottom: 18px; overflow: hidden;
        }
        .endpoint-head {
            display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
            padding: 14px 16px; border-bottom: 1px solid var(--line);
        }
        .method {
            font-family: 'IBM Plex Mono', monospace; font-size: .75rem; font-weight: 700;
            padding: 4px 8px; border-radius: 6px; color: #041016;
        }
        .get { background: var(--green); }
        .post { background: var(--blue); color: #fff; }
        .put { background: var(--amber); color: #111; }
        .path {
            font-family: 'IBM Plex Mono', monospace; font-size: .9rem; color: var(--cyan);
            word-break: break-all;
        }
        .endpoint-body { padding: 16px; }
        .label {
            font-size: .75rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .05em; margin: 14px 0 8px;
        }
        .endpoint-body > .label:first-child { margin-top: 0; }
        pre {
            background: #0a101c; border: 1px solid var(--line); border-radius: 10px;
            padding: 12px 14px; overflow-x: auto;
            font-family: 'IBM Plex Mono', monospace; font-size: .82rem; color: #d7e3f7;
        }
        code { font-family: 'IBM Plex Mono', monospace; font-size: .86rem; color: #c7d7f5; }
        .nav {
            display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0 8px;
        }
        .nav a {
            color: var(--text); text-decoration: none; font-size: .85rem;
            background: var(--panel); border: 1px solid var(--line);
            border-radius: 999px; padding: 7px 12px;
        }
        .nav a:hover { border-color: var(--cyan); color: var(--cyan); }
        .share { margin-top: 8px; font-size: .85rem; color: var(--muted); word-break: break-all; }
        .share strong { color: var(--text); }
        .ok { color: var(--green); font-weight: 600; }
        .section-title {
            font-size: 1.15rem; margin: 28px 0 12px; padding-bottom: 8px;
            border-bottom: 1px solid var(--line); color: var(--cyan);
        }
        footer { margin-top: 28px; color: var(--muted); font-size: .85rem; }
        a.main-docs { color: var(--cyan); }
        .steps { counter-reset: step; }
        .steps li { margin: 8px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Wallet &amp; Razorpay API Docs</h1>
            <p class="sub">Super Admin enables wallet per company user. That user then sees wallet in the app, recharges with Razorpay, and money is credited only to that user’s balance.</p>
            <p class="share"><strong>Share this page:</strong> <span id="shareUrl"></span></p>
        </div>
        <div class="badge">Per-user wallet · Razorpay INR</div>
    </div>

    <div class="nav">
        <a href="#flow">Flow</a>
        <a href="#billing">Bulk billing</a>
        <a href="#auth">Auth</a>
        <a href="#admin-enable">Admin enable</a>
        <a href="#admin-credit">Admin credit</a>
        <a href="#razorpay-keys">Razorpay keys</a>
        <a href="#user-show">Show wallet</a>
        <a href="#create-order">Create order</a>
        <a href="#verify">Verify payment</a>
        <a href="#frontend">Frontend</a>
    </div>

    <div class="card" id="billing">
        <h2>Bulk send billing (wallet-enabled users)</h2>
        <ul>
            <li><strong>Minimum balance:</strong> ₹250 (configurable) required to start a bulk campaign.</li>
            <li><strong>Charge on delivered:</strong> Marketing ₹0.86 · Utility ₹0.17 · Authentication ₹0.17 (defaults).</li>
            <li>While a campaign runs, if wallet cannot cover the next message, <strong>remaining contacts fail</strong> with “Insufficient wallet balance” — only messages already sent/delivered keep going.</li>
            <li>Users without wallet enabled are unchanged (plan limits only).</li>
        </ul>
        <div class="label">Admin update prices</div>
        <pre>PUT /api/v1/admin/settings/credentials/wallet-pricing

{
  "minBalance": 250,
  "priceMarketing": 0.86,
  "priceUtility": 0.17,
  "priceAuthentication": 0.17
}</pre>
    </div>

    <div class="card" id="flow">
        <h2>How it works</h2>
        <ol class="steps" style="padding-left:18px;color:var(--muted)">
            <li>Super Admin turns <code>walletEnabled</code> on for a company user.</li>
            <li>Login / profile for that user returns <code>walletEnabled: true</code> — show Wallet in the UI only then.</li>
            <li>User enters amount → <code>POST /api/wallet/create-order</code> → Razorpay Checkout.</li>
            <li>After payment → <code>POST /api/wallet/verify</code> → amount is added to <strong>that user’s</strong> <code>wallet_balance</code>.</li>
        </ol>
        <p style="margin-top:10px">Staff inherit the parent company wallet. Money is never shared across other companies.</p>
    </div>

    <div class="card" id="auth">
        <h2>Base URL &amp; Auth</h2>
        <p><strong>Customer API:</strong> <code id="baseUrl"></code></p>
        <p style="margin-top:8px"><strong>Super Admin API:</strong> <code id="adminBaseUrl"></code></p>
        <p style="margin-top:8px"><strong>Headers:</strong></p>
        <pre>Authorization: Bearer {token}
Content-Type: application/json</pre>
        <p style="margin-top:10px">Customer wallet routes need a user token (billing permission). Admin routes need a Super Admin token.</p>
    </div>

    <h2 class="section-title">Super Admin</h2>

    <div class="endpoint" id="admin-enable">
        <div class="endpoint-head">
            <span class="method put">PUT</span>
            <span class="path">/api/v1/admin/users/{userId}/wallet</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Enable or disable wallet for one company user. Also accepted on <code>PUT /api/v1/admin/users/{userId}</code> as <code>walletEnabled</code>.</p>
            <div class="label">Body</div>
            <pre>{
  "walletEnabled": true
}</pre>
            <div class="label">Response</div>
            <pre>{
  "success": true,
  "message": "Wallet enabled for this user",
  "data": {
    "userId": "usr_12",
    "walletEnabled": true,
    "wallet_enabled": true,
    "balance": 0
  }
}</pre>
        </div>
    </div>

    <div class="endpoint" id="admin-wallet-get">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/v1/admin/users/{userId}/wallet</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Balance, enable flag, and last 50 transactions for that user.</p>
        </div>
    </div>

    <div class="endpoint" id="admin-credit">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/v1/admin/users/{userId}/wallet/credit</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Add or deduct money without Razorpay (admin adjustment). Positive amount credits; negative deducts.</p>
            <pre>{
  "amount": 500,
  "description": "Promo credit"
}</pre>
        </div>
    </div>

    <div class="endpoint" id="admin-all-txns">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/v1/admin/wallet/transactions</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">All wallet and plan transactions across users (Super Admin). Supports separation via <code>transaction_type</code> filter, source, user, and date pagination.</p>
            <div class="label">Query params</div>
            <pre>transaction_type = wallet | plan | all (default: all)
page, limit
user_id / userId
type = credit | debit
source = razorpay | admin | usage | refund
date_from, date_to
search (name, email, company, description, reference)</pre>
            <div class="label">Separate History Examples</div>
            <pre>// 1. Pure Wallet Transactions (recharges, promo credits, campaign debits, refunds)
GET /api/v1/admin/wallet/transactions?transaction_type=wallet&amp;page=1&amp;limit=20

// 2. Plan Subscription Transactions Only (plan renewals, package purchases)
GET /api/v1/admin/wallet/transactions?transaction_type=plan&amp;page=1&amp;limit=20

// 3. Or use the Dedicated Plan Transactions Endpoint:
GET /api/v1/admin/plans/transactions?page=1&amp;limit=20</pre>
        </div>
    </div>

    <div class="endpoint" id="admin-wallet-summary">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/v1/admin/wallet/summary</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Totals: enabled users, balances, recharges, usage debits, pricing.</p>
        </div>
    </div>

    <div class="endpoint" id="razorpay-keys">
        <div class="endpoint-head">
            <span class="method put">PUT</span>
            <span class="path">/api/v1/admin/settings/credentials/razorpay</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Save platform Razorpay keys (or set <code>RAZORPAY_KEY_ID</code> / <code>RAZORPAY_KEY_SECRET</code> in <code>.env</code>). Keys appear on <code>GET /api/v1/admin/settings/credentials</code> under <code>razorpay</code> (secret masked).</p>
            <pre>{
  "keyId": "rzp_live_xxx",
  "keySecret": "xxxxxxxx"
}</pre>
        </div>
    </div>

    <h2 class="section-title">Customer app</h2>

    <div class="card">
        <h2>Show wallet only when enabled</h2>
        <p>Login and <code>GET /api/profile</code> (or getProfile) include:</p>
        <pre>{
  "walletEnabled": true,
  "wallet_enabled": true,
  "walletBalance": 250.00
}</pre>
        <p style="margin-top:8px">Hide Wallet / Recharge in the sidebar when <code>walletEnabled</code> is <span class="ok">false</span>.</p>
    </div>

    <div class="endpoint" id="user-show">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/wallet</span>
        </div>
        <div class="endpoint-body">
            <div class="label">Response</div>
            <pre>{
  "status": true,
  "data": {
    "walletEnabled": true,
    "balance": 250.00,
    "currency": "INR",
    "razorpayKeyId": "rzp_live_xxx",
    "userId": 12
  }
}</pre>
            <p style="margin-top:10px;color:var(--muted)">History: <code>GET /api/wallet/history</code> (Supports filters: category, search, date_from, date_to)</p>
        </div>
    </div>

    <div class="endpoint" id="wallet-history">
        <div class="endpoint-head">
            <span class="method get">GET</span>
            <span class="path">/api/wallet/history</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Get chronological wallet transaction history with filters for message categories, keyword search, date range, and pagination.</p>
            <div class="label">Query Parameters</div>
            <pre>transaction_type = wallet | plan | all (optional, default: all)
category  = MARKETING | UTILITY | AUTHENTICATION (case-insensitive, optional)
search    = text search on description, reference ID, amount, or source (optional)
date_from = YYYY-MM-DD (start of day, optional)
date_to   = YYYY-MM-DD (end of day, optional)
date      = YYYY-MM-DD (single day, optional)
page      = 1 (optional, default 1)
limit     = 50 (optional, default 50, max 100)</pre>
            <div class="label">Example Request</div>
            <pre>GET /api/wallet/history?category=Marketing&search=campaign&date_from=2026-10-01&date_to=2026-10-31&page=1&limit=25</pre>
            <div class="label">Response</div>
            <pre>{
  "status": true,
  "success": true,
  "data": [
    {
      "id": 142,
      "userId": 12,
      "type": "debit",
      "source": "usage",
      "category": "MARKETING",
      "amount": "0.86",
      "balance_after": "249.14",
      "reference_id": "delivery_38",
      "description": "Bulk MARKETING ₹0.86 · campaign #4 · delivery #38 (sent)",
      "created_at": "2026-10-01T12:00:00.000000Z"
    }
  ],
  "wallet": {
    "walletEnabled": true,
    "balance": 249.14,
    "currency": "INR"
  },
  "pagination": {
    "total": 1,
    "page": 1,
    "limit": 25,
    "totalPages": 1,
    "has_more": false
  },
  "summary": {
    "totalCredit": 0.00,
    "totalDebit": 0.86,
    "net": -0.86,
    "currency": "INR"
  },
  "filters": {
    "category": ["MARKETING"],
    "search": "campaign",
    "date_from": "2026-10-01",
    "date_to": "2026-10-31"
  }
}</pre>
        </div>
    </div>

    <div class="endpoint" id="create-order">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/wallet/create-order</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Creates a Razorpay order for <strong>this</strong> logged-in company. Fails if wallet is disabled or Razorpay keys are missing. Amount is INR, minimum 1.</p>
            <div class="label">Body</div>
            <pre>{ "amount": 100 }</pre>
            <div class="label">Response</div>
            <pre>{
  "status": true,
  "data": {
    "order_id": "order_Rxxxxxxxx",
    "amount": 100,
    "amount_paise": 10000,
    "currency": "INR",
    "key": "rzp_live_xxx",
    "prefill": {
      "name": "Cyberdyne",
      "email": "sarah@cyberdyne.com",
      "contact": "+15550199"
    }
  }
}</pre>
            <p style="margin-top:10px;color:var(--muted)">Same handler: <code>POST /api/credits/create-order</code></p>
        </div>
    </div>

    <div class="endpoint" id="verify">
        <div class="endpoint-head">
            <span class="method post">POST</span>
            <span class="path">/api/wallet/verify</span>
        </div>
        <div class="endpoint-body">
            <p style="color:var(--muted)">Verifies Razorpay HMAC signature, then credits <strong>only this user’s</strong> wallet. Safe to retry (already-paid orders are not double-credited).</p>
            <div class="label">Body</div>
            <pre>{
  "razorpay_order_id": "order_Rxxxxxxxx",
  "razorpay_payment_id": "pay_Pxxxxxxxx",
  "razorpay_signature": "a1b2c3..."
}</pre>
            <div class="label">Response</div>
            <pre>{
  "status": true,
  "message": "Payment verified and wallet credited",
  "data": {
    "payment_id": "pay_Pxxxxxxxx",
    "amount_added": 100,
    "balance": 350.00
  }
}</pre>
            <p style="margin-top:10px;color:var(--muted)">Aliases: <code>order_id</code>, <code>payment_id</code>, <code>signature</code> — also <code>POST /api/credits/verify</code></p>
        </div>
    </div>

    <div class="card" id="frontend">
        <h2>Razorpay Checkout (frontend)</h2>
        <pre>const Checkout = window.Razorpay({
  key: order.key,
  amount: order.amount_paise,
  currency: order.currency,
  order_id: order.order_id,
  name: 'Wallet Recharge',
  prefill: order.prefill,
  handler: async function (response) {
    await fetch(apiBase + '/wallet/verify', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        razorpay_order_id: response.razorpay_order_id,
        razorpay_payment_id: response.razorpay_payment_id,
        razorpay_signature: response.razorpay_signature
      })
    });
  }
});
Checkout.open();</pre>
        <p style="margin-top:10px">Load checkout script: <code>https://checkout.razorpay.com/v1/checkout.js</code></p>
    </div>

    <div class="card">
        <h2>Deploy</h2>
        <ul>
            <li>Run <code>php artisan migrate</code> (users.wallet_enabled, wallet_balance, wallet_orders, wallet_transactions, Razorpay columns on settings).</li>
            <li>User list/detail already return <code>walletEnabled</code> and <code>walletBalance</code> for the Super Admin UI switch.</li>
        </ul>
    </div>

    <footer>
        Full CRM API docs:
        <a class="main-docs" href="{{ url('/api-docs') }}">{{ url('/api-docs') }}</a>
        · Auto AI:
        <a class="main-docs" href="{{ url('/api-docs/auto-ai') }}">{{ url('/api-docs/auto-ai') }}</a>
        <br>
        © {{ date('Y') }} ZapReach — Wallet &amp; Razorpay API docs
    </footer>
</div>
<script>
    (function () {
        var pageUrl = window.location.href.split('#')[0];
        var origin = window.location.origin;
        var underServices = window.location.pathname.indexOf('/services') !== -1 || window.location.href.indexOf('/services/') !== -1;
        var apiBase = underServices ? origin + '/services/api' : origin + '/api';
        var adminBase = underServices ? origin + '/services/api/v1/admin' : origin + '/api/v1/admin';
        document.getElementById('shareUrl').textContent = pageUrl;
        document.getElementById('baseUrl').textContent = apiBase;
        document.getElementById('adminBaseUrl').textContent = adminBase;
    })();
</script>
</body>
</html>
