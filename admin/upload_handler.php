<?php
// upload_handler.php
header('Content-Type: application/json');

// ImgBB API Configuration
define('IMGBB_API_KEY', '32ddacde5d921e494c118af76931ef76');
define('IMGBB_API_URL', 'https://api.imgbb.com/1/upload');

function uploadToImgBB($imageData, $filename = null) {
    $postData = [
        'key' => IMGBB_API_KEY,
        'image' => base64_encode($imageData),
        'name' => $filename ? pathinfo($filename, PATHINFO_FILENAME) : 'product_' . time()
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, IMGBB_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Failed to connect to ImgBB API'];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['success']) && $result['success']) {
        return [
            'success' => true,
            'url' => $result['data']['url'],
            'display_url' => $result['data']['display_url'],
            'delete_url' => $result['data']['delete_url'] ?? null,
            'thumb' => $result['data']['thumb']['url'] ?? null
        ];
    }
    
    return [
        'success' => false, 
        'message' => $result['error']['message'] ?? 'Unknown error'
    ];
}

function validateImage($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 10MB (ImgBB limit is 32MB)
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['valid' => false, 'message' => 'Tipe file tidak diizinkan. Hanya JPG, PNG, GIF, WEBP.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 10MB.'];
    }
    
    return ['valid' => true];
}

// Handle upload request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error');
        }
        
        $file = $_FILES['gambar'];
        
        // Validate file
        $validation = validateImage($file);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'message' => $validation['message']]);
            exit;
        }
        
        // Read file data
        $imageData = file_get_contents($file['tmp_name']);
        if ($imageData === false) {
            throw new Exception('Failed to read uploaded file');
        }
        
        // Upload to ImgBB
        $result = uploadToImgBB($imageData, $file['name']);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'url' => $result['url'],
                'display_url' => $result['display_url'],
                'thumb' => $result['thumb'],
                'message' => 'Gambar berhasil diupload ke ImgBB'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Upload failed: ' . $result['message']
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>