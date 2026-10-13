# Tawseeleh

Tawseeleh is a Laravel 12 API for a local food delivery platform. It manages customers, restaurants, drivers, products, and orders. The project has no frontend; requests and responses use JSON over HTTP.

## Requirements

- PHP 8.2 or later
- Composer
- MySQL or MariaDB

## Setup

### 1. Install dependencies

```bash
composer install
```

### 2. Configure the environment

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
php artisan key:generate
```

On Windows, you can copy the file manually if the `cp` command is unavailable.

Update the database settings in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tawseeleh
DB_USERNAME=root
DB_PASSWORD=
```

Use the credentials configured on your machine.

### 3. Create the database

Create the database in MySQL or MariaDB before running migrations:

```sql
CREATE DATABASE tawseeleh
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

### 4. Run migrations

```bash
php artisan migrate
```

### 5. Seed demo data

```bash
php artisan db:seed --class=TawseelehDemoSeeder
```

The seeder creates sample data for customers, restaurants, drivers, products, orders, and order items. It includes 3,000 orders.

**Important:** The seeder checks that all six business tables are empty before inserting data. If any of them already contain rows, it stops without inserting demo data. Run it on a fresh database, not on a database containing existing records.

## Delivery Fee

The delivery fee is calculated on the server. Clients cannot submit or override the fee when creating an order.

Set the value in `.env`:

```env
DELIVERY_FEE=10.00
```

The default is `10.00` when the variable is not set. The value is exposed through `config/app.php` and used by the `DeliveryFeeCalculator` contract, which is bound in `AppServiceProvider`.

## Running the API

Start the local development server:

```bash
php artisan serve
```

The API is available at:

`http://localhost:8000/api/`

The API routes are defined in `routes/api.php`.

## API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/orders` | List orders with optional filters and pagination |
| GET | `/api/orders/current` | List current orders |
| GET | `/api/orders/{id}` | Get one order with its details |
| POST | `/api/orders` | Create an order |
| PATCH | `/api/orders/{id}/status` | Update an order's status |
| PATCH | `/api/orders/{id}/assign-driver` | Assign a driver to an order |

The API returns JSON responses. Validation errors return HTTP 422, while requests for missing orders return HTTP 404.

### Listing Orders

`GET /api/orders` supports the following query parameters:

| Parameter | Type | Description |
|---|---|---|
| `status` | string | Filter by order status |
| `restaurant_id` | integer | Filter by restaurant |
| `driver_id` | integer | Filter by assigned driver |
| `date` | string | Filter by creation date in `Y-m-d` format |
| `per_page` | integer | Results per page, from 1 to 100; default is 15 |

Available statuses are `pending`, `accepted`, `picked_up`, `delivered`, and `cancelled`.

Example:

```text
GET /api/orders?status=pending&per_page=10
```

### Creating an Order

`POST /api/orders` accepts the customer, restaurant, delivery address, and order items.

Example request:

```json
{
  "customer_id": 1,
  "restaurant_id": 2,
  "delivery_address": "123 Main St, Riyadh",
  "items": [
    {
      "product_id": 5,
      "quantity": 2
    },
    {
      "product_id": 9,
      "quantity": 1
    }
  ]
}
```

The client does not provide `delivery_fee` or `total_amount`. The server calculates the delivery fee and order total.

Other rules:

- The restaurant must be active.
- Products must be available and belong to the selected restaurant.
- Product prices are read from the database when the order is created.
- The price of each item is stored with the order to preserve the price at the time of purchase.
- Repeated product IDs are merged before the final quantity is checked.
- Each product can have a maximum merged quantity of 100.
- An order can contain up to 50 distinct products.
- A new order starts with the `pending` status and no assigned driver.
- Order creation and its items are saved inside a database transaction.

### Order Statuses

Orders use the following statuses:

- `pending`
- `accepted`
- `picked_up`
- `delivered`
- `cancelled`

Allowed transitions:

```text
pending   -> accepted or cancelled
accepted  -> picked_up or cancelled
picked_up -> delivered
```

The `delivered` and `cancelled` statuses are final. Invalid transitions return HTTP 422.

### Assigning a Driver

`PATCH /api/orders/{id}/assign-driver` accepts a driver ID.

Example request:

```json
{
  "driver_id": 3
}
```

The order must be `pending` or `accepted`. The driver must exist, be active, and be available.

## Design Notes

### Local Scopes

The models define reusable query scopes for common conditions:

- `Order::active()` filters orders with `pending`, `accepted`, or `picked_up` status.
- `Order::forDriver($id)` filters orders assigned to a specific driver.
- `Order::today()` filters orders created today using a date range.
- `Order::olderThanMinutes($n)` filters orders older than a specified number of minutes.
- `Order::withStatus($status)` filters orders by status.
- `Product::available()` filters available products.
- `Driver::available()` filters drivers that are both active and available.

These scopes keep common query conditions in the models and allow them to be combined when needed.

### Active Restaurant Scope

`ActiveRestaurantScope` is a global scope on the `Restaurant` model. It filters out inactive restaurants from normal queries.

The order history endpoints intentionally bypass this scope when loading a restaurant related to an existing order. This allows historical orders to display their restaurant even if it has since been deactivated.

### Eager Loading and Lazy Loading

Order endpoints eager-load related records to reduce unnecessary database queries and avoid the N+1 problem. The order list also uses `withCount` to retrieve the number of order items without loading every item.

`Model::preventLazyLoading(! app()->isProduction())` is configured in `AppServiceProvider` to help detect accidental lazy loading during development and testing.

### Query Count

The query count for `GET /api/orders` was measured against the populated demo database with 3,000 orders, default pagination, and no filters.

The measured result was **5 SQL queries**:

1. The pagination count query.
2. The query that retrieves the current page of orders, including the `order_items_count` subquery from `withCount`.
3. The eager-loading query for customers.
4. The eager-loading query for drivers.
5. The eager-loading query for restaurants.

The `withCount` subquery is part of the orders SQL statement, not a separate database round trip. The measured query count describes the current implementation and may change if the query or its relationships are modified.

### Database Indexes

The migrations define indexes for common filtering patterns:

| Table | Column(s) | Purpose |
|---|---|---|
| `restaurants` | `is_active` | Supports filtering active restaurants |
| `products` | `restaurant_id`, `is_available` | Supports filtering available products for a restaurant |
| `orders` | `status` | Supports filtering orders by status |
| `orders` | `restaurant_id`, `status` | Supports filtering orders by restaurant and status |

Foreign keys also support relationships between the business tables. Check the migrations for the exact foreign-key constraints and index definitions.

## Project Structure

- `app/Models` - Eloquent models and query scopes.
- `app/Models/Scopes` - Global query scopes.
- `app/Http/Controllers` - API controllers.
- `app/Http/Requests` - Request validation.
- `app/Http/Resources` - JSON response formatting.
- `app/Services` - Delivery fee calculation.
- `app/Enums` - Order status values.
- `database/migrations` - Database schema and indexes.
- `database/factories` - Model factories.
- `database/seeders` - Demo data.
- `routes/api.php` - API routes.