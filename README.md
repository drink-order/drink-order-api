<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Project Setup and Running Guide

## Step 1: Setup Environment

1. Copy `.env.example` to `.env`
    ```sh
    cp .env.example .env
    ```
2. Configure the database settings in the `.env` file to use PostgreSQL.

## Step 2: Install Dependencies

1. Install PHP dependencies using Composer:
    ```sh
    composer install
    ```
2. Install JavaScript dependencies using npm:
    ```sh
    npm install
    ```

## Step 3: Generate Application Key

1. Generate the application key:
    ```sh
    php artisan key:generate
    ```

## Step 4: Run Migrations

1. Run the database migrations:
    ```sh
    php artisan migrate:fresh --seed
    ```

## Step 5: Start Development Server

1. Simply run:
    ```sh
    composer run dev
    ```

## Notes

- Ensure you have PHP 8.4.2 and Node.js 22 installed on your machine.
- For more information, refer to the [Laravel documentation](https://laravel.com/docs).

