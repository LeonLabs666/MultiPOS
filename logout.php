<?php
session_start();

// Kalau user klik tombol "Ya Logout"
if (isset($_POST['confirm_logout'])) {

    // Hapus semua session
    $_SESSION = [];

    // Hapus cookie session kalau ada
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy session
    session_destroy();

    // Base path otomatis (support subfolder /multipos)
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    // Redirect ke login
    header("Location: {$base}/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Konfirmasi Logout</title>
<style>
  :root{
    --bg:#f4f6f9;
    --card:#fff;
    --text:#111827;
    --muted:#6b7280;
    --shadow:0 10px 25px rgba(0,0,0,.10);
    --radius:14px;
  }
  *{box-sizing:border-box}
  body{
    font-family: Arial, sans-serif;
    background:var(--bg);
    margin:0;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:18px;
    color:var(--text);
  }
  .box{
    background:var(--card);
    padding:22px;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    text-align:center;
    width:min(420px, 92vw);
  }
  h3{
    margin:0;
    font-size:18px;
    line-height:1.35;
  }
  p{
    margin:10px 0 0;
    font-size:13px;
    color:var(--muted);
    line-height:1.4;
  }
  form{
    margin-top:18px;
    display:flex;
    gap:10px;
    justify-content:center;
    flex-wrap:wrap;
  }
  .btn{
    padding:12px 16px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-weight:700;
    font-size:15px;
    min-height:44px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }
  .btn-yes{
    background:#e74c3c;
    color:#fff;
  }
  .btn-no{
    background:#95a5a6;
    color:#fff;
  }

  /* Mobile: tombol full width biar gak kecil */
  @media (max-width:480px){
    .box{padding:18px}
    h3{font-size:17px}
    form{flex-direction:column}
    .btn{width:100%}
  }
</style>
</head>
<body>

<div class="box">
  <h3>Logout dan kembali ke menu Login?</h3>
  <p>Sesi akan diakhiri dan  perlu login lagi.</p>

  <form method="POST">
    <button type="submit" name="confirm_logout" class="btn btn-yes">
      Ya, Logout
    </button>

    <button type="button" class="btn btn-no" onclick="window.history.back()">
      Batal
    </button>
  </form>
</div>

</body>
</html>
