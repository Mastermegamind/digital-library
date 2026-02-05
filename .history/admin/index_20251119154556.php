<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$sections = [
    [
        'title' => 'Dashboard',
        'text' => 'View quick stats and recent activity.',
        'link' => 'dashboard.php',
        'btn'  => 'Go to Dashboard',
    ],
    [
        'title' => 'Resources',
        'text' => 'Manage all uploaded learning resources.',
        'link' => 'resources.php',
        'btn'  => 'Manage Resources',
    ],
    [
        'title' => 'Categories',
        'text' => 'Create or edit resource categories.',
        'link' => 'categories.php',
        'btn'  => 'Manage Categories',
    ],
    [
        'title' => 'Users',
        'text' => 'View and manage user accounts.',
        'link' => 'users.php',
        'btn'  => 'Manage Users',
    ],
];

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3">Admin Home</h4>
<div class="row g-3">
  <?php foreach ($sections as $section): ?>
    <div class="col-md-6 col-lg-3">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title"><?php echo h($section['title']); ?></h5>
          <p class="card-text flex-grow-1"><?php echo h($section['text']); ?></p>
          <a href="<?php echo h($section['link']); ?>" class="btn btn-primary mt-2"><?php echo h($section['btn']); ?></a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
