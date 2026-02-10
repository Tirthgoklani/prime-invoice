<?php
// test path
echo "Current file: " . __FILE__ . "<br>";
echo "Directory: " . __DIR__ . "<br>";
echo "Config path should be: " . __DIR__ . "/../../config/config.php<br>";
echo "Config exists: " . (file_exists(__DIR__ . "/../../config/config.php") ? "YES" : "NO") . "<br>";
echo "Controller path should be: " . __DIR__ . "/../controllers/process_edit_product.php<br>";
echo "Controller exists: " . (file_exists(__DIR__ . "/../controllers/process_edit_product.php") ? "YES" : "NO") . "<br>";
?>
