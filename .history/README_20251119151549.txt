CONS-UNTH E-LIBRARY SYSTEM (MVP)
===============================

Structure:
/
    index.php          -> search + list (requires login)
    login.php          -> login form
    logout.php         -> logout and redirect to login
    resource.php       -> view single resource (PDF, EPUB, video, docs, links)
    download.php       -> admin-only direct download endpoint

    /admin/
        dashboard.php  -> basic stats
        resources.php  -> list/manage resources
        resource_add.php
        resource_edit.php
        resource_delete.php
        categories.php -> manage categories
        users.php      -> view all users

    /includes/
        config.php     -> app name, ONLYOFFICE URL
        db.php         -> SQLite DB connection + schema + default admin
        auth.php       -> login helpers, require_login, require_admin
        functions.php  -> CSRF, flash messages, helpers
        header.php     -> Bootstrap + SweetAlert layout header
        footer.php     -> layout footer

    /uploads/          -> uploaded files (PDF, EPUB, DOC, PPT, XLS, videos)
    /data/             -> SQLite database file (library.sqlite)

Features:
- Bootstrap 5 for UI
- SweetAlert2 for flash messages
- Login system with default admin:
    Email: admin@example.com
    Password: admin123
- Resource types:
    pdf, epub, video_file, video_link, doc, ppt, xls, link
- View-only:
    - PDF inside iframe (toolbar hidden)
    - EPUB using epub.js
    - Video with controlsList="nodownload" and no right-click
    - DOC/PPT/XLS via ONLYOFFICE (if configured) or Microsoft Office viewer fallback
    - External links inside iframe
- Admin panel:
    - Manage resources (add/edit/delete)
    - Manage categories
    - View users

Setup:
1. Place the "elib" folder under your web root.
2. Ensure PHP has PDO SQLite enabled.
3. Ensure the web server can write to:
   - elib/uploads
   - elib/data
4. Visit: http(s)://yourdomain/login.php
5. Login with admin@example.com / admin123
6. Add categories and resources via the Admin panel.

ONLYOFFICE integration (optional):
- Install ONLYOFFICE Document Server.
- Set $ONLYOFFICE_BASE_URL in includes/config.php.
- Then DOC/PPT/XLS will be opened via ONLYOFFICE instead of Microsoft viewer.

Note:
- There is no "download" button for normal users.
- Admins can download files directly via download.php, but this is not exposed to students by default.
