# Create API key (using authenticated token)
curl -X POST http://10.10.114.22:8000/api/user/api-keys \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test API Key",
    "scopes": ["user:read", "user:write"]
  }'

# Use API key for requests
curl -X GET http://10.10.114.22:8000/api/api-v1/user \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -H "Accept: application/json"