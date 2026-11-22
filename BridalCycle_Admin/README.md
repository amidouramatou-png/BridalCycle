# BridalCycle

Bridal fashion marketplace for Niamey, Niger: buy, sell and borrow wedding outfits (prices in XOF).

E-commerce course project - Group 6.

## Run it on XAMPP

1. Put the folder in `htdocs`.
2. In phpMyAdmin, create a database and import `database/bridalcycle.sql`.
3. In `core/`, copy each `*_cred.example.php` file and remove `.example` from the name
   (`db_cred.php`, `mail_cred.php`, `google_cred.php`, `payment_cred.php`). Fill in your own details.
   These files are in `.gitignore`: never push them.
4. In `core/settings.php`, set `BASE_URL` to the address of the folder.
5. Open `BASE_URL` in the browser.

## Structure (MVC)

- `classes/` models (SQL with prepared statements)
- `controllers/` rules between actions and models
- `actions/` form handlers (POST + CSRF)
- `views/` pages
- `core/` settings, database connection, helpers, languages (EN / FR)
- `css/`, `js/`, `fonts/`, `images/`
- `tests/` PHPUnit login speed test
