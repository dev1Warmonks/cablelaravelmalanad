# Asset copy & scan (Phase 2)
Source (read only): `cablebill_old/{assets,css,cssjs,js,common}` → `public/`.

Copied (static only): assets 730 files, css 4, cssjs 6, js 5, common 901.

**Not copied (listed instead):**
- All `*.php/.phtml/.phar/.sh/.exe/.htaccess/.gitignore` (≈190 files). Breakdown:
  - `assets/fpdf`, `assets/fpdi`, `assets/excel` (libraries, incl. `excel/test.php` which reads `?File=` — a file-read risk) → replaced by composer packages.
  - `assets/grocery_crud/**` PHP (themes' views, languages, ckeditor `.php`), `assets/image_crud/**` PHP → replaced by Blade + generic CRUD (static JS/CSS/images of Grocery_CRUD were copied so HTML stays identical).
  - `common/php/Encryption.php`, `common/php/variables.php`, ckeditor `samples/*posteddata.php`, datatables `ssp.php` example → not needed.
- `assets/uploads/files` (130 MB: 854 CSV, 4 XLSX, 1 HTML of customer import data) and `assets/uploads/backup` (DB dumps) → **user data, not assets**; empty dirs created. Import will write to private `storage/app/imports`.
- Post-copy check: 0 executable/script files under `public/` (except Laravel's own `index.php`, `.htaccess`).
- `public/assets/uploads/files` and `/backup` must never be web-served: add deny rules at go-live (done in step: security headers/`.htaccess`).
