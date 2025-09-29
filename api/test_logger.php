<?php
require_once __DIR__ . '/../logger.php';
file_put_contents(__DIR__ . '/../diagnostic_logs/diagnostics_errors.log', date('[Y-m-d H:i:s] ') . "HELLO test_logger.php\n", FILE_APPEND);
echo json_encode(["success" => true, "message" => "test_logger.php OK"]);
