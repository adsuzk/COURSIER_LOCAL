<?php
file_put_contents(__DIR__ . '/../diagnostic_logs/diagnostics_errors.log', date('[Y-m-d H:i:s] ') . "HELLO test_php.php\n", FILE_APPEND);
echo json_encode(["success" => true, "message" => "test_php.php OK"]);
