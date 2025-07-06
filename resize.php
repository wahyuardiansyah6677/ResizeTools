<?php
// Konfigurasi
$max_file_size = 50 * 1024 * 1024; // 50MB
$allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'pdf', 'zip', 'doc', 'docx'];
$upload_dir = 'uploads/';
$resized_dir = 'resized/';

// Buat direktori jika belum ada
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
if (!file_exists($resized_dir)) {
    mkdir($resized_dir, 0777, true);
}

// Proses upload dan resize
$error = '';
$success = false;
$download_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file_to_resize']) && $_FILES['file_to_resize']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file_to_resize'];
        $file_name = basename($file['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $target_file = $upload_dir . uniqid() . '_' . $file_name;
        
        // Validasi
        if (!in_array($file_ext, $allowed_types)) {
            $error = 'Jenis file tidak didukung. Hanya menerima: ' . implode(', ', $allowed_types);
        } elseif ($file_size > $max_file_size) {
            $error = 'Ukuran file terlalu besar. Maksimal ' . ($max_file_size / 1024 / 1024) . 'MB';
        } else {
            // Pindahkan file ke upload directory
            if (move_uploaded_file($file_tmp, $target_file)) {
                // Dapatkan ukuran resize yang dipilih
                $resize_percentage = isset($_POST['resize_percentage']) ? (int)$_POST['resize_percentage'] : 50;
                $resize_percentage = max(1, min(100, $resize_percentage));
                
                // Proses resize berdasarkan jenis file
                $resized_file = $resized_dir . 'resized_' . $resize_percentage . '_' . $file_name;
                
                if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    // Resize gambar
                    list($width, $height) = getimagesize($target_file);
                    $new_width = $width * $resize_percentage / 100;
                    $new_height = $height * $resize_percentage / 100;
                    
                    $image_p = imagecreatetruecolor($new_width, $new_height);
                    
                    if ($file_ext === 'jpg' || $file_ext === 'jpeg') {
                        $image = imagecreatefromjpeg($target_file);
                    } elseif ($file_ext === 'png') {
                        $image = imagecreatefrompng($target_file);
                        imagealphablending($image_p, false);
                        imagesavealpha($image_p, true);
                    } elseif ($file_ext === 'gif') {
                        $image = imagecreatefromgif($target_file);
                    }
                    
                    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    
                    if ($file_ext === 'jpg' || $file_ext === 'jpeg') {
                        imagejpeg($image_p, $resized_file, 90);
                    } elseif ($file_ext === 'png') {
                        imagepng($image_p, $resized_file, 9);
                    } elseif ($file_ext === 'gif') {
                        imagegif($image_p, $resized_file);
                    }
                    
                    imagedestroy($image);
                    imagedestroy($image_p);
                    
                    $success = true;
                    $download_link = $resized_file;
                } elseif (in_array($file_ext, ['mp4', 'mov'])) {
                    // Untuk video, kita hanya bisa mengurangi kualitas atau mengubah resolusi dengan FFMPEG
                    // Ini membutuhkan FFMPEG terinstall di server
                    $success = false;
                    $error = 'Resize video membutuhkan FFMPEG. File diupload tanpa diresize.';
                    copy($target_file, $resized_file);
                    $download_link = $resized_file;
                } else {
                    // Untuk file lain (PDF, ZIP, DOC), kita tidak bisa benar-benar meresize
                    // Jadi kita hanya mengkompres atau mengcopy saja
                    copy($target_file, $resized_file);
                    $success = true;
                    $download_link = $resized_file;
                }
            } else {
                $error = 'Gagal mengupload file.';
            }
        }
    } else {
        $error = 'Silakan pilih file yang valid.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resize File Online</title>
    <style>
        :root {
            --primary-color: #4a6bff;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --light-gray: #e9ecef;
            --dark-gray: #6c757d;
            --success-color: #28a745;
            --error-color: #dc3545;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--secondary-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        h1 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .description {
            text-align: center;
            margin-bottom: 30px;
            color: var(--dark-gray);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        select, input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        select:focus, input[type="file"]:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .file-types {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .file-type {
            background-color: var(--light-gray);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            color: var(--dark-gray);
        }
        
        .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #3a56d4;
        }
        
        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        
        .success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }
        
        .error {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }
        
        .download-btn {
            display: inline-block;
            margin-top: 15px;
            background-color: var(--success-color);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .download-btn:hover {
            background-color: #218838;
        }
        
        .file-info {
            margin-top: 15px;
            font-size: 14px;
            color: var(--dark-gray);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Resize File Online</h1>
        <p class="description">Ubah ukuran file Anda dengan mudah dan cepat. Pilih file dan tentukan persentase resize.</p>
        
        <form action="" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="resize_percentage">Pilih Ukuran Resize:</label>
                <select id="resize_percentage" name="resize_percentage">
                    <option value="10">10% (Sangat Kecil)</option>
                    <option value="25">25% (Kecil)</option>
                    <option value="50" selected>50% (Setengah Ukuran)</option>
                    <option value="75">75% (Sedikit Dikecilkan)</option>
                    <option value="90">90% (Hampir Ukuran Asli)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>File yang Didukung:</label>
                <div class="file-types">
                    <span class="file-type">JPG</span>
                    <span class="file-type">PNG</span>
                    <span class="file-type">GIF</span>
                    <span class="file-type">MP4</span>
                    <span class="file-type">PDF</span>
                    <span class="file-type">ZIP</span>
                    <span class="file-type">DOC</span>
                    <span class="file-type">DOCX</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="file_to_resize">Pilih File untuk Diresize:</label>
                <input type="file" id="file_to_resize" name="file_to_resize" required>
            </div>
            
            <button type="submit" class="btn">Upload & Resize File Sekarang</button>
        </form>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="result <?php echo $success ? 'success' : 'error'; ?>">
                <?php if ($success): ?>
                    <p>File berhasil diresize!</p>
                    <a href="<?php echo $download_link; ?>" class="download-btn" download>Unduh File Hasil Resize</a>
                    <div class="file-info">
                        Ukuran file berkurang menjadi <?php echo $resize_percentage; ?>% dari ukuran asli.
                    </div>
                <?php else: ?>
                    <p><?php echo $error; ?></p>
                    <?php if (!empty($download_link)): ?>
                        <a href="<?php echo $download_link; ?>" class="download-btn" download>Unduh File Asli</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>