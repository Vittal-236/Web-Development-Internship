<?php
require 'config.php';
$id = $_GET['id'];
if ($_POST) {
    $stmt = $conn->prepare("UPDATE posts SET title=?, content=? WHERE id=?");
    $stmt->bind_param("ssi", $_POST['title'], $_POST['content'], $id);
    $stmt->execute();
    header("Location: read.php");
}
$result = $conn->query("SELECT * FROM posts WHERE id=$id");
$post = $result->fetch_assoc();
?>
<form method="post">
  Title: <input name="title" value="<?= $post['title'] ?>"><br>
  Content: <textarea name="content"><?= $post['content'] ?></textarea><br>
  <button type="submit">Update</button>
</form>