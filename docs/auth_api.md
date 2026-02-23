# Account Authentication API (Admin)

Base: `/admin/auth_api.php`

All modifying requests require CSRF token and Admin/Superadmin session.

## Change Password
- Method: POST
- Body: `op=change_password`, `csrf`, `id`, `password`
- Response: `{ ok }`

## Setup Two-Factor Authentication
- Method: POST
- Body: `op=setup_2fa`, `csrf`, `id`
- Response: `{ ok, secret }`

## Disable Two-Factor Authentication
- Method: POST
- Body: `op=disable_2fa`, `csrf`, `id`
- Response: `{ ok }`

## Login History
- Method: GET
- Params: `op=login_history`, `id`
- Response: `{ ok, items: [{ id, at, ip, user_agent, success, reason }] }`

## Notes
- 2FA secret is generated server-side and stored with the user record
- Security policies can be enforced via `failed_login_count` and `locked_until` fields in `users` table
- Writes require Supabase service role configured in `.env`
