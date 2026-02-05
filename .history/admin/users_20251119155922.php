<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stmt = $pdo->query("SELECT id, name, email, role, created_at, profile_image_path FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csrf = get_csrf_token();

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Manage Users</h4>
  <a href="user_add.php" class="btn btn-sm btn-success">Add User</a>
</div>
<div class="card">
  <div class="card-body p-0">
    <?php if (empty($users)): ?>
      <p class="p-3 mb-0">No users.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Profile</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td style="width:80px;">
                <?php if (!empty($u['profile_image_path'])): ?>
                  <?php $avatarUrl = app_path($u['profile_image_path']); ?>
                  <img src="<?php echo h($avatarUrl); ?>" alt="Avatar" style="width:50px;height:50px;object-fit:cover;" class="rounded-circle border">
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?php echo h($u['name']); ?></td>
              <td><?php echo h($u['email']); ?></td>
              <td><?php echo h($u['role']); ?></td>
              <td><?php echo h($u['created_at']); ?></td>
              <td class="text-end">
                <a href="user_edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-secondary me-1">Edit</a>
                <a href="user_delete.php?id=<?php echo $u['id']; ?>&csrf=<?php echo h($csrf); ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Delete this user?');">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
