# Fleet Database Setup

This document explains how to set up the vehicles, providers, and drivers database for the Fleet Management page.

## Files Created

### Vehicles
1. **Model**: `app/Models/Vehicle.php` - Eloquent model for vehicles
2. **Migration**: `database/migrations/2026_04_26_000001_create_vehicles_table.php` - Database schema
3. **Seeder**: `database/seeders/VehicleSeeder.php` - Sample data

### Fleet Providers
1. **Model**: `app/Models/FleetProvider.php` - Eloquent model for providers
2. **Migration**: `database/migrations/2026_04_26_000002_create_fleet_providers_table.php` - Database schema
3. **Seeder**: `database/seeders/FleetProviderSeeder.php` - Sample data

### Drivers
1. **Model**: `app/Models/Driver.php` - Eloquent model for drivers
2. **Migration**: `database/migrations/2026_04_26_000003_create_drivers_table.php` - Database schema
3. **Seeder**: `database/seeders/DriverSeeder.php` - Sample data

### Controller
- **Controller Update**: `app/Http/Controllers/LmsController.php` - Fetches vehicles, providers, and drivers from database

## Database Setup Commands

Run these commands in your terminal:

```bash
# Run the migrations to create the tables
php artisan migrate

# Seed the database with sample data
php artisan db:seed
```

## Database Schema

The `vehicles` table has the following columns:

- `id` - Primary key (auto-increment)
- `code` - Vehicle identifier (e.g., "Coach 03", "Van 22")
- `provider_id` - Foreign key to providers table (nullable)
- `plate_number` - Vehicle registration plate (nullable)
- `vehicle_type` - Vehicle type (Coach, Minivan, etc.)
- `capacity` - Number of seats (nullable)
- `fuel_level` - Fuel level percentage (nullable)
- `status` - Vehicle status (available, on_job, maintenance, standby)
- `is_active` - Active status flag (nullable)
- `notes` - Additional notes (nullable)
- `category` - Vehicle category (Team, Official, VIP, Media) (nullable)
- `created_at` - Timestamp
- `updated_at` - Timestamp

## Vehicle Statuses

- **available** - Vehicle is ready for assignment
- **on_job** - Vehicle is currently on a job
- **maintenance** - Vehicle is undergoing maintenance
- **standby** - Vehicle is on standby

## Vehicle Categories

- **Team** - For team transportation
- **Official** - For official use
- **VIP** - For VIP guests
- **Media** - For media personnel

## Fleet Providers Schema

The `fleet_providers` table has the following columns:

- `id` - Primary key (auto-increment)
- `code` - Provider identifier (e.g., "P001", "P002")
- `name` - Provider company name
- `contact_person` - Contact person name (nullable)
- `phone` - Contact phone number (nullable)
- `email` - Contact email address (nullable)
- `total_vehicles` - Number of vehicles (default: 0)
- `total_drivers` - Number of drivers (default: 0)
- `rating` - Provider rating out of 5 (default: 0.0)
- `status` - Provider status (active, standby)
- `notes` - Additional notes about provider (nullable)
- `is_active` - Active status flag (nullable)
- `created_at` - Timestamp
- `updated_at` - Timestamp

## Provider Statuses

- **active** - Provider is currently active and available
- **standby** - Provider is on standby

## Drivers Schema

The `drivers` table has the following columns:

- `id` - Primary key (auto-increment)
- `provider_id` - Foreign key to fleet_providers table (nullable)
- `name` - Driver full name (nullable)
- `phone` - Driver phone number (nullable)
- `license_number` - Driver license number/type (e.g., "D", "D+E", "B") (nullable)
- `status` - Driver status (available, on_shift, off, rest)
- `created_at` - Timestamp
- `updated_at` - Timestamp

## Driver Statuses

- **available** - Driver is available for assignment
- **on_shift** - Driver is currently on shift
- **off** - Driver is off duty
- **rest** - Driver is on rest/break

## Model Features

The Vehicle model includes helpful scopes:

```php
// Get only available vehicles
Vehicle::available()->get();

// Get vehicles on job
Vehicle::onJob()->get();

// Get vehicles in maintenance
Vehicle::maintenance()->get();

// Get vehicles on standby
Vehicle::standby()->get();
```

The FleetProvider model includes helpful scopes:

```php
// Get only active providers
FleetProvider::active()->get();

// Get standby providers
FleetProvider::standby()->get();
```

The Driver model includes helpful scopes:

```php
// Get only available drivers
Driver::available()->get();

// Get drivers on shift
Driver::onShift()->get();

// Get off-duty drivers
Driver::off()->get();

// Get resting drivers
Driver::rest()->get();
```

## Component Changes

The Fleet.vue component now:
- Receives **vehicles**, **providers**, and **drivers** as props from the controller
- Uses `v.code` as the unique identifier for vehicles
- Displays `vehicle_type` instead of `type`
- Displays `fuel_level` instead of `fuel`
- Uses `on_job` status instead of `in-use`
- Shows `plate_number` and `category` columns for vehicles
- Shows providers table with code, name, contact, phone, rating, and status
- Shows drivers table with name, phone, license number, and status
- No longer has hardcoded vehicle, provider, or driver data
- Refresh button reloads vehicles, providers, and drivers from database

## Next Steps

To add more vehicles, you can:

1. **Via Tinker** (for testing):
```bash
php artisan tinker
Vehicle::create([
    'code' => 'Bus 01',
    'vehicle_type' => 'Bus',
    'capacity' => 45,
    'fuel_level' => '85%',
    'plate_number' => 'XYZ-9999',
    'category' => 'Official',
    'status' => 'available',
    'is_active' => 1
]);
```

2. **Via API** - Create a vehicle management interface
3. **Via Database** - Insert directly into the database
4. **Via Seeder** - Add to VehicleSeeder.php and re-run

To add more providers:

```bash
php artisan tinker
FleetProvider::create([
    'code' => 'P005',
    'name' => 'Express Transport',
    'contact_person' => 'J. Smith',
    'phone' => '+33 6 78 90 05',
    'email' => 'j.smith@express.com',
    'total_vehicles' => 10,
    'total_drivers' => 15,
    'rating' => 4.6,
    'status' => 'active',
    'notes' => '24/7 availability, premium service',
    'is_active' => 1
]);
```

To add more drivers:

```bash
php artisan tinker
Driver::create([
    'name' => 'John Doe',
    'phone' => '+33 6 78 90 10',
    'license_number' => 'D+E',
    'status' => 'available',
    'provider_id' => 1
]);
```

## Refreshing Data

To reload the data in the browser after database changes:
- The page automatically fetches fresh data on load
- Click the refresh button (🔄) to refetch vehicles, providers, and drivers without page reload
