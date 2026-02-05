<?php
// includes/header.php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo h($APP_NAME); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container">
    <a class="navbar-brand" href="<?php echo h(str_replace('/admin', '', $_SERVER['PHP_SELF'])); ?>">
        <?php echo h($APP_NAME); ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <?php if (is_logged_in()): ?>
            <li class="nav-item">
              <a class="nav-link" href="/elib/index.php">Library</a>
            </li>
            <?php if (is_admin()): ?>
            <li class="nav-item">
              <a class="nav-link" href="/elib/admin/dashboard.php">Admin</a>
            </li>
            <?php endif; ?>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto">
        <?php if (is_logged_in()): $u = current_user(); ?>
            <li class="nav-item">
              <span class="navbar-text me-3">
                <?php echo h($u['name']); ?> (<?php echo h($u['role']); ?>)
              </span>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="/elib/logout.php">Logout</a>
            </li>
        <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="/elib/login.php">Login</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mb-4">
<?php
$flash = flash_message();
if ($flash):
?>
<script>
Swal.fire({
    icon: '<?php echo h($flash['type']); ?>',
    title: '<?php echo $flash['type'] === 'success' ? 'Success' : 'Notice'; ?>',
    text: '<?php echo h($flash['message']); ?>',
    timer: 2500,
    showConfirmButton: false
});
</script>
<?php endif; ?>
