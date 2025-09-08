# Sky API — Auth & JWT Quick Reference

This guide shows how to log in, use the JWT token, and verify authorization.

## Endpoints

- Customer login: `POST /api/v1/customer/login`
- Customer token health: `GET /api/v1/customer/health` (requires JWT)
- Customer profile: `GET /api/v1/customer/profile` (requires JWT)

Registration (email code flow):
- Start: `POST /api/v1/registration/start`
- Confirm code: `POST /api/v1/registration/confirm`
- Resend code: `POST /api/v1/registration/resend-code`
- Finalize: `POST /api/v1/registration/register`

## CORS (Frontend origins)

Allowed origins are configurable via env var `CORS_ALLOWED_ORIGINS` (comma-separated). Example (.env.local):

```
CORS_ALLOWED_ORIGINS=http://185.213.25.106,http://localhost:5173,http://localhost:3000
```

In dev, localhost:3000 and :5173 are also allowed by default. CORS handling is implemented by `App\\Security\\CorsHandler`.

## cURL Examples

1) Login (customer)

```
curl -s -X POST http://185.213.25.106/api/v1/customer/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@test.com","password":"test1234"}'
```

Sample response:

```
{
  "success": true,
  "message": "Login successful",
  "token": "<JWT>",
  "tokenType": "Bearer",
  "expiresAt": "2025-09-05T12:51:04+02:00",
  "user": { "email": "test@test.com", ... }
}
```

2) Use JWT to call a protected endpoint (health)

```
TOKEN="<paste JWT>"
curl -s http://185.213.25.106/api/v1/customer/health \
  -H "Authorization: Bearer $TOKEN"
```

3) Get profile

```
curl -s http://185.213.25.106/api/v1/customer/profile \
  -H "Authorization: Bearer $TOKEN"
```

4) Registration (email code)

Start:
```
curl -s -X POST http://185.213.25.106/api/v1/registration/start \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"Aa!23456","customerType":"individual"}'
```

Confirm (code sent to MailHog http://185.213.25.106:8025/):
```
curl -s -X POST http://185.213.25.106/api/v1/registration/confirm \
  -H 'Content-Type: application/json' \
  -d '{"token":"<token-from-start>","code":"123456"}'
```

Finalize registration:
```
curl -s -X POST http://185.213.25.106/api/v1/registration/register \
  -H 'Content-Type: application/json' \
  -d '{"token":"<token>","email":"user@example.com","firstName":"Jan","lastName":"Kowalski","customerType":"individual","password":"Aa!23456"}'
```

## Postman Tips

- Create a collection with variables: `baseUrl = http://185.213.25.106`, `token = <JWT>`.
- Add a pre-request script to set `Authorization: Bearer {{token}}` on protected requests.
- Save environment values after login by pasting the `token` from the response.

