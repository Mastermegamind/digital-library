<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Handle search
$search = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT r.*, c.name AS category_name FROM resources r
        LEFT JOIN categories c ON r.category_id = c.id";

if ($search !== '') {
    $sql .= " WHERE r.title LIKE :q OR r.description LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$sql .= " ORDER BY datetime(r.created_at) DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter or admin forms
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Library Resources</h4>
  <?php if (is_admin()): ?>
    <a href="admin/resource_add.php" class="btn btn-sm btn-success">Add Resource</a>
  <?php endif; ?>
</div>

<form class="row g-2 mb-3" method="get" action="index.php">
  <div class="col-md-6">
    <input type="text" name="q" class="form-control" placeholder="Search by title or description..."
           value="<?php echo h($search); ?>">
  </div>
  <div class="col-md-2">
    <button class="btn btn-outline-secondary w-100" type="submit">Search</button>
  </div>
</form>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($resources)): ?>
      <p class="p-3 mb-0">No resources found.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Title</th>
              <th>Type</th>
              <th>Category</th>
              <th>Added</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($resources as $r): ?>
            <tr>
              <td><?php echo h($r['title']); ?></td>
              <td><span class="badge bg-info text-dark"><?php echo h(strtoupper($r['type'])); ?></span></td>
              <td><?php echo h($r['category_name']); ?></td>
              <td><?php echo h($r['created_at']); ?></td>
              <td class="text-end">
                <a href="resource.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-primary">Open</a>
                <?php if (is_admin()): ?>
                  <a href="admin/resource_edit.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
