<?php

$host="localhost";
$user="root";
$pass="root";
$db="sportovni_aplikace";
$port=8889;
$conn=new mysqli($host,$user,$pass,$db,$port);
if($conn->connect_error){
    echo "Failed to connect DB".$conn->connect_error;
}
?>