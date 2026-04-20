<?php

declare(strict_types=1);

final class SupplierController extends BaseController
{
    public function index(): void
    {
        $this->auth->requireRole('ADMIN');

        $search = trim((string)($_GET['search'] ?? ''));
        $pageNo = max(1, (int)($_GET['page_no'] ?? 1));
        $perPage = 50;
        $total = $this->supplierService->countSuppliers($search);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($pageNo > $totalPages) {
            $pageNo = $totalPages;
        }

        $this->render('view_suppliers', [
            'rows' => $this->supplierService->listSuppliers($perPage, ($pageNo - 1) * $perPage, $search),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'page_no' => $pageNo,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ], 200, 'Suppliers');
    }

    public function create(): void
    {
        $this->auth->requireRole('ADMIN');

        $error = null;
        $createdId = null;
        $form = [
            'company_name' => '',
            'short_name' => '',
            'contact_person' => '',
            'email' => '',
            'homepage' => '',
            'vat_number' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'region' => '',
            'postal_code' => '',
            'country_code' => 'SE',
            'phone_country_prefix' => '',
            'phone_area_code' => '',
            'phone_number' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $form = array_merge($form, [
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
                'country_code' => (string)($_POST['country_code'] ?? 'SE'),
                'phone_country_prefix' => (string)($_POST['phone_country_prefix'] ?? ''),
                'phone_area_code' => (string)($_POST['phone_area_code'] ?? ''),
                'phone_number' => (string)($_POST['phone_number'] ?? ''),
            ]);

            try {
                $logoUpload = is_array($_FILES['logo'] ?? null) ? $_FILES['logo'] : null;
                $createdId = $this->supplierService->createSupplier($_POST, $logoUpload, $this->auth->userId());
                $this->logActivity('Supplier created', [
                    'supplier_id' => $createdId,
                    'actor_user_id' => $this->auth->userId(),
                ]);
                $this->redirect('?page=supplier&id=' . $createdId . '&created=1');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to create the supplier right now.');
            }
        }

        $this->render('view_supplier_create', [
            'form' => $form,
            'error' => $error,
            'createdId' => $createdId,
        ], 200, 'Create Supplier');
    }

    public function show(): void
    {
        $this->auth->requireLogin();

        $isAdmin = $this->auth->hasRole('ADMIN');
        $isSupplier = $this->auth->hasRole('SUPPLIER');
        if (!$isAdmin && !$isSupplier) {
            $this->redirect('?page=403');
        }

        $id = $_GET['id'] ?? null;
        if (!$id || !ctype_digit((string)$id)) {
            $this->redirect('?page=404');
        }

        $supplierIdRequested = (int)$id;

        if ($isSupplier && !$isAdmin) {
            $ownSupplierId = $this->auth->supplierId();
            if ($ownSupplierId === null || $ownSupplierId !== $supplierIdRequested) {
                $this->redirect('?page=403');
            }
        }

        $error = trim((string)($_GET['error'] ?? ''));
        if ($error === '') {
            $error = null;
        }
        $updated = isset($_GET['updated']) && $_GET['updated'] === '1';
        $created = isset($_GET['created']) && $_GET['created'] === '1';
        $notice = trim((string)($_GET['notice'] ?? ''));
        if ($notice === '') {
            $notice = null;
        }

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
                $logoUpload = is_array($_FILES['logo'] ?? null) ? $_FILES['logo'] : null;
                $removeLogo = ((string)($_POST['remove_logo'] ?? '0') === '1');

                $this->supplierService->updateProfile(
                    $supplierIdRequested,
                    $_POST,
                    $logoUpload,
                    $removeLogo,
                    $this->auth->userId()
                );
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
        $logo = $this->supplierService->getLogoMeta($supplierIdRequested);

        if (!$supplier) {
            $this->redirect('?page=404');
        }

        if ($postedValues !== null && $error !== null) {
            $supplier = array_merge($supplier, $postedValues);
        }

        $this->render('view_supplier', [
            'supplier' => $supplier,
            'logo' => $logo,
            'error' => $error,
            'updated' => $updated,
            'created' => $created,
            'isAdmin' => $isAdmin,
            'notice' => $notice,
        ], 200, 'Supplier Profile');
    }

    public function status(): void
    {
        $this->auth->requireRole('ADMIN');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('?page=404');
        }

        Csrf::verifyOrFail();

        $idRaw = trim((string)($_POST['id'] ?? ''));
        if (!ctype_digit($idRaw)) {
            $this->redirect('?page=404');
        }

        $supplierId = (int)$idRaw;
        $active = ((string)($_POST['active'] ?? '0') === '1');

        try {
            $this->supplierService->setSupplierActiveState($supplierId, $active);
            $this->logActivity('Supplier approval state changed', [
                'supplier_id' => $supplierId,
                'active' => $active,
                'actor_user_id' => $this->auth->userId(),
            ]);
            $notice = $active ? 'Supplier approved and activated.' : 'Supplier deactivated.';
            $this->redirect('?page=supplier&id=' . $supplierId . '&notice=' . rawurlencode($notice));
        } catch (Throwable $e) {
            $error = $this->presentError($e, 'Unable to update the supplier status right now.');
            $this->redirect('?page=supplier&id=' . $supplierId . '&error=' . rawurlencode($error));
        }
    }

    public function logo(): void
    {
        $this->auth->requireLogin();

        $isAdmin = $this->auth->hasRole('ADMIN');
        $isSupplier = $this->auth->hasRole('SUPPLIER');
        if (!$isAdmin && !$isSupplier) {
            $this->redirect('?page=403');
        }

        $id = $_GET['id'] ?? null;
        if (!$id || !ctype_digit((string)$id)) {
            $this->redirect('?page=404');
        }

        $supplierIdRequested = (int)$id;

        if ($isSupplier && !$isAdmin) {
            $ownSupplierId = $this->auth->supplierId();
            if ($ownSupplierId === null || $ownSupplierId !== $supplierIdRequested) {
                $this->redirect('?page=403');
            }
        }

        $logo = $this->supplierService->getLogoAsset($supplierIdRequested);
        if ($logo === null) {
            $this->redirect('?page=404');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . (string)$logo['mime_type']);
        header('Content-Length: ' . (string)(filesize((string)$logo['path']) ?: 0));
        header('Content-Disposition: inline; filename="' . (string)$logo['download_name'] . '"');
        readfile((string)$logo['path']);
        exit;
    }
}
