<?php

declare(strict_types=1);

abstract class BaseController
{
    protected View $view;
    protected Auth $auth;
    protected Database $db;
    protected Crypto $crypto;
    protected AdsService $adsService;
    protected SupplierService $supplierService;
    protected array $config;

    public function __construct(View $view, Auth $auth, Database $db, Crypto $crypto, AdsService $adsService, SupplierService $supplierService, array $config)
    {
        $this->view = $view;
        $this->auth = $auth;
        $this->db = $db;
        $this->crypto = $crypto;
        $this->adsService = $adsService;
        $this->supplierService = $supplierService;
        $this->config = $config;
    }

    protected function render(string $template, array $data = [], int $status = 200, string $title = 'Supplier Portal'): void
    {
        http_response_code($status);

        $this->view->render($template, $data, [
            'auth' => $this->auth,
            'title' => $title,
            'currentPage' => (string)($_GET['page'] ?? 'home'),
        ]);
    }

    protected function redirect(string $location): void
    {
        header("Location: {$location}");
        exit;
    }

    protected function pdo(): PDO
    {
        return $this->db->pdo();
    }

    protected function presentError(Throwable $e, string $fallback = 'Something went wrong. Please try again later.'): string
    {
        if ($e instanceof UserFacingException) {
            return $e->getMessage();
        }

        $this->logUnexpected($e);

        return $fallback;
    }

    protected function logActivity(string $event, array $context = []): void
    {
        $this->writeLog('activity', $event, $context);
    }

    protected function logUnexpected(Throwable $e, string $event = 'Unhandled exception'): void
    {
        $this->writeLog('error', $event, [
            'page' => (string)($_GET['page'] ?? 'home'),
            'user_id' => $this->auth->userId(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    private function writeLog(string $level, string $message, array $context = []): void
    {
        $payload = '';
        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $payload = ' ' . $encoded;
            }
        }

        error_log('[Supplier Portal][' . strtoupper($level) . '] ' . $message . $payload);
    }
}
