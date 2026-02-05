<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stmt = $pdo->query("SELECT r.*, c.name AS category_name FROM resources r
                     LEFT JOIN categories c ON r.category_id = c.id
                     ORDER BY r.created_at DESC");
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Manage Resources</h4>
  <a href="resource_add.php" class="btn btn-sm btn-success">Add Resource</a>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($resources)): ?>
      <p class="p-3 mb-0">No resources yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Cover</th>
              <th>Title</th>
              <th>Type</th>
              <th>Category</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($resources as $r): ?>
            <tr>
              <td>
                <?php if (!empty($r['cover_image_path'])): ?>
                  <?php $coverUrl = app_path($r['cover_image_path']); ?>
                  <img src="<?php echo h($coverUrl); ?>" alt="Cover" style="width:60px;height:60px;object-fit:cover;" class="rounded border">
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?php echo h($r['title']); ?></td>
              <td><span class="badge bg-info text-dark"><?php echo h(strtoupper($r['type'])); ?></span></td>
              <td><?php echo h($r['category_name']); ?></td>
              <td><?php echo h($r['created_at']); ?></td>
              <td class="text-end">
                <a href="../viewer.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-primary">Open</a>
                <a href="resource_edit.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                <a href="resource_delete.php?id=<?php echo $r['id']; ?>&csrf=<?php echo h(get_csrf_token()); ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Delete this resource?');">Delete</a>
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
