<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();

echo "Session ID: " . session_id() . "<br>";
echo "SESSION dump: " . json_encode($_SESSION) . "<br>";
echo "Session save path: " . session_save_path() . "<br>";
echo "Session file: " . session_save_path() . '/sess_' . session_id() . "<br>";
echo "File exists: " . (file_exists(session_save_path() . '/sess_' . session_id()) ? 'YES' : 'NO') . "<br>";
if (file_exists(session_save_path() . '/sess_' . session_id())) {
    echo "File contents: " . file_get_contents(session_save_path() . '/sess_' . session_id()) . "<br>";
}
