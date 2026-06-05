# API Authentication

Base URL: `/api`

Seeder membuat empat akun awal dengan password `password`:

- `admin@example.com` role `admin`
- `apoteker@example.com` role `apoteker`
- `kasir@example.com` role `kasir`
- `pelanggan@example.com` role `pelanggan`

## Register Pelanggan

`POST /api/register`

Request:

```json
{
  "name": "Budi Santoso",
  "email": "budi@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "device_name": "web"
}
```

Response `201`:

```json
{
  "message": "Registrasi pelanggan berhasil.",
  "data": {
    "user": {
      "id": 5,
      "name": "Budi Santoso",
      "email": "budi@example.com",
      "role": "pelanggan",
      "email_verified_at": null,
      "created_at": "2026-06-05T08:00:00.000000Z",
      "updated_at": "2026-06-05T08:00:00.000000Z"
    },
    "token": "1|plain-text-token"
  }
}
```

## Login

`POST /api/login`

Request:

```json
{
  "email": "admin@example.com",
  "password": "password",
  "device_name": "web"
}
```

Response `200`:

```json
{
  "message": "Login berhasil.",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin Klinik",
      "email": "admin@example.com",
      "role": "admin",
      "email_verified_at": null,
      "created_at": "2026-06-05T08:00:00.000000Z",
      "updated_at": "2026-06-05T08:00:00.000000Z"
    },
    "token": "1|plain-text-token"
  }
}
```

## Current User

`GET /api/me`

Header token:

```http
Authorization: Bearer 1|plain-text-token
Accept: application/json
```

Response `200`:

```json
{
  "message": "Data user saat ini.",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin Klinik",
      "email": "admin@example.com",
      "role": "admin",
      "email_verified_at": null,
      "created_at": "2026-06-05T08:00:00.000000Z",
      "updated_at": "2026-06-05T08:00:00.000000Z"
    }
  }
}
```

## Logout

`POST /api/logout`

Response `200`:

```json
{
  "message": "Logout berhasil."
}
```

## Role Middleware

Contoh endpoint yang sudah dibatasi role:

- `GET /api/admin/dashboard` menggunakan `role:admin`
- `GET /api/apoteker/prescriptions` menggunakan `role:apoteker,admin`
- `GET /api/kasir/orders` menggunakan `role:kasir,admin`
- `GET /api/pelanggan/orders` menggunakan `role:pelanggan`

Response ketika role tidak sesuai `403`:

```json
{
  "message": "Anda tidak memiliki akses ke endpoint ini."
}
```
