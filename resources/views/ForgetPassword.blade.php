<!DOCTYPE html>
<html>
<head>
    <title>Password Reset OTP</title>
</head>
<body>
    <p>Dear {{ $username }},</p>
    <p>You have requested to reset your password.</p>
    <p>Your OTP for password reset is: <strong>{{ $otp }}</strong></p>
    <p>This OTP is valid for 2 minutes.</p>
    <p>If you did not request this, please ignore this email.</p>
</body>
</html>
