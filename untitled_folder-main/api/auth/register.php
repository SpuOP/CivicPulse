<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/email_functions.php';

$response = ['success' => false, 'message' => '', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

try {
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $city_id = (int)($_POST['city_id'] ?? 0);
    $metro_area_id = (int)($_POST['metro_area_id'] ?? 0);
    $address_detail = sanitizeInput($_POST['address_detail'] ?? '');
    $occupation = $_POST['occupation'] ?? '';
    $motivation = sanitizeInput($_POST['motivation'] ?? '');
    $document_type = $_POST['document_type'] ?? '';
    $proof_document = $_FILES['proof_document'] ?? null;
    
    // Validation
    $errors = [];
    
    if (empty($full_name) || strlen($full_name) < 3) {
        $errors[] = 'Full name is required (min 3 characters)';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($phone) || !preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
        $errors[] = 'Valid phone number is required';
    }
    
    if (empty($password) || strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if ($city_id <= 0) {
        $errors[] = 'Please select your city';
    }
    
    if (empty($address_detail) || strlen($address_detail) < 10) {
        $errors[] = 'Complete address is required (min 10 characters)';
    }
    
    if (empty($occupation)) {
        $errors[] = 'Please select your occupation';
    }
    
    if (empty($motivation) || strlen($motivation) < 20) {
        $errors[] = 'Please explain why you want to join (min 20 characters)';
    }
    
    if (empty($document_type)) {
        $errors[] = 'Please select a document type';
    }
    
    // Validate proof document upload
    if (!$proof_document || $proof_document['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a proof of residence document';
    } else {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($proof_document['type'], $allowed_types)) {
            $errors[] = 'Only JPEG, PNG, or PDF files are allowed';
        } elseif ($proof_document['size'] > $max_size) {
            $errors[] = 'File size must be less than 5MB';
        }
    }
    
    if (!empty($errors)) {
        $response['message'] = 'Validation errors';
        $response['errors'] = $errors;
        echo json_encode($response);
        exit;
    }
    
    // Check if email already exists
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM user_applications WHERE email = ? UNION SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email, $email]);
    
    if ($stmt->fetch()) {
        $response['message'] = 'This email is already in use';
        echo json_encode($response);
        exit;
    }
    
    // Upload proof document
    $upload_dir = __DIR__ . '/../../uploads/proof_documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = pathinfo($proof_document['name'], PATHINFO_EXTENSION);
    $filename = 'proof_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($proof_document['tmp_name'], $file_path)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO user_applications 
            (full_name, email, phone, password_hash, city_id, metro_area_id, 
             address_detail, proof_document_path, document_type, occupation, motivation) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $full_name, $email, $phone, $hashedPassword, $city_id, 
            $metro_area_id, $address_detail, 'uploads/proof_documents/' . $filename, 
            $document_type, $occupation, $motivation
        ])) {
            // Send confirmation email
            sendApplicationConfirmationEmail($email, $full_name);
            
            $response['success'] = true;
            $response['message'] = 'Application submitted successfully. We will review your application and email you within 2-3 business days.';
            $response['data'] = [
                'application_id' => $pdo->lastInsertId(),
                'email' => $email,
                'full_name' => $full_name
            ];
        } else {
            $response['message'] = 'Failed to submit application. Please try again.';
            unlink($file_path); // Remove uploaded file on database error
        }
    } else {
        $response['message'] = 'Failed to upload document. Please try again.';
    }
    
} catch (Exception $e) {
    $response['message'] = 'An error occurred during registration';
    error_log("Registration error: " . $e->getMessage());
}

echo json_encode($response);
?>
