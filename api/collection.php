<?php
// api/collection.php - Manage collections and items
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$user = current_user();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $collections = get_user_collections($userId);
    echo json_encode(['collections' => $collections]);
    exit;
}

// POST actions
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';

function collection_owned_by_user(int $collectionId, int $userId): bool {
    $col = get_collection($collectionId);
    return $col && (int)$col['user_id'] === $userId;
}

switch ($action) {
    case 'create': {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPublic = !empty($_POST['is_public']);
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Collection name is required']);
            exit;
        }
        $id = create_collection($userId, $name, $description, $isPublic);
        echo json_encode(['success' => true, 'collection_id' => $id]);
        break;
    }
    case 'update': {
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPublic = !empty($_POST['is_public']);
        if ($collectionId <= 0 || $name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }
        if (!collection_owned_by_user($collectionId, $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        update_collection($collectionId, $name, $description, $isPublic);
        echo json_encode(['success' => true]);
        break;
    }
    case 'delete': {
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        if ($collectionId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid collection']);
            exit;
        }
        if (!collection_owned_by_user($collectionId, $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        delete_collection($collectionId);
        echo json_encode(['success' => true]);
        break;
    }
    case 'add_item': {
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        if ($collectionId <= 0 || $resourceId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }
        if (!collection_owned_by_user($collectionId, $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id FROM resources WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $resourceId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
            exit;
        }
        $added = add_to_collection($collectionId, $resourceId);
        echo json_encode(['success' => true, 'added' => $added]);
        break;
    }
    case 'remove_item': {
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        if ($collectionId <= 0 || $resourceId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }
        if (!collection_owned_by_user($collectionId, $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed']);
            exit;
        }
        remove_from_collection($collectionId, $resourceId);
        echo json_encode(['success' => true]);
        break;
    }
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
