# LazyCaptcha for MyBB

Self-hostable CAPTCHA plugin for [MyBB](https://mybb.com) 1.8.x forums. Protects registration, login, password recovery, and posting with the LazyCaptcha service.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![MyBB](https://img.shields.io/badge/MyBB-1.8.x-00678D.svg)](https://mybb.com)

## What it protects

- ✅ New account registration (`member.php?action=register`)
- ✅ Login (`member.php?action=login`) — optional, off by default
- ✅ Lost password (`member.php?action=lostpw`)
- ✅ New thread creation (`newthread.php`)
- ✅ New replies (`newreply.php`)

Seasoned users (configurable threshold, default 10 posts) automatically skip the posting CAPTCHA so regulars aren't bothered.

## Requirements

- MyBB 1.8.0 or higher
- PHP 7.4+ with `curl` and `json` extensions
- Outbound HTTPS connectivity to your LazyCaptcha instance

## Installation

### Step 1 — Upload files

Copy the contents of the `inc/` directory into your MyBB `inc/` directory, preserving structure:

```
inc/
├── languages/english/admin/config_lazycaptcha.lang.php
└── plugins/lazycaptcha.php
```

Via FTP/SFTP that's one upload. Via zip-install, just upload the ZIP through the MyBB admin's plugin installer (if using the release zip).

### Step 2 — Activate

1. Log in to the **Admin Control Panel**
2. Go to **Configuration → Plugins**
3. Find **LazyCaptcha** in the list and click **Install & Activate**

The plugin will:
- Create a **LazyCaptcha** settings group
- Inject the `{$lazycaptcha}` template variable into affected forms
- Register hooks for rendering and verification

### Step 3 — Configure

1. Go to **Configuration → Settings → LazyCaptcha**
2. Enter your **Site Key** and **Secret Key** from your LazyCaptcha dashboard
3. Choose which forms to protect (registration, login, lost password, posting)
4. *(Optional)* Change the post-count threshold for skipping the posting CAPTCHA
5. Save

### Step 4 — Verify

Log out and try to register a new account. You should see the LazyCaptcha widget below the registration fields.

## Configuration

| Setting | Default | Purpose |
|---------|---------|---------|
| Site Key | — | Public UUID embedded in the widget |
| Secret Key | — | Private key used for server-side verification |
| LazyCaptcha URL | `https://lazycaptcha.com` | Your instance URL (change for self-hosted) |
| Challenge Type | `auto` | Image puzzle / PoW / behavioral / text-math |
| Theme | `light` | Widget appearance |
| Protect registration | Yes | |
| Protect login | No | |
| Protect forgot password | Yes | |
| Protect posting | Yes | |
| Skip posting after N posts | 10 | Regulars skip the CAPTCHA |

## How it works

On registration/login/posting, the plugin renders the LazyCaptcha widget and loads the script from your configured URL. When the user solves the challenge, the widget injects a hidden `lazycaptcha-token` input into the form.

On submit, the plugin hooks into MyBB's verification flow (`*_do_*_start` hooks), reads the token, and verifies it server-to-server by POSTing to `/api/captcha/v1/verify`. On failure, an error is added to the form's error array (or `error()` is called for login, which MyBB displays natively).

## Updating the plugin

1. Deactivate & Uninstall in **Configuration → Plugins**
2. Upload the new files
3. Install & Activate again

Uninstall cleans up settings. Deactivation alone preserves them.

## Troubleshooting

### Widget doesn't appear

- Verify the plugin is **activated** (not just uploaded) in Configuration → Plugins
- Check that **Site Key** is set
- Some custom themes override the templates — if `{$lazycaptcha}` isn't in your theme's `member_register`, `member_login`, `member_lostpw`, `newthread`, or `newreply` template, the plugin's template-inject step didn't match. Edit your theme templates manually and add `{$lazycaptcha}` where you want the widget to appear.

### Registration always fails with "CAPTCHA verification failed"

- Confirm the Secret Key is correct (paste it fresh from your LazyCaptcha dashboard)
- Confirm your forum's server can reach `https://lazycaptcha.com` (or your self-hosted URL) — PHP's curl must have outbound HTTPS
- Check your server's TLS CA bundle is up to date (`curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true)` is enforced)

### I want to also protect PMs / contact form / custom pages

Call `lazycaptcha_widget_html()` in your template and `lazycaptcha_verify_token()` in your handler. Both functions are exposed.

## License

[MIT](LICENSE)
