<?php
// admin/upload_handler.php
require_once 'config/auth.php';
requireAdmin();

header('Content-Type: application/json');

if (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diupload atau terjadi error']);
    exit;
}

$file = $_FILES['gambar'];
$upload_dir = '../assets/images/products/';

// Create directory if not exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan. Hanya JPEG, PNG, GIF, WEBP yang diperbolehkan']);
    exit;
}

// Validate file size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB']);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'product_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Optional: Resize image if too large
    resizeImage($filepath, 800, 800);
    
    echo json_encode([
        'success' => true, 
        'filename' => $filename,
        'url' => 'assets/images/products/' . $filename,
        'message' => 'Gambar berhasil diupload'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
}

function resizeImage($filepath, $max_width, $max_height) {
    $image_info = getimagesize($filepath);
    if (!$image_info) return false;
    
    list($orig_width, $orig_height, $image_type) = $image_info;
    
    // Skip if image is already small enough
    if ($orig_width <= $max_width && $orig_height <= $max_height) {
        return true;
    }
    
    // Calculate new dimensions
    $ratio = min($max_width / $orig_width, $max_height / $orig_height);
    $new_width = round($orig_width * $ratio);
    $new_height = round($orig_height * $ratio);
    
    // Create new image resource
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($filepath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($filepath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($filepath);
            break;
        default:
            return false;
    }
    
    if (!$source) return false;
    
    // Create new image
    $destination = imagecreatetruecolor($new_width, $new_height);
    
    // Handle transparency for PNG and GIF
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Resize image
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
    
    // Save resized image
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            imagejpeg($destination, $filepath, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($destination, $filepath, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($destination, $filepath);
            break;
    }
    
    // Clean up
    imagedestroy($source);
    imagedestroy($destination);
    
    return true;
}
?>