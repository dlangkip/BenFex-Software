<?php

include 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');


try {
    // Create connection using PDO for better error handling
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to fetch plans
    $sql = "SELECT name_plan, price, validity, validity_unit, shared_users 
            FROM tbl_plans 
            WHERE enabled = 1 AND allow_purchase = 'yes' AND type = 'Hotspot'";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return data as JSON
    echo json_encode($plans);
    
} catch(PDOException $e) {
    // Return error message as JSON
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
