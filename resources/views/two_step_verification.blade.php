<!DOCTYPE html>
<html>
<head>
    <title>2-Step Verification</title>
</head>
<body>
    <h2>Hello {{ $username }},</h2>

    <p>We have received a login attempt for your account.</p>

    <p><strong>Your One-Time Password (OTP) is:</strong></p>
    <h1 style="color: #3490dc;">{{ $otp }}</h1>

    <p>This OTP is valid for <strong>5 minutes</strong>. Please do not share it with anyone.</p>

    <p>If you did not initiate this login, please secure your account immediately.</p>

    <br>
    <p>Thank you,<br>Your Application Team</p>
</body>
</html>
