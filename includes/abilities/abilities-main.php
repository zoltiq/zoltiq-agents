<?php

namespace Zoltiq\Agents\Includes;

use \Zoltiq\Agents\Includes\Abilities\Zoltiq_Core_Abilities_Bootstrap;

defined( 'ABSPATH' ) || exit;

final class Main {

	protected static $instance = null;

	public $loader;

	private function __construct() {

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/list-plugins.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/activate-plugin.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/deactivate-plugin.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/install-plugin.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/update-plugin.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/check-plugin-updates.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/read-plugin-structure.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/read-plugin-code.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/manage-plugin-files.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/plugins/get-plugin-lifecycle-context.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/activate-theme.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/delete-theme.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/install-theme.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/update-theme.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/list-themes.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/read-theme-structure.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/read-theme-code.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/edit-theme-file.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/themes/get-theme-lifecycle-context.php';
		
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/read-file.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/create-file.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/edit-file.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/delete-file.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/read-wp-config.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/edit-wp-config.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/read-debug-log.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/clear-debug-log.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/create-zip-backup.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/upload-zip-backup.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/extract-zip-backup.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/download-zip-backup.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/list-zip-backups.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/file-manager/delete-zip-backup.php';
		
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cache/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cache/flush-object-cache.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cache/flush-transients.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cache/flush-rewrite-rules.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/extract-db-schema.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/run-db-select-query.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/insert-db-row.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/update-db-rows.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/delete-db-rows.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/list-db-tables.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/explain-db-query.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/get-db-stats.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/database/optimize-db-tables.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/get-permalink-structure.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/set-permalink-structure.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/flush-permalink-structure.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/get-site-title.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/update-site-title.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/get-tagline.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/update-tagline.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/update-site-logo.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/get-site-icon.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/settings/update-site-icon.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/get-user.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/list-users.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/create-user.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/update-user.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/delete-user.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/reset-user-password.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/list-user-roles.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/get-role-capabilities.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/users/get-current-user-access.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/list-block-patterns.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/read-block-pattern.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/create-block-pattern.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/update-block-pattern.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/delete-block-pattern.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/list-block-templates.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/read-block-template.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/create-block-template.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/update-block-template.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/delete-block-template.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/list-global-styles.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/read-global-style.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/create-global-style.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/update-global-style.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/delete-global-style.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/read-theme-json.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/update-theme-json.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/list-block-style-variations.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/read-block-style-variation.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/create-block-style-variation.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/update-block-style-variation.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/delete-block-style-variation.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/list-blocks.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/read-block.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/list-block-template-parts.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/read-block-template-part.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/create-block-template-part.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/update-block-template-part.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/delete-block-template-part.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/get-site-editor-context.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/refresh-site-editor-context.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/list-reusable-blocks.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/block/list-block-areas.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/fonts/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/fonts/list-font-families.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/fonts/get-font-family.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/fonts/create-font-family.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/fonts/delete-font-family.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/fonts/list-font-faces.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/fonts/get-font-face.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/fonts/create-font-face.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/fonts/delete-font-face.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/create-post.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/get-post.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/list-post-revisions.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/list-posts.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/update-post.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/delete-post.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/get-post-meta.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/update-post-meta.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/create-page.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/get-page.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/list-page-revisions.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/list-pages.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/update-page.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/list-post-types.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/create-cpt-item.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/get-cpt-item.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/list-cpt-item-revisions.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/list-cpt-items.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/update-cpt-item.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/delete-cpt-item.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/list-post-translations.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/set-post-language.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/link-post-translation.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/list-jet-engine-options-pages.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/get-jet-engine-options-page.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/update-jet-engine-options-page-field.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/update-post-block.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content/inspect-post-autosaves.php';
		
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/list-taxonomies.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/get-taxonomy.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/list-cpt-taxonomies.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/list-terms.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/get-term.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/create-term.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/update-term.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/delete-term.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/assign-cpt-terms.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/taxonomies/set-term-image.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/upload-media.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/get-media.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/list-media.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/update-media.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/delete-media.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/get-media-meta.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/update-media-meta.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/list-upload-mime-types.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/update-upload-mime-types.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/media/rename-media-file.php';
		
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/create-comment.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/get-comment.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/list-comments.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/update-comment.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/delete-comment.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/approve-comment.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/unapprove-comment.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/mark-as-spam.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/get-comment-meta.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/update-comment-meta.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/comments/bulk-update-comments.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/list-menus.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/get-menu.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/create-menu.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/update-menu.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/delete-menu.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/list-menu-items.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/get-menu-item.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/create-menu-item.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/update-menu-item.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/delete-menu-item.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/get-navigation-context.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/menus/list-navigation-locations.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/options/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/options/get-option.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/options/update-option.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/options/delete-option.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/options/list-options.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/options/search-options.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/list-cron-jobs.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/get-cron-job.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/get-next-cron-run.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/check-cron-job-exists.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/list-cron-schedules.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/get-cron-schedule.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/get-cron-status.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/list-overdue-cron-jobs.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/create-cron-job.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/update-cron-job.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/run-cron-job-now.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/create-cron-schedule.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/delete-cron-job.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/delete-cron-jobs-by-hook.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/cron/delete-cron-schedule.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/site-health/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/site-health/get-site-health-status.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/site-health/get-site-health-info.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/site-health/get-site-maintenance-report.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/core/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/core/check-wp-core-update.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/core/update-wp-core.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/core/rollback-wp-core.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/core/reinstall-wp-core.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/admin-menu/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/admin-menu/get-admin-menu-context.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/admin-menu/refresh-admin-menu-context.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/admin-menu/list-admin-menu-pages.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/admin-menu/get-admin-menu-navigation-target.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/admin-menu/list-admin-settings.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/refresh-content-index-batch.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/search-content-items.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/search-content-chunks.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/find-related-content.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/find-internal-links.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/get-internal-link-policy.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/create-internal-link-suggestions.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/list-internal-link-suggestions.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/review-internal-link-suggestion.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/apply-internal-link-suggestion.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/audit-internal-links.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/content-search/documents-search.php';
		
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/recovery/category-registrar.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/recovery/get-recovery-mode-status.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/recovery/list-paused-plugins.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/recovery/list-paused-themes.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/recovery/get-recovery-exit-url.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/recovery/unpause-plugin.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/recovery/unpause-theme.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/recovery/list-recent-fatal-errors.php';

		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/backups-storage.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/block-info.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/cron-helpers.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/file-mods-guard.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/jet-engine-helpers.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/lifecycle-event-log.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/mime-types-store.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/multilang-helpers.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/plugin-helpers.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/theme-helpers.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/user-helpers.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/zip-target-resolver.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/block-style-variations/variation-db.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/block-style-variations/variation-detector.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/block-style-variations/variation-file.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/global-styles/global-styles-db.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/global-styles/global-styles-detector.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/global-styles/global-styles-file.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/pattern/pattern-db.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/pattern/pattern-detector.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/pattern/pattern-helper.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/template/template-db.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/template/template-detector.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/template/template-file.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/template-part/template-part-db.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/template-part/template-part-detector.php';
		require ZOLTIQ_AGENTS_PATH . 'includes/abilities/utilities/template-part/template-part-file.php';




		$this->loader = Zoltiq_Loader::instance();

		$core_abilities_bootstrap = Zoltiq_Core_Abilities_Bootstrap::instance();
		$core_abilities_bootstrap->register_category_callbacks( $this->loader );
		$this->loader->add_action( 'plugins_loaded', $core_abilities_bootstrap, 'register_abilities', 20 );
			
		$this->loader->add_action( 'wp_abilities_api_init', $this, 'register_abilities', 5 );
					

	}


	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$definitions =  apply_filters( 'zoltiq_abilities_api_init', array() ); 
		foreach ( $definitions as $definition ) {
		//	if ( ! $this->is_permitted( $definition, $config ) ) {
		//		continue;
		//	}
			wp_register_ability( $definition['name'], $definition['args'] );
		}
	}


	public function run() {
		$this->loader->run();
	}

}
