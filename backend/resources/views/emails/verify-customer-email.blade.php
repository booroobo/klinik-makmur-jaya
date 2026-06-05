<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Verifikasi Email</title></head>
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.6">
    <h1>Verifikasi Email Klinik Makmur Jaya</h1>
    <p>Halo {{ $user->name }},</p>
    <p>Klik tombol berikut untuk memverifikasi alamat email Anda.</p>
    <p><a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 20px;background:#0f766e;color:#fff;text-decoration:none;border-radius:6px">Verifikasi Email</a></p>
    <p>Link berlaku selama {{ $expirationHours }} jam. Jika Anda tidak membuat akun ini, abaikan email ini.</p>
</body>
</html>
