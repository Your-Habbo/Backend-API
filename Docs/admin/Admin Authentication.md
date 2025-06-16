# Login as admin (seeded user)
curl -X POST http://10.10.114.22:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "login": "admin@example.com",
    "password": "password"
  }'

# Access admin endpoint
curl -X GET http://10.10.114.22:8000/api/admin/users \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE" \
  -H "Accept: application/json"