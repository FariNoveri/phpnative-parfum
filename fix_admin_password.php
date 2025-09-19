<?php
// fix_admin_password.php
// Jalankan file ini di browser untuk fix password admin

require_once 'config/database.php';

// Generate password hash yang benar
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>🔧 Fix Admin Password</h2>";
echo "<p><strong>Password:</strong> $password</p>";
echo "<p><strong>Hash baru:</strong> $hashed_password</p>";

// Update password di database
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@parfumpremium.com' AND role = 'admin'");

if ($stmt->execute([$hashed_password])) {
    echo "<div style='background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
    echo "✅ <strong>SUCCESS!</strong> Password admin berhasil diupdate!";
    echo "</div>";
    
    // Test login
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@parfumpremium.com' AND role = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
        echo "<div style='background: #d1ecf1; color: #0c5460; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
        echo "🎉 <strong>VERIFIED!</strong> Password sudah benar dan bisa digunakan untuk login.";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
        echo "❌ <strong>ERROR!</strong> Password masih belum bisa diverifikasi.";
        echo "</div>";
    }
    
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
    echo "❌ <strong>ERROR!</strong> Gagal update password di database.";
    echo "</div>";
}

echo "<hr>";
echo "<h3>📋 Admin Login Info:</h3>";
echo "<ul>";
echo "<li><strong>URL:</strong> <a href='admin_login.php'>admin_login.php</a></li>";
echo "<li><strong>Email:</strong> admin@parfumpremium.com</li>";
echo "<li><strong>Password:</strong> admin123</li>";
echo "</ul>";

echo "<hr>";
echo "<h3>📝 Atau Buat Admin Baru Manual:</h3>";
echo "<p>Jika masih bermasalah, jalankan query SQL ini di phpMyAdmin:</p>";
echo "<textarea style='width: 100%; height: 100px; font-family: monospace; padding: 10px;' readonly>";
echo "-- Hapus admin lama dan buat baru\n";
echo "DELETE FROM users WHERE email = 'admin@parfumpremium.com';\n\n";
echo "-- Insert admin baru dengan password yang pasti benar\n";
echo "INSERT INTO users (nama, email, password, telepon, alamat, role) VALUES \n";
echo "('Administrator', 'admin@parfumpremium.com', '$hashed_password', '081234567890', 'Kantor Toko Parfum Premium', 'admin');";
echo "</textarea>";

echo "<br><br>";
echo "<div style='background: #fff3cd; color: #856404; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
echo "⚠️ <strong>PENTING:</strong> Hapus file ini (fix_admin_password.php) setelah berhasil login admin untuk keamanan!";
echo "</div>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 2rem auto;
    padding: 2rem;
    background: #f8f9fa;
}

h2 {
    color: #1e3c72;
    border-bottom: 2px solid #667eea;
    padding-bottom: 0.5rem;
}

h3 {
    color: #333;
    margin-top: 2rem;
}

code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}

a {
    color: #667eea;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>