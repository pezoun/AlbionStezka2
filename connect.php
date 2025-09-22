<?php

$host="localhost";
$user="root";
$pass="";
$db="sportovni_aplikace";
$conn=new mysqli($host,$user,$pass,$db);
if($conn->connect_error){
    echo "Failed to connect DB".$conn->connect_error;
}
?>