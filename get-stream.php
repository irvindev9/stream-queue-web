<?php
/**
 * Stream Queue - Get and Delete Next Stream Endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Cargar configuración si existe
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Clave de respaldo por si no se ha configurado config.php
    define('API_KEY', 'super-secret-api-key-12345');
}

// Validar API Key
if (defined('API_KEY') && !empty(API_KEY)) {
    $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    
    // Soporte para Bearer Token
    if (empty($providedKey) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $providedKey = $matches[1];
        }
    }

    if ($providedKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Invalid API Key.']);
        exit;
    }
}

const DB_FILE = __DIR__ . '/queue.sqlite';

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


    try {
        $db->exec("ALTER TABLE stream_queue ADD COLUMN priority INTEGER NOT NULL DEFAULT 3");
    } catch (PDOException $e) {}
    // Asegurar existencia de tabla clients_status
    $db->exec("
        CREATE TABLE IF NOT EXISTS clients_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_name TEXT NOT NULL UNIQUE,
            active_count INTEGER NOT NULL DEFAULT 0,
            waiting_count INTEGER NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Procesar actualización de estado del cliente si viene en el request
    $clientName = trim($_GET['client_name'] ?? $_GET['client'] ?? $_POST['client_name'] ?? $_POST['client'] ?? '');
    if (!empty($clientName)) {
        $activeCount = isset($_GET['active']) ? (int)$_GET['active'] : (isset($_POST['active']) ? (int)$_POST['active'] : 0);
        $waitingCount = isset($_GET['waiting']) ? (int)$_GET['waiting'] : (isset($_POST['waiting']) ? (int)$_POST['waiting'] : 0);

        $upsertStmt = $db->prepare("
            INSERT INTO clients_status (client_name, active_count, waiting_count, updated_at)
            VALUES (:client_name, :active_count, :waiting_count, CURRENT_TIMESTAMP)
            ON CONFLICT(client_name) DO UPDATE SET
                active_count = excluded.active_count,
                waiting_count = excluded.waiting_count,
                updated_at = CURRENT_TIMESTAMP
        ");
        $upsertStmt->execute([
            ':client_name' => $clientName,
            ':active_count' => $activeCount,
            ':waiting_count' => $waitingCount,
        ]);
    }
    
    // Iniciar transacción atómica para obtener trabajo de la cola
    $db->beginTransaction();
    
    // Obtener el primer registro con mayor prioridad (1 alta, 2 media, 3 baja)
    $stmt = $db->query("SELECT id, url, priority FROM stream_queue ORDER BY priority ASC, id ASC LIMIT 1");
    $job = $stmt->fetch();
    
    $value = null;
    $priority = 3;
    
    if ($job) {
        $value = $job['url'];
        $priority = (int)($job['priority'] ?? 3);
        // Eliminar el registro
        $deleteStmt = $db->prepare("DELETE FROM stream_queue WHERE id = :id");
        $deleteStmt->execute([':id' => $job['id']]);
    }
    
    // Obtener cuántos registros quedan en la cola
    $countStmt = $db->query("SELECT COUNT(*) as total FROM stream_queue");
    $countResult = $countStmt->fetch();
    $remaining = (int)($countResult['total'] ?? 0);
    
    $db->commit();
    
    echo json_encode([
        'value' => $value,
        'priority' => $priority,
        'remaining' => $remaining
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'error' => 'Database operation failed: ' . $e->getMessage()
    ]);
}
