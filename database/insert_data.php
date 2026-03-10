<?php
include_once "database_connection.php";

$sql="INSERT INTO beginners(id,email,username,`password`,fname,lname)
VALUES(1,'tom@ukr.net','tbart77','123456qwerty','Tom','Bart')";

$insert_data=mysqli_query($conn,$sql);
if($insert_data)
{
    echo "Insert data successfully";
}
else {
    echo "Data not inserted successfully";
}

?>