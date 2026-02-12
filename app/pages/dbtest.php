<?php
$stmt = $db->pdo()->query("SELECT NOW() AS now_time");
$row = $stmt->fetch();
?>
<h1>DB Test</h1>
<p>DB connection OK. Server time: <?= htmlspecialchars($row["now_time"]) ?></p>