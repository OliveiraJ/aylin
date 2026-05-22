# aylin

Basic fuzzy file search and indexing tool with tags, built in PHP and SQLite.

## Requirements

- PHP 8.4+
- Composer

## Setup

```bash
composer install
php bin/migrate.php
```

The SQLite database is created at `database/aylin.db` (gitignored).

## Run tests

```bash
composer test
```

Tests use an in-memory SQLite database and do not depend on `database/aylin.db`.

## CLI usage

### Search the filesystem (no database)

```bash
php fuzzy_search.php config . --threshold=0.5
php fuzzy_search.php readme . --recursive --ext=md,txt
```

### Index a directory into the database

```bash
php fuzzy_search.php --index . --recursive --ext=php,md
```

Re-indexing the same path updates the existing record (upsert by unique path).

### Search indexed files

```bash
php fuzzy_search.php --indexed config --threshold=0.4
php fuzzy_search.php --indexed config --tag-id=1
```

### Attach a tag to an indexed file

```bash
php fuzzy_search.php --attach 1 php
```

## Project layout

- `src/Domain/` — entities (`File`, `Tag`) and repository contracts
- `src/Application/` — use cases (`IndexDirectory`, `SearchFiles`, `TagFile`)
- `src/Infraestructure/` — PDO repositories and persistence
- `fuzzy_search.php` — CLI entrypoint
- `database/initial_script/init.sql` — schema

## Development checklist

See `docs/CHECKLIST.md` for remaining improvements and review notes.
