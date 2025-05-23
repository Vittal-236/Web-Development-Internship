<?php
require 'config.php';
$result = $conn->query("SELECT * FROM posts");
while ($row = $result->fetch_assoc()) {
    echo "<h3>{$row['title']}</h3><p>{$row['content']}</p>";
    echo "<a href='update.php?id={$row['id']}'>Edit</a> | ";
    echo "<a href='delete.php?id={$row['id']}'>Delete</a><hr>";
}
?>