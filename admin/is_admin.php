<?php
// is_admin.php
function is_admin(mysqli $conn, int $userId): bool {
  $st = $conn->prepare("SELECT 1 FROM admins WHERE admin_user_id = ? LIMIT 1");
  $st->bind_param("i", $userId);
  $st->execute(); $st->store_result();
  $is = $st->num_rows > 0;
  $st->close();
  return $is;
}
