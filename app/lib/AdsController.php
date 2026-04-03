<?php

declare(strict_types=1);

final class AdsController extends BaseController
{
    public function index(): void
    {
        $this->auth->requireRole('SUPPLIER');

        $supplierId = $this->auth->supplierId();
        if ($supplierId === null) {
            $this->redirect('?page=403');
        }

        $error = trim((string)($_GET['error'] ?? ''));
        if ($error === '') {
            $error = null;
        }

        $this->render('view_ads_list', [
            'rows' => $this->adsService->listForSupplier($supplierId),
            'error' => $error,
            'deleted' => isset($_GET['deleted']) && $_GET['deleted'] === '1',
        ], 200, 'My Ads');
    }

    public function create(): void
    {
        $this->auth->requireRole('SUPPLIER');

        $supplierId = $this->auth->supplierId();
        if ($supplierId === null) {
            $this->redirect('?page=403');
        }

        $error = null;
        $form = [
            'title' => '',
            'description' => '',
            'price_text' => '',
            'category_id' => '',
            'valid_from' => '',
            'valid_to' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $action = (string)($_POST['action'] ?? '');
            if ($action === 'save_draft') {
                $_POST['save_as_draft'] = 1;
                $_POST['submit_for_approval'] = 0;
            } elseif ($action === 'submit') {
                $_POST['save_as_draft'] = 0;
                $_POST['submit_for_approval'] = 1;
            }

            $form = [
                'title' => (string)($_POST['title'] ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
                'price_text' => (string)($_POST['price_text'] ?? ''),
                'category_id' => (string)($_POST['category_id'] ?? ''),
                'valid_from' => (string)($_POST['valid_from'] ?? ''),
                'valid_to' => (string)($_POST['valid_to'] ?? ''),
            ];

            try {
                $this->adsService->createForSupplier($supplierId, $_POST, (int)$this->auth->userId());
                $this->logActivity('Ad created', [
                    'supplier_id' => $supplierId,
                    'actor_user_id' => $this->auth->userId(),
                ]);
                $this->redirect('?page=ads_list');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to save the advertisement right now.');
            }
        }

        $this->render('view_ad_create', [
            'error' => $error,
            'form' => $form,
            'categories' => $this->adsService->listCategories(),
        ], 200, 'Create Ad');
    }

    public function edit(): void
    {
        $this->auth->requireRole('SUPPLIER');

        $supplierId = $this->auth->supplierId();
        if ($supplierId === null) {
            $this->redirect('?page=403');
        }

        $idRaw = $_GET['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->redirect('?page=404');
        }
        $adId = (int)$idRaw;

        $error = null;
        $ad = $this->adsService->getForSupplier($adId, $supplierId);
        if (!$ad) {
            $this->redirect('?page=404');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $action = (string)($_POST['action'] ?? '');

            try {
                if ($action === 'submit') {
                    $this->adsService->submitForSupplier($adId, $supplierId, (int)$this->auth->userId());
                    $this->logActivity('Ad submitted for approval', [
                        'ad_id' => $adId,
                        'supplier_id' => $supplierId,
                        'actor_user_id' => $this->auth->userId(),
                    ]);
                } else {
                    if ($action === 'save_draft') {
                        $_POST['save_as_draft'] = 1;
                        $_POST['submit_for_approval'] = 0;
                    } elseif ($action === 'save_submit') {
                        $_POST['save_as_draft'] = 0;
                        $_POST['submit_for_approval'] = 1;
                    }

                    $this->adsService->updateForSupplier($adId, $supplierId, $_POST, (int)$this->auth->userId());
                    $this->logActivity('Ad updated', [
                        'ad_id' => $adId,
                        'supplier_id' => $supplierId,
                        'actor_user_id' => $this->auth->userId(),
                    ]);
                }

                $this->redirect('?page=ads_list');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to update the advertisement right now.');
            }

            $ad = $this->adsService->getForSupplier($adId, $supplierId) ?? $ad;
        }

        $status = strtoupper((string)$ad['status']);

        $this->render('view_ad_edit', [
            'ad' => $ad,
            'adId' => $adId,
            'error' => $error,
            'status' => $status,
            'isLocked' => $status === 'PENDING',
            'categories' => $this->adsService->listCategories(),
        ], 200, 'Edit Ad');
    }

    public function toggle(): void
    {
        $this->auth->requireRole('SUPPLIER');

        $supplierId = $this->auth->supplierId();
        if ($supplierId === null) {
            $this->redirect('?page=403');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('?page=404');
        }

        Csrf::verifyOrFail();

        $idRaw = $_POST['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->redirect('?page=404');
        }

        $adId = (int)$idRaw;
        $active = ((string)($_POST['active'] ?? '0') === '1');

        try {
            $this->adsService->toggleActiveForSupplier($adId, $supplierId, $active, (int)$this->auth->userId());
            $this->logActivity('Ad activation changed', [
                'ad_id' => $adId,
                'supplier_id' => $supplierId,
                'actor_user_id' => $this->auth->userId(),
                'active' => $active,
            ]);
        } catch (Throwable $e) {
            $error = $this->presentError($e, 'Unable to change advertisement activation right now.');
            $this->redirect('?page=ads_list&error=' . rawurlencode($error));
        }

        $this->redirect('?page=ads_list');
    }

    public function delete(): void
    {
        $this->auth->requireRole('SUPPLIER');

        $supplierId = $this->auth->supplierId();
        if ($supplierId === null) {
            $this->redirect('?page=403');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('?page=404');
        }

        Csrf::verifyOrFail();

        $idRaw = $_POST['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->redirect('?page=404');
        }

        $adId = (int)$idRaw;

        try {
            $this->adsService->deleteForSupplier($adId, $supplierId);
            $this->logActivity('Ad deleted', [
                'ad_id' => $adId,
                'supplier_id' => $supplierId,
                'actor_user_id' => $this->auth->userId(),
            ]);
            $this->redirect('?page=ads_list&deleted=1');
        } catch (Throwable $e) {
            $error = $this->presentError($e, 'Unable to delete the advertisement right now.');
            $this->redirect('?page=ads_list&error=' . rawurlencode($error));
        }
    }
}
