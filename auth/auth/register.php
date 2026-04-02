<?php
require_once '../config/db.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_POST['username'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT); // Security: Password Hashing

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'passenger')");
    if ($stmt->execute([$user, $pass])) {
        header("Location: login.php?msg=success");
    }
}
?>
<div class="max-w-md mx-auto mt-20 p-8 bg-white rounded-3xl shadow-xl">
    <h2 class="text-3xl font-black mb-6">Create Account</h2>
    <form method="POST" class="flex flex-col gap-4">
        <input type="text" name="username" placeholder="Username" class="p-4 bg-slate-100 rounded-xl" required>
        <input type="password" name="password" placeholder="Password" class="p-4 bg-slate-100 rounded-xl" required>
        <button type="submit" class="bg-blue-600 text-white p-4 rounded-xl font-bold">Register</button>
    </form>
</div>