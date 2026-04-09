<?php

declare(strict_types=1);

final class InvoiceService
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SENT = 'SENT';
    public const STATUS_PAID = 'PAID';
    public const STATUS_OVERDUE = 'OVERDUE';

    private const DEFAULT_CURRENCY = 'SEK';
    private const DEFAULT_DUE_DAYS = 30;
    private const DEFAULT_VAT_RATE = 25.00;

    private const SNAPSHOT_AAD = [
        'supplier_name_snapshot' => 'invoices.supplier_name_snapshot',
        'supplier_short_name_snapshot' => 'invoices.supplier_short_name_snapshot',
        'contact_person_snapshot' => 'invoices.contact_person_snapshot',
        'supplier_email_snapshot' => 'invoices.supplier_email_snapshot',
        'supplier_vat_number_snapshot' => 'invoices.supplier_vat_number_snapshot',
        'homepage_snapshot' => 'invoices.homepage_snapshot',
        'address_line_1_snapshot' => 'invoices.address_line_1_snapshot',
        'address_line_2_snapshot' => 'invoices.address_line_2_snapshot',
        'city_snapshot' => 'invoices.city_snapshot',
        'region_snapshot' => 'invoices.region_snapshot',
        'postal_code_snapshot' => 'invoices.postal_code_snapshot',
    ];

    private PDO $pdo;
    private Crypto $crypto;
    private SupplierService $supplierService;
    private array $config;

    public function __construct(PDO $pdo, Crypto $crypto, SupplierService $supplierService, array $config = [])
    {
        $this->pdo = $pdo;
        $this->crypto = $crypto;
        $this->supplierService = $supplierService;
        $this->config = $config;
    }

    public function listInvoicesForAdmin(array $filters = []): array
    {
        $where = [];
        $params = [];

        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        if ($status !== '' && $status !== 'ALL') {
            $where[] = 'i.status = :status';
            $params[':status'] = $status;
        }

        $invoiceNumber = trim((string)($filters['invoice_number'] ?? ''));
        if ($invoiceNumber !== '') {
            $where[] = 'i.invoice_number LIKE :invoice_number';
            $params[':invoice_number'] = '%' . $invoiceNumber . '%';
        }

        $supplierId = trim((string)($filters['supplier_id'] ?? ''));
        if ($supplierId !== '' && ctype_digit($supplierId)) {
            $where[] = 'i.supplier_id = :supplier_id';
            $params[':supplier_id'] = (int)$supplierId;
        }

        $month = trim((string)($filters['billing_month'] ?? ''));
        if ($month !== '') {
            [$year, $monthNumber] = $this->parseBillingMonth($month);
            $where[] = 'i.billing_year = :billing_year AND i.billing_month = :billing_month';
            $params[':billing_year'] = $year;
            $params[':billing_month'] = $monthNumber;
        }

        $sql = "
            SELECT
                i.*,
                p.amount AS payment_amount,
                p.payment_date,
                p.payment_method,
                pr.name AS pricing_rule_name
            FROM invoices i
            LEFT JOIN invoice_payments p ON p.invoice_id = i.id
            LEFT JOIN pricing_rules pr ON pr.id = i.pricing_rule_id
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= '
            ORDER BY
                i.billing_year DESC,
                i.billing_month DESC,
                i.sequence_no DESC,
                i.id DESC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row = $this->decryptInvoiceRow($row);
        }
        unset($row);

        return $rows;
    }

    public function listInvoicesForSupplier(int $supplierId, array $filters = []): array
    {
        $params = [':supplier_id' => $supplierId];
        $where = ['i.supplier_id = :supplier_id'];

        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        if ($status !== '' && $status !== 'ALL') {
            $where[] = 'i.status = :status';
            $params[':status'] = $status;
        }

        $month = trim((string)($filters['billing_month'] ?? ''));
        if ($month !== '') {
            [$year, $monthNumber] = $this->parseBillingMonth($month);
            $where[] = 'i.billing_year = :billing_year AND i.billing_month = :billing_month';
            $params[':billing_year'] = $year;
            $params[':billing_month'] = $monthNumber;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                i.*,
                p.amount AS payment_amount,
                p.payment_date,
                p.payment_method,
                pr.name AS pricing_rule_name
            FROM invoices i
            LEFT JOIN invoice_payments p ON p.invoice_id = i.id
            LEFT JOIN pricing_rules pr ON pr.id = i.pricing_rule_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                i.billing_year DESC,
                i.billing_month DESC,
                i.sequence_no DESC,
                i.id DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row = $this->decryptInvoiceRow($row);
        }
        unset($row);

        return $rows;
    }

    public function getInvoiceDetailForAdmin(int $invoiceId): ?array
    {
        $invoice = $this->loadInvoiceRowById($invoiceId);
        if ($invoice === null) {
            return null;
        }

        return $this->attachInvoiceDetail($invoice);
    }

    public function getInvoiceDetailForSupplier(int $invoiceId, int $supplierId): ?array
    {
        $invoice = $this->loadInvoiceRowById($invoiceId);
        if ($invoice === null || (int)$invoice['supplier_id'] !== $supplierId) {
            return null;
        }

        return $this->attachInvoiceDetail($invoice);
    }

    public function generateMonthlyInvoices(string $billingMonth, int $actorUserId): array
    {
        [$year, $month] = $this->parseBillingMonth($billingMonth);
        [$periodStart, $periodEnd] = $this->billingPeriodBounds($year, $month);
        $pricingRule = $this->findApplicablePricingRule($periodStart, $periodEnd);

        if ($pricingRule === null) {
            throw new UserFacingException('No active pricing rule overlaps that billing month.');
        }

        $eligibleBySupplier = $this->collectBillableAdsBySupplier($periodStart, $periodEnd);
        $result = [
            'billing_month' => sprintf('%04d-%02d', $year, $month),
            'eligible_suppliers' => count($eligibleBySupplier),
            'eligible_ads' => 0,
            'created' => 0,
            'updated' => 0,
            'removed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($eligibleBySupplier as $ads) {
            $result['eligible_ads'] += count($ads);
        }

        foreach ($eligibleBySupplier as $supplierId => $ads) {
            try {
                $action = $this->syncDraftInvoiceForSupplier(
                    (int)$supplierId,
                    $year,
                    $month,
                    $periodStart,
                    $periodEnd,
                    $pricingRule,
                    $ads,
                    $actorUserId
                );

                if ($action === 'created') {
                    $result['created']++;
                } elseif ($action === 'updated') {
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                }
            } catch (Throwable $e) {
                $result['failed']++;
                $result['errors'][] = sprintf(
                    'Supplier #%d: %s',
                    (int)$supplierId,
                    $e instanceof UserFacingException ? $e->getMessage() : 'unexpected billing error'
                );
            }
        }

        $result['removed'] = $this->removeStaleDraftInvoicesForMonth(
            $year,
            $month,
            array_map('intval', array_keys($eligibleBySupplier))
        );

        return $result;
    }

    public function transitionToSent(int $invoiceId, int $actorUserId): void
    {
        $this->pdo->beginTransaction();

        try {
            $invoice = $this->loadInvoiceRowForUpdate($invoiceId);
            if ($invoice === null) {
                throw new UserFacingException('Invoice not found.');
            }

            if ((string)$invoice['status'] !== self::STATUS_DRAFT) {
                throw new UserFacingException('Only draft invoices can be marked as sent.');
            }

            if ($this->countInvoiceLines($invoiceId) < 1) {
                throw new UserFacingException('Cannot send an invoice without any billable lines.');
            }

            $today = new DateTimeImmutable('today');
            $dueDate = $today->add(new DateInterval('P' . $this->defaultDueDays() . 'D'));

            $stmt = $this->pdo->prepare("
                UPDATE invoices
                SET status = :status,
                    issue_date = :issue_date,
                    due_date = :due_date,
                    sent_at = NOW(),
                    overdue_at = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => self::STATUS_SENT,
                ':issue_date' => $today->format('Y-m-d'),
                ':due_date' => $dueDate->format('Y-m-d'),
                ':id' => $invoiceId,
            ]);

            $this->insertInvoiceStatusHistory(
                $invoiceId,
                (string)$invoice['status'],
                self::STATUS_SENT,
                $actorUserId,
                'Invoice marked as sent by admin.'
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function checkOverdue(int $actorUserId): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, status
                FROM invoices
                WHERE status = :status
                  AND due_date < CURDATE()
                ORDER BY due_date ASC, id ASC
                FOR UPDATE
            ");
            $stmt->execute([':status' => self::STATUS_SENT]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                $invoiceId = (int)$row['id'];
                $update = $this->pdo->prepare("
                    UPDATE invoices
                    SET status = :status,
                        overdue_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $update->execute([
                    ':status' => self::STATUS_OVERDUE,
                    ':id' => $invoiceId,
                ]);

                $this->insertInvoiceStatusHistory(
                    $invoiceId,
                    (string)$row['status'],
                    self::STATUS_OVERDUE,
                    $actorUserId,
                    'Marked overdue by manual overdue check.'
                );
            }

            $this->pdo->commit();

            return count($rows);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function recordPayment(int $invoiceId, array $input, int $actorUserId): void
    {
        $paymentAmount = $this->normalizeMoney($input['amount'] ?? null, 'Payment amount is required.');
        $paymentDate = $this->normalizeDateInput($input['payment_date'] ?? null, 'Payment date is required.');
        $paymentMethod = trim((string)($input['payment_method'] ?? ''));

        if ($paymentMethod === '' || mb_strlen($paymentMethod) > 100) {
            throw new UserFacingException('Payment method is required and must be 100 characters or fewer.');
        }

        $this->pdo->beginTransaction();

        try {
            $invoice = $this->loadInvoiceRowForUpdate($invoiceId);
            if ($invoice === null) {
                throw new UserFacingException('Invoice not found.');
            }

            $status = (string)$invoice['status'];
            if (!in_array($status, [self::STATUS_SENT, self::STATUS_OVERDUE], true)) {
                throw new UserFacingException('Only sent or overdue invoices can be marked as paid.');
            }

            if ($this->hasPaymentRecord($invoiceId)) {
                throw new UserFacingException('A payment has already been recorded for this invoice.');
            }

            $today = (new DateTimeImmutable('today'))->format('Y-m-d');
            if ($paymentDate > $today) {
                throw new UserFacingException('Payment date cannot be in the future.');
            }

            $issueDate = trim((string)($invoice['issue_date'] ?? ''));
            if ($issueDate !== '' && $paymentDate < $issueDate) {
                throw new UserFacingException('Payment date cannot be earlier than the invoice issue date.');
            }

            $expectedAmount = round((float)$invoice['total_amount'], 2);
            if (abs($paymentAmount - $expectedAmount) > 0.01) {
                throw new UserFacingException('Payment amount must match the invoice total for manual payment confirmation.');
            }

            $paymentStmt = $this->pdo->prepare("
                INSERT INTO invoice_payments (
                    invoice_id,
                    amount,
                    payment_date,
                    payment_method,
                    recorded_by_user_id
                ) VALUES (
                    :invoice_id,
                    :amount,
                    :payment_date,
                    :payment_method,
                    :recorded_by_user_id
                )
            ");
            $paymentStmt->execute([
                ':invoice_id' => $invoiceId,
                ':amount' => number_format($paymentAmount, 2, '.', ''),
                ':payment_date' => $paymentDate,
                ':payment_method' => $paymentMethod,
                ':recorded_by_user_id' => $actorUserId,
            ]);

            $update = $this->pdo->prepare("
                UPDATE invoices
                SET status = :status,
                    paid_at = :paid_at,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                ':status' => self::STATUS_PAID,
                ':paid_at' => $paymentDate . ' 00:00:00',
                ':id' => $invoiceId,
            ]);

            $this->insertInvoiceStatusHistory(
                $invoiceId,
                $status,
                self::STATUS_PAID,
                $actorUserId,
                sprintf('Payment recorded via %s.', $paymentMethod)
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function listPricingRules(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM pricing_rules
            ORDER BY
                is_active DESC,
                COALESCE(effective_from, '1000-01-01') DESC,
                id DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createPricingRule(array $input): int
    {
        $clean = $this->validatePricingRuleInput($input);

        $stmt = $this->pdo->prepare("
            INSERT INTO pricing_rules (
                name,
                description,
                price_per_ad,
                currency_code,
                vat_rate,
                effective_from,
                effective_to,
                is_active
            ) VALUES (
                :name,
                :description,
                :price_per_ad,
                :currency_code,
                :vat_rate,
                :effective_from,
                :effective_to,
                :is_active
            )
        ");
        $stmt->execute([
            ':name' => $clean['name'],
            ':description' => $clean['description'],
            ':price_per_ad' => $clean['price_per_ad'],
            ':currency_code' => $clean['currency_code'],
            ':vat_rate' => $clean['vat_rate'],
            ':effective_from' => $clean['effective_from'],
            ':effective_to' => $clean['effective_to'],
            ':is_active' => $clean['is_active'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updatePricingRule(int $ruleId, array $input): void
    {
        $clean = $this->validatePricingRuleInput($input);

        $stmt = $this->pdo->prepare("
            UPDATE pricing_rules
            SET name = :name,
                description = :description,
                price_per_ad = :price_per_ad,
                currency_code = :currency_code,
                vat_rate = :vat_rate,
                effective_from = :effective_from,
                effective_to = :effective_to,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':name' => $clean['name'],
            ':description' => $clean['description'],
            ':price_per_ad' => $clean['price_per_ad'],
            ':currency_code' => $clean['currency_code'],
            ':vat_rate' => $clean['vat_rate'],
            ':effective_from' => $clean['effective_from'],
            ':effective_to' => $clean['effective_to'],
            ':is_active' => $clean['is_active'],
            ':id' => $ruleId,
        ]);

        if ($stmt->rowCount() < 1 && !$this->pricingRuleExists($ruleId)) {
            throw new UserFacingException('Pricing rule not found.');
        }
    }

    public function deactivatePricingRule(int $ruleId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pricing_rules
            SET is_active = 0,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $ruleId]);

        if ($stmt->rowCount() < 1 && !$this->pricingRuleExists($ruleId)) {
            throw new UserFacingException('Pricing rule not found.');
        }
    }

    public function generatePdfBinary(array $invoice): string
    {
        require_once __DIR__ . '/../vendor/tcpdf/tcpdf.php';

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Supplier Portal');
        $pdf->SetAuthor((string)$this->invoiceConfig('issuer_name', 'HEDVC AB'));
        $pdf->SetTitle((string)$invoice['invoice_number']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setFontSubsetting(true);
        $pdf->AddPage();
        // DejaVu Sans is bundled with TCPDF and renders UTF-8 invoice content reliably.
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->writeHTML($this->buildInvoicePdfHtml($invoice), true, false, true, false, '');

        return (string)$pdf->Output($this->downloadFilename($invoice), 'S');
    }

    public function downloadFilename(array $invoice): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$invoice['invoice_number']) ?? '';
        $base = trim($base, '._-');

        return ($base === '' ? 'invoice' : $base) . '.pdf';
    }

    private function attachInvoiceDetail(array $invoice): array
    {
        $invoice = $this->decryptInvoiceRow($invoice);
        $invoice['lines'] = $this->loadInvoiceLines((int)$invoice['id']);
        $invoice['payment'] = $this->loadInvoicePayment((int)$invoice['id']);
        $invoice['status_history'] = $this->loadInvoiceStatusHistory((int)$invoice['id']);

        return $invoice;
    }

    private function loadInvoiceRowById(int $invoiceId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                i.*,
                p.amount AS payment_amount,
                p.payment_date,
                p.payment_method,
                pr.name AS pricing_rule_name
            FROM invoices i
            LEFT JOIN invoice_payments p ON p.invoice_id = i.id
            LEFT JOIN pricing_rules pr ON pr.id = i.pricing_rule_id
            WHERE i.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function loadInvoiceRowForUpdate(int $invoiceId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM invoices
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':id' => $invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function loadInvoiceLines(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM invoice_lines
            WHERE invoice_id = :invoice_id
            ORDER BY ad_title ASC, id ASC
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function loadInvoicePayment(int $invoiceId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM invoice_payments
            WHERE invoice_id = :invoice_id
            LIMIT 1
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function loadInvoiceStatusHistory(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                h.*,
                u.username
            FROM invoice_status_history h
            LEFT JOIN portal_users u ON u.id = h.changed_by_user_id
            WHERE h.invoice_id = :invoice_id
            ORDER BY h.changed_at DESC, h.id DESC
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function syncDraftInvoiceForSupplier(
        int $supplierId,
        int $year,
        int $month,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        array $pricingRule,
        array $ads,
        int $actorUserId
    ): string {
        $this->pdo->beginTransaction();

        try {
            $invoice = $this->loadInvoiceBySupplierMonthForUpdate($supplierId, $year, $month);
            if ($invoice !== null && (string)$invoice['status'] !== self::STATUS_DRAFT) {
                $this->pdo->commit();
                return 'skipped';
            }

            $snapshot = $this->buildSupplierSnapshot($supplierId);
            $today = new DateTimeImmutable('today');
            $issueDate = $today->format('Y-m-d');
            $dueDate = $today->add(new DateInterval('P' . $this->defaultDueDays() . 'D'))->format('Y-m-d');

            if ($invoice === null) {
                $sequenceNo = $this->reserveNextInvoiceSequence($year, $month);
                $invoiceNumber = $this->formatInvoiceNumber($year, $month, $sequenceNo);

                $insert = $this->pdo->prepare("
                    INSERT INTO invoices (
                        supplier_id, pricing_rule_id, billing_year, billing_month,
                        billing_period_start, billing_period_end, sequence_no, invoice_number,
                        status, currency_code, issue_date, due_date, vat_rate,
                        subtotal_amount, vat_amount, total_amount,
                        supplier_name_snapshot, supplier_short_name_snapshot, contact_person_snapshot,
                        supplier_email_snapshot, supplier_vat_number_snapshot, homepage_snapshot,
                        address_line_1_snapshot, address_line_2_snapshot, city_snapshot,
                        region_snapshot, postal_code_snapshot, country_code_snapshot,
                        generated_by_user_id
                    ) VALUES (
                        :supplier_id, :pricing_rule_id, :billing_year, :billing_month,
                        :billing_period_start, :billing_period_end, :sequence_no, :invoice_number,
                        :status, :currency_code, :issue_date, :due_date, :vat_rate,
                        0.00, 0.00, 0.00,
                        :supplier_name_snapshot, :supplier_short_name_snapshot, :contact_person_snapshot,
                        :supplier_email_snapshot, :supplier_vat_number_snapshot, :homepage_snapshot,
                        :address_line_1_snapshot, :address_line_2_snapshot, :city_snapshot,
                        :region_snapshot, :postal_code_snapshot, :country_code_snapshot,
                        :generated_by_user_id
                    )
                ");
                $insert->execute([
                    ':supplier_id' => $supplierId,
                    ':pricing_rule_id' => (int)$pricingRule['id'],
                    ':billing_year' => $year,
                    ':billing_month' => $month,
                    ':billing_period_start' => $periodStart->format('Y-m-d'),
                    ':billing_period_end' => $periodEnd->format('Y-m-d'),
                    ':sequence_no' => $sequenceNo,
                    ':invoice_number' => $invoiceNumber,
                    ':status' => self::STATUS_DRAFT,
                    ':currency_code' => (string)$pricingRule['currency_code'],
                    ':issue_date' => $issueDate,
                    ':due_date' => $dueDate,
                    ':vat_rate' => number_format((float)$pricingRule['vat_rate'], 2, '.', ''),
                    ':supplier_name_snapshot' => $snapshot['supplier_name_snapshot'],
                    ':supplier_short_name_snapshot' => $snapshot['supplier_short_name_snapshot'],
                    ':contact_person_snapshot' => $snapshot['contact_person_snapshot'],
                    ':supplier_email_snapshot' => $snapshot['supplier_email_snapshot'],
                    ':supplier_vat_number_snapshot' => $snapshot['supplier_vat_number_snapshot'],
                    ':homepage_snapshot' => $snapshot['homepage_snapshot'],
                    ':address_line_1_snapshot' => $snapshot['address_line_1_snapshot'],
                    ':address_line_2_snapshot' => $snapshot['address_line_2_snapshot'],
                    ':city_snapshot' => $snapshot['city_snapshot'],
                    ':region_snapshot' => $snapshot['region_snapshot'],
                    ':postal_code_snapshot' => $snapshot['postal_code_snapshot'],
                    ':country_code_snapshot' => $snapshot['country_code_snapshot'],
                    ':generated_by_user_id' => $actorUserId,
                ]);

                $invoiceId = (int)$this->pdo->lastInsertId();
                $this->insertInvoiceStatusHistory($invoiceId, null, self::STATUS_DRAFT, $actorUserId, 'Draft generated from monthly billing.');
                $action = 'created';
            } else {
                $invoiceId = (int)$invoice['id'];
                $update = $this->pdo->prepare("
                    UPDATE invoices
                    SET pricing_rule_id = :pricing_rule_id,
                        currency_code = :currency_code,
                        issue_date = :issue_date,
                        due_date = :due_date,
                        vat_rate = :vat_rate,
                        supplier_name_snapshot = :supplier_name_snapshot,
                        supplier_short_name_snapshot = :supplier_short_name_snapshot,
                        contact_person_snapshot = :contact_person_snapshot,
                        supplier_email_snapshot = :supplier_email_snapshot,
                        supplier_vat_number_snapshot = :supplier_vat_number_snapshot,
                        homepage_snapshot = :homepage_snapshot,
                        address_line_1_snapshot = :address_line_1_snapshot,
                        address_line_2_snapshot = :address_line_2_snapshot,
                        city_snapshot = :city_snapshot,
                        region_snapshot = :region_snapshot,
                        postal_code_snapshot = :postal_code_snapshot,
                        country_code_snapshot = :country_code_snapshot,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $update->execute([
                    ':pricing_rule_id' => (int)$pricingRule['id'],
                    ':currency_code' => (string)$pricingRule['currency_code'],
                    ':issue_date' => $issueDate,
                    ':due_date' => $dueDate,
                    ':vat_rate' => number_format((float)$pricingRule['vat_rate'], 2, '.', ''),
                    ':supplier_name_snapshot' => $snapshot['supplier_name_snapshot'],
                    ':supplier_short_name_snapshot' => $snapshot['supplier_short_name_snapshot'],
                    ':contact_person_snapshot' => $snapshot['contact_person_snapshot'],
                    ':supplier_email_snapshot' => $snapshot['supplier_email_snapshot'],
                    ':supplier_vat_number_snapshot' => $snapshot['supplier_vat_number_snapshot'],
                    ':homepage_snapshot' => $snapshot['homepage_snapshot'],
                    ':address_line_1_snapshot' => $snapshot['address_line_1_snapshot'],
                    ':address_line_2_snapshot' => $snapshot['address_line_2_snapshot'],
                    ':city_snapshot' => $snapshot['city_snapshot'],
                    ':region_snapshot' => $snapshot['region_snapshot'],
                    ':postal_code_snapshot' => $snapshot['postal_code_snapshot'],
                    ':country_code_snapshot' => $snapshot['country_code_snapshot'],
                    ':id' => $invoiceId,
                ]);
                $action = 'updated';
            }

            $this->syncInvoiceLines($invoiceId, $ads, $pricingRule);
            $this->recalculateInvoiceTotals($invoiceId);

            $this->pdo->commit();
            return $action;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function syncInvoiceLines(int $invoiceId, array $ads, array $pricingRule): void
    {
        $existingStmt = $this->pdo->prepare("
            SELECT id, ad_id
            FROM invoice_lines
            WHERE invoice_id = :invoice_id
        ");
        $existingStmt->execute([':invoice_id' => $invoiceId]);
        $existingByAdId = [];

        foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $existingByAdId[(int)$row['ad_id']] = (int)$row['id'];
        }

        $keptAdIds = [];
        $unitPrice = number_format((float)$pricingRule['price_per_ad'], 2, '.', '');
        $vatRate = number_format((float)$pricingRule['vat_rate'], 2, '.', '');
        $vatAmount = number_format(round(((float)$pricingRule['price_per_ad']) * ((float)$pricingRule['vat_rate']) / 100, 2), 2, '.', '');
        $grossAmount = number_format(round(((float)$pricingRule['price_per_ad']) + (float)$vatAmount, 2), 2, '.', '');

        foreach ($ads as $ad) {
            $adId = (int)$ad['ad_id'];
            $keptAdIds[] = $adId;
            $description = sprintf('Monthly advertising fee for ad "%s"', (string)$ad['title']);

            $params = [
                ':pricing_rule_id' => (int)$pricingRule['id'],
                ':ad_title' => (string)$ad['title'],
                ':description' => $description,
                ':line_period_start' => (string)$ad['line_period_start'],
                ':line_period_end' => (string)$ad['line_period_end'],
                ':unit_price' => $unitPrice,
                ':net_amount' => $unitPrice,
                ':vat_rate' => $vatRate,
                ':vat_amount' => $vatAmount,
                ':gross_amount' => $grossAmount,
            ];

            if (isset($existingByAdId[$adId])) {
                $stmt = $this->pdo->prepare("
                    UPDATE invoice_lines
                    SET pricing_rule_id = :pricing_rule_id,
                        ad_title = :ad_title,
                        description = :description,
                        line_period_start = :line_period_start,
                        line_period_end = :line_period_end,
                        quantity = 1.00,
                        unit_price = :unit_price,
                        net_amount = :net_amount,
                        vat_rate = :vat_rate,
                        vat_amount = :vat_amount,
                        gross_amount = :gross_amount,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $params[':id'] = $existingByAdId[$adId];
                $stmt->execute($params);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO invoice_lines (
                        invoice_id, ad_id, pricing_rule_id, ad_title, description,
                        line_period_start, line_period_end, quantity, unit_price,
                        net_amount, vat_rate, vat_amount, gross_amount
                    ) VALUES (
                        :invoice_id, :ad_id, :pricing_rule_id, :ad_title, :description,
                        :line_period_start, :line_period_end, 1.00, :unit_price,
                        :net_amount, :vat_rate, :vat_amount, :gross_amount
                    )
                ");
                $params[':invoice_id'] = $invoiceId;
                $params[':ad_id'] = $adId;
                $stmt->execute($params);
            }
        }

        if ($existingByAdId !== []) {
            $staleAdIds = array_diff(array_keys($existingByAdId), $keptAdIds);
            if ($staleAdIds !== []) {
                $placeholders = implode(',', array_fill(0, count($staleAdIds), '?'));
                $params = array_merge([$invoiceId], array_map('intval', $staleAdIds));
                $delete = $this->pdo->prepare("
                    DELETE FROM invoice_lines
                    WHERE invoice_id = ?
                      AND ad_id IN ($placeholders)
                ");
                $delete->execute($params);
            }
        }
    }

    private function recalculateInvoiceTotals(int $invoiceId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(net_amount), 0) AS subtotal_amount,
                COALESCE(SUM(vat_amount), 0) AS vat_amount,
                COALESCE(SUM(gross_amount), 0) AS total_amount
            FROM invoice_lines
            WHERE invoice_id = :invoice_id
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'subtotal_amount' => '0.00',
            'vat_amount' => '0.00',
            'total_amount' => '0.00',
        ];

        $update = $this->pdo->prepare("
            UPDATE invoices
            SET subtotal_amount = :subtotal_amount,
                vat_amount = :vat_amount,
                total_amount = :total_amount,
                updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute([
            ':subtotal_amount' => number_format((float)$totals['subtotal_amount'], 2, '.', ''),
            ':vat_amount' => number_format((float)$totals['vat_amount'], 2, '.', ''),
            ':total_amount' => number_format((float)$totals['total_amount'], 2, '.', ''),
            ':id' => $invoiceId,
        ]);
    }

    private function buildSupplierSnapshot(int $supplierId): array
    {
        $profile = $this->supplierService->getProfile($supplierId);
        if ($profile === null) {
            throw new UserFacingException('Supplier profile could not be loaded for invoice generation.');
        }

        return [
            'supplier_name_snapshot' => $this->encryptSnapshotValue((string)$profile['company_name'], self::SNAPSHOT_AAD['supplier_name_snapshot']),
            'supplier_short_name_snapshot' => $this->encryptSnapshotValue((string)$profile['short_name'], self::SNAPSHOT_AAD['supplier_short_name_snapshot']),
            'contact_person_snapshot' => $this->encryptSnapshotValue((string)$profile['contact_person'], self::SNAPSHOT_AAD['contact_person_snapshot']),
            'supplier_email_snapshot' => $this->encryptSnapshotValue((string)$profile['email'], self::SNAPSHOT_AAD['supplier_email_snapshot']),
            'supplier_vat_number_snapshot' => $this->encryptSnapshotValue((string)$profile['vat_number'], self::SNAPSHOT_AAD['supplier_vat_number_snapshot']),
            'homepage_snapshot' => $this->encryptSnapshotValue((string)$profile['homepage'], self::SNAPSHOT_AAD['homepage_snapshot']),
            'address_line_1_snapshot' => $this->encryptSnapshotValue((string)$profile['address_line_1'], self::SNAPSHOT_AAD['address_line_1_snapshot']),
            'address_line_2_snapshot' => $this->encryptSnapshotValue((string)$profile['address_line_2'], self::SNAPSHOT_AAD['address_line_2_snapshot']),
            'city_snapshot' => $this->encryptSnapshotValue((string)$profile['city'], self::SNAPSHOT_AAD['city_snapshot']),
            'region_snapshot' => $this->encryptSnapshotValue((string)$profile['region'], self::SNAPSHOT_AAD['region_snapshot']),
            'postal_code_snapshot' => $this->encryptSnapshotValue((string)$profile['postal_code'], self::SNAPSHOT_AAD['postal_code_snapshot']),
            'country_code_snapshot' => strtoupper((string)$profile['country_code']),
        ];
    }

    private function loadInvoiceBySupplierMonthForUpdate(int $supplierId, int $year, int $month): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM invoices
            WHERE supplier_id = :supplier_id
              AND billing_year = :billing_year
              AND billing_month = :billing_month
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            ':supplier_id' => $supplierId,
            ':billing_year' => $year,
            ':billing_month' => $month,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function reserveNextInvoiceSequence(int $year, int $month): int
    {
        $periodKey = sprintf('%04d%02d', $year, $month);
        $stmt = $this->pdo->prepare("
            INSERT INTO invoice_number_sequences (period_key, last_number)
            VALUES (:period_key, 0)
            ON DUPLICATE KEY UPDATE period_key = VALUES(period_key)
        ");
        $stmt->execute([':period_key' => $periodKey]);

        $select = $this->pdo->prepare("
            SELECT last_number
            FROM invoice_number_sequences
            WHERE period_key = :period_key
            LIMIT 1
            FOR UPDATE
        ");
        $select->execute([':period_key' => $periodKey]);
        $next = ((int)$select->fetchColumn()) + 1;

        $update = $this->pdo->prepare("
            UPDATE invoice_number_sequences
            SET last_number = :last_number,
                updated_at = NOW()
            WHERE period_key = :period_key
        ");
        $update->execute([
            ':last_number' => $next,
            ':period_key' => $periodKey,
        ]);

        return $next;
    }

    private function collectBillableAdsBySupplier(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                a.id,
                a.supplier_id,
                a.title,
                a.valid_from,
                a.valid_to,
                a.status,
                a.is_active,
                a.created_at
            FROM ads a
            WHERE (a.valid_from IS NULL OR a.valid_from <= :period_end)
              AND (a.valid_to IS NULL OR a.valid_to >= :period_start)
              AND (
                    a.status = 'APPROVED'
                    OR EXISTS (
                        SELECT 1
                        FROM ad_status_history h
                        WHERE h.ad_id = a.id
                          AND (h.new_status = 'APPROVED' OR h.old_status = 'APPROVED')
                    )
              )
            ORDER BY a.supplier_id ASC, a.id ASC
        ");
        $stmt->execute([
            ':period_start' => $periodStart->format('Y-m-d'),
            ':period_end' => $periodEnd->format('Y-m-d'),
        ]);
        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($ads === []) {
            return [];
        }

        $adIds = array_map(static fn(array $ad): int => (int)$ad['id'], $ads);
        $statusHistory = $this->loadStatusHistoryByAdIds($adIds);
        $activationHistory = $this->loadActivationHistoryByAdIds($adIds);
        $billingInterval = [
            'start' => $this->dateToTimestamp($periodStart->format('Y-m-d'), false),
            'end' => $this->dateToTimestamp($periodEnd->format('Y-m-d'), true),
        ];

        $result = [];

        foreach ($ads as $ad) {
            $adId = (int)$ad['id'];
            $approvedIntervals = $this->buildApprovedIntervals($ad, $statusHistory[$adId] ?? []);
            $activeIntervals = $this->buildActiveIntervals($ad, $activationHistory[$adId] ?? [], $approvedIntervals);
            $validityInterval = $this->buildValidityInterval($ad);
            $coverage = $this->findCoverageInterval($approvedIntervals, $activeIntervals, $validityInterval, $billingInterval);

            if ($coverage === null) {
                continue;
            }

            $result[(int)$ad['supplier_id']][] = [
                'ad_id' => $adId,
                'title' => (string)$ad['title'],
                'line_period_start' => date('Y-m-d', $coverage['start']),
                'line_period_end' => date('Y-m-d', $coverage['end']),
            ];
        }

        return $result;
    }

    private function loadStatusHistoryByAdIds(array $adIds): array
    {
        if ($adIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($adIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT id, ad_id, old_status, new_status, changed_at
            FROM ad_status_history
            WHERE ad_id IN ($placeholders)
            ORDER BY changed_at ASC, id ASC
        ");
        $stmt->execute(array_values($adIds));

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int)$row['ad_id']][] = $row;
        }

        return $map;
    }

    private function loadActivationHistoryByAdIds(array $adIds): array
    {
        if ($adIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($adIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT id, ad_id, old_is_active, new_is_active, changed_at
            FROM ad_activation_history
            WHERE ad_id IN ($placeholders)
            ORDER BY changed_at ASC, id ASC
        ");
        $stmt->execute(array_values($adIds));

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int)$row['ad_id']][] = $row;
        }

        return $map;
    }

    private function buildApprovedIntervals(array $ad, array $history): array
    {
        $intervals = [];
        $approvedStart = null;
        $lastApprovedAt = null;
        $fallbackStart = $this->dateTimeToTimestamp((string)$ad['created_at']);

        foreach ($history as $row) {
            $changedAt = $this->dateTimeToTimestamp((string)$row['changed_at']);
            if ((string)$row['new_status'] === 'APPROVED') {
                $approvedStart = $changedAt;
                $lastApprovedAt = $changedAt;
            }

            if ((string)($row['old_status'] ?? '') === 'APPROVED') {
                $intervals[] = [
                    'start' => $approvedStart ?? $lastApprovedAt ?? $fallbackStart,
                    'end' => $changedAt,
                ];
                $approvedStart = null;
            }
        }

        if (strtoupper((string)$ad['status']) === 'APPROVED') {
            $intervals[] = [
                'start' => $approvedStart ?? $lastApprovedAt ?? $fallbackStart,
                'end' => null,
            ];
        }

        return $intervals;
    }

    private function buildActiveIntervals(array $ad, array $history, array $approvedIntervals): array
    {
        $intervals = [];
        $activeStart = null;
        $lastActivationAt = null;

        foreach ($history as $row) {
            $changedAt = $this->dateTimeToTimestamp((string)$row['changed_at']);
            $newIsActive = (int)$row['new_is_active'];
            $oldIsActive = (int)$row['old_is_active'];

            if ($newIsActive === 1) {
                $activeStart = $changedAt;
                $lastActivationAt = $changedAt;
            }

            if ($oldIsActive === 1 && $newIsActive === 0) {
                $intervals[] = [
                    'start' => $activeStart ?? $lastActivationAt ?? $this->fallbackActiveStart($ad, $approvedIntervals),
                    'end' => $changedAt,
                ];
                $activeStart = null;
            }
        }

        if ((int)$ad['is_active'] === 1) {
            $intervals[] = [
                'start' => $activeStart ?? $lastActivationAt ?? $this->fallbackActiveStart($ad, $approvedIntervals),
                'end' => null,
            ];
        }

        return $intervals;
    }

    private function fallbackActiveStart(array $ad, array $approvedIntervals): int
    {
        $candidates = [$this->dateTimeToTimestamp((string)$ad['created_at'])];

        if (!empty($ad['valid_from'])) {
            $candidates[] = $this->dateToTimestamp((string)$ad['valid_from'], false);
        }

        if ($approvedIntervals !== []) {
            $candidates[] = (int)$approvedIntervals[count($approvedIntervals) - 1]['start'];
        }

        return max($candidates);
    }

    private function buildValidityInterval(array $ad): array
    {
        return [
            'start' => !empty($ad['valid_from']) ? $this->dateToTimestamp((string)$ad['valid_from'], false) : null,
            'end' => !empty($ad['valid_to']) ? $this->dateToTimestamp((string)$ad['valid_to'], true) : null,
        ];
    }

    private function findCoverageInterval(array $approvedIntervals, array $activeIntervals, array $validityInterval, array $billingInterval): ?array
    {
        $firstStart = null;
        $lastEnd = null;

        foreach ($approvedIntervals as $approved) {
            foreach ($activeIntervals as $active) {
                $intersection = $this->intersectIntervals($approved, $active);
                if ($intersection === null) {
                    continue;
                }

                $intersection = $this->intersectIntervals($intersection, $validityInterval);
                if ($intersection === null) {
                    continue;
                }

                $intersection = $this->intersectIntervals($intersection, $billingInterval);
                if ($intersection === null) {
                    continue;
                }

                if ($firstStart === null || $intersection['start'] < $firstStart) {
                    $firstStart = $intersection['start'];
                }

                if ($lastEnd === null || $intersection['end'] > $lastEnd) {
                    $lastEnd = $intersection['end'];
                }
            }
        }

        if ($firstStart === null || $lastEnd === null) {
            return null;
        }

        return ['start' => $firstStart, 'end' => $lastEnd];
    }

    private function intersectIntervals(array $left, array $right): ?array
    {
        $start = max($left['start'] ?? PHP_INT_MIN, $right['start'] ?? PHP_INT_MIN);
        $end = min($left['end'] ?? PHP_INT_MAX, $right['end'] ?? PHP_INT_MAX);

        if ($start > $end) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    private function findApplicablePricingRule(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM pricing_rules
            WHERE is_active = 1
              AND (effective_from IS NULL OR effective_from <= :period_end)
              AND (effective_to IS NULL OR effective_to >= :period_start)
            ORDER BY COALESCE(effective_from, '1000-01-01') DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':period_start' => $periodStart->format('Y-m-d'),
            ':period_end' => $periodEnd->format('Y-m-d'),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function validatePricingRuleInput(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $currencyCode = strtoupper(trim((string)($input['currency_code'] ?? self::DEFAULT_CURRENCY)));
        $effectiveFrom = $this->normalizeOptionalDate($input['effective_from'] ?? null);
        $effectiveTo = $this->normalizeOptionalDate($input['effective_to'] ?? null);
        $isActive = !empty($input['is_active']) ? 1 : 0;

        if ($name === '' || mb_strlen($name) > 100) {
            throw new UserFacingException('Pricing rule name is required and must be 100 characters or fewer.');
        }

        if ($description !== '' && mb_strlen($description) > 255) {
            throw new UserFacingException('Pricing rule description must be 255 characters or fewer.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new UserFacingException('Currency code must be a 3-letter ISO code such as SEK.');
        }

        $pricePerAd = $this->normalizeMoney($input['price_per_ad'] ?? null, 'Price per ad is required.');
        $vatRate = $this->normalizeMoney($input['vat_rate'] ?? null, 'VAT rate is required.');

        if ($pricePerAd <= 0) {
            throw new UserFacingException('Price per ad must be greater than zero.');
        }

        if ($vatRate < 0 || $vatRate > 100) {
            throw new UserFacingException('VAT rate must be between 0 and 100.');
        }

        if ($effectiveFrom !== null && $effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            throw new UserFacingException('Pricing rule end date must be on or after the start date.');
        }

        return [
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'price_per_ad' => number_format($pricePerAd, 2, '.', ''),
            'currency_code' => $currencyCode,
            'vat_rate' => number_format($vatRate, 2, '.', ''),
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'is_active' => $isActive,
        ];
    }

    private function pricingRuleExists(int $ruleId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM pricing_rules
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $ruleId]);

        return (bool)$stmt->fetchColumn();
    }

    private function removeStaleDraftInvoicesForMonth(int $year, int $month, array $eligibleSupplierIds): int
    {
        $params = [
            ':billing_year' => $year,
            ':billing_month' => $month,
            ':status' => self::STATUS_DRAFT,
        ];

        $sql = "
            DELETE FROM invoices
            WHERE billing_year = :billing_year
              AND billing_month = :billing_month
              AND status = :status
        ";

        if ($eligibleSupplierIds !== []) {
            $placeholders = [];
            foreach (array_values($eligibleSupplierIds) as $index => $supplierId) {
                $placeholder = ':supplier_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = (int)$supplierId;
            }

            $sql .= ' AND supplier_id NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function decryptInvoiceRow(array $row): array
    {
        foreach (self::SNAPSHOT_AAD as $column => $aad) {
            if (array_key_exists($column, $row)) {
                $row[$column] = $this->crypto->decryptNullable((string)$row[$column], $aad) ?? '';
            }
        }

        return $row;
    }

    private function encryptSnapshotValue(string $value, string $aad): string
    {
        return (string)$this->crypto->encryptNullable($value, $aad);
    }

    private function insertInvoiceStatusHistory(int $invoiceId, ?string $oldStatus, string $newStatus, int $actorUserId, ?string $note): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO invoice_status_history (
                invoice_id, old_status, new_status, note, changed_by_user_id
            ) VALUES (
                :invoice_id, :old_status, :new_status, :note, :changed_by_user_id
            )
        ");
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':old_status' => $oldStatus,
            ':new_status' => $newStatus,
            ':note' => $note,
            ':changed_by_user_id' => $actorUserId,
        ]);
    }

    private function hasPaymentRecord(int $invoiceId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM invoice_payments
            WHERE invoice_id = :invoice_id
            LIMIT 1
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);

        return (bool)$stmt->fetchColumn();
    }

    private function countInvoiceLines(int $invoiceId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM invoice_lines
            WHERE invoice_id = :invoice_id
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);

        return (int)$stmt->fetchColumn();
    }

    private function parseBillingMonth(string $billingMonth): array
    {
        if (!preg_match('/^(?<year>\d{4})-(?<month>0[1-9]|1[0-2])$/', trim($billingMonth), $matches)) {
            throw new UserFacingException('Billing month must be in YYYY-MM format.');
        }

        return [(int)$matches['year'], (int)$matches['month']];
    }

    private function billingPeriodBounds(int $year, int $month): array
    {
        $periodStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $periodEnd = $periodStart->modify('last day of this month');

        return [$periodStart, $periodEnd];
    }

    private function formatInvoiceNumber(int $year, int $month, int $sequenceNo): string
    {
        return sprintf('INV-%04d%02d-%04d', $year, $month, $sequenceNo);
    }

    private function normalizeMoney(mixed $value, string $requiredMessage): float
    {
        $value = trim((string)$value);
        if ($value === '') {
            throw new UserFacingException($requiredMessage);
        }

        $normalized = str_replace([' ', ','], ['', '.'], $value);
        if (!is_numeric($normalized)) {
            throw new UserFacingException('Please enter a valid numeric amount.');
        }

        return round((float)$normalized, 2);
    }

    private function normalizeDateInput(mixed $value, string $requiredMessage): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            throw new UserFacingException($requiredMessage);
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new UserFacingException('Please provide a valid date in YYYY-MM-DD format.');
        }

        return $value;
    }

    private function normalizeOptionalDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new UserFacingException('Dates must use the YYYY-MM-DD format.');
        }

        return $value;
    }

    private function dateToTimestamp(string $date, bool $endOfDay): int
    {
        $timestamp = strtotime($date . ($endOfDay ? ' 23:59:59' : ' 00:00:00'));
        if ($timestamp === false) {
            throw new RuntimeException('Unable to parse date: ' . $date);
        }

        return $timestamp;
    }

    private function dateTimeToTimestamp(string $value): int
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('Unable to parse datetime: ' . $value);
        }

        return $timestamp;
    }

    private function defaultDueDays(): int
    {
        $configured = (int)($this->config['invoicing']['default_due_days'] ?? self::DEFAULT_DUE_DAYS);

        return $configured > 0 ? $configured : self::DEFAULT_DUE_DAYS;
    }

    private function invoiceConfig(string $key, mixed $default = null): mixed
    {
        return $this->config['invoicing'][$key] ?? $default;
    }

    private function buildInvoicePdfHtml(array $invoice): string
    {
        $supplierBlock = implode("\n", array_filter([
            (string)$invoice['supplier_name_snapshot'],
            (string)$invoice['contact_person_snapshot'],
            trim((string)$invoice['address_line_1_snapshot'] . ' ' . (string)$invoice['address_line_2_snapshot']),
            trim((string)$invoice['postal_code_snapshot'] . ' ' . (string)$invoice['city_snapshot']),
            trim((string)$invoice['region_snapshot'] . ' ' . (string)$invoice['country_code_snapshot']),
            (string)$invoice['supplier_email_snapshot'],
            (string)$invoice['supplier_vat_number_snapshot'] !== '' ? 'VAT: ' . (string)$invoice['supplier_vat_number_snapshot'] : '',
        ], static fn(string $line): bool => trim($line) !== ''));

        $issuerBlock = implode("\n", array_filter([
            (string)$this->invoiceConfig('issuer_name', 'HEDVC AB'),
            (string)$this->invoiceConfig('issuer_address_line_1', 'Supplier Portal'),
            (string)$this->invoiceConfig('issuer_address_line_2', 'Sweden'),
            (string)$this->invoiceConfig('issuer_email', ''),
        ], static fn(string $line): bool => trim($line) !== ''));

        $linesHtml = '';
        foreach ((array)$invoice['lines'] as $line) {
            $linesHtml .= sprintf(
                '<tr><td style="border:1px solid #ccc;padding:6px;">%s</td><td style="border:1px solid #ccc;padding:6px;">%s</td><td style="border:1px solid #ccc;padding:6px;">%s</td><td style="border:1px solid #ccc;padding:6px;text-align:right;">%s %s</td><td style="border:1px solid #ccc;padding:6px;text-align:right;">%s %s</td><td style="border:1px solid #ccc;padding:6px;text-align:right;">%s %s</td></tr>',
                $this->escapeHtml((string)$line['ad_title']),
                $this->escapeHtml((string)$line['description']),
                $this->escapeHtml((string)$line['line_period_start'] . ' - ' . (string)$line['line_period_end']),
                $this->escapeHtml(number_format((float)$line['net_amount'], 2)),
                $this->escapeHtml((string)$invoice['currency_code']),
                $this->escapeHtml(number_format((float)$line['vat_amount'], 2)),
                $this->escapeHtml((string)$invoice['currency_code']),
                $this->escapeHtml(number_format((float)$line['gross_amount'], 2)),
                $this->escapeHtml((string)$invoice['currency_code'])
            );
        }

        if ($linesHtml === '') {
            $linesHtml = '<tr><td colspan="6" style="border:1px solid #ccc;padding:6px;">No billable lines.</td></tr>';
        }

        $paymentHtml = '';
        if (is_array($invoice['payment'] ?? null)) {
            $payment = $invoice['payment'];
            $paymentHtml = sprintf(
                '<h3 style="margin-top:18px;">Payment</h3><table cellpadding="0" cellspacing="0" style="width:100%%;"><tr><td style="width:28%%;font-weight:bold;">Payment date</td><td>%s</td></tr><tr><td style="font-weight:bold;">Method</td><td>%s</td></tr><tr><td style="font-weight:bold;">Amount</td><td>%s %s</td></tr></table>',
                $this->escapeHtml((string)$payment['payment_date']),
                $this->escapeHtml((string)$payment['payment_method']),
                $this->escapeHtml(number_format((float)$payment['amount'], 2)),
                $this->escapeHtml((string)$invoice['currency_code'])
            );
        }

        return sprintf(
            '<h1 style="font-size:22px;">Invoice %s</h1><table cellpadding="0" cellspacing="0" style="width:100%%;margin-bottom:16px;"><tr><td style="width:50%%;vertical-align:top;"><h3>Issuer</h3><div>%s</div></td><td style="width:50%%;vertical-align:top;"><h3>Supplier</h3><div>%s</div></td></tr></table><table cellpadding="0" cellspacing="0" style="width:100%%;margin-bottom:16px;"><tr><td style="width:25%%;font-weight:bold;">Status</td><td style="width:25%%;">%s</td><td style="width:25%%;font-weight:bold;">Billing month</td><td style="width:25%%;">%s-%02d</td></tr><tr><td style="font-weight:bold;">Issue date</td><td>%s</td><td style="font-weight:bold;">Due date</td><td>%s</td></tr></table><h3>Line items</h3><table cellpadding="0" cellspacing="0" style="width:100%%;border-collapse:collapse;"><thead><tr><th style="border:1px solid #ccc;padding:6px;background-color:#f5f5f5;">Ad</th><th style="border:1px solid #ccc;padding:6px;background-color:#f5f5f5;">Description</th><th style="border:1px solid #ccc;padding:6px;background-color:#f5f5f5;">Coverage</th><th style="border:1px solid #ccc;padding:6px;background-color:#f5f5f5;">Net</th><th style="border:1px solid #ccc;padding:6px;background-color:#f5f5f5;">VAT</th><th style="border:1px solid #ccc;padding:6px;background-color:#f5f5f5;">Gross</th></tr></thead><tbody>%s</tbody></table><h3 style="margin-top:18px;">Totals</h3><table cellpadding="0" cellspacing="0" style="width:45%%;margin-left:auto;"><tr><td style="font-weight:bold;">Subtotal</td><td style="text-align:right;">%s %s</td></tr><tr><td style="font-weight:bold;">VAT (%s%%)</td><td style="text-align:right;">%s %s</td></tr><tr><td style="font-weight:bold;">Total</td><td style="text-align:right;">%s %s</td></tr></table>%s',
            $this->escapeHtml((string)$invoice['invoice_number']),
            nl2br($this->escapeHtml($issuerBlock)),
            nl2br($this->escapeHtml($supplierBlock)),
            $this->escapeHtml((string)$invoice['status']),
            (int)$invoice['billing_year'],
            (int)$invoice['billing_month'],
            $this->escapeHtml((string)$invoice['issue_date']),
            $this->escapeHtml((string)$invoice['due_date']),
            $linesHtml,
            $this->escapeHtml(number_format((float)$invoice['subtotal_amount'], 2)),
            $this->escapeHtml((string)$invoice['currency_code']),
            $this->escapeHtml(number_format((float)$invoice['vat_rate'], 2)),
            $this->escapeHtml(number_format((float)$invoice['vat_amount'], 2)),
            $this->escapeHtml((string)$invoice['currency_code']),
            $this->escapeHtml(number_format((float)$invoice['total_amount'], 2)),
            $this->escapeHtml((string)$invoice['currency_code']),
            $paymentHtml
        );
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
