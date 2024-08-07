# FlatLayer CMS Image Setup and Management

This guide provides detailed instructions for setting up and managing images in your FlatLayer CMS project. It covers image processing, caching, optimization, and maintenance.

## Prerequisites

Before setting up image handling in FlatLayer CMS, ensure you have the following:

- PHP 8.2 or higher
- Composer
- Laravel 11.x
- Imagick PHP extension (typically pre-installed on Laravel Forge)

## Image Processing Setup

FlatLayer CMS uses the Intervention Image library for image processing. This library is already included in the project dependencies.

1. Verify Imagick installation:

   ```bash
   php -m | grep imagick
   ```

   If Imagick is not listed, you'll need to install it. On most systems, you can use:

   ```bash
   sudo apt-get install php-imagick
   ```

   Restart your web server after installation.

2. Configure the image driver in `config/image.php`:

   ```php
   <?php

   return [
       'driver' => 'imagick'
   ];
   ```

## Image Caching

FlatLayer CMS implements an image caching system to improve performance. Cached images are stored in the `storage/app/public/cache/images` directory.

To enable image caching:

1. Ensure your `config/flatlayer.php` file has the following setting:

   ```php
   'media' => [
       'use_signatures' => true,
   ],
   ```

2. In your `.env` file, set the `CACHE_DRIVER` to your preferred caching method (e.g., file, redis):

   ```
   CACHE_DRIVER=file
   ```

## Image Optimization

FlatLayer CMS uses the Spatie Image Optimizer package for image optimization. This package is already included in the project dependencies.

To set up image optimization:

1. Install the required optimization tools on your server. For detailed instructions, refer to the [Spatie Image Optimizer documentation](https://github.com/spatie/image-optimizer#optimization-tools).

2. The optimization is automatically applied when processing images through the `ImageService`.

## Maintenance

To keep your image cache manageable, FlatLayer CMS provides a command to clear old cached images.

### Setting Up Weekly Cache Clearing

1. The `image:clear-cache` command is defined in `app/Console/Commands/ClearImageCache.php` with the following signature:

   ```php
   protected $signature = 'image:clear-cache {days=30 : Number of days old to clear}';
   ```

   This is the correct way to set a default value for the `days` argument.

2. To run this command weekly, add the following to your `app/Console/Kernel.php` file in the `schedule` method:

   ```php
   protected function schedule(Schedule $schedule)
   {
       $schedule->command('image:clear-cache')->weekly();
   }
   ```

3. Ensure your server's cron job is set up to run Laravel's scheduler. Add this line to your crontab:

   ```
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

### Manual Cache Clearing

You can manually clear the image cache at any time by running:

```bash
php artisan image:clear-cache
```

Or specify a custom number of days:

```bash
php artisan image:clear-cache 60
```

This will clear cached images older than 60 days.

## Conclusion

With these steps, your FlatLayer CMS should be fully set up for efficient image processing, caching, and optimization. Regular maintenance through the scheduled cache clearing will help manage disk space and keep your application running smoothly.
