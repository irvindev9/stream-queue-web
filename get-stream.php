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
    
    // Iniciar transacción atómica
    $db->beginTransaction();
    
    // Obtener el primer registro más antiguo
    $stmt = $db->query("SELECT id, url FROM stream_queue ORDER BY id ASC LIMIT 1");
    $job = $stmt->fetch();
    
    $value = null;
    
    if ($job) {
        $value = $job['url'];
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
