<?php
class FileUpload {
    private $uploadDir;
    private $allowedTypes;
    private $maxFileSize;
    
    public function __construct($uploadDir = 'uploads/', $maxFileSize = 5242880) { // 5MB default
        $this->uploadDir = $uploadDir;
        $this->maxFileSize = $maxFileSize;
        $this->allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
    }
    
    public function uploadFile($file, $subDir = '') {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file uploaded or upload error occurred.'];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'error' => 'File size exceeds maximum allowed size (5MB).'];
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['success' => false, 'error' => 'File type not allowed. Only images, PDF, and Word documents are permitted.'];
        }
        
        // Create directory if it doesn't exist
        $targetDir = $this->uploadDir . $subDir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $targetPath = $targetDir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'success' => true,
                'path' => $targetPath,
                'filename' => $filename,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'mime_type' => $mimeType
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to move uploaded file.'];
        }
    }
    
    public function deleteFile($filePath) {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
    
    public function getFileIcon($mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
            case 'image/png':
            case 'image/gif':
                return 'fa-image';
            case 'application/pdf':
                return 'fa-file-pdf';
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return 'fa-file-word';
            default:
                return 'fa-file';
        }
    }
    
    public function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
?>
