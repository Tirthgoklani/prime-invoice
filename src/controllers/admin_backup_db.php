<?php
// Database backup controller
require_once "../../config/config.php";

session_start();
if(!isset($_SESSION['admin_loggedin'])) { 
    die("Access Denied"); 
}

// log the backup action
log_activity($_SESSION['admin_id'] ?? 0, "DB_BACKUP", "Downloaded database backup");

$host = DB_SERVER;
$user = DB_USERNAME;
$pass = DB_PASSWORD;
$dbname = DB_NAME;

$backup_file = $dbname . "_backup_" . date("Y-m-d_H-i-s") . ".sql";

// force download
header('Content-Type: application/octet-stream');   
header("Content-Transfer-Encoding: Binary"); 
header("Content-disposition: attachment; filename=\"".$backup_file."\""); 

$conn->set_charset("utf8");

// get all tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while($row = $result->fetch_row()){
    $tables[] = $row[0];
}

$output = "";
foreach($tables as $table){
    $result = $conn->query("SELECT * FROM $table");
    $num_fields = $result->field_count;
    
    $output .= "DROP TABLE IF EXISTS $table;";
    $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
    $output .= "\n\n" . $row2[1] . ";\n\n";
    
    for($i = 0; $i < $num_fields; $i++) {
        while($row = $result->fetch_row()){
            $output .= "INSERT INTO $table VALUES(";
            for($j=0; $j<$num_fields; $j++){
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n","\\n",$row[$j]);
                if(isset($row[$j])) { 
                    $output.= '"'.$row[$j].'"' ; 
                } else { 
                    $output.= '""'; 
                }
                if($j < ($num_fields-1)) { $output.= ','; }
            }
            $output .= ");\n";
        }
    }
    $output .="\n\n\n";
}

echo $output;
exit;
?>
