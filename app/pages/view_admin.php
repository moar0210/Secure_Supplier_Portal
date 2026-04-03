<h1>Admin Dashboard</h1>

<p>Use this area to manage the portal workflow across supplier profiles, advertisement approvals, invoicing, pricing rules, and security checks.</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:20px;">
    <div style="border:1px solid #ccc;padding:16px;background:#fafafa;">
        <h2 style="margin-top:0;">Suppliers</h2>
        <p>Review supplier records and verify profile data before billing and approvals.</p>
        <a href="?page=suppliers">Open supplier list</a>
    </div>

    <div style="border:1px solid #ccc;padding:16px;background:#fafafa;">
        <h2 style="margin-top:0;">Ads Queue</h2>
        <p>Approve or reject supplier advertisements and inspect the status history for each listing.</p>
        <a href="?page=admin_ads_queue">Review advertisements</a>
    </div>

    <div style="border:1px solid #ccc;padding:16px;background:#fafafa;">
        <h2 style="margin-top:0;">Invoices</h2>
        <p>Generate monthly drafts, mark invoices as sent, track payments, and check overdue balances.</p>
        <a href="?page=admin_invoices">Open invoicing</a>
    </div>

    <div style="border:1px solid #ccc;padding:16px;background:#fafafa;">
        <h2 style="margin-top:0;">Pricing Rules</h2>
        <p>Maintain the pricing and VAT rules used by the monthly invoice generation workflow.</p>
        <a href="?page=admin_pricing_rules">Manage pricing rules</a>
    </div>

    <div style="border:1px solid #ccc;padding:16px;background:#fafafa;">
        <h2 style="margin-top:0;">Security Check</h2>
        <p>Verify secure session behavior, cookie settings, and the current encryption configuration.</p>
        <a href="?page=security_check">Open security check</a>
    </div>
</div>
