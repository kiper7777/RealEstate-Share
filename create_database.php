<?php
$servername="localhost";
$username="root";
$password="";

$conn=mysqli_connect($servername, $username, $password);
if($conn)
{
    echo "connect with server successfully";
} else {
    echo "not connected please try again" + mysqli_connect_error();
}


$sql="CREATE DATABASE realestate_share";
$create_db=mysqli_query($conn, $sql);
if($create_db)
{
    echo "Database created successfully";
} 
else echo "Database not created successfully".mysqli_error($conn);


?>