<!DOCTYPE html>
<html>
<head>
    <title>Password Changed</title>
</head>
<body>
    <p>Hey {{ $username }},</p>

    <p>Your password was just updated successfully. Your new password looks something like this:  
       <strong>{{ $maskedPassword }}</strong> (Don't worry, we can't see it either! 😉)</p>

    <p><strong>For your security:</strong></p>
    <ul>
        <li>✅ If you didn’t change your password, reset it immediately.</li>
        <li>✅ Never share your password with anyone (even us!).</li>
        <li>✅ Consider using a password manager for extra security.</li>
    </ul>

    <p>Stay safe,  
    <br>The Security Team 🚀</p>
</body>
</html>
