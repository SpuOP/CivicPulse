<?php
/**
 * Email Functions for CivicPulse
 * Simple email system for sending Special IDs and notifications
 */

// Check if we're in local development mode
function isLocalDevelopment() {
    return ($_SERVER['HTTP_HOST'] === 'localhost' || 
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || 
            strpos($_SERVER['HTTP_HOST'], '.local') !== false);
}

// Show email notification for local development
function showLocalEmailNotification($email, $subject, $type = 'info') {
    $colors = [
        'success' => ['bg' => '#10b981', 'title' => '✅ EMAIL SENT'],
        'info' => ['bg' => '#7c3aed', 'title' => '📧 EMAIL SENT'], 
        'warning' => ['bg' => '#f59e0b', 'title' => '⚠️ EMAIL SENT'],
        'error' => ['bg' => '#ef4444', 'title' => '❌ EMAIL SENT']
    ];
    
    $color = $colors[$type] ?? $colors['info'];
    
    echo "<div style='position: fixed; top: 20px; right: 20px; z-index: 9999; background: white; border: 3px solid {$color['bg']}; border-radius: 12px; padding: 20px; max-width: 400px; box-shadow: 0 8px 25px rgba(0,0,0,0.2); font-family: Arial, sans-serif;'>";
    echo "<h6 style='color: {$color['bg']}; margin: 0 0 10px 0; font-size: 14px; font-weight: bold;'>{$color['title']} (Local Mode)</h6>";
    echo "<p style='margin: 5px 0; font-size: 12px; color: #333;'><strong>To:</strong> " . htmlspecialchars($email) . "</p>";
    echo "<p style='margin: 5px 0; font-size: 12px; color: #333;'><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>";
    echo "<p style='margin: 10px 0 5px 0; font-size: 11px; color: #666; font-style: italic;'>Email would be sent in production mode</p>";
    echo "<button onclick='this.parentElement.style.display=\"none\"' style='background: {$color['bg']}; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; float: right; font-size: 11px;'>Close</button>";
    echo "<div style='clear: both;'></div>";
    echo "</div>";
}

// Renderers (English-only) for email previews and sending
function renderSpecialIDEmailHtml($full_name, $special_id, $community_name) {
    return "
    <html><head><meta charset='UTF-8'><style>
    body{font-family:Arial,sans-serif;line-height:1.6;color:#333}
    .header{background:linear-gradient(135deg,#7c3aed,#8b5cf6);color:#fff;padding:20px;text-align:center}
    .content{padding:20px;background:#f8fafc}
    .special-id{background:#7c3aed;color:#fff;padding:15px;border-radius:8px;text-align:center;margin:20px 0}
    .footer{background:#374151;color:#fff;padding:15px;text-align:center;font-size:12px}
    </style></head><body>
            <div class='header'><h1>🗳️ CivicPulse</h1><h2>Welcome to Our Civic Community!</h2></div>
    <div class='content'>
      <h3>Dear " . htmlspecialchars($full_name) . ",</h3>
      <p><strong>🎉 Congratulations! Your application has been approved.</strong></p>
              <p>You are now a verified member of CivicPulse representing <strong>" . htmlspecialchars($community_name) . "</strong>.</p>
      <div class='special-id'>
        <h3>Your Special ID:</h3>
        <h1 style='font-size:32px;margin:10px 0;letter-spacing:3px;'>" . $special_id . "</h1>
        <p>Keep this ID safe and use it to login to CivicPulse.</p>
      </div>
      <h4>How to Login:</h4>
      <ol>
        <li>Go to: <a href='https://your-domain/auth/login.php'>CivicPulse Login</a></li>
        <li>Enter your Special ID: <strong>" . $special_id . "</strong></li>
        <li>Enter the password you created during application</li>
        <li>Click Login and start contributing!</li>
      </ol>
      <div style='background:#dbeafe;padding:15px;border-radius:8px;margin:20px 0;'>
        <h4>🔒 Security & Privacy</h4>
        <ul>
          <li>Your Special ID is unique and linked to your verified identity</li>
          <li>Never share your Special ID or password with others</li>
          <li>Your personal information is protected and will not be shared</li>
        </ul>
      </div>
      <p><strong>Thank you for joining our mission to improve communities!</strong></p>
      <hr><p><small>If you have any questions, reply to this email.</small></p>
    </div>
    <div class='footer'>&copy; 2024 CivicPulse - Civic Community Platform</div>
    </body></html>";
}

function renderRejectionEmailHtml($full_name, $reason = '') {
    return "
    <html><head><meta charset='UTF-8'><style>
    body{font-family:Arial,sans-serif;line-height:1.6;color:#333}
    .header{background:linear-gradient(135deg,#ef4444,#f87171);color:#fff;padding:20px;text-align:center}
    .content{padding:20px;background:#f8fafc}
    .footer{background:#374151;color:#fff;padding:15px;text-align:center;font-size:12px}
    </style></head><body>
    <div class='header'><h1>📧 CivicPulse</h1><h2>Application Status Update</h2></div>
    <div class='content'>
      <h3>Dear " . htmlspecialchars($full_name) . ",</h3>
      <p>Thank you for your interest in joining the CivicPulse civic community.</p>
      <p>After careful review, we are unable to approve your application at this time.</p>
      " . (!empty($reason) ? "<div style='background:#fef2f2;padding:15px;border-left:4px solid #ef4444;margin:20px 0;'><h4>Reason:</h4><p>" . htmlspecialchars($reason) . "</p></div>" : "") . "
      <h4>What You Can Do:</h4>
      <ul>
        <li>🔄 Reapply with updated information</li>
        <li>📞 Contact us if you have questions about the decision</li>
        <li>📋 Review requirements and ensure documentation is clear</li>
      </ul>
      <p><strong>We encourage you to reapply in the future.</strong></p>
      <hr><p><small>If you have questions, reply to this email.</small></p>
    </div>
    <div class='footer'>&copy; 2024 CivicPulse - Civic Community Platform</div>
    </body></html>";
}

function renderApplicationConfirmationEmailHtml($full_name) {
    return "
    <html><head><meta charset='UTF-8'><style>
    body{font-family:Arial,sans-serif;line-height:1.6;color:#333}
    .header{background:linear-gradient(135deg,#7c3aed,#8b5cf6);color:#fff;padding:20px;text-align:center}
    .content{padding:20px;background:#f8fafc}
    .footer{background:#374151;color:#fff;padding:15px;text-align:center;font-size:12px}
    </style></head><body>
    <div class='header'><h1>📋 CivicPulse</h1><h2>Application Confirmation</h2></div>
    <div class='content'>
      <h3>Dear " . htmlspecialchars($full_name) . ",</h3>
      <p><strong>✅ Your application has been successfully received.</strong></p>
      <h4>What Happens Next:</h4>
      <ol>
        <li>Review Process: our team will review your application</li>
        <li>Verification: we will verify your proof of residence and community details</li>
        <li>Decision: you will receive an email within 2–3 business days</li>
        <li>Special ID: if approved, you'll receive your unique Special ID for login</li>
      </ol>
      <p><strong>Thank you for your interest in joining CivicPulse!</strong></p>
      <hr><p><small>If you have any questions, please reply to this email.</small></p>
    </div>
    <div class='footer'>&copy; 2024 CivicPulse - Civic Community Platform</div>
    </body></html>";
}

function sendSpecialIDEmail($email, $full_name, $special_id, $community_name) {
    $subject = "CivicPulse - Your Application Approved";
    $message = renderSpecialIDEmailHtml($full_name, $special_id, $community_name);
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: CivicPulse <noreply@civicpulse.org>" . "\r\n";
    $headers .= "Reply-To: support@civicpulse.org" . "\r\n";
    
    // Check if we're in local development mode
    if (isLocalDevelopment()) {
        showLocalEmailNotification($email, $subject, 'success');
        return true; // Simulate successful sending
    }
    
    // In production, use a proper email service like PHPMailer, SendGrid, etc.
    // For now, using basic mail() function
    return mail($email, $subject, $message, $headers);
}

function sendRejectionEmail($email, $full_name, $reason = '') {
    $subject = "CivicPulse - Application Update";
    $message = renderRejectionEmailHtml($full_name, $reason);
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: CivicPulse <noreply@civicpulse.org>" . "\r\n";
    $headers .= "Reply-To: support@civicpulse.org" . "\r\n";
    
    // Check if we're in local development mode
    if (isLocalDevelopment()) {
        showLocalEmailNotification($email, $subject, 'error');
        return true; // Simulate successful sending
    }
    
    return mail($email, $subject, $message, $headers);
}

function sendApplicationConfirmationEmail($email, $full_name) {
    $subject = "Application Received - CivicPulse";
    $message = renderApplicationConfirmationEmailHtml($full_name);
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: CivicPulse <noreply@civicpulse.org>" . "\r\n";
    $headers .= "Reply-To: support@civicpulse.org" . "\r\n";
    
    // Check if we're in local development mode
    if (isLocalDevelopment()) {
        showLocalEmailNotification($email, $subject, 'info');
        return true; // Simulate successful sending
    }
    
    return mail($email, $subject, $message, $headers);
}
?>
