<?php
// api/group.php - Groups API
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$user = current_user();
$userId = (int)$user['id'];
$role = $user['role'] ?? 'student';
$canCreate = in_array($role, ['admin', 'staff'], true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $groupId = (int)($_GET['group_id'] ?? 0);
    if ($groupId > 0) {
        if (!is_group_member($userId, $groupId) && !is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        $group = get_group($groupId);
        if (!$group) {
            http_response_code(404);
            echo json_encode(['error' => 'Group not found']);
            exit;
        }
        echo json_encode([
            'group' => $group,
            'members' => get_group_members($groupId),
            'resources' => get_group_resources($groupId),
        ]);
        exit;
    }
    echo json_encode(['groups' => get_user_groups($userId)]);
    exit;
}

// POST actions
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create': {
        if (!$canCreate) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Group name is required']);
            exit;
        }
        $groupId = create_group($userId, $name, $desc);
        echo json_encode(['success' => true, 'group_id' => $groupId]);
        break;
    }
    case 'join': {
        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Join code required']);
            exit;
        }
        $result = join_group_by_code($userId, $code);
        echo json_encode($result);
        break;
    }
    case 'leave': {
        $groupId = (int)($_POST['group_id'] ?? 0);
        if ($groupId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid group']);
            exit;
        }
        leave_group($userId, $groupId);
        echo json_encode(['success' => true]);
        break;
    }
    case 'add_resource': {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        $dueDate = trim($_POST['due_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($groupId <= 0 || $resourceId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }
        if (!is_group_admin($userId, $groupId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        $added = add_resource_to_group($groupId, $resourceId, $userId, $dueDate ?: null, $notes);
        echo json_encode(['success' => true, 'added' => $added]);
        break;
    }
    case 'remove_resource': {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        if ($groupId <= 0 || $resourceId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }
        if (!is_group_admin($userId, $groupId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        remove_resource_from_group($groupId, $resourceId);
        echo json_encode(['success' => true]);
        break;
    }
    case 'regenerate_code': {
        $groupId = (int)($_POST['group_id'] ?? 0);
        if ($groupId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid group']);
            exit;
        }
        if (!is_group_admin($userId, $groupId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        $code = regenerate_join_code($groupId);
        echo json_encode(['success' => true, 'join_code' => $code]);
        break;
    }
    case 'remove_member': {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($groupId <= 0 || $memberId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }
        if (!is_group_admin($userId, $groupId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        $pdo->prepare("DELETE FROM group_members WHERE group_id = :gid AND user_id = :uid")
            ->execute([':gid' => $groupId, ':uid' => $memberId]);
        echo json_encode(['success' => true]);
        break;
    }
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
