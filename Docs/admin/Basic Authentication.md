# Register a new user
curl -X POST http://10.10.114.22:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "username": "testuser",
    "password": "password123",
    "password_confirmation": "password123",
    "terms": true
  }'

# Login
curl -X POST http://10.10.114.22:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "login": "test@example.com",
    "password": "password123"
  }'

# Use the returned token for authenticated requests
curl -X GET http://10.10.114.22:8000/api/auth/user \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"