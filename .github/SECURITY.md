# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| php8    | :white_check_mark: |
| < php8  | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability, please **DO NOT** open a public issue.

Instead, please report it privately by emailing the maintainers. Include:

1. Description of the vulnerability
2. Steps to reproduce
3. Potential impact
4. Suggested fix (if any)

You will receive a response within 48 hours. If the vulnerability is confirmed,
a fix will be released as soon as possible.

## Security Measures

This project implements the following security measures:

- **Authentication**: Custom NexusWebGuard with challenge-response + HMAC passkey
- **CSRF**: Laravel's CSRF tokens on all POST forms
- **SQL Injection**: Eloquent ORM + parameterized queries throughout
- **XSS**: Blade's `{{ }}` escaping by default; `{!! !!}` only for audited helpers
- **Rate Limiting**: Laravel's throttle middleware on auth endpoints
- **Dependency Audit**: `composer audit` and `npm audit` run in CI on every push
- **Static Analysis**: PHPStan level 8 catches type-safety issues
- **Code Style**: Laravel Pint enforces consistent, readable code
