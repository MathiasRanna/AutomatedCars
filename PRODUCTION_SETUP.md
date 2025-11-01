# Production Queue Worker Setup

This guide explains how to keep the Laravel queue worker running in production.

## Option 1: Supervisor (Recommended for most servers)

Supervisor is a process manager that keeps your queue worker running and automatically restarts it if it crashes.

### Installation

**Ubuntu/Debian:**
```bash
sudo apt-get install supervisor
```

**CentOS/RHEL:**
```bash
sudo yum install supervisor
# or
sudo dnf install supervisor
```

### Configuration

1. Copy the supervisor config file to the supervisor directory:
```bash
sudo cp supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
```

2. Edit the config file and update the paths:
```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

Update these values:
- `command=php /var/www/your-app/artisan queue:work ...` - Change `/var/www/your-app` to your actual application path
- `user=www-data` - Change to your web server user (usually `www-data`, `nginx`, or `apache`)
- `stdout_logfile=/var/www/your-app/storage/logs/worker.log` - Update the path

3. Reload supervisor and start the worker:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

4. Check status:
```bash
sudo supervisorctl status
```

### Managing the Worker

```bash
# Start
sudo supervisorctl start laravel-worker:*

# Stop
sudo supervisorctl stop laravel-worker:*

# Restart
sudo supervisorctl restart laravel-worker:*

# View logs
tail -f /var/www/your-app/storage/logs/worker.log
```

---

## Option 2: Systemd Service (Modern Linux distributions)

Systemd is the default init system on most modern Linux distributions.

### Setup

1. Copy the service file:
```bash
sudo cp systemd/laravel-worker.service /etc/systemd/system/laravel-worker.service
```

2. Edit the service file and update paths:
```bash
sudo nano /etc/systemd/system/laravel-worker.service
```

Update:
- `User=www-data` - Your web server user
- `WorkingDirectory=/var/www/your-app` - Your application path
- `ExecStart=/usr/bin/php /var/www/your-app/artisan queue:work ...` - Full path to PHP and your app

3. Enable and start the service:
```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-worker
sudo systemctl start laravel-worker
```

4. Check status:
```bash
sudo systemctl status laravel-worker
```

### Managing the Service

```bash
# Start
sudo systemctl start laravel-worker

# Stop
sudo systemctl stop laravel-worker

# Restart
sudo systemctl restart laravel-worker

# View logs
sudo journalctl -u laravel-worker -f
```

---

## Option 3: Cron Job (Simple but less reliable)

This runs the queue worker in short bursts via cron. Less reliable but simpler.

Add to crontab:
```bash
crontab -e
```

Add this line (runs every minute, processes jobs for 59 seconds):
```cron
* * * * * cd /var/www/your-app && php artisan queue:work database --stop-when-empty
```

---

## Option 4: PM2 (If using Node.js ecosystem)

If you're familiar with PM2 from Node.js:

```bash
pm2 start "php artisan queue:work database --sleep=3 --tries=3" --name laravel-worker
pm2 save
pm2 startup
```

---

## Testing

After setting up any option, test that jobs are processing:

1. Make a POST request to `/receive-post`
2. Check the queue:
```bash
php artisan queue:work --once
```
3. Check logs:
```bash
tail -f storage/logs/laravel.log
```

## Important Notes

- **Always use `--tries=3`** to retry failed jobs
- **Use `--max-time=3600`** to prevent memory leaks (restarts worker every hour)
- **Use `numprocs=2`** in Supervisor to run multiple workers for better throughput
- **Monitor logs** regularly to catch any issues

