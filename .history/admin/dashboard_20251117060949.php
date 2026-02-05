<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stats = [];
$stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['resources'] = (int)$pdo->query("SELECT COUNT(*) FROM resources")->fetchColumn();
$stats['categories'] = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3">Admin Dashboard</h4>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card text-bg-primary">
      <div class="card-body">
        <h5 class="card-title">Users</h5>
        <p class="display-6"><?php echo (int)$stats['users']; ?></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-bg-success">
      <div class="card-body">
        <h5 class="card-title">Resources</h5>
        <p class="display-6"><?php echo (int)$stats['resources']; ?></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-bg-info">
      <div class="card-body">
        <h5 class="card-title">Categories</h5>
        <p class="display-6"><?php echo (int)$stats['categories']; ?></p>
      </div>
    </div>
  </div>
</div>

<div class="mt-4">
  <a href="resources.php" class="btn btn-outline-light">Manage Resources</a>
  <a href="categories.php" class="btn btn-outline-light">Manage Categories</a>
  <a href="users.php" class="btn btn-outline-light">View Users</a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
