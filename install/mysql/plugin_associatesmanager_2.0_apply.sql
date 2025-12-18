-- Apply DB changes for AssociatesManager v2.0
-- Adds matricule to associates and converts parts table into pivot (associates_id, supplier_id, nbparts, dates)

-- Add matricule to associates (if missing) and drop legacy columns if present
ALTER TABLE `glpi_plugin_associatesmanager_associates`
  ADD COLUMN IF NOT EXISTS `matricule` VARCHAR(64) DEFAULT NULL AFTER `name`;

-- Convert parts table to pivot-style columns (idempotent)
ALTER TABLE `glpi_plugin_associatesmanager_parts`
  ADD COLUMN IF NOT EXISTS `associates_id` INT UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `supplier_id` INT UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `nbparts` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
  ADD COLUMN IF NOT EXISTS `date_attribution` DATE DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `date_fin` DATE DEFAULT NULL;

-- Allow attaching an initial total number of parts to a Supplier.
-- If this column is > 0 it will be used as the denominator when computing
-- percent shares; when 0 the plugin falls back to summing active parts.
ALTER TABLE `glpi_suppliers`
  ADD COLUMN IF NOT EXISTS `nbparttotal` DECIMAL(15,4) NOT NULL DEFAULT '0.0000';

-- Add indexes if not present (MySQL 8+ supports IF NOT EXISTS)
CREATE INDEX IF NOT EXISTS `idx_associates_id` ON `glpi_plugin_associatesmanager_parts` (`associates_id`);
CREATE INDEX IF NOT EXISTS `idx_supplier_id` ON `glpi_plugin_associatesmanager_parts` (`supplier_id`);

-- Remove legacy columns if present
ALTER TABLE `glpi_plugin_associatesmanager_parts` DROP COLUMN IF EXISTS `valeur`;
ALTER TABLE `glpi_plugin_associatesmanager_associates` DROP COLUMN IF EXISTS `state`;
ALTER TABLE `glpi_plugin_associatesmanager_associates` DROP COLUMN IF EXISTS `suppliers_id`;

-- No automated migration from a dedicated history table is required here because
-- this plugin stores historical parts directly in `glpi_plugin_associatesmanager_parts`
-- by setting `date_fin`. If you have an older installation with a separate
-- `partshistories` table, perform a manual migration before applying this file.
