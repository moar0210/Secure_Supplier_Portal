<?php

declare(strict_types=1);

final class AdminController extends BaseController
{
    public function dashboard(): void
    {
        $this->auth->requireRole('ADMIN');
        $this->render('view_admin', [], 200, 'Admin');
    }

    public function adsQueue(): void
    {
        $this->auth->requireRole('ADMIN');

        $status = strtoupper(trim((string)($_GET['status'] ?? 'PENDING')));
        $allowed = ['PENDING', 'APPROVED', 'REJECTED', 'DRAFT', 'ALL'];
        if (!in_array($status, $allowed, true)) {
            $status = 'PENDING';
        }

        $rows = [];
        $error = null;

        try {
            $rows = $status === 'ALL'
                ? $this->adsService->adminQueue(null)
                : $this->adsService->adminQueue($status);
        } catch (Throwable $e) {
            $error = $this->presentError($e, 'Unable to load the ads queue right now.');
        }

        $this->render('view_admin_ads_queue', [
            'status' => $status,
            'allowed' => $allowed,
            'rows' => $rows,
            'error' => $error,
        ], 200, 'Ads Queue');
    }

    public function adReview(): void
    {
        $this->auth->requireRole('ADMIN');

        $idRaw = $_GET['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->redirect('?page=404');
        }
        $adId = (int)$idRaw;

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $decision = (string)($_POST['decision'] ?? '');
            $approve = $decision === 'approve';
            $reject = $decision === 'reject';

            try {
                if (!$approve && !$reject) {
                    throw new RuntimeException('Invalid decision.');
                }

                $this->adsService->adminDecision(
                    $adId,
                    $approve,
                    $approve ? null : (string)($_POST['reason'] ?? ''),
                    (int)$this->auth->userId()
                );
                $this->logActivity('Ad review completed', [
                    'ad_id' => $adId,
                    'actor_user_id' => $this->auth->userId(),
                    'decision' => $approve ? 'approve' : 'reject',
                ]);

                $this->redirect('?page=admin_ads_queue&status=PENDING');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to review this advertisement right now.');
            }
        }

        $ad = $this->adsService->adminGet($adId);
        if (!$ad) {
            $this->redirect('?page=404');
        }

        $history = [];
        try {
            $history = $this->adsService->adminHistory($adId);
        } catch (Throwable $e) {
            $this->logUnexpected($e, 'Failed to load advertisement history');
            $history = [];
        }

        $this->render('view_admin_ad_review', [
            'ad' => $ad,
            'history' => $history,
            'error' => $error,
        ], 200, 'Review Ad');
    }

    public function categories(): void
    {
        $this->auth->requireRole('ADMIN');

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $action = (string)($_POST['action'] ?? '');

            try {
                if ($action === 'create') {
                    $this->adsService->createCategory((string)($_POST['name'] ?? ''));
                    $this->logActivity('Category created', [
                        'actor_user_id' => $this->auth->userId(),
                    ]);
                    $this->redirect('?page=admin_categories');
                }

                if ($action === 'delete') {
                    $id = (string)($_POST['id'] ?? '');
                    if (!ctype_digit($id)) {
                        throw new RuntimeException('Invalid category id.');
                    }

                    $this->adsService->deleteCategory((int)$id);
                    $this->logActivity('Category deleted', [
                        'category_id' => (int)$id,
                        'actor_user_id' => $this->auth->userId(),
                    ]);
                    $this->redirect('?page=admin_categories');
                }

                throw new RuntimeException('Unknown action.');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to update categories right now.');
            }
        }

        $this->render('view_admin_categories', [
            'error' => $error,
            'categories' => $this->adsService->listCategories(),
        ], 200, 'Categories');
    }

    public function categoryEdit(): void
    {
        $this->auth->requireRole('ADMIN');

        $idRaw = $_GET['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->redirect('?page=404');
        }

        $categoryId = (int)$idRaw;
        $categories = $this->adsService->listCategories();

        $current = null;
        foreach ($categories as $category) {
            if ((int)$category['id'] === $categoryId) {
                $current = $category;
                break;
            }
        }

        if ($current === null) {
            $this->redirect('?page=404');
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            try {
                $this->adsService->renameCategory($categoryId, (string)($_POST['name'] ?? ''));
                $this->logActivity('Category renamed', [
                    'category_id' => $categoryId,
                    'actor_user_id' => $this->auth->userId(),
                ]);
                $this->redirect('?page=admin_categories');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to rename the category right now.');
                $current['name'] = (string)($_POST['name'] ?? $current['name']);
            }
        }

        $this->render('view_admin_category_edit', [
            'categoryId' => $categoryId,
            'current' => $current,
            'error' => $error,
        ], 200, 'Rename Category');
    }

    public function securityCheck(): void
    {
        $this->auth->requireRole('ADMIN');

        $cookieParams = session_get_cookie_params();
        $sessName = session_name();
        $sessId = session_id();
        $sessIdMasked = $sessId === ''
            ? 'n/a'
            : substr($sessId, 0, 4) . '...' . substr($sessId, -4);

        $last = $_SESSION['security']['last_activity'] ?? null;
        $lastStr = is_int($last) ? date('Y-m-d H:i:s', $last) : 'n/a';
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        $this->render('view_security_check', [
            'cookieParams' => $cookieParams,
            'sessName' => $sessName,
            'sessIdMasked' => $sessIdMasked,
            'lastStr' => $lastStr,
            'isHttps' => $isHttps,
            'cryptoEnabled' => $this->crypto->isEnabled(),
            'cryptoDriver' => $this->crypto->driverName(),
            'cryptoActiveKeyId' => $this->crypto->activeKeyId() === '' ? 'n/a' : $this->crypto->activeKeyId(),
            'cryptoKeyCount' => $this->crypto->configuredKeyCount(),
        ], 200, 'Security Check');
    }
}
