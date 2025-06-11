<?php
// final_project.php

session_start();
require_once 'secure_db.php';
require_once 'validate.php';
require_once 'roles.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user role
$user_role = $_SESSION['role'] ?? 'guest';

// Connect to DB
$conn = getDB();

// Pagination setup
require_once 'pagination.php';

// Handle search
$search_query = "";
if (isset($_GET['search'])) {
    $search_query = validate_input($_GET['search']);
    $stmt = $conn->prepare("SELECT * FROM posts WHERE title LIKE ? OR content LIKE ? ORDER BY created_at DESC LIMIT $offset, $limit");
    $stmt->execute(["%$search_query%", "%$search_query%"]);
} else {
    $stmt = $conn->prepare("SELECT * FROM posts ORDER BY created_at DESC LIMIT $offset, $limit");
    $stmt->execute();
}
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Final Project - Blog</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h2>Welcome, <?= htmlspecialchars($_SESSION['username']) ?> (<?= $user_role ?>)</h2>
    <a href="logout.php">Logout</a>
    <hr>

    <!-- Search Form -->
    <form method="GET" action="final_project.php">
        <input type="text" name="search" placeholder="Search posts..." value="<?= htmlspecialchars($search_query) ?>">
        <button type="submit">Search</button>
    </form>

    <!-- New Post Form (Only for Editor/Admin) -->
    <?php if (in_array($user_role, ['admin', 'editor'])): ?>
        <h3>Create New Post</h3>
        <form method="POST" action="create.php">
            <input type="text" name="title" placeholder="Title" required>
            <textarea name="content" placeholder="Content" required></textarea>
            <button type="submit">Create</button>
        </form>
    <?php endif; ?>

    <!-- Display Posts -->
    <h3>Blog Posts</h3>
    <?php foreach ($posts as $post): ?>
        <div>
            <h4><?= htmlspecialchars($post['title']) ?></h4>
            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
            <small>Posted on <?= $post['created_at'] ?></small><br>

            <?php if (in_array($user_role, ['admin', 'editor'])): ?>
                <a href="update.php?id=<?= $post['id'] ?>">Edit</a> |
                <a href="delete.php?id=<?= $post['id'] ?>" onclick="return confirm('Delete this post?');">Delete</a>
            <?php endif; ?>
        </div>
        <hr>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?= renderPagination($conn, $search_query); ?>
</body>
</html>
