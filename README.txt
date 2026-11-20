QUOTATION SYSTEM  (PHP + MySQL + Tailwind CSS)
==============================================

SETUP (XAMPP)
  1. Copy this folder to  C:\xampp\htdocs\quotation_system
  2. Start Apache and MySQL in the XAMPP Control Panel
  3. phpMyAdmin > Import > database/quotation_system.sql
  4. Open http://localhost/quotation_system/ and click "Get started".
     The first account you create is the company admin.

  Optional: import database/sample_data.sql (after creating your account) to get sample products and one quotation.
  Database host / name / user / password: the .env file in the project root (copy .env.example to .env if it is missing).
  XAMPP default is root with no password. Never share or upload your .env file.

DOCUMENT DESIGN
  Company page > Document layout: choose Modern, Classic or Minimal (and the colour). It applies to every quotation and invoice.

OLD DATABASE?
  Nothing to import. The first page you open upgrades it automatically (config/migrate.php, runs once).
  Take a backup first if the data matters.


WHAT THE SYSTEM DOES
  Quotation -> Invoice
    Numbers: Q-0001 / I-0001. One series per company, shared by all its users. The database enforces it
    (UNIQUE company_id + quotation_no, and company_id + invoice_no).
    A draft quotation cannot be invoiced: change its status to "sent" first.
    "Generate invoice" gives the invoice its OWN copy of the items, customer details, notice and amounts
    (tables invoice + invoice_item). Editing the quotation later does not change an issued invoice.
    To correct one, delete the invoice and generate it again.

  Items, units, decimals
    Every quotation row has Qty + Unit. kg, g, l, ml, m, cm, ft, sq.ft, hr, day accept up to 3 decimals (2.5 kg).
    pcs, box, pack, set and "No unit" accept whole numbers only.
    Products & services stores the unit with the price (Rs. 450 / kg); picking the item fills in both.
    Items typed by hand stay on that quotation only. The same item cannot be used twice in one quotation.
    Line amount = unit price x qty, rounded to 2 decimals; the subtotal adds the rounded lines.

  Discount + VAT
    Total = Subtotal - Discount + VAT, with VAT charged after the discount. A box left at 0 is not printed.

  Document (quotation / invoice)
    A4 sheet, PDF download (html2pdf.js) and Print. Live preview next to the quotation form.
    Company page (admin): logo, document colour, address, phone, bank details.
    The bank box is printed on invoices only (not on quotations), just above the footer line.

  Item picker (quotation form)
    Type several words in any order ("red rice" finds "Rice Red 5kg"); matching words are highlighted.
    The best match is listed first: click any row, or press Enter to take the best match (no arrow keys needed).
    With nothing typed, the picker shows "Recently used" items first (from earlier quotations), then all items A-Z.
    "Use ... as a custom item" keeps what you typed for this quotation only.

  Search
    Quotations: quotation no (Q-0005 / 5), name, contact no, address, date.   Invoices: also invoice no and invoice date.


WHO CAN DO WHAT
  Admin: everything.
  Staff: create quotations; edit / delete / change status / generate invoice only for quotations THEY created;
         add products and services (edit and delete are admin only).
         Staff do not see the Company and Users pages or the "Invoiced" total on the dashboard.
  Users are never deleted. Admin > Users > Deactivate: the person can no longer sign in (an open session ends on
  their next click) and everything they created is kept. Activate brings them back.
  The role is re-read from the database on every page, so a role change or deactivation applies at once.
  Search the code for: can_modify_quotation / can_modify_invoice / is_admin.


FOLDER STRUCTURE
  index.php                  Landing page (the only page in the project root)
  pages/                     Every other page
    login, register, logout, forgot_password
    dashboard
    quotations, quotation_form, quotation_view, quotation_preview (live preview), quotation_delete
    invoices, invoice_view, invoice_create
    items, company, users
  includes/
    auth.php                 Session, sign in, who can do what, CSRF, flash messages. Loads ui.php + helpers.php
    helpers.php              Money, units, totals, status list, search, shared database lookups
    ui.php                   Button / input classes, icons, logo, document colour theme, page heading
    head.php                 <head> tags + Tailwind colours (edit the 8 "brand" colours to re-colour the project)
    header.php / footer.php  Sidebar + top bar / closing tags
    document.php             Printable quotation / invoice layout (one design for both)
    auth_layout.php, auth_panel.php   Shared frame of Login / Sign up / Forgot password
  config/
    db.php                   Database connection (reads settings from .env)
    env.php                  Small .env file loader
    migrate.php              One-time upgrade of an old database (loaded by db.php only when needed)
  assets/
    css/style.css            Small custom CSS (print, A4 sheet, mobile table cards, form rows, sidebar)
    js/app.js                Menu, password eye, A4 fit, status dropdown, sidebar collapse
    js/pdf.js                PDF download
    js/quotation_form.js     Quotation form: item rows, picker, totals, live preview
  database/
    quotation_system.sql     Full database (use this for a new install)
    sample_data.sql          Optional sample products + quotation
    useful_queries.sql       Handy reports for phpMyAdmin

  There is no setup.php. The first admin is created on the Sign up page (pages/register.php).
