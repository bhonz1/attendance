# Users API (Admin)

Base: `/admin/users_api.php`

All modifying requests require CSRF token and Admin/Superadmin session.

## List Users
- Method: GET
- Params: `op=list`, `search` (optional), `role_id` (optional: 0|1|2), `status` (optional: active|inactive|suspended), `page` (default 1), `page_size` (default 10)
- Response: `{ ok, items: [{ id, username, email, role_id, status, two_factor_enabled }], page, page_size, total }`

## Create User
- Method: POST
- Body: `op=create`, `csrf`, `username`, `email`, `password`, `role_id` (0|1|2), `status` (active|inactive|suspended)
- Response: `{ ok, item: { id } }`

## Update User
- Method: POST
- Body: `op=update`, `csrf`, `id`, optional: `username`, `email`, `role_id`, `status`
- Response: `{ ok }`

## Delete User
- Method: POST
- Body: `op=delete`, `csrf`, `id`
- Response: `{ ok }`

## Bulk Status
- Method: POST
- Body: `op=bulk_status`, `csrf`, `ids[]`, `status` (active|inactive|suspended)
- Response: `{ ok }`

## Assign Role
- Method: POST
- Body: `op=assign_role`, `csrf`, `ids[]`, `role_id` (0|1|2)
- Response: `{ ok }`

## Logging
- Actions are logged to `user_action_logs` (server-side); fallback to session store in mock mode.

## Security
- CSRF required; inputs are validated server-side
- Role-based access enforced via session `auth_id`
