# Putting Mahale Tours billing on InfinityFree (free, no card)

About 30 minutes. You need a laptop for the upload (FileZilla). After that everything
works from the phone.

What you get: the full app on a free address like `https://mahaletours.infinityfreeapp.com`,
installable on the phone, with PDF bills, WhatsApp sharing, payments and reports.
Free-plan limits: no automatic backups (use **Settings → Download backup** once a week),
and the very first visit may show a short "checking your browser" page. That is normal
on InfinityFree.

## 1. Create the hosting account (5 min)

1. Go to <https://www.infinityfree.com> → **Register** (email + password, no card).
2. In the client area click **Create Account**.
3. Choose a **free subdomain**, for example `mahaletours` + `.infinityfreeapp.com`.
   Use the free subdomain to start. Do **not** change the nameservers of
   `mahalestravels.in`; that would take the existing website offline.
4. Open the new account. Note these from **Account Details / FTP Details**:
   - **FTP hostname** (e.g. `ftpupload.net`), **username** (`if0_…`), **password**
   - The same username and password are used for MySQL.

## 2. Create the database (2 min)

1. Open **Control Panel** → **MySQL Databases**.
2. Create a database named `mahale`. Its full name looks like `if0_12345678_mahale`.
3. Note the **MySQL Hostname** shown there (e.g. `sql123.infinityfree.com`).

## 3. Turn on HTTPS (5 min, mostly waiting)

Client area → your account → **Free SSL Certificates** → issue a certificate for your
subdomain and wait until it says it is installed. Then the site opens with `https://`.

## 4. Fill in `.env` on your computer (2 min)

1. Unzip `mahale-upload.zip`. Open the `mahale-upload` folder.
2. Open the file `.env` in a text editor (it is a hidden file: on Windows turn on
   *View → Hidden items*; on a Mac press `Cmd + Shift + .` in Finder).
3. Change these lines:

```dotenv
APP_URL=https://mahaletours.infinityfreeapp.com
DB_HOST=sql123.infinityfree.com
DB_DATABASE=if0_12345678_mahale
DB_USERNAME=if0_12345678
DB_PASSWORD=your-hosting-account-password
```

4. Optional, for emailing bills from Gmail: create a Gmail **App Password**
   (Google Account → Security → 2-Step Verification → App passwords) and set:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=mahale.veeru1@gmail.com
MAIL_PASSWORD="xxxx xxxx xxxx xxxx"
MAIL_FROM_ADDRESS=mahale.veeru1@gmail.com
```

## 5. Upload with FileZilla (10–30 min)

InfinityFree cannot unzip files on the server, so upload the folder itself.

1. Install FileZilla (<https://filezilla-project.org>, the *Client*).
2. Connect: Host = FTP hostname, Username, Password, Port `21` → **Quickconnect**.
3. On the right, open the **htdocs** folder and delete the default `index2.html` there.
4. On the left, open your `mahale-upload` folder, select **everything inside it**
   (including `.env` and `.htaccess`: in FileZilla use *Server → Force showing hidden files*)
   and drag it into `htdocs`.
5. Wait until the queue at the bottom is empty and "Failed transfers" is 0. If any failed,
   right-click them → *Reset and requeue*.

## 6. Run the installer (1 min)

1. Open `https://your-site/install` (for example `https://mahaletours.infinityfreeapp.com/install`).
2. It should say **✓ Database connected**. If not, re-check the `DB_` lines in `.env`
   (you can edit `.env` in InfinityFree's File Manager too).
3. Enter the **setup code** from `README.txt`, the owner name, login email and a password,
   then **Finish setup**. You are logged in.
4. The installer switches itself off once the owner login exists.

## 7. First things in the app

- **Settings → Business settings**: check address, phone, add GSTIN, UPI ID and bank details.
- **Settings → Cars / Drivers**: add the 6–7 cars with their rates, and the drivers.
- On jiju's phone: open the site in **Chrome → ⋮ → Install app**. It gets the logo icon.

## Weekly backup (important on the free plan)

**Settings → Business settings → ⬇ Download backup** saves a `.sql` file with every bill,
payment and customer. Keep it in Google Drive. To restore: create an empty database and
import the file with **phpMyAdmin → Import**.

## Updating later

Get a new package, then upload these folders over the old ones: `app`, `bootstrap`,
`config`, `database`, `public`, `resources`, `routes`, `vendor`.
Never overwrite `.env` or `storage`. If an update adds database changes, Shubham can
run them from the package's instructions.

## If something goes wrong

| What you see | What to do |
|---|---|
| "Index of /" or a default InfinityFree page | `.htaccess` is missing in `htdocs`; upload it again (FileZilla hidden files on). |
| `/install` says the database can't be reached | Fix the `DB_` lines in `.env`; the password is your hosting account password. |
| Page says 500 / Server Error | Check `storage/logs/laravel.log` in File Manager and send it to Shubham. |
| Email bill says it could not be sent | Check the Gmail App Password lines in `.env`. |
