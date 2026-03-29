<?php
/**
 * Migration: Add performance indexes to tblCMAMonitoring
 *
 * The cmamonitoring form list query was slow due to missing indexes on
 * frequently queried/sorted columns. This migration adds indexes for:
 * - datestamp: used for ORDER BY and date range filtering
 * - Username: used in quickSearchFields
 * - Actie: used in quickSearchFields and list display
 * - LogLevel: used for log level filtering
 *
 * Note: Form column already has an index (migration 5.6.0).
 * Note: Notificatie is a MEMO field and cannot be indexed.
 */
