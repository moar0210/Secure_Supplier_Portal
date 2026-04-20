# Vendored Dependencies

This project intentionally vendors small runtime dependencies so the thesis
handoff can run on XAMPP without Composer.

## TCPDF

- Package: `tecnickcom/tcpdf`
- Version: `6.11.2`
- Source: <https://github.com/tecnickcom/TCPDF>
- Purpose: invoice PDF rendering in `app/lib/InvoiceService.php`

To update, replace `app/vendor/tcpdf` with the matching upstream release and
rerun the PHP lint plus `app/scripts/test_invoicing.php`.
