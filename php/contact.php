<?php
// Contact form handler. Set $to to your real address. Requires working mail() on the host.
$to = "hiambartolome@gmail.com";
if ($_SERVER["REQUEST_METHOD"] !== "POST") { http_response_code(405); exit("Method not allowed."); }
if (!empty($_POST["website_url"])) { header("Location: page2.html"); exit; }   // honeypot tripped

$name    = trim(strip_tags($_POST["name"] ?? ""));
$email   = trim($_POST["email"] ?? "");
$subject = trim(strip_tags($_POST["subject"] ?? ""));
$message = trim(strip_tags($_POST["message"] ?? ""));

if ($name === "" || $message === "" || $subject === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); exit("Please go back and fill in all fields with a valid email.");
}
$clean = fn($v) => str_replace(["\r", "\n"], " ", $v);   // block header injection
$headers = "From: no-reply@" . ($_SERVER["SERVER_NAME"] ?? "localhost") . "\r\nReply-To: " . $clean($email);
$ok = mail($to, "Portfolio: " . $clean($subject), "From: $name <$email>\n\n$message", $headers);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Message</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <main>
        <section class="Title-Card">
            <h1><?= $ok ? "THANK YOU!" : "OOPS" ?></h1>
            <h2><?= $ok ? "Your message was sent." : "The message could not be sent. Please try again later." ?></h2>
        <div class="Hero-Actions">
            <a class="Btn" href="page2.html">Back</a>
        </div>
        </section>
    </main>
</body>

</html>