<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/audit.php';

require_role(['developer']);

$error = '';
$success = '';
$tempPassword = '';

/**
 * Generate password sementara yang cukup aman dan mudah dibaca.
 */
function generateTemporaryPassword(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
    $max = strlen($chars) - 1;
    $result = '';

    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[random_int(0, $max)];
    }

    return $result;
}

/**
 * Ambil semua admin untuk dropdown.
 */
$stmtAdmins = $pdo->query("
    SELECT id, name, email, is_active
    FROM users
    WHERE role = 'admin'
    ORDER BY id DESC
");
$admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $adminId = (int)($_POST['admin_id'] ?? 0);

    if ($adminId <= 0) {
        $error = 'Pilih admin yang akan di-reset.';
    } else {
        try {
            /**
             * Ambil data admin target.
             */
            $stmtTarget = $pdo->prepare("
                SELECT id, name, email, role, is_active
                FROM users
                WHERE id = ?
                  AND role = 'admin'
                LIMIT 1
            ");
            $stmtTarget->execute([$adminId]);
            $targetAdmin = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            if (!$targetAdmin) {
                $error = 'Admin tidak ditemukan atau bukan role admin.';
            } else {
                /**
                 * Cari store yang dimiliki admin target.
                 */
                $stmtStore = $pdo->prepare("
                    SELECT id, name
                    FROM stores
                    WHERE owner_admin_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmtStore->execute([$adminId]);
                $store = $stmtStore->fetch(PDO::FETCH_ASSOC);

                $storeId = $store ? (int)$store['id'] : null;
                $storeName = $store ? (string)$store['name'] : '-';

                /**
                 * Generate password sementara.
                 */
                $tempPassword = generateTemporaryPassword(10);
                $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

                /**
                 * Update password admin.
                 */
                $stmtUpdate = $pdo->prepare("
                    UPDATE users
                    SET password_hash = ?,
                        must_change_password = 1,
                        password_reset_at = NOW(),
                        password_reset_by = ?,
                        is_active = 1
                    WHERE id = ?
                      AND role = 'admin'
                    LIMIT 1
                ");
                $stmtUpdate->execute([
                    $passwordHash,
                    (int)auth_user()['id'],
                    $adminId
                ]);

                if ($stmtUpdate->rowCount() <= 0) {
                    $error = 'Reset password gagal diproses.';
                } else {
                    /**
                     * Simpan activity log.
                     */
                    log_activity(
                        $pdo,
                        auth_user(),
                        'RESET_ADMIN_PASSWORD',
                        'Developer reset password admin #' . $adminId . ($storeId ? (' untuk store #' . $storeId) : ''),
                        'users',
                        $adminId,
                        [
                            'target_admin_id' => $adminId,
                            'target_admin_email' => (string)$targetAdmin['email'],
                            'store_id' => $storeId,
                            'store_name' => $storeName,
                        ],
                        $storeId
                    );

                    $success = 'Password admin berhasil di-reset. Password sementara hanya tampil sekali di halaman ini.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Terjadi kesalahan saat reset password admin.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Reset Password Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            font-family: Arial, sans-serif;
            background:#f6f7fb;
            margin:0;
            padding:24px;
            color:#222;
        }
        .wrap{
            max-width:760px;
            margin:0 auto;
            background:#fff;
            border:1px solid #ddd;
            border-radius:12px;
            padding:24px;
        }
        h2{
            margin-top:0;
        }
        .msg{
            padding:12px 14px;
            border-radius:8px;
            margin-bottom:16px;
        }
        .msg.error{
            background:#ffe7e7;
            color:#9b1c1c;
            border:1px solid #f5b5b5;
        }
        .msg.success{
            background:#e8fff0;
            color:#146c2e;
            border:1px solid #b7ebc5;
        }
        .field{
            margin-bottom:16px;
        }
        label{
            display:block;
            margin-bottom:6px;
            font-weight:600;
        }
        select, button{
            width:100%;
            padding:10px 12px;
            border:1px solid #ccc;
            border-radius:8px;
            font-size:14px;
        }
        button{
            background:#4f46e5;
            color:#fff;
            border:none;
            cursor:pointer;
            font-weight:600;
        }
        button:hover{
            opacity:.95;
        }
        .back{
            display:inline-block;
            margin-bottom:16px;
            text-decoration:none;
        }
        .temp-box{
            margin-top:16px;
            padding:16px;
            border:1px dashed #999;
            border-radius:10px;
            background:#fafafa;
        }
        .temp-pass{
            font-size:22px;
            font-weight:700;
            letter-spacing:1px;
            margin:8px 0 0;
            word-break:break-all;
        }
        .note{
            font-size:13px;
            color:#555;
            margin-top:10px;
            line-height:1.5;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <a class="back" href="dev_dashboard.php">← Kembali ke Developer Dashboard</a>

        <h2>Reset Password Admin</h2>
        <p>Kata Sandi Sudah Di Reset.</p>

        <?php if ($error !== ''): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="msg success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" onsubmit="return confirm('Yakin reset password admin ini?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

            <div class="field">
                <label for="admin_id">Pilih Admin</label>
                <select name="admin_id" id="admin_id" required>
                    <option value="">-- pilih admin --</option>
                    <?php foreach ($admins as $a): ?>
                        <option value="<?= (int)$a['id'] ?>">
                            #<?= (int)$a['id'] ?>
                            <?= htmlspecialchars((string)$a['name']) ?>
                            (<?= htmlspecialchars((string)$a['email']) ?>)
                            <?= ((int)$a['is_active'] === 1 ? '' : ' [NONAKTIF]') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">Reset Password Admin</button>
        </form>

        <?php if ($tempPassword !== ''): ?>
            <div class="temp-box">
                <strong>Password sementara admin:</strong>
                <div class="temp-pass"><?= htmlspecialchars($tempPassword) ?></div>
                <div class="note">
                    Simpan password ini sekarang dan kirim ke admin yang bersangkutan.<br>
                    Password ini hanya ditampilkan sekali.<br>
                    Setelah login, admin wajib mengganti password sendiri.
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>