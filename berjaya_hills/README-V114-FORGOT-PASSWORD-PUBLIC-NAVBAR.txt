V114 — FORGOT PASSWORD + PUBLIC ACCOUNT NAVBAR

Implemented:
- Added Forgot password link to the public sign-in page.
- Added password reset request page with CSRF protection.
- Added one-hour, single-use password reset tokens.
- Added reset password page with password confirmation and secure password hashing.
- The reset table is created automatically by the forgot-password page; SQL migration is also included at database/add_password_resets.sql.
- On localhost, a development reset link is shown because a local mail server may not be configured.
- Added email delivery through PHP mail() for environments with mail configured.
- Public/user account navbar now keeps the BH brand on the left and user name + Sign out on the far right, matching the management navbar alignment.
- CSS cache version bumped to v114.
