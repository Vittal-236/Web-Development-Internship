<?php
require 'config.php';
$id = $_GET['id'];
$conn->query("DELETE FROM posts WHERE id=$id");
header("Location: read.php");
?>