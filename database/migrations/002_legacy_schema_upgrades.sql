-- Idempotent upgrades for databases created before versioned migrations.
-- Safe to run on fresh installs that already include 001 constraints.

CREATE UNIQUE INDEX IF NOT EXISTS idx_tags_name ON tags(name);
CREATE UNIQUE INDEX IF NOT EXISTS idx_files_path ON files(path);
