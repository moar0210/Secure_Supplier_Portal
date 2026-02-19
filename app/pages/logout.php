<?php

declare(strict_types=1);

$auth->logout();
header("Location: ?page=login");
exit;
