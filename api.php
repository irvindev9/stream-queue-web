<?php
/**
 * Stream Queue API - Lightweight SQLite Queue Manager
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

const DB_FILE = __DIR__ . '/queue.sqlite';

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Initialize Database Table
    $db->exec("
        CREATE TABLE IF NOT EXISTS stream_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending', -- pending, recording, completed, failed
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_stream_queue_status ON stream_queue(status);

        CREATE TABLE IF NOT EXISTS clients_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_name TEXT NOT NULL UNIQUE,
            active_count INTEGER NOT NULL DEFAULT 0,
            waiting_count INTEGER NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        // Retrieve all jobs and active clients status
        try {
            $stmt = $db->query("SELECT * FROM stream_queue ORDER BY id DESC LIMIT 100");
            $jobs = $stmt->fetchAll();

            $clientsStmt = $db->query("SELECT * FROM clients_status ORDER BY updated_at DESC");
            $clients = $clientsStmt->fetchAll();

            echo json_encode(['success' => true, 'jobs' => $jobs, 'clients' => $clients]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'clients':
        try {
            $clientsStmt = $db->query("SELECT * FROM clients_status ORDER BY updated_at DESC");
            $clients = $clientsStmt->fetchAll();
            echo json_encode(['success' => true, 'clients' => $clients]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'add':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed. Use POST.']);
            exit;
        }

        // Handle raw JSON or form-urlencoded
        $input = json_decode(file_get_contents('php://input'), true);
        $url = $input['url'] ?? $_POST['url'] ?? '';

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['error' => 'A valid URL is required']);
            exit;
        }

        try {
            $stmt = $db->prepare("INSERT INTO stream_queue (url, status) VALUES (:url, 'pending')");
            $stmt->execute([':url' => $url]);
            $newId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Stream added to queue',
                'job' => [
                    'id' => $newId,
                    'url' => $url,
                    'status' => 'pending'
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'next':
        // Fetch and lock the next pending stream (Atomic transaction)
        try {
            $db->beginTransaction();
            
            // Find the oldest pending job
            $stmt = $db->query("SELECT * FROM stream_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
            $job = $stmt->fetch();
            
            if ($job) {
                // Update status to 'recording'
                $update = $db->prepare("UPDATE stream_queue SET status = 'recording', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute([':id' => $job['id']]);
                $db->commit();
                
                // Return the updated job
                $job['status'] = 'recording';
                echo json_encode(['success' => true, 'job' => $job]);
            } else {
                $db->commit();
                echo json_encode(['success' => true, 'job' => null, 'message' => 'No pending jobs in queue']);
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed. Use POST.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? $_POST['id'] ?? null;
        $status = $input['status'] ?? $_POST['status'] ?? '';
        $errorMessage = $input['error_message'] ?? $_POST['error_message'] ?? null;

        if (!$id || !in_array($status, ['pending', 'recording', 'completed', 'failed'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters. id and valid status are required.']);
            exit;
        }

        try {
            $stmt = $db->prepare("
                UPDATE stream_queue 
                SET status = :status, error_message = :error_message, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':error_message' => $errorMessage,
                ':id' => $id
            ]);

            echo json_encode(['success' => true, 'message' => 'Job status updated successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'clear':
        // Optional helper to clear completed or failed jobs from the interface view
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed. Use POST.']);
            exit;
        }

        try {
            $db->exec("DELETE FROM stream_queue WHERE status IN ('completed', 'failed')");
            echo json_encode(['success' => true, 'message' => 'Cleaned completed and failed jobs']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Specify a valid ?action= parameter.']);
        break;
}
