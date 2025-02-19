# Quick Install Guide

This guide has been written for Ubuntu 24.04 LTS and tested on Ubuntu 24.04 on Windows Subsystem for Linux 2 (WSL2).

## Step 1: Installation

Make sure everything is up to date:

```bash
sudo apt update
sudo apt upgrade
```

Install PHP (with extensions):

```bash
sudo apt install php php-cli php-pgsql php-curl php-mbstring php-zip php-bcmath php-xml php-intl
```

If this doesn't install PHP 8.3, change php to php8.3 throughout the command. You need the [Ondrej PHP PPA](https://launchpad.net/~ondrej/+archive/ubuntu/php) for this to work.

Install Postgres:

```bash
sudo apt install postgresql postgresql-contrib
```

Install Composer:

```bash
sudo apt install composer
```

Install Symfony CLI:
Follow the Debian/Ubuntu instructions on [the Symfony download page](https://symfony.com/download).

Install Node:

First, follow the instructions for installing [Node Version Manager](https://github.com/nvm-sh/nvm?tab=readme-ov-file#installing-and-updating).

Now, install Node v22:
```bash
nvm install 22
sudo apt-get install build-essential
```

## Step 2: Setup Database

Start the postgres service (if it hasn't started yet):

```bash
sudo systemctl start postgresql
```
or if you're in WSL:

```bash
sudo service postgresql start
```

Create a `webcie` user:

```bash
sudo -u postgres createuser --interactive --pwprompt webcie
```

Enter password and answer no to all other questions.

Create a database:

```bash
sudo -u postgres createdb -O webcie --encoding=UTF8 --template=template0 webcie
```

If you get `WARNING:  could not flush dirty data: Function not implemented` (this may happen on WSL1), interrupt (`CTRL+C`) and do the following:  

Open `/etc/postgresql/<postgres version number>/main/postgresql.conf` and change the following settings:

```
bgwriter_flush_after = 0
backend_flush_after = 0
fsync = off
wal_writer_flush_after = 0
checkpoint_flush_after = 0
```

## Step 3: Setup Repository

Clone the repository and `cd` into its directory. If you're using WSL make sure to clone it to a location you can easily access from Windows.

Copy the config files:

```bash
cp .env .env.local
cp .env.test .env.test.local
cp config.js config.dev.js
```

Adjust `.env.local` to match your database settings as follows.

Install PHP dependencies:

```bash
composer install
```

Install Node dependencies:

```bash
npm ci
```

Load bare-bones database:

```bash
sudo -u postgres psql webcie < data/webcie-minimal.sql
```

Set password for test user (ID = 1):

```bash
php bin/console app:set-password 1
```

## Step 4: Run locally

To build the front-end:

```bash
npm run dev
```

To run the website, execute the following in the root folder of your repository:

```bash
symfony server:start
```

Now, you should be able to load `localhost:8000/` in a browser and log in with `test@svcover.nl` and the password you just set.

For more information on the Symfony developmen server, check out [the documentation](https://symfony.com/doc/current/setup/symfony_server.html).

Please note that the bare-bones database is quite empty. If you need more content, you should add it yourself. The `test@svcover.nl` user is a member of the AC/DCee in this setup, so you should be able to do anything you need with this user. Feel free to create more users if you want.


## Step 5: Fix additional functionality

Some things will not work with this setup.

### Fixing config

Photo albums will not show photos. To fix this, enable the `APP_PHOTOS_SCALED_URL` setting in `.env.local`.

### ImageMagick

Profile pictures won't render correctly. For this you need ImageMagick. This can be installed for your PHP installation by running

```bash
sudo apt install php-imagick
```

This should fix the issue (in theory at least).
