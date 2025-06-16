# Enable 2FA (using authenticated token)
curl -X POST http://10.10.114.22:8000/api/auth/two-factor/enable \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"

# Confirm 2FA with code from authenticator app
curl -X POST http://10.10.114.22:8000/api/auth/two-factor/confirm \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "code": "123456"
  }'