<?php
// contact.php — PHPMailer + Gmail SMTP (App Password)
// ---------------------------------------------------
// 1) Enable 2-Step Verification on your Gmail
// 2) Create an App Password (Mail → Other)
// 3) Put the 16-char app password below (NOT your normal Gmail password)

require 'PHPMailer/PHPMailerAutoload.php';

$recipientEmail = 'bgcm35@gmail.com';        // where you receive leads (can be same Gmail)
$recipientName  = 'Turkiye Dental';
$fromEmail      = 'bgcm35@gmail.com';        // must be your Gmail when using Gmail SMTP
$fromName       = 'Turkiye Dental Website';

// Helper to get POST values safely
$get = function($key, $default = '') {
  return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
};

// Map to your form fields (required)
$name   = $get('name');
$email  = $get('email');
$phone  = $get('phone');
$treat  = $get('requested_treatment');

// Optional details
$fields = [
  'Chronic / Meds / HbA1c'                => $get('chronic'),
  'Oral pain / bleeding / gum disease'    => $get('oral_issues'),
  'Wobbly teeth'                           => $get('mobile_teeth'),
  'Missing teeth duration'                 => $get('missing_duration'),
  'Smoking'                                => $get('smoking'),
  'Full name for plan'                     => $get('legal_name'),
  'Age'                                    => $get('age'),
  'Travel dates'                           => $get('travel_dates'),
  'City'                                   => $get('city'),
  'Postcode'                               => $get('postcode'),
  'Departure airport'                      => $get('departure_airport'),
  'Medications & dose'                     => $get('medications'),
  'Medical conditions'                     => $get('medical_conditions'),
  'Allergies'                              => $get('allergies'),
  'Last GP appointment'                    => $get('last_gp'),
  'Last blood test'                        => $get('last_blood_test'),
  'Recent surgeries'                       => $get('surgeries'),
  'Insurance'                              => $get('insurance'),
];

// Basic validation
$errors = [];
if ($name === '')  $errors[] = 'Name is required.';
if ($phone === '') $errors[] = 'Phone is required.';
if ($treat === '') $errors[] = 'Requested treatment is required.';
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $errors[] = 'Email format is invalid.';
}
if ($errors) {
  echo '<div class="alert alert-danger" role="alert">'.implode(' ', $errors).'</div>';
  exit;
}

// Build HTML body
function rowCell($k, $v) {
  return "<tr><td style='background:#f7f9fb;width:40%;font-weight:600;border:1px solid #e8edf3;'>$k</td>"
       . "<td style='border:1px solid #e8edf3;'>$v</td></tr>";
}
$rows = [];
$rows[] = rowCell('Name', htmlspecialchars($name));
if ($email) $rows[] = rowCell('Email', htmlspecialchars($email));
$rows[] = rowCell('Phone', htmlspecialchars($phone));
$rows[] = rowCell('Requested Treatment', htmlspecialchars($treat));
foreach ($fields as $label => $val) {
  if ($val !== '') $rows[] = rowCell($label, nl2br(htmlspecialchars($val)));
}
$body = '<html><body>'
      . '<table rules="all" style="border:1px solid #666;border-collapse:collapse;width:100%;max-width:640px" cellpadding="10">'
      . implode('', $rows)
      . '</table>'
      . '</body></html>';

// Init PHPMailer
$mail = new PHPMailer();
$mail->isHTML(true);
$mail->CharSet = 'UTF-8';

// Gmail SMTP with App Password
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'bgcm35@gmail.com';
$mail->Password   = 'hcns lxkz zfwv cpkq'; // <-- paste Gmail App Password here
$mail->SMTPSecure = 'tls'; // 'tls' on 587 is typical; use 'ssl' with port 465 if needed
$mail->Port       = 587;

$mail->setFrom($fromEmail, $fromName);
$mail->addAddress($recipientEmail, $recipientName); // send to you
if (!empty($email)) {
  $mail->addReplyTo($email, $name); // replies go to patient
}
$mail->Subject = 'New Second-Opinion Request — ' . $treat . ' — ' . $name;
$mail->Body    = $body;

// Attach uploaded files (multiple)
if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
  $allowed = ['image/jpeg','image/png','image/webp','application/pdf','image/heic','image/heif'];
  for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
      $tmp  = $_FILES['files']['tmp_name'][$i];
      $nameFile = basename($_FILES['files']['name'][$i]);
      $type = @mime_content_type($tmp);
      if ($type === false) $type = 'application/octet-stream';
      if (in_array($type, $allowed, true)) {
        $mail->addAttachment($tmp, $nameFile);
      }
    }
  }
}

// Send
if (!$mail->send()) {
  echo '<div class="alert alert-danger" role="alert">Error: ' . htmlspecialchars($mail->ErrorInfo) . '</div>';
} else {
  echo '<div class="alert alert-success" role="alert">Thank you. We will contact you shortly.</div>';
}