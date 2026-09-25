<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

function finish(int $status, bool $success, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    finish(405, false, 'Method not allowed.');
}

// Hidden field: genuine visitors never fill this in.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    finish(200, true, 'Thanks! Your enquiry has been received.');
}

function clean_field(string $value, int $maximumLength): string
{
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maximumLength, 'UTF-8')
        : substr($value, 0, $maximumLength);
}

$name = clean_field((string) ($_POST['name'] ?? ''), 100);
$phone = clean_field((string) ($_POST['phone'] ?? ''), 30);
$suburb = clean_field((string) ($_POST['suburb'] ?? ''), 80);
$inquiry = clean_field((string) ($_POST['inquiry'] ?? ''), 120);

if ($name === '' || $phone === '' || $suburb === '' || $inquiry === '') {
    finish(422, false, 'Please complete all required fields.');
}

if (!preg_match('/^[0-9+() .-]{6,30}$/', $phone)) {
    finish(422, false, 'Please enter a valid phone or WhatsApp number.');
}

$allowedSuburbs = ['Hampton Park', 'Lynbrook', 'Lyndhurst', 'Other'];
if (!in_array($suburb, $allowedSuburbs, true)) {
    finish(422, false, 'Please select a valid location.');
}

$recipient = 'sanjeev@ezykeys.com.au';
$subject = 'Ezy Keys Website Enquiry - ' . $inquiry;
$receivedAt = date('j M Y, g:i a T');
$visitorIp = clean_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'Unavailable'), 45);

$message = implode("\r\n", [
    'New enquiry from the Ezy Keys website',
    '',
    'Name: ' . $name,
    'Call / WhatsApp Number: ' . $phone,
    'Location: ' . $suburb,
    'Inquiry Option: ' . $inquiry,
    '',
    'Received: ' . $receivedAt,
    'Visitor IP: ' . $visitorIp,
]);

$headers = implode("\r\n", [
    'From: Ezy Keys Website <no-reply@ezykeys.com.au>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: PHP/' . PHP_VERSION,
]);

if (!mail($recipient, $subject, $message, $headers)) {
    error_log('Ezy Keys enquiry form: mail() failed.');
    finish(500, false, 'Your enquiry could not be sent. Please call 0417 545 545.');
}

finish(200, true, 'Thanks! Your enquiry has been sent. We will contact you shortly.');
