<?php
declare(strict_types=1);

function ensure_dir(string $absDir): void {
  if (!is_dir($absDir)) mkdir($absDir, 0775, true);
}

function list_preset_images(): array {
  $base = __DIR__ . '/../publik/assets/product_presets';
  if (!is_dir($base)) return [];
  $files = glob($base . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
  $out = [];
  foreach ($files as $f) $out[] = 'publik/assets/product_presets/' . basename($f);
  sort($out);
  return $out;
}

function save_uploaded_product_image(string $field = 'image_upload'): ?string {
  if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return null;

  $tmp = $_FILES[$field]['tmp_name'];
  $size = (int)($_FILES[$field]['size'] ?? 0);
  if ($size <= 0 || $size > 2_000_000) return null; // max 2MB

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($tmp) ?: '';
  $allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
  ];
  if (!isset($allowed[$mime])) return null;

  $dirAbs = __DIR__ . '/../upload/products';
  ensure_dir($dirAbs);

  $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
  $destAbs = $dirAbs . '/' . $name;

  if (!move_uploaded_file($tmp, $destAbs)) return null;

  return 'upload/products/' . $name; // simpan path relatif dari webroot
}