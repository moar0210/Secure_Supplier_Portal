<?php

declare(strict_types=1);

// Admin-only
$auth->requireRole('ADMIN');
?>

<h1>Admin dashboard (demo)</h1>
<p>If you can see this, RBAC works.</p>