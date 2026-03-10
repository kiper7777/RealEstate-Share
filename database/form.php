<?php
include_once "database_connection.php";

if(isset($_GET['submit']))
{
    $name=$_GET['name'];
    $lname=$_GET['lname'];
    $age=$_GET['age'];

    $sql="insert into contentcreation_stud(name,lname,age)
    values('$name','$lname',' $age')";

   $res= mysqli_query($conn, $sql);
   if($res)
   {
    echo 'insert the data successfully';
   }
else
{
   echo 'not inserted';
}
    
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <form action="form.php" method="get">

        <label for="name">Name</label>
        <input type="text" name='name'>

        <label for="lname">Last Name</label>
        <input type="text" name='lname'>

        <label for="">Age</label>
        <input type="text" name='age'>

        <input type="submit" value="submit" name="submit">

    </form>
    
</body>
</html>