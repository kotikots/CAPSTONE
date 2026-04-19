<?php
$file = 'config/kiosk_settings.php';
if (file_exists($file)) {
    echo "✅ Success! PHP found the file.";
} else {
    echo "❌ Error: PHP cannot find the file at $file";
}
?>