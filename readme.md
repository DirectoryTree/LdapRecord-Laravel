<p align="center">
    <img src="https://ldaprecord.com/logo.svg" width="300" alt="LdapRecord-Laravel">
</p>

<p align="center">Integrate LDAP into your Laravel application.</p>

<p align="center">
    <a href="https://github.com/DirectoryTree/LdapRecord-Laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/DirectoryTree/LdapRecord-Laravel/run-tests.yml?branch=master&amp;style=flat-square" alt="Tests"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/packagist/dt/directorytree/ldaprecord-laravel.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/packagist/v/directorytree/ldaprecord-laravel.svg?style=flat-square" alt="Latest Version"></a>
    <a href="https://github.com/DirectoryTree/LdapRecord-Laravel/blob/master/license.md"><img src="https://img.shields.io/github/license/DirectoryTree/LdapRecord-Laravel?style=flat-square" alt="License"></a>
</p>

<p align="center">
    <a href="#installation">Installation</a>
    <span> · </span>
    <a href="https://ldaprecord.com/docs/laravel/v4/">Documentation</a>
    <span> · </span>
    <a href="https://github.com/DirectoryTree/LdapRecord-Browser">Directory Browser</a>
    <span> · </span>
    <a href="https://github.com/DirectoryTree/LdapRecord-Laravel/discussions/new">Post a Question</a>
</p>

---

## Installation

Install the package via Composer:

```bash
composer require directorytree/ldaprecord-laravel
```

See the [installation guide](https://ldaprecord.com/docs/laravel/v4/installation/) for requirements and setup.

## Features

### Developer Experience First

LdapRecord focuses on clean, easy to understand syntax along with thorough documentation.

### Authenticate LDAP Users

Allow LDAP users to log into your application and control which users can login via [Scopes](https://ldaprecord.com/docs/laravel/v4/usage/#scopes) and [Rules](https://ldaprecord.com/docs/laravel/v4/auth/plain/configuration#rules).

### Import & Synchronize LDAP users

Import users from your directory via [command](https://ldaprecord.com/docs/laravel/v4/auth/database/importing): `php artisan ldap:import`.

### Multi-Domain Support

Authenticate users from as many LDAP domains as you'd like. Support comes [out of the box](https://ldaprecord.com/docs/laravel/v4/auth/multi-domain).

### Eloquent Query Builder

Search for LDAP objects with a [fluent and easy to use interface](https://ldaprecord.com/docs/core/v4/searching) you're used to. You'll feel right at home.

### Active Record LDAP Models

LDAP objects are [individual models](https://ldaprecord.com/docs/core/v4/models). Persist them to your LDAP server with a single `save()`.

### LDAP Directory Emulator

Test [authenticating](https://ldaprecord.com/docs/laravel/v4/auth/testing/#getting-started) and
[querying users](https://ldaprecord.com/docs/laravel/v4/testing/#getting-started) without
changing your application code.

Create, update, and delete LDAP objects without touching a real LDAP server.

## LdapRecord-Laravel is Supportware™

If you require support using LdapRecord-Laravel, a [sponsorship](https://github.com/sponsors/stevebauman) is required :pray:

Thank you for your understanding :heart:

## Security Vulnerabilities

If you discover a security vulnerability within LdapRecord-Laravel, please send an e-mail to Steve Bauman via [steven_bauman@outlook.com](mailto:steven_bauman@outlook.com).

All security vulnerabilities will be promptly addressed.
