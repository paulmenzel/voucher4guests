<?php
require_once 'Db.php';

$db = new Db();

//load config
//$config = include('../../config/database.config.php');
$config = array(
    'db_base' => 'voucher_old',
    'db_user' => 'root',
    'db_password' => 'anna1992',
    'db_host' => 'localhost'
);

//test database connection
print "\nTest database connection: ";
if(!$db->connect()){
    print "ERROR\n";
    print " The database connection could not be established\n";
    print "\nUpdate aborted\n";
    exit(0);
} else {
    print "READY\n";
}

/*$sql="DROP TABLE IF EXISTS `mac_addresses`;";
$db->query($sql);*/

$message = "";
$databaseIsReady = true;

//test if database is ready for update
print "\nTest if database is ready for update: ";

$sql="SHOW TABLES FROM ".$config['db_base'];
$tables = $db->query($sql);

$tables_arr = array();
while($row = $tables->fetch_array()){
    $tables_arr[] = $row[0];
}
if (array_search('vouchers', $tables_arr) === false){
    $databaseIsReady = false;
    $message = " Table 'vouchers' does not exist\n";
}
if (array_search('mac_addresses', $tables_arr) !== false){
    $databaseIsReady = false;
    $message = " New table 'mac_addresses' already exists\n";
}

if(!$databaseIsReady){
    print "ERROR\n";
    print " The database is not ready for the update\n";
    print $message;
    print "\nUpdate aborted\n";
    exit(0);
} else {
    print "READY\n";
}




//dump database
print "\nBackup current database:\n";

$sql_file = dirname( __FILE__ )."/dump_" . $config['db_base'] . "_" . date('Ymd_Hi') . ".sql";

print "Location: ".$sql_file."\n";
exec("mysqldump -u ".$config['db_user']." -p'".$config['db_password']."' --allow-keywords --add-drop-table --complete-insert --quote-names ".$config['db_base']." > ".$sql_file);

if (file_exists($sql_file)) {
    echo "Backup successful\n";
} else {
    echo "ERROR: Backup failed";
    print "\nUpdate aborted\n";
    exit(0);
}




//create new table
print "\nCreate new table 'mac_addresses': ";


$sql = "
CREATE TABLE `mac_addresses` (
  `mid`               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `vid`               INT UNSIGNED      NOT NULL,
  `mac_address`       VARCHAR(12)       NOT NULL,
  `active`            TINYINT UNSIGNED  NOT NULL,
  `activation_time`   DATETIME          NOT NULL,
  `deactivation_time` DATETIME,
  PRIMARY KEY (`mid`)
);
";

if ($db->query($sql) === true) {
    print "OK\n";
    print " Table created successfully\n";
} else {
    print "ERROR\n";
    print " Error creating table: " . $db->error() . "\n";
    abortUpdate($sql_file, $config);
}


//fill new table

print "Migrate data\n";

$result = $db->select("SELECT * FROM vouchers");

if ($result) {
    $i = 0;
    foreach ($result as $row) {
        $i++;
        if($row['mac']!='') {
            //print $row['mac']."\n";
            $insert = "INSERT INTO mac_addresses(vid, mac_address, active) VALUES(" . $row['vid'] . ", '" . $row['mac'] . "', " . $row['activ'] . ")";
            $result = $db->query($insert);
            if ($result) {
            } else {
                print "ERROR\n";
                print " Error insert entry in table mac_addresses: " . $db->error() . "\n";
                abortUpdate($sql_file, $config, $db);
            }
        }
    }

    print " ".$i." entries migrated\n";
} else {

}



//update other tables
print "Update voucher table\n";
//rename columns
$sql="ALTER TABLE `vouchers` CHANGE `activ` `active` tinyint unsigned NOT NULL;";
$result = $db->query($sql);
if ($result) {
} else {
    print "ERROR\n";
    print " Error renaming column: " . $db->error() . "\n";
    abortUpdate($sql_file, $config, $db);
}

//remove not needed column
$sql="ALTER TABLE `vouchers` DROP `mac`;";
$result = $db->query($sql);
if ($result) {
} else {
    print "ERROR\n";
    print " Error removing column: " . $db->error() . "\n";
    abortUpdate($sql_file, $config, $db);
}

print "\nUpdate successful\n";



function abortUpdate($sql_file, $config, $db) {
    print "\nUpdate aborted\n";
    print "\nRestore database\n";
    $sql="DROP TABLE IF EXISTS `mac_addresses`;";
    $db->query($sql);
    exec("mysql -u ".$config['db_user']." -p'".$config['db_password']."' < ".$sql_file);
    exit(0);
}

