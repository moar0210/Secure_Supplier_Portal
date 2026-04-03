<?php

declare(strict_types=1);

final class InvoiceController extends BaseController
{
    private InvoiceService $invoiceService;

    public function __construct(View $view, Auth $auth, Database $db, Crypto $crypto, AdsService $adsService, SupplierService $supplierService, array $config)
    {
        parent::__construct($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
        $this->invoiceService = new InvoiceService($this->pdo(), $crypto, $supplierService, $config);
    }

    public function adminInvoices(): void
    {
        $this->auth->requireRole('ADMIN');

        $filters = [
            'status' => (string)($_GET['status'] ?? ''),
            'invoice_number' => (string)($_GET['invoice_number'] ?? ''),
            'supplier_id' => (string)($_GET['supplier_id'] ?? ''),
            'billing_month' => (string)($_GET['billing_month'] ?? ''),
        ];

        $error = null;
        $notice = trim((string)($_GET['notice'] ?? ''));
        if ($notice === '') {
            $notice = null;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            try {
                $action = (string)($_POST['action'] ?? '');
                if ($action === 'generate') {
                    $billingMonth = (string)($_POST['billing_month'] ?? date('Y-m'));
                    $result = $this->invoiceService->generateMonthlyInvoices($billingMonth, (int)$this->auth->userId());
                    $notice = sprintf(
                        'Billing %s: created %d, updated %d, skipped %d, failed %d.',
                        $result['billing_month'],
                        $result['created'],
                        $result['updated'],
                        $result['skipped'],
                        $result['failed']
                    );

                    $this->logActivity('Monthly invoices generated', [
                        'billing_month' => $result['billing_month'],
                        'created' => $result['created'],
                        'updated' => $result['updated'],
                        'skipped' => $result['skipped'],
                        'failed' => $result['failed'],
                        'actor_user_id' => $this->auth->userId(),
                    ]);

                    if ($result['errors'] !== []) {
                        $notice .= ' First error: ' . $result['errors'][0];
                    }

                    $this->redirect('?page=admin_invoices&notice=' . rawurlencode($notice) . '&billing_month=' . rawurlencode($billingMonth));
                }

                if ($action === 'check_overdue') {
                    $count = $this->invoiceService->checkOverdue((int)$this->auth->userId());
                    $this->logActivity('Overdue check completed', [
                        'marked_overdue' => $count,
                        'actor_user_id' => $this->auth->userId(),
                    ]);

                    $notice = sprintf('Marked %d sent invoice(s) as overdue.', $count);
                    $this->redirect('?page=admin_invoices&notice=' . rawurlencode($notice));
                }

                throw new RuntimeException('Unknown action.');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to process the invoice action right now.');
            }
        }

        $this->render('view_admin_invoices', [
            'filters' => $filters,
            'rows' => $this->invoiceService->listInvoicesForAdmin($filters),
            'error' => $error,
            'notice' => $notice,
        ], 200, 'Invoices');
    }

    public function adminInvoiceView(): void
    {
        $this->auth->requireRole('ADMIN');

        $idRaw = $_GET['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->redirect('?page=404');
        }

        $invoiceId = (int)$idRaw;
        $error = null;
        $notice = trim((string)($_GET['notice'] ?? ''));
        if ($notice === '') {
            $notice = null;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            try {
                $action = (string)($_POST['action'] ?? '');
                if ($action === 'mark_sent') {
                    $this->invoiceService->transitionToSent($invoiceId, (int)$this->auth->userId());
                    $this->logActivity('Invoice marked as sent', [
                        'invoice_id' => $invoiceId,
                        'actor_user_id' => $this->auth->userId(),
                    ]);
                    $this->redirect('?page=admin_invoice_view&id=' . $invoiceId . '&notice=' . rawurlencode('Invoice marked as sent.'));
                }

                if ($action === 'record_payment') {
                    $this->invoiceService->recordPayment($invoiceId, $_POST, (int)$this->auth->userId());
                    $this->logActivity('Invoice payment recorded', [
                        'invoice_id' => $invoiceId,
                        'actor_user_id' => $this->auth->userId(),
                    ]);
                    $this->redirect('?page=admin_invoice_view&id=' . $invoiceId . '&notice=' . rawurlencode('Payment recorded and invoice marked as paid.'));
                }

                throw new RuntimeException('Unknown action.');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to update this invoice right now.');
            }
        }

        $invoice = $this->invoiceService->getInvoiceDetailForAdmin($invoiceId);
        if ($invoice === null) {
            $this->redirect('?page=404');
        }

        $this->render('view_admin_invoice', [
            'invoice' => $invoice,
            'error' => $error,
            'notice' => $notice,
        ], 200, 'Invoice');
    }

    public function adminPricingRules(): void
    {
        $this->auth->requireRole('ADMIN');

        $error = null;
        $notice = trim((string)($_GET['notice'] ?? ''));
        if ($notice === '') {
            $notice = null;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            try {
                $action = (string)($_POST['action'] ?? '');

                if ($action === 'create') {
                    $ruleId = $this->invoiceService->createPricingRule($_POST);
                    $this->logActivity('Pricing rule created', [
                        'pricing_rule_id' => $ruleId,
                        'actor_user_id' => $this->auth->userId(),
                    ]);
                    $this->redirect('?page=admin_pricing_rules&notice=' . rawurlencode('Pricing rule created.'));
                }

                if ($action === 'update') {
                    $idRaw = (string)($_POST['id'] ?? '');
                    if (!ctype_digit($idRaw)) {
                        throw new RuntimeException('Invalid pricing rule id.');
                    }

                    $this->invoiceService->updatePricingRule((int)$idRaw, $_POST);
                    $this->logActivity('Pricing rule updated', [
                        'pricing_rule_id' => (int)$idRaw,
                        'actor_user_id' => $this->auth->userId(),
                    ]);
                    $this->redirect('?page=admin_pricing_rules&notice=' . rawurlencode('Pricing rule updated.'));
                }

                if ($action === 'deactivate') {
                    $idRaw = (string)($_POST['id'] ?? '');
                    if (!ctype_digit($idRaw)) {
                        throw new RuntimeException('Invalid pricing rule id.');
                    }

                    $this->invoiceService->deactivatePricingRule((int)$idRaw);
                    $this->logActivity('Pricing rule deactivated', [
                        'pricing_rule_id' => (int)$idRaw,
                        'actor_user_id' => $this->auth->userId(),
                    ]);
                    $this->redirect('?page=admin_pricing_rules&notice=' . rawurlencode('Pricing rule deactivated.'));
                }

                throw new RuntimeException('Unknown action.');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to update pricing rules right now.');
            }
        }

        $this->render('view_admin_pricing_rules', [
            'rows' => $this->invoiceService->listPricingRules(),
            'error' => $error,
            'notice' => $notice,
        ], 200, 'Pricing Rules');
    }

    public function supplierInvoices(): void
    {
        $this->auth->requireRole('SUPPLIER');

        $supplierId = $this->auth->supplierId();
        if ($supplierId === null) {
            $this->redirect('?page=403');
        }

        $filters = [
            'status' => (string)($_GET['status'] ?? ''),
            'billing_month' => (string)($_GET['billing_month'] ?? ''),
        ];

        $this->render('view_supplier_invoices', [
            'filters' => $filters,
            'rows' => $this->invoiceService->listInvoicesForSupplier($supplierId, $filters),
        ], 200, 'My Invoices');
    }

    public function pdf(): void
    {
        $this->auth->requireLogin();

        $idRaw = $_GET['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->redirect('?page=404');
        }

        $invoiceId = (int)$idRaw;
        $invoice = null;

        if ($this->auth->hasRole('ADMIN')) {
            $invoice = $this->invoiceService->getInvoiceDetailForAdmin($invoiceId);
        } elseif ($this->auth->hasRole('SUPPLIER')) {
            $supplierId = $this->auth->supplierId();
            if ($supplierId === null) {
                $this->redirect('?page=403');
            }

            $invoice = $this->invoiceService->getInvoiceDetailForSupplier($invoiceId, $supplierId);
        }

        if ($invoice === null) {
            $this->redirect('?page=403');
        }

        $binary = $this->invoiceService->generatePdfBinary($invoice);
        $filename = $this->invoiceService->downloadFilename($invoice);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($binary));
        echo $binary;
        exit;
    }
}
