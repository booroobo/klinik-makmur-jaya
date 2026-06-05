export const validateRegistration = (form) => {
  const errors = {}

  if (form.name.trim().length < 3) errors.name = 'Nama lengkap minimal 3 karakter.'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) errors.email = 'Format email tidak valid.'
  if (!/^[0-9]{10,20}$/.test(form.phone)) errors.phone = 'Telepon harus terdiri dari 10-20 digit angka.'
  if (!form.address.trim()) errors.address = 'Alamat wajib diisi.'
  if (form.password.length < 8) errors.password = 'Password minimal 8 karakter.'
  if (form.password !== form.password_confirmation) errors.password_confirmation = 'Konfirmasi password tidak sama.'

  return errors
}
