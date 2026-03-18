<?php

declare(strict_types=1);

final class SupplierController extends BaseController
{
    public function index(): void
    {
        $this->auth->requireRole('ADMIN');

        $this->render('view_suppliers', [
            'rows' => $this->supplierService->listSuppliers(),
        ], 200, 'Suppliers');
    }

    public function show(): void
    {
        $this->auth->requireLogin();

        $id = $_GET['id'] ?? null;
        if (!$id || !ctype_digit((string)$id)) {
            $this->redirect('?page=404');
        }

        $supplierIdRequested = (int)$id;

        if ($this->auth->hasRole('SUPPLIER') && !$this->auth->hasRole('ADMIN')) {
            $ownSupplierId = $this->auth->supplierId();
            if ($ownSupplierId === null || $ownSupplierId !== $supplierIdRequested) {
                $this->redirect('?page=403');
            }
        }

        $error = null;
        $updated = isset($_GET['updated']) && $_GET['updated'] === '1';

        $postedValues = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();
            $postedValues = [
                'company_name' => (string)($_POST['company_name'] ?? ''),
                'short_name' => (string)($_POST['short_name'] ?? ''),
                'contact_person' => (string)($_POST['contact_person'] ?? ''),
                'email' => (string)($_POST['email'] ?? ''),
                'homepage' => (string)($_POST['homepage'] ?? ''),
                'vat_number' => (string)($_POST['vat_number'] ?? ''),
                'address_line_1' => (string)($_POST['address_line_1'] ?? ''),
                'address_line_2' => (string)($_POST['address_line_2'] ?? ''),
                'city' => (string)($_POST['city'] ?? ''),
                'region' => (string)($_POST['region'] ?? ''),
                'postal_code' => (string)($_POST['postal_code'] ?? ''),
                'country_code' => (string)($_POST['country_code'] ?? ''),
                'phone_country_prefix' => (string)($_POST['phone_country_prefix'] ?? ''),
                'phone_area_code' => (string)($_POST['phone_area_code'] ?? ''),
                'phone_number' => (string)($_POST['phone_number'] ?? ''),
                'phone_display' => '',
            ];

            try {
                $this->supplierService->updateProfile($supplierIdRequested, $_POST);
                $this->logActivity('Supplier profile updated', [
                    'supplier_id' => $supplierIdRequested,
                    'actor_user_id' => $this->auth->userId(),
                ]);
                $this->redirect('?page=supplier&id=' . $supplierIdRequested . '&updated=1');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to save the supplier profile right now.');
            }
        }

        $supplier = $this->supplierService->getProfile($supplierIdRequested);

        if (!$supplier) {
            $this->redirect('?page=404');
        }

        if ($postedValues !== null && $error !== null) {
            $supplier = array_merge($supplier, $postedValues);
        }

        $this->render('view_supplier', [
            'supplier' => $supplier,
            'error' => $error,
            'updated' => $updated,
        ], 200, 'Supplier Profile');
    }
}
