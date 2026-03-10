<?php
include_once "database_connection.php";
$sql="CREATE TABLE ContentCreation_stud(
    stId INT(12) NOT NULL AUTO_INCREMENT,
    name VARCHAR(22),
    lname VARCHAR(20),
    age INT(5),
    PRIMARY KEY(stId)
);";

    $create_table=mysqli_query($conn, $sql);
    if($create_table)
    {
        echo "table created successfully";
    } 
    else 
    echo "table not created successfully".mysqli_error($conn);


?>