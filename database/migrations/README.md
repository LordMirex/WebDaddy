# Database Migrations

This directory contains database migration scripts for WebDaddy Empire.

## Running Migrations

Migrations should be run via PHP command line:

```bash
php database/migrations/fix_bounce_tracking.php
```

## Available Migrations

### fix_bounce_tracking.php
**Purpose**: Backfills the `is_bounce` field in the `session_summary` table to ensure accurate bounce rate statistics.

**When to run**: 
- After deploying the analytics bounce tracking fix
- On any existing production database
- Safe to run multiple times (idempotent)

**What it does**:
- Sets `is_bounce=1` for sessions with `total_pages=1` (single-page visits)
- Sets `is_bounce=0` for sessions with `total_pages>1` (multi-page visits)

**Example output**:
```
Starting bounce tracking migration...

Sessions to update:
  - Single-page visits (set is_bounce=1): 1583
  - Multi-page visits (set is_bounce=0): 0

✅ Migration completed successfully!
  - Updated 1583 single-page sessions to is_bounce=1
  - Updated 0 multi-page sessions to is_bounce=0

New bounce statistics:
  - Total sessions: 1,679
  - Bounce sessions: 1,583
  - Bounce rate: 94.3%
```

## Migration Best Practices

1. **Backup first**: Always backup your database before running migrations
2. **Test locally**: Run migrations on a local copy of the database first
3. **Check output**: Review the migration output to ensure it ran correctly
4. **Monitor**: Check the admin analytics dashboard after migration to verify data accuracy
