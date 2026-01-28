<?php
// src/controllers/admin_backup_db.php
require_once "../../config/config.php";

// Admin check
session_start();
if (!isset($_SESSION['admin_loggedin'])) { die("Access Denied"); }

// Log activity (safe to call since we included config)
log_activity($_SESSION['admin_id'] ?? 0, "DB_BACKUP", "Downloaded database backup");

// Settings
$host = DB_SERVER;
$user = DB_USERNAME;
$pass = DB_PASSWORD;
$name = DB_NAME;

// Generate Filename
$backup_name = $name . "_backup_" . date("Y-m-d_H-i-s") . ".sql";

// Headers to force download
header('Content-Type: application/octet-stream');   
header("Content-Transfer-Encoding: Binary"); 
header("Content-disposition: attachment; filename=\"".$backup_name."\""); 

// Simple Export Logic (Structure + Data)
// Note: exec('mysqldump ...') relies on system path. 
// Fallback: Pure PHP loop
$conn->set_charset("utf8");

// Get Tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while($row = $result->fetch_row()){
    $tables[] = $row[0];
}

$return = "";
foreach($tables as $table){
    $result = $conn->query("SELECT * FROM $table");
    $num_fields = $result->field_count;
    
    $return .= "DROP TABLE IF EXISTS $table;"; // Drop table if exists
    $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
    $return .= "\n\n" . $row2[1] . ";\n\n"; // Create Table Structure
    
    for ($i = 0; $i < $num_fields; $i++) {
        while($row = $result->fetch_row()){
            $return .= "INSERT INTO $table VALUES(";
            for($j=0; $j<$num_fields; $j++){
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n","\\n",$row[$j]);
                if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
                if ($j < ($num_fields-1)) { $return.= ','; }
            }
            $return .= ");\n";
        }
    }
    $return .="\n\n\n";
}

echo $return;
exit;
?>
