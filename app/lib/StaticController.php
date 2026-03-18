<?php

declare(strict_types=1);

final class StaticController extends BaseController
{
    public function home(): void
    {
        $this->render('view_home');
    }

    public function dbtest(): void
    {
        $this->auth->requireRole('ADMIN');

        $stmt = $this->pdo()->query('SELECT NOW() AS now_time');
        $row = $stmt->fetch();

        $this->render('view_dbtest', [
            'serverTime' => (string)($row['now_time'] ?? ''),
        ]);
    }

    public function forbidden(): void
    {
        $this->render('view_403', [], 403, '403 Forbidden');
    }

    public function notFound(): void
    {
        $this->render('view_404', [], 404, '404 Not Found');
    }
}
