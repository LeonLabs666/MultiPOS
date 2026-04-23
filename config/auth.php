<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_verify(): void {
  $t = $_POST['csrf'] ?? '';
  if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
    http_response_code(403); exit('CSRF invalid.');
  }
}
function auth_user(): ?array { return $_SESSION['user'] ?? null; }

function login_user(array $u): void {
  $_SESSION['user'] = [
    'id'   => (int)$u['id'],
    'name' => $u['name'],
    'email'=> $u['email'],
    'role' => $u['role']
  ];
  session_regenerate_id(true);
}
function logout_user(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
  }
  session_destroy();
}
function require_login(): void {
  if (!auth_user()) { header('Location: login.php'); exit; }
}
function require_role(array $roles): void {
  require_login();
  $r = auth_user()['role'] ?? '';
  if (!in_array($r, $roles, true)) { http_response_code(403); exit('Forbidden'); }
}

/**
 * Ambil store_type untuk admin (1 admin = 1 toko).
 * Default: 'bom'
 *
 * Catatan: file yang memanggil redirect_by_role() biasanya sudah require db.php,
 * jadi $pdo sudah tersedia secara global.
 */
function admin_store_type(int $adminId): string {
  try {
    /** @var PDO $pdo */
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) return 'bom';

    $st = $pdo->prepare("
      SELECT store_type
      FROM stores
      WHERE owner_admin_id=?
      ORDER BY id DESC
      LIMIT 1
    ");
    $st->execute([$adminId]);
    $t = $st->fetchColumn();

    return $t ? (string)$t : 'bom';
  } catch (Throwable $e) {
    return 'bom';
  }
}

/**
 * Ambil store_type untuk kasir (kasir terhubung ke admin via users.created_by).
 * Default: 'bom'
 */
function kasir_store_type(int $kasirId): string {
  try {
    /** @var PDO $pdo */
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) return 'bom';

    // created_by = admin_id
    $q = $pdo->prepare("SELECT created_by FROM users WHERE id=? LIMIT 1");
    $q->execute([$kasirId]);
    $adminId = (int)$q->fetchColumn();
    if ($adminId <= 0) return 'bom';

    return admin_store_type($adminId);
  } catch (Throwable $e) {
    return 'bom';
  }
}

function redirect_by_role(string $role): void {
  if ($role === 'developer') { header('Location: advance/dev_dashboard.php'); exit; }

  if ($role === 'admin') {
    $u = auth_user();
    $adminId = (int)($u['id'] ?? 0);

    if ($adminId > 0) {
      $type = admin_store_type($adminId);

      // basic dashboard file yang benar adalah admin_basic_dashboard.php
      if ($type === 'basic') {
        $basicDash = __DIR__ . '/../basic/admin_basic_dashboard.php';
        if (is_file($basicDash)) {
          header('Location: basic/admin_basic_dashboard.php'); exit;
        }
        header('Location: advance/admin_dashboard.php'); exit;
      }
    }

    header('Location: advance/admin_dashboard.php'); exit;
  }

  if ($role === 'kasir') {
    $u = auth_user();
    $kasirId = (int)($u['id'] ?? 0);
    if ($kasirId > 0) {
      $type = kasir_store_type($kasirId);
      if ($type === 'basic') {
        $basicKasirDash = __DIR__ . '/../basic/kasir_dashboard.php';
        if (is_file($basicKasirDash)) {
          header('Location: basic/kasir_dashboard.php'); exit;
        }
      }
    }
    header('Location: advance/kasir_dashboard.php'); exit;
  }

  if ($role === 'dapur') { header('Location: advance/dapur_dashboard.php'); exit; }
  header('Location: login.php'); exit;
}
