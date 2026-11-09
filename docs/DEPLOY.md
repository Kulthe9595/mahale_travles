# Deploying Mahale Tours and Travels (free, on Oracle Cloud)

This puts the app on an Oracle Cloud **Always Free** server with HTTPS, daily backups to
Google Drive and uptime alerts. Plan on about an hour the first time.

## 1. Oracle account (do these two things first)

1. Sign up at <https://www.oracle.com/cloud/free/>. A card is needed for identity checks;
   nothing is charged inside the Always Free limits. Pick the **home region** closest to you
   (e.g. India West – Mumbai or India South – Hyderabad). It cannot be changed later.
2. **Upgrade to Pay As You Go** (Billing → Upgrade). Always Free resources stay free, and
   Oracle stops reclaiming "idle" free VMs, which matters for a small app like this.
3. **Budget alert:** Billing → Budgets → create a budget of **₹1** with an email alert, so you
   hear about any charge immediately.

## 2. Create the server

Compute → Instances → Create instance:

- Image: **Canonical Ubuntu 24.04**
- Shape: **Ampere A1 (VM.Standard.A1.Flex)**, 1 OCPU / 6 GB RAM is plenty
  (if "out of capacity", retry later or try another availability domain)
- Add your SSH public key, create.

Networking → your VCN → Security List → add **Ingress rules** for TCP **80** and **443**
from `0.0.0.0/0`. (The setup script also opens Ubuntu's own firewall.)

## 3. Domain

Point a domain or subdomain (an `A` record) at the instance's public IP, e.g.
`bills.mahaletours.in`. A cheap `.in` domain works; free DNS-only subdomain services also
work. HTTPS needs a real name, not a bare IP.

## 4. Give the server read access to the GitHub repo

The repo is private, so on the server create a deploy key:

```bash
ssh ubuntu@<server-ip>
sudo -i
ssh-keygen -t ed25519 -f /root/.ssh/id_ed25519 -N ""
cat /root/.ssh/id_ed25519.pub
```

GitHub → repo **Settings → Deploy keys → Add deploy key** → paste it (read-only).

## 5. Run the setup script

```bash
sudo -i
curl -fsSL https://raw.githubusercontent.com/Kulthe9595/mahale_travles/main/deploy/server-setup.sh -o setup.sh
# private repo? copy deploy/server-setup.sh over with scp instead.
bash setup.sh bills.mahaletours.in you@gmail.com
```

It installs Nginx, PHP 8.3, MySQL, Node, Composer and certbot; creates the database
(password saved in `/root/mahale-db.txt`); clones the app to `/var/www/mahale`; builds it;
gets the HTTPS certificate; and installs the cron jobs (scheduler + 02:30 IST backup).

Then set the owner login and email in `/var/www/mahale/.env`:

```dotenv
OWNER_NAME="Jiju's name"
OWNER_EMAIL=owner@example.com
OWNER_PASSWORD=choose-a-strong-one

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=yourname@gmail.com
MAIL_PASSWORD="xxxx xxxx xxxx xxxx"   # Gmail App Password
MAIL_FROM_ADDRESS=yourname@gmail.com
```

```bash
cd /var/www/mahale && php artisan db:seed --force && php artisan optimize
```

Log in at `https://<your-domain>`, open **Settings**, and fill in address, GSTIN, UPI ID,
bank details and logo. Add cars and drivers, and you're ready.

## 6. Install on the phone

Open the site in **Chrome on Android** → menu → **Install app** (or "Add to Home screen").
On iPhone: Safari → Share → **Add to Home Screen**. "Share PDF" sends the bill straight
into WhatsApp from the phone.

## 7. Daily backups to Google Drive

```bash
sudo -i
rclone config        # n) new remote → name: gdrive → storage: drive → follow prompts
                     # On a server without a browser choose "remote config" and paste the
                     # token from `rclone authorize "drive"` run on your laptop.
/var/www/mahale/deploy/backup.sh   # run once now to check
```

Backups land in Google Drive folder `mahale-backups`, one `.tar.gz` per day, 30 days kept.
Each file has `database.sql` (all bills, payments, customers…) and `files/` (logo).
PDFs are generated from the database, so they don't need separate backup.

## 8. Uptime alerts

Create a free monitor at <https://uptimerobot.com>: HTTP(s), URL
`https://<your-domain>/up`, every 5 minutes, alert to your email (or Telegram).

## Updating the app

Push to `main` on GitHub, then on the server:

```bash
sudo /var/www/mahale/deploy/deploy.sh
```

It pulls, installs, builds, migrates and reloads, with a short maintenance page meanwhile.

## Restoring a backup / moving to another server

1. Run `server-setup.sh` on the new Ubuntu server (any provider) and point the domain at it.
2. Copy the newest backup from Google Drive (`rclone copy gdrive:mahale-backups/<file> .`).
3. Restore:

```bash
tar -xzf mahale-YYYY-MM-DD_HHMM.tar.gz
mysql -u mahale -p"$(cat /root/mahale-db.txt)" mahale < database.sql
cp -a files/. /var/www/mahale/storage/app/public/
chown -R www-data:www-data /var/www/mahale/storage
cd /var/www/mahale && php artisan optimize
```

Bill links already sent to customers keep working, because they use the same secret tokens
stored in the database.
