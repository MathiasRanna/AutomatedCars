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
