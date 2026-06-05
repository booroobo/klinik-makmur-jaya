export const VERIFICATION_SENT_MESSAGE = 'Registrasi berhasil. Email verifikasi telah dikirim.'

export const resendVerificationEmail = async (client, email) => {
  const response = await client.post('/resend-verification-email', { email })
  return response.data.message
}

export const registrationSuccessMessage = (response) => response?.message || VERIFICATION_SENT_MESSAGE
