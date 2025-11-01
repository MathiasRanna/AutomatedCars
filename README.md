# AutomatedCars

V2 of automated cars. With Laravel server handling requests.

## Initialization

To set up the project, follow these steps:

1. Install Node.js dependencies:
   ```bash
   npm install
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Set up environment variables:
   - Copy `.env.example` to `.env`
   - Update the `.env` file with your configuration

4. Generate application encryption key:
   ```bash
   php artisan key:generate
   ```

5. Run database migrations:
   ```bash
   php artisan migrate
   ```

## Running the Application

To start the development servers:

```bash
composer run dev
```

## Production Queue Worker

The application uses Laravel queues for asynchronous processing (image downloads and AI processing). 

**In production, you MUST run a queue worker continuously.**

See `PRODUCTION_SETUP.md` for detailed setup instructions. Quick options:

1. **Supervisor** (recommended): `supervisor/laravel-worker.conf`
2. **Systemd**: `systemd/laravel-worker.service`
3. **Cron**: Add `* * * * * cd /path/to/app && php artisan queue:work --stop-when-empty` to crontab

For development, you can run: `php artisan queue:work`
