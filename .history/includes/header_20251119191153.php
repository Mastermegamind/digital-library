<?php
ini_set('display_errors', 1);
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
    <?php
      $homeLink = app_path('index.php');
      $adminLink = app_path('admin/index.php');
      $libraryLink = app_path('index.php');
      $loginLink = app_path('login.php');
      $logoutLink = app_path('logout.php');
    ?>
    <a class="navbar-brand" href="<?php echo h($homeLink); ?>"><?php echo h($APP_NAME); ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <?php if (is_logged_in()): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo h($libraryLink); ?>">Library</a>
            </li>
            <?php if (is_admin()): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo h($adminLink); ?>">Admin</a>
            </li>
            <?php endif; ?>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto">
        <?php if (is_logged_in()): $u = current_user(); $avatarUrl = !empty($u['profile_image_path']) ? app_path($u['profile_image_path']) : null; ?>
            <li class="nav-item d-flex align-items-center">
              <?php if ($avatarUrl): ?>
                <img src="<?php echo h($avatarUrl); ?>" alt="Profile" class="rounded-circle me-2 border border-light" style="width:36px;height:36px;object-fit:cover;">
              <?php else: ?>
                <span class="me-2 d-inline-flex rounded-circle bg-light text-primary fw-semibold justify-content-center align-items-center" style="width:36px;height:36px;">
                  <?php echo h(strtoupper(substr($u['name'], 0, 1))); ?>
                </span>
              <?php endif; ?>
              <span class="navbar-text me-3">
                <?php echo h($u['name']); ?> (<?php echo h($u['role']); ?>)
              </span>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo h($logoutLink); ?>">Logout</a>
            </li>
        <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo h($loginLink); ?>">Login</a>
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
