<?php
require_once dirname(__DIR__) . '/config/config.php';

use PHPMailer\PHPMailer\PHPMailer;

function sendResiProEmail($to, $toName, $subject, $htmlBody, $altBody = '') {
    $projectRoot = dirname(__DIR__);

    $exceptionFile = $projectRoot . '/PHPMailer/src/Exception.php';
    $phpMailerFile = $projectRoot . '/PHPMailer/src/PHPMailer.php';
    $smtpFile     = $projectRoot . '/PHPMailer/src/SMTP.php';
    $mailConfig   = $projectRoot . '/config/mail.php';

    if (!file_exists($exceptionFile) || !file_exists($phpMailerFile) || !file_exists($smtpFile)) {
        throw new \RuntimeException(
            'PHPMailer is not installed. Put the PHPMailer folder inside the Apartment project folder.'
        );
    }

    if (!file_exists($mailConfig)) {
        throw new \RuntimeException(
            'Missing config/mail.php. Add your Gmail SMTP settings first.'
        );
    }

    require_once $exceptionFile;
    require_once $phpMailerFile;
    require_once $smtpFile;
    require_once $mailConfig;

    if (!defined('RESIPRO_SMTP_USERNAME') || !defined('RESIPRO_SMTP_PASSWORD')) {
        throw new \RuntimeException(
            'Gmail SMTP credentials are missing in config/mail.php.'
        );
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = RESIPRO_SMTP_USERNAME;
    $mail->Password   = RESIPRO_SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ];
    $mail->CharSet = 'UTF-8';
    $mail->SMTPDebug = 0;

    $fromEmail = defined('RESIPRO_SMTP_FROM_EMAIL')
        ? RESIPRO_SMTP_FROM_EMAIL
        : RESIPRO_SMTP_USERNAME;
    $fromName = defined('RESIPRO_SMTP_FROM_NAME')
        ? RESIPRO_SMTP_FROM_NAME
        : 'JLD Apartment Management';

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($to, $toName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;
    $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($htmlBody);

    $mail->send();
    return true;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function ensurePasswordResetsTable($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(100) NOT NULL,
            `code` VARCHAR(100) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `expires_at` DATETIME NOT NULL,
            INDEX (`email`),
            INDEX (`code`)
        ) ENGINE=InnoDB");

        // Upgrade column length if table existed previously with shorter code length
        $pdo->exec("ALTER TABLE `password_resets` MODIFY `code` VARCHAR(100) NOT NULL");
    } catch (Exception $e) {}
}

if ($action === 'approve_landlord') {
    $token = trim($_GET['token'] ?? '');
    $email = trim($_GET['email'] ?? '');

    if (empty($token) || empty($email)) {
        setFlash('danger', 'Invalid or missing approval link parameters.');
        redirect(BASE_URL . 'login.php');
    }

    try {
        ensurePasswordResetsTable($pdo);

        $shortToken = substr($token, 0, 10);
        $checkToken = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND (code = ? OR code = ?) ORDER BY id DESC LIMIT 1");
        $checkToken->execute([$email, $token, $shortToken]);
        $validToken = $checkToken->fetch();

        $uStmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
        $uStmt->execute([$email]);
        $user = $uStmt->fetch();

        if (!$user) {
            setFlash('danger', 'Landlord user record not found.');
            redirect(BASE_URL . 'login.php');
        }

        $pdo->beginTransaction();

        $actStmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $actStmt->execute([$user['id']]);

        $pCheck = $pdo->prepare("SELECT id FROM properties WHERE owner_id = ? LIMIT 1");
        $pCheck->execute([$user['id']]);
        if (!$pCheck->fetch()) {
            $propStmt = $pdo->prepare("INSERT INTO properties (owner_id, name, address) VALUES (?, ?, ?)");
            $propStmt->execute([$user['id'], $user['name'] . "'s Apartment", 'Main Property Address']);
        }

        if ($validToken) {
            $delToken = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
            $delToken->execute([$validToken['id']]);
        }

        $pdo->commit();

        logActivity('LANDLORD_APPROVED', "Super Admin approved landlord registration for {$user['name']} ({$user['email']}).");

        try {
            $subject = "Account Approved! Welcome to JLD Apartment";
            $msg = "
            <html>
            <body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px;'>
                <div style='max-width: 500px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0;'>
                    <h2 style='color: #16a34a; margin-top: 0;'>Account Approved!</h2>
                    <p>Hello <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                    <p>Great news! The Super Administrator has <strong>approved</strong> your Landlord account on <strong>JLD Apartment Management</strong>.</p>
                    <p>You can now sign in to your dashboard and manage your properties, units, and tenants.</p>
                    <div style='text-align: center; margin: 25px 0;'>
                        <a href='" . BASE_URL . "login.php' style='background-color: #2563eb; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Sign In to JLD Apartment</a>
                    </div>
                </div>
            </body>
            </html>";
            sendResiProEmail($user['email'], $user['name'], $subject, $msg, "Your JLD Apartment landlord account has been approved by the Super Admin! You can now log in at " . BASE_URL . "login.php");
        } catch (\Throwable $mailErr) {}

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Landlord Account Approved - JLD Apartment</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
                .card-box { background: #ffffff; border-radius: 16px; max-width: 500px; width: 100%; padding: 2.5rem; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.25); }
            </style>
        </head>
        <body>
            <div class="card-box">
                <div style="width: 72px; height: 72px; background: #ecfdf5; color: #059669; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; margin-bottom: 1.25rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="fw-bold text-dark mb-2">Landlord Account Approved!</h3>
                <p class="text-muted mb-4">
                    The registration request for <strong><?php echo htmlspecialchars($user['name']); ?></strong> (<code><?php echo htmlspecialchars($user['email']); ?></code>) has been successfully activated.
                </p>
                <div class="alert alert-success small text-start mb-4">
                    <i class="fas fa-envelope-circle-check me-1"></i> A confirmation notification has been sent to the Landlord. They can now log in to the JLD Apartment portal.
                </div>
                <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-primary px-4 py-2 fw-semibold" style="border-radius: 10px;">
                    <i class="fas fa-sign-in-alt me-1"></i> Go to Sign In
                </a>
            </div>
        </body>
        </html>
        <?php
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('danger', 'Error processing approval: ' . $e->getMessage());
        redirect(BASE_URL . 'login.php');
    }
}

if ($action === 'reject_landlord') {
    $token = trim($_GET['token'] ?? '');
    $email = trim($_GET['email'] ?? '');

    if (empty($token) || empty($email)) {
        setFlash('danger', 'Invalid or missing rejection link.');
        redirect(BASE_URL . 'login.php');
    }

    try {
        ensurePasswordResetsTable($pdo);

        $checkToken = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND code = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
        $checkToken->execute([$email, $token]);
        $validToken = $checkToken->fetch();

        if ($validToken) {

            $delUser = $pdo->prepare("DELETE FROM users WHERE email = ? AND role = 'admin' AND status = 'inactive'");
            $delUser->execute([$email]);

            $delToken = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
            $delToken->execute([$validToken['id']]);

            logActivity('LANDLORD_REJECTED', "Super Admin rejected landlord registration request for $email.");
            setFlash('warning', "Landlord registration request for <strong>" . htmlspecialchars($email) . "</strong> has been rejected and removed.");
        } else {
            setFlash('info', 'This request has already been processed or expired.');
        }

        redirect(BASE_URL . 'login.php');
    } catch (Exception $e) {
        setFlash('danger', 'Error processing rejection: ' . $e->getMessage());
        redirect(BASE_URL . 'login.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'login') {
        $usernameOrEmail = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($usernameOrEmail) || empty($password)) {
            setFlash('danger', 'Please enter your username/email and password.');
            redirect(BASE_URL . 'login.php');
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
            $user = $stmt->fetch();

            if (!$user) {
                setFlash('danger', 'Invalid username or password. Please verify your credentials.');
                redirect(BASE_URL . 'login.php');
            }

            if ($user['status'] === 'inactive' && $user['role'] === 'admin') {
                setFlash('warning', 'Your Landlord account is currently pending Super Administrator approval via Gmail. You will be able to sign in once approved.');
                redirect(BASE_URL . 'login.php');
            } elseif ($user['status'] !== 'active') {
                setFlash('danger', 'Your account is inactive or suspended. Please contact the system administrator.');
                redirect(BASE_URL . 'login.php');
            }

            $isValidPassword = false;

            if (password_verify($password, $user['password'])) {
                $isValidPassword = true;
            } elseif ($password === $user['password']) {

                $isValidPassword = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $user['id']]);
            }

            if ($isValidPassword) {

                $_SESSION['user_id']       = $user['id'];
                $_SESSION['user_name']     = $user['name'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_email']    = $user['email'];
                $_SESSION['user_role']     = $user['role'];
                $_SESSION['user_phone']    = $user['phone'];
                $_SESSION['user_avatar']   = $user['avatar'];

                logActivity('LOGIN', 'User ' . $user['name'] . ' (' . $user['role'] . ') logged in.');


                if ($user['role'] === 'super_admin') {
                    redirect(BASE_URL . 'views/super_admin/dashboard.php');
                } elseif ($user['role'] === 'admin') {
                    redirect(BASE_URL . 'views/admin/dashboard.php');
                } else {
                    redirect(BASE_URL . 'views/tenant/dashboard.php');
                }
            } else {
                setFlash('danger', 'Invalid username or password. Please verify your credentials.');
                redirect(BASE_URL . 'login.php');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Authentication error: ' . $e->getMessage());
            redirect(BASE_URL . 'login.php');
        }
    }

    if ($action === 'request_admin_otp') {
        header('Content-Type: application/json');
        $applicantName  = trim($_POST['applicant_name'] ?? 'Landlord Applicant');
        $applicantEmail = trim($_POST['applicant_email'] ?? '');

        if (empty($applicantEmail)) {
            echo json_encode(['success' => false, 'message' => 'Please enter your email address first.']);
            exit;
        }

        try {
            ensurePasswordResetsTable($pdo);

            $saStmt = $pdo->query("SELECT email, name FROM users WHERE role = 'super_admin' LIMIT 1");
            $superAdmin = $saStmt->fetch();
            $superAdminEmail = $superAdmin['email'] ?? 'superadmin@jldapartment.ph';
            $superAdminName  = $superAdmin['name'] ?? 'Super Administrator';

            $adminOtpCode = sprintf('%06d', mt_rand(100000, 999999));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->execute([$superAdminEmail]);

            $ins = $pdo->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$superAdminEmail, $adminOtpCode, $expiresAt]);

            $parts = explode('@', $superAdminEmail);
            $namePart = $parts[0];
            $domainPart = $parts[1] ?? '';
            $maskedSuperAdmin = (strlen($namePart) > 2) ? substr($namePart, 0, 1) . str_repeat('*', strlen($namePart) - 2) . substr($namePart, -1) . '@' . $domainPart : $superAdminEmail;

            $subject = "[APPROVAL CODE: $adminOtpCode] Landlord Registration Request - JLD Apartment";
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px;'>
                <div style='max-width: 520px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>
                    <h2 style='color: #2563eb; margin-top: 0;'>Landlord Registration Request</h2>
                    <p>Hello <strong>$superAdminName</strong>,</p>
                    <p>A new applicant is requesting authorization to register as a <strong>Property Owner / Landlord</strong> on JLD Apartment:</p>
                    <div style='background: #f1f5f9; padding: 12px 16px; border-radius: 8px; margin: 15px 0;'>
                        <p style='margin: 3px 0;'><strong>Applicant Name:</strong> " . htmlspecialchars($applicantName) . "</p>
                        <p style='margin: 3px 0;'><strong>Applicant Email:</strong> " . htmlspecialchars($applicantEmail) . "</p>
                    </div>
                    <p>If you approve this registration, provide the <strong>6-digit authorization code</strong> below to the applicant:</p>
                    <div style='background: #eff6ff; border: 2px dashed #2563eb; border-radius: 10px; padding: 18px; text-align: center; margin: 20px 0;'>
                        <span style='font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #1d4ed8; font-family: monospace;'>$adminOtpCode</span>
                    </div>
                    <p style='color: #64748b; font-size: 12px;'>This authorization code is valid for <strong>30 minutes</strong>. If you do not approve this applicant, you can ignore this email.</p>
                </div>
            </body>
            </html>";

            sendResiProEmail(
                $superAdminEmail,
                $superAdminName,
                $subject,
                $message,
                "JLD Apartment landlord registration approval code: $adminOtpCode. Provide this code to $applicantName ($applicantEmail) if approved."
            );

            logActivity('SUPER_ADMIN_CODE_REQUESTED', "Landlord approval code requested for $applicantName ($applicantEmail).");

            echo json_encode([
                'success' => true,
                'message' => "6-Digit Approval Code sent to Super Admin's Gmail (<strong>" . htmlspecialchars($maskedSuperAdmin) . "</strong>)!"
            ]);
            exit;
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'register') {
        $name      = trim($_POST['name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $role      = trim($_POST['role'] ?? 'tenant');
        $adminCode = trim($_POST['admin_code'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if (!in_array($role, ['tenant', 'admin'], true)) {
            setFlash('danger', 'Invalid account type selected.');
            redirect(BASE_URL . 'register.php');
        }

        if ($name === '' || $username === '' || $email === '' || $phone === '' || $password === '') {
            setFlash('danger', 'Please complete all required fields before creating your account.');
            redirect(BASE_URL . 'register.php');
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            setFlash('danger', 'Please enter a valid full name (2–100 characters).');
            redirect(BASE_URL . 'register.php');
        }

        if (!preg_match('/^[\p{L}\p{M} .\-\'’]+$/u', $name)) {
            setFlash('danger', 'Full Name contains invalid characters. Please use letters, spaces, periods, or hyphens only.');
            redirect(BASE_URL . 'register.php');
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            setFlash('danger', 'Username must be 3–50 characters and may contain only letters, numbers, dots, underscores, or hyphens.');
            redirect(BASE_URL . 'register.php');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            setFlash('danger', 'Please enter a valid email address.');
            redirect(BASE_URL . 'register.php');
        }

        if (!preg_match('/^[0-9]{11}$/', $phone)) {
            setFlash('danger', 'Contact number must be exactly 11 numeric digits (e.g. 09171234567) and cannot contain letters.');
            redirect(BASE_URL . 'register.php');
        }

        if ($password !== $confirm) {
            setFlash('danger', 'Passwords do not match. No account was created.');
            redirect(BASE_URL . 'register.php');
        }

        if (strlen($password) < 6) {
            setFlash('danger', 'Password must be at least 6 characters in length. No account was created.');
            redirect(BASE_URL . 'register.php');
        }

        try {

            $dup = $pdo->prepare("SELECT id, name, username, email, phone, role FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) OR LOWER(name) = LOWER(?) OR phone = ? LIMIT 1");
            $dup->execute([$username, $email, $name, $phone]);
            $existing = $dup->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if (strcasecmp($existing['username'], $username) === 0) {
                    setFlash('danger', 'Username "' . htmlspecialchars($username) . '" is already in use. Please choose another username.');
                } elseif (strcasecmp($existing['email'], $email) === 0) {
                    setFlash('danger', 'This email address is already registered. Please use another email.');
                } elseif (strcasecmp($existing['name'], $name) === 0) {
                    setFlash('danger', 'An account with the name "' . htmlspecialchars($name) . '" already exists. Please check your name.');
                } else {
                    setFlash('danger', 'This phone number is already registered. Please use another phone number.');
                }
                redirect(BASE_URL . 'register.php');
            }

            ensurePasswordResetsTable($pdo);

            $validReset = null;
            if ($role === 'admin') {
                if (!preg_match('/^\d{6}$/', $adminCode)) {
                    setFlash('danger', 'A valid 6-digit Super Admin Authorization Code is required. No account was created.');
                    redirect(BASE_URL . 'register.php');
                }

                $saStmt = $pdo->query("SELECT email FROM users WHERE role = 'super_admin' LIMIT 1");
                $superAdminEmail = $saStmt->fetchColumn() ?: 'superadmin@jldapartment.ph';

                $checkCode = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND code = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
                $checkCode->execute([$superAdminEmail, $adminCode]);
                $validReset = $checkCode->fetch(PDO::FETCH_ASSOC);

                if (!$validReset) {
                    setFlash('danger', 'Invalid or expired Super Admin Authorization Code. No account was created.');
                    redirect(BASE_URL . 'register.php');
                }
            }

            $pdo->beginTransaction();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            if ($hashedPassword === false) {
                throw new RuntimeException('Unable to secure the password.');
            }

            $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role, phone, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $username, $email, $hashedPassword, $role, $phone]);
            $newUserId = (int)$pdo->lastInsertId();

            if ($role === 'admin') {

                $propStmt = $pdo->prepare("INSERT INTO properties (owner_id, name, address) VALUES (?, ?, ?)");
                $propStmt->execute([$newUserId, $name . "'s Apartment", 'Main Property Address']);

                if ($validReset) {
                    $delCode = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
                    $delCode->execute([$validReset['id']]);
                }
            } else {

                $nameParts = preg_split('/\s+/', trim($name));
                $firstName = $nameParts[0] ?? 'Tenant';
                $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';
                $leaseStart = date('Y-m-d');
                $leaseEnd = date('Y-m-d', strtotime('+1 year'));

                $tenantStmt = $pdo->prepare("INSERT INTO tenants (user_id, unit_id, first_name, last_name, email, phone, lease_start, lease_end, status, notes) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 'active', ?)");
                $tenantStmt->execute([
                    $newUserId,
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $leaseStart,
                    $leaseEnd,
                    'Automatically created from tenant account registration; unit assignment pending.'
                ]);
            }


            $pdo->commit();

            logActivity('USER_REGISTERED', "New account registered: $name ($username, $role)" . ($role === 'admin' ? " [Authorized by Super Admin Code]" : ""));
            setFlash('success', 'Your ' . ($role === 'admin' ? 'Landlord' : 'Tenant') . ' account has been created successfully! You may now sign in.');
            redirect(BASE_URL . 'login.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ((string)$e->getCode() === '23000') {
                $msg = strtolower($e->getMessage());
                if (strpos($msg, 'username') !== false) {
                    setFlash('danger', 'That username is already in use. No account was created.');
                } elseif (strpos($msg, 'email') !== false) {
                    setFlash('danger', 'That email address is already registered. No account was created.');
                } else {
                    setFlash('danger', 'Some account information is already in use. No account was created.');
                }
            } else {
                setFlash('danger', 'Registration could not be completed. No account was created. Please check the information and try again.');
            }
            redirect(BASE_URL . 'register.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', 'Registration could not be completed. No account was created. Please try again.');
            redirect(BASE_URL . 'register.php');
        }
    }

    if ($action === 'send_otp') {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('danger', 'Please provide a valid registered email address.');
            redirect(BASE_URL . 'forgot_password.php');
        }

        try {
            ensurePasswordResetsTable($pdo);

            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                setFlash('danger', 'No active account found with email: ' . htmlspecialchars($email));
                redirect(BASE_URL . 'forgot_password.php');
            }

            $otpCode = sprintf('%06d', mt_rand(100000, 999999));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->execute([$email]);

            $ins = $pdo->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$email, $otpCode, $expiresAt]);

            $subject = "Your Verification Code: $otpCode - JLD Apartment Management";
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px;'>
                <div style='max-width: 500px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0;'>
                    <h2 style='color: #2563eb; margin-top: 0;'>JLD Apartment Password Reset</h2>
                    <p>Hello <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                    <p>We received a request to reset your password. Please use the 6-digit verification code below:</p>
                    <div style='background: #eff6ff; border: 2px dashed #2563eb; border-radius: 8px; padding: 18px; text-align: center; margin: 20px 0;'>
                        <span style='font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #1d4ed8; font-family: monospace;'>$otpCode</span>
                    </div>
                    <p style='color: #64748b; font-size: 13px;'>This code is valid for <strong>15 minutes</strong>. If you did not request this, please ignore this email.</p>
                </div>
            </body>
            </html>";

            sendResiProEmail(
                $email,
                $user['name'],
                $subject,
                $message,
                "Your JLD Apartment verification code is $otpCode. It expires in 15 minutes."
            );

            $_SESSION['pending_reset_email'] = $email;
            logActivity('OTP_REQUESTED', "Password reset OTP requested for $email.");

            setFlash('success', "A 6-digit verification code has been sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox and Spam folder.");
            redirect(BASE_URL . 'verify_otp.php?email=' . urlencode($email));
        } catch (\Throwable $e) {

            try {
                $cleanup = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $cleanup->execute([$email]);
            } catch (\Throwable $cleanupError) {
  
            }

            setFlash('danger', 'Could not send the verification email. Please check the Gmail SMTP settings. Details: ' . htmlspecialchars($e->getMessage()));
            redirect(BASE_URL . 'forgot_password.php');
        }
    }

    if ($action === 'verify_otp') {
        $email = trim($_POST['email'] ?? ($_SESSION['pending_reset_email'] ?? ''));
        $otp   = trim($_POST['otp_code'] ?? '');

        if (empty($email) || empty($otp)) {
            setFlash('danger', 'Please enter your verification code.');
            redirect(BASE_URL . 'verify_otp.php');
        }

        try {
            ensurePasswordResetsTable($pdo);

            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND code = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
            $stmt->execute([$email, $otp]);
            $resetRecord = $stmt->fetch();

            if ($resetRecord) {

                $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $del->execute([$email]);

                $_SESSION['verified_reset_email'] = $email;
                unset($_SESSION['pending_reset_email']);

                setFlash('success', 'Verification code confirmed! Please create your new password below.');
                redirect(BASE_URL . 'reset_password.php');
            } else {
                setFlash('danger', 'Invalid or expired verification code. Please check your Gmail or request a new code.');
                redirect(BASE_URL . 'verify_otp.php?email=' . urlencode($email));
            }
        } catch (Exception $e) {
            setFlash('danger', 'Verification error: ' . $e->getMessage());
            redirect(BASE_URL . 'verify_otp.php?email=' . urlencode($email));
        }
    }

    if ($action === 'reset_password') {
        $email   = $_SESSION['verified_reset_email'] ?? '';
        $newPass = trim($_POST['new_password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');

        if (empty($email)) {
            setFlash('danger', 'Session expired. Please start the password reset process again.');
            redirect(BASE_URL . 'forgot_password.php');
        }

        if (empty($newPass) || empty($confirm)) {
            setFlash('danger', 'Please fill in both password fields.');
            redirect(BASE_URL . 'reset_password.php');
        }

        if ($newPass !== $confirm) {
            setFlash('danger', 'Passwords do not match.');
            redirect(BASE_URL . 'reset_password.php');
        }

        if (strlen($newPass) < 6) {
            setFlash('danger', 'Password must be at least 6 characters in length.');
            redirect(BASE_URL . 'reset_password.php');
        }

        try {
            $hashedPassword = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $email]);

            unset($_SESSION['verified_reset_email']);
            unset($_SESSION['pending_reset_email']);

            logActivity('PASSWORD_RESET', "Password successfully reset for $email.");
            setFlash('success', 'Your password has been successfully reset! You can now log in with your new password.');
            redirect(BASE_URL . 'login.php');
        } catch (Exception $e) {
            setFlash('danger', 'Error updating password: ' . $e->getMessage());
            redirect(BASE_URL . 'reset_password.php');
        }
    }

    if ($action === 'send_profile_otp') {
        header('Content-Type: application/json');
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
            exit;
        }

        try {
            ensurePasswordResetsTable($pdo);
            $userId = $_SESSION['user_id'];

            $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || empty($user['email'])) {
                echo json_encode(['success' => false, 'message' => 'No active user or email found for your account.']);
                exit;
            }

            $email = $user['email'];
            $name = $user['name'];


            $otpCode = sprintf('%06d', mt_rand(100000, 999999));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->execute([$email]);

            $ins = $pdo->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$email, $otpCode, $expiresAt]);

            $parts = explode('@', $email);
            $namePart = $parts[0];
            $domainPart = $parts[1] ?? '';
            $maskedEmail = (strlen($namePart) > 2) ? substr($namePart, 0, 2) . str_repeat('*', strlen($namePart) - 2) . '@' . $domainPart : $email;

            $subject = "Security Verification Code: $otpCode - Password Change Authentication";
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px;'>
                <div style='max-width: 500px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0;'>
                    <h2 style='color: #2563eb; margin-top: 0;'>Password Change Verification</h2>
                    <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p>We received a request to update your account password on <strong>JLD Apartment</strong>. Please use the 6-digit authentication code below to complete your password update:</p>
                    <div style='background: #eff6ff; border: 2px dashed #2563eb; border-radius: 8px; padding: 18px; text-align: center; margin: 20px 0;'>
                        <span style='font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #1d4ed8; font-family: monospace;'>$otpCode</span>
                    </div>
                    <p style='color: #64748b; font-size: 13px;'>This authentication code is valid for <strong>15 minutes</strong>. If you did not initiate this request, please contact the administrator immediately.</p>
                </div>
            </body>
            </html>";

            sendResiProEmail(
                $email,
                $name,
                $subject,
                $message,
                "Your JLD Apartment password change authentication code is $otpCode. This code expires in 15 minutes."
            );

            logActivity('PASSWORD_OTP_REQUESTED', "Password change verification OTP requested by user ID #$userId ($email).");

            echo json_encode([
                'success' => true,
                'message' => "Verification code sent to your email (<strong>" . htmlspecialchars($maskedEmail) . "</strong>)."
            ]);
            exit;
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send verification email: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    if ($action === 'upload_avatar') {
        requireRole(['super_admin', 'admin', 'tenant']);
        $userId = intval($_SESSION['user_id'] ?? 0);
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

        if ($userId <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
                exit;
            }
            setFlash('danger', 'User not authenticated.');
            redirect(BASE_URL . 'views/shared/profile.php');
        }

        try {
            $userStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
            $userStmt->execute([$userId]);
            $existing = $userStmt->fetch();
            if (!$existing) {
                throw new Exception('User account not found.');
            }

            $avatarDir = ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR;
            if (!is_dir($avatarDir) && !mkdir($avatarDir, 0755, true)) {
                throw new Exception('Unable to create the avatar upload directory.');
            }

            $filename = '';
            $destination = '';
            $relativePath = '';

            // Check if base64 cropped data was provided
            if (!empty($_POST['cropped_image_data']) && preg_match('/^data:image\/(jpeg|png|webp);base64,/', $_POST['cropped_image_data'], $matches)) {
                $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                $base64Data = substr($_POST['cropped_image_data'], strpos($_POST['cropped_image_data'], ',') + 1);
                $decoded = base64_decode($base64Data);

                if ($decoded === false || strlen($decoded) > 3 * 1024 * 1024) {
                    throw new Exception('Invalid cropped image data or image exceeds size limit.');
                }

                $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $destination = $avatarDir . $filename;
                $relativePath = 'assets/uploads/avatars/' . $filename;

                if (file_put_contents($destination, $decoded) === false) {
                    throw new Exception('Unable to save cropped image.');
                }
            } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                if ($file['size'] > 3 * 1024 * 1024) {
                    throw new Exception('Profile picture must be 3 MB or smaller.');
                }

                $imageInfo = @getimagesize($file['tmp_name']);
                if ($imageInfo === false) {
                    throw new Exception('Please upload a valid image file.');
                }

                $allowedMime = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];
                $mime = $imageInfo['mime'] ?? '';
                if (!isset($allowedMime[$mime])) {
                    throw new Exception('Only JPG, PNG, and WEBP profile pictures are allowed.');
                }

                $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowedMime[$mime];
                $destination = $avatarDir . $filename;
                $relativePath = 'assets/uploads/avatars/' . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new Exception('Unable to save the uploaded profile picture.');
                }
            } else {
                throw new Exception('Please select and crop a profile picture to upload.');
            }

            $update = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $update->execute([$relativePath, $userId]);
            $_SESSION['user_avatar'] = $relativePath;

            // Remove old uploaded avatar file if exists
            if (!empty($existing['avatar']) && strpos($existing['avatar'], 'assets/uploads/avatars/') === 0) {
                $oldFile = ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $existing['avatar']);
                if (is_file($oldFile) && $oldFile !== $destination) {
                    @unlink($oldFile);
                }
            }

            logActivity('AVATAR_UPDATED', 'Updated profile picture with crop/center adjustment.');

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Profile picture cropped and updated successfully.',
                    'avatar_url' => BASE_URL . $relativePath
                ]);
                exit;
            }

            setFlash('success', 'Your profile picture has been adjusted and updated successfully.');
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            setFlash('danger', 'Unable to update profile picture: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/shared/profile.php');
    }

    if ($action === 'delete_my_account') {
        requireRole(['admin', 'tenant']);
        $userId = intval($_SESSION['user_id'] ?? 0);
        $currentPass = trim($_POST['current_password'] ?? '');

        if ($userId <= 0 || empty($currentPass)) {
            setFlash('danger', 'Please enter your current password to delete your account.');
            redirect(BASE_URL . 'views/shared/profile.php');
        }

        try {
            $stmt = $pdo->prepare("SELECT id, name, role, password, avatar FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $account = $stmt->fetch();

            if (!$account) {
                setFlash('danger', 'Account not found.');
                redirect(BASE_URL . 'login.php');
            }

            if (!password_verify($currentPass, $account['password']) && $currentPass !== $account['password']) {
                setFlash('danger', 'Current password is incorrect. Your account was not deleted.');
                redirect(BASE_URL . 'views/shared/profile.php');
            }

            $pdo->beginTransaction();
            $del = $pdo->prepare("DELETE FROM users WHERE id = ? AND role IN ('admin', 'tenant')");
            $del->execute([$userId]);
            if ($del->rowCount() !== 1) {
                throw new Exception('The account could not be deleted.');
            }
            $pdo->commit();

            if (!empty($account['avatar']) && strpos($account['avatar'], 'assets/uploads/avatars/') === 0) {
                $avatarFile = ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $account['avatar']);
                if (is_file($avatarFile)) @unlink($avatarFile);
            }

            logActivity('ACCOUNT_DELETED', "User deleted their own {$account['role']} account: {$account['name']}.");
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            session_start();
            setFlash('success', 'Your account has been permanently deleted.');
            redirect(BASE_URL . 'login.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            setFlash('danger', 'Unable to delete your account: ' . $e->getMessage());
            redirect(BASE_URL . 'views/shared/profile.php');
        }
    }

    if ($action === 'update_profile') {
        requireRole(['super_admin', 'admin', 'tenant']);
        $userId = $_SESSION['user_id'];
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $currentPass = trim($_POST['current_password'] ?? '');
        $otpCode = trim($_POST['otp_code'] ?? '');
        $newPass = trim($_POST['new_password'] ?? '');
        $confirmPass = trim($_POST['confirm_password'] ?? '');

        if (empty($name) || empty($email)) {
            setFlash('danger', 'Name and email are required fields.');
            redirect(BASE_URL . 'views/shared/profile.php');
        }

        try {
            ensurePasswordResetsTable($pdo);

            $userCheck = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $userCheck->execute([$userId]);
            $currentUserData = $userCheck->fetch();

            if (!$currentUserData) {
                setFlash('danger', 'User account not found.');
                redirect(BASE_URL . 'login.php');
            }

            if ($email !== $currentUserData['email']) {
                $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $emailCheck->execute([$email, $userId]);
                if ($emailCheck->fetch()) {
                    setFlash('danger', 'The email address is already in use by another account.');
                    redirect(BASE_URL . 'views/shared/profile.php');
                }
            }

            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $userId]);
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_phone'] = $phone;

            $isChangingPassword = !empty($newPass) || !empty($confirmPass) || !empty($currentPass) || !empty($otpCode);

            if ($isChangingPassword) {
                if (empty($currentPass)) {
                    setFlash('danger', 'Current password is required to authorize password change.');
                    redirect(BASE_URL . 'views/shared/profile.php');
                }

                $storedHash = $currentUserData['password'];
                if (!password_verify($currentPass, $storedHash) && $currentPass !== $storedHash) {
                    setFlash('danger', 'Current password is incorrect.');
                    redirect(BASE_URL . 'views/shared/profile.php');
                }

                if (empty($otpCode)) {
                    setFlash('danger', 'Email verification code (OTP) is required to authenticate your password change. Please click "Send Verification Code".');
                    redirect(BASE_URL . 'views/shared/profile.php');
                }

                $checkOtp = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND code = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
                $checkOtp->execute([$currentUserData['email'], $otpCode]);
                $validOtp = $checkOtp->fetch();

                if (!$validOtp) {
                    setFlash('danger', 'Invalid or expired email verification code (OTP). Please request a new code.');
                    redirect(BASE_URL . 'views/shared/profile.php');
                }

                if (empty($newPass) || empty($confirmPass)) {
                    setFlash('danger', 'New password and confirmation fields cannot be empty.');
                    redirect(BASE_URL . 'views/shared/profile.php');
                }

                if ($newPass !== $confirmPass) {
                    setFlash('danger', 'New password and confirmation do not match.');
                    redirect(BASE_URL . 'views/shared/profile.php');
                }

                if (strlen($newPass) < 6) {
                    setFlash('danger', 'New password must be at least 6 characters in length.');
                    redirect(BASE_URL . 'views/shared/profile.php');
                }

                $delOtp = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
                $delOtp->execute([$validOtp['id']]);

                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $passUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $passUpdate->execute([$newHash, $userId]);

                logActivity('PASSWORD_CHANGED', "User {$currentUserData['name']} successfully changed password using email OTP authentication.");
                setFlash('success', 'Profile and password have been successfully updated with security authentication!');
                redirect(BASE_URL . 'views/shared/profile.php');
            }

            logActivity('PROFILE_UPDATED', 'Updated account settings.');
            setFlash('success', 'Your profile details have been successfully updated.');
            redirect(BASE_URL . 'views/shared/profile.php');
        } catch (Exception $e) {
            setFlash('danger', 'Error updating profile: ' . $e->getMessage());
            redirect(BASE_URL . 'views/shared/profile.php');
        }
    }
}

redirect(BASE_URL . 'login.php');
