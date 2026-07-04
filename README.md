# Education CMS

Self-hosted PHP CMS for education institutions. It is intentionally framework-free:
plain PHP 8.2+, PDO, server-rendered templates, a small admin panel, roles,
documents, news, pages, and a structured public information module.

## Local demo

```bash
php -S 127.0.0.1:8080 -t public
```

Open `http://127.0.0.1:8080/install` and use SQLite for a quick demo.

## Shared hosting install

1. Upload the project files to hosting.
2. Point the web root to `public/`.
3. Open `/install`.
4. Choose MySQL/MariaDB, enter database credentials, institution details, and
   first administrator account.
5. Remove write access to `config/local.php` after installation if your hosting
   panel allows it.

Default writable directories:

- `storage/`
- `storage/uploads/`
- `storage/cache/`

## Core modules

- Pages with block-based content.
- News.
- Documents.
- Public information checklist.
- Users, roles, permissions.
- Audit log.
- Installer.

## Notes

The admin panel is compact on purpose. It is meant to be a solid foundation for
a distributable education-site product, not a full framework clone.
