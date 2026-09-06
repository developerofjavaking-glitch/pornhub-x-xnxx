# Boom Host Bot (PHP + Docker) 🤖

Same bot as before — users upload and run their own Python (`.py`),
JavaScript (`.js`), or zipped project (`.zip`) from Telegram — rewritten in
**PHP**, packaged as a **Docker** image, meant to run on **Render**.

The bot process itself is PHP, but the Docker image also includes
**Python 3** and **Node.js**, so it can still validate and run uploaded
`.py`/`.js` scripts exactly like before — nothing lost in the rewrite.

Every deployment-specific value (bot token, admin ID, owner link, channel
link, etc.) is read from **environment variables**. Nothing is hard-coded.

---

## ✨ Features (same as the Python version)

- **/start dashboard**, **Upload File**, **Check Files**, **Bot Speed**,
  **Statistics**, **Contact Owner**, **Updates Channel**
- `.py` / `.js` / `.zip` uploads — zips are safely extracted (zip-slip
  protected) and `requirements.txt` is auto-installed via `pip`
- `.py` syntax pre-check via the container's own `python3 -m py_compile`,
  reported in the same file/line/message format
- Start/stop/delete hosted projects, see 🟢 running / 🔴 stopped
- Hosting is open to anyone who messages the bot (no invite-code system)
- `/admin` panel — usage stats, toggle the whole bot on/off, broadcast to
  every user
- No external database — a small JSON file is used for storage

---

## 📁 Project structure

```
boom-host-bot-php/
├── Dockerfile                # PHP 8.2-cli + Python3 + Node.js in one image
├── entrypoint.sh              # starts the optional keep-alive server + the bot
├── src/
│   ├── bot.php                 # entry point — long-polling loop
│   ├── Config.php               # reads all environment variables
│   ├── Storage.php              # JSON-file "database"
│   ├── TelegramApi.php          # minimal cURL-based Bot API client
│   ├── SyntaxCheck.php          # python3 -m py_compile wrapper
│   ├── ZipHandler.php           # safe zip extraction + pip install
│   ├── ProcessManager.php       # proc_open-based subprocess management
│   ├── UserState.php            # in-memory per-user conversation state
│   ├── Router.php               # dispatches Telegram updates to handlers
│   ├── Util.php
│   └── Handlers/
│       ├── Start.php
│       ├── Buttons.php
│       ├── Upload.php
│       └── Admin.php
├── public/index.php            # keep-alive HTTP health check page
├── render.yaml
├── .env.example
└── README.md
```

No Composer dependency is required — the Telegram API client is a plain
cURL wrapper, so there's one less thing that can fail in a Docker build.

---

## 🔧 Environment variables

| Variable              | Required | Default            | Description |
|------------------------|:--------:|---------------------|-------------|
| `BOT_TOKEN`            | ✅       | —                   | Token from [@BotFather](https://t.me/BotFather) |
| `ADMIN_ID`              | ✅ for /admin | —              | Comma-separated numeric Telegram user IDs |
| `OWNER_LINK`            |          | `https://t.me/`     | Used by the "📞 Contact Owner" button |
| `CHANNEL_LINK`          |          | `https://t.me/`     | Used by the "📢 Updates Channel" button |
| `BOT_NAME`              |          | `Boom Host Bot`     | Display name used in messages |
| `MAX_FILES_PER_USER`    |          | `3`                 | Max concurrent projects per user |
| `MAX_UPLOAD_MB`         |          | `1`                 | Max upload size in MB |

The three variables below are **auto-configured — you never need to set
them**, but can still be overridden if you have a reason to:

| Variable              | Default (auto)      | How it's determined |
|------------------------|----------------------|----------------------|
| `DATA_DIR`              | `/app/data`         | Always resolves relative to where the code lives inside the image |
| `PORT`                  | Render's `$PORT`, else `8080` | Render injects `$PORT` itself for "Web Service" deploys; falls back to `8080` otherwise |
| `ENABLE_KEEPALIVE`      | on if `$PORT` was given by the platform, off otherwise | Only "Web Service"-style deploys need a bound port to pass health checks — a "Background Worker" deploy doesn't set `$PORT`, so this switches off automatically |

---

## 🚀 Setup (get your token & admin ID first)

1. Message [@BotFather](https://t.me/BotFather) → `/newbot` → copy the
   token → that's `BOT_TOKEN`.
2. Message [@userinfobot](https://t.me/userinfobot) to get your numeric
   Telegram user ID → that's `ADMIN_ID`.
3. (Optional) Set up an updates channel → `CHANNEL_LINK`.
4. Set `OWNER_LINK` to `https://t.me/your_username`.

---

## ▶️ Run locally with Docker

```bash
git clone <your-repo-url>
cd boom-host-bot-php
cp .env.example .env
# edit .env with your real BOT_TOKEN / ADMIN_ID / links

docker build -t boom-host-bot-php .
docker run --rm -it --env-file .env -p 8080:8080 boom-host-bot-php
```

---

## ☁️ Deploy on Render (Docker)

1. Push this project to a GitHub repo (it needs the `Dockerfile` at the
   repo root, as it is here).
2. Render dashboard → **New → Background Worker** (recommended — no port
   required for a long-polling bot) or **Web Service** if you'd rather have
   a URL/health check.
3. Choose **Docker** as the environment — Render will build straight from
   the `Dockerfile`, no build/start command needed.
4. Add `BOT_TOKEN`, `ADMIN_ID`, `OWNER_LINK`, `CHANNEL_LINK` under
   **Environment**. Nothing else needs to be set — whichever deploy type you
   pick (Background Worker or Web Service), the bot detects it automatically
   and configures the keep-alive server accordingly.
5. (Optional but recommended) Attach a **Persistent Disk** mounted at
   `/app/data` — otherwise the database and any hosted projects reset on
   every redeploy, since the container filesystem is ephemeral without one.
6. Deploy. `render.yaml` in this repo can also be used for a one-click
   Blueprint deploy (defaults to Background Worker).

---

## 💾 Persistence

Same caveat as any container-based host: without a persistent volume,
`DATA_DIR` (the JSON database + every uploaded project's files) is wiped on
redeploy/restart. Attach a Render Persistent Disk if you want hosted
projects to survive deploys.

---

## 🔐 Notes on running user-uploaded code

- Only give `ADMIN_ID` to people you trust with `/admin`'s broadcast and
  maintenance-toggle powers.
- Hosting is open to anyone who messages the bot — the small default
  `MAX_FILES_PER_USER=3` and `MAX_UPLOAD_MB=1` limits keep any one user's
  footprint small; raise them only if you trust your userbase more.
- The container runs `.py` scripts with `python3` and `.js` scripts with
  `node`, with the same OS-level permissions as the bot process itself —
  treat this the way you'd treat any code-hosting platform, and consider
  running it inside an isolated VM/sandbox for stricter isolation.

---

## 🛠 Admin commands

- `/admin` — opens the admin panel (Stats, Toggle Bot On/Off, Broadcast).
- `/cancel` — cancel whatever multi-step flow you're currently in.

---

## License

Use and modify freely for your own hosting bot.
