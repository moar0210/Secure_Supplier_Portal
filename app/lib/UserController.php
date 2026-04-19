<?php

declare(strict_types=1);

final class UserController extends BaseController
{
    private PortalUserService $portalUserService;

    public function __construct(
        View $view,
        Auth $auth,
        Database $db,
        Crypto $crypto,
        AdsService $adsService,
        SupplierService $supplierService,
        PortalUserService $portalUserService,
        array $config
    ) {
        parent::__construct($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
        $this->portalUserService = $portalUserService;
    }

    public function adminUsers(): void
    {
        $this->auth->requireRole('ADMIN');

        $filters = [
            'search' => (string)($_GET['search'] ?? ''),
            'role' => (string)($_GET['role'] ?? 'ALL'),
            'status' => (string)($_GET['status'] ?? 'ALL'),
            'supplier_id' => (string)($_GET['supplier_id'] ?? ''),
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
                if ($action === 'create') {
                    $userId = $this->portalUserService->createUserAsAdmin($_POST);
                    $this->logActivity('Portal user created', [
                        'actor_user_id' => $this->auth->userId(),
                        'target_user_id' => $userId,
                        'supplier_id' => trim((string)($_POST['supplier_id'] ?? '')),
                    ]);
                    $this->redirect('?page=admin_users&notice=' . rawurlencode('User created.'));
                }

                if ($action === 'update') {
                    $userIdRaw = trim((string)($_POST['id'] ?? ''));
                    if (!ctype_digit($userIdRaw)) {
                        throw new RuntimeException('Invalid user id.');
                    }

                    $userId = (int)$userIdRaw;
                    $this->portalUserService->updateUserAsAdmin($userId, $_POST, $this->auth->userId());
                    $this->logActivity('Portal user updated', [
                        'actor_user_id' => $this->auth->userId(),
                        'target_user_id' => $userId,
                        'supplier_id' => trim((string)($_POST['supplier_id'] ?? '')),
                    ]);
                    $this->redirect('?page=admin_users&notice=' . rawurlencode('User updated.'));
                }

                throw new RuntimeException('Unknown action.');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to update portal users right now.');
            }
        }

        $this->render('view_admin_users', [
            'filters' => $filters,
            'rows' => $this->portalUserService->listUsers($filters),
            'supplierOptions' => $this->supplierService->listSupplierOptions(true),
            'error' => $error,
            'notice' => $notice,
            'currentUserId' => $this->auth->userId(),
        ], 200, 'Portal Users');
    }

    public function supplierUsers(): void
    {
        $this->auth->requireRole('SUPPLIER');

        $supplierId = $this->auth->supplierId();
        if ($supplierId === null) {
            $this->redirect('?page=403');
        }

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
                    $userId = $this->portalUserService->createUserForSupplier($supplierId, $_POST);
                    $this->logActivity('Supplier company user created', [
                        'supplier_id' => $supplierId,
                        'actor_user_id' => $this->auth->userId(),
                        'target_user_id' => $userId,
                    ]);
                    $this->redirect('?page=supplier_users&notice=' . rawurlencode('Company user created.'));
                }

                if ($action === 'update') {
                    $userIdRaw = trim((string)($_POST['id'] ?? ''));
                    if (!ctype_digit($userIdRaw)) {
                        throw new RuntimeException('Invalid user id.');
                    }

                    $userId = (int)$userIdRaw;
                    $this->portalUserService->updateUserForSupplier($userId, $supplierId, $_POST, $this->auth->userId());
                    $this->logActivity('Supplier company user updated', [
                        'supplier_id' => $supplierId,
                        'actor_user_id' => $this->auth->userId(),
                        'target_user_id' => $userId,
                    ]);
                    $this->redirect('?page=supplier_users&notice=' . rawurlencode('Company user updated.'));
                }

                throw new RuntimeException('Unknown action.');
            } catch (Throwable $e) {
                $error = $this->presentError($e, 'Unable to update company users right now.');
            }
        }

        $this->render('view_supplier_users', [
            'rows' => $this->portalUserService->listUsersForSupplier($supplierId),
            'error' => $error,
            'notice' => $notice,
            'currentUserId' => $this->auth->userId(),
        ], 200, 'Company Users');
    }
}
