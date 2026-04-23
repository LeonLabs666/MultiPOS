<?php
declare(strict_types=1);

require __DIR__ . '/config/auth.php';

$u = auth_user();
if ($u) {
  redirect_by_role((string)$u['role']);
}
header('Location: home.php');
exit;
