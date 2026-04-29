CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "modes"(
  "id" varchar not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text not null,
  "icon" varchar not null,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);
CREATE UNIQUE INDEX "modes_slug_unique" on "modes"("slug");
CREATE TABLE IF NOT EXISTS "sub_modes"(
  "id" varchar not null,
  "mode_id" varchar not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text not null,
  "icon" varchar not null,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("mode_id") references "modes"("id") on delete cascade,
  primary key("id")
);
CREATE UNIQUE INDEX "sub_modes_mode_id_slug_unique" on "sub_modes"(
  "mode_id",
  "slug"
);
CREATE TABLE IF NOT EXISTS "catalog_items"(
  "id" varchar not null,
  "admin_id" varchar not null,
  "mode_id" varchar not null,
  "sub_mode_id" varchar not null,
  "name" varchar not null,
  "description" text not null,
  "width" numeric not null,
  "height" numeric not null,
  "depth" numeric not null,
  "dimension_unit" varchar not null default 'cm',
  "price" numeric not null,
  "currency" varchar not null default 'USD',
  "delivery_days" integer not null default '14',
  "category" varchar not null,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "model_url" varchar,
  "model_job_id" varchar,
  "model_status" varchar,
  "model_error" text,
  "model" varchar,
  "additional_categories" text,
  foreign key("admin_id") references "users"("id") on delete cascade,
  foreign key("mode_id") references "modes"("id") on delete cascade,
  foreign key("sub_mode_id") references "sub_modes"("id") on delete cascade,
  primary key("id")
);
CREATE INDEX "catalog_items_admin_id_index" on "catalog_items"("admin_id");
CREATE TABLE IF NOT EXISTS "catalog_item_images"(
  "id" integer primary key autoincrement not null,
  "catalog_item_id" varchar not null,
  "url" text not null,
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("catalog_item_id") references "catalog_items"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "catalog_item_colors"(
  "id" integer primary key autoincrement not null,
  "catalog_item_id" varchar not null,
  "name" varchar not null,
  "hex" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("catalog_item_id") references "catalog_items"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "materials"(
  "id" varchar not null,
  "admin_id" varchar not null,
  "mode_id" varchar not null,
  "sub_mode_id" varchar,
  "name" varchar not null,
  "type" varchar not null,
  "category" varchar not null,
  "color" varchar not null,
  "color_hex" varchar,
  "color_code" varchar,
  "price" numeric not null default '0',
  "price_per_unit" numeric not null,
  "currency" varchar not null default 'USD',
  "unit" varchar not null,
  "image" text,
  "image_url" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "categories" text,
  "sheet_width_cm" numeric,
  "sheet_height_cm" numeric,
  "grain_direction" varchar,
  "kerf_mm" numeric,
  "manufacturer" varchar,
  "types" text,
  foreign key("admin_id") references "users"("id") on delete cascade,
  foreign key("mode_id") references "modes"("id") on delete cascade,
  foreign key("sub_mode_id") references "sub_modes"("id") on delete set null,
  primary key("id")
);
CREATE INDEX "materials_admin_id_index" on "materials"("admin_id");
CREATE TABLE IF NOT EXISTS "module_images"(
  "id" integer primary key autoincrement not null,
  "module_id" varchar not null,
  "url" text not null,
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("module_id") references "modules"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "module_connection_points"(
  "id" integer primary key autoincrement not null,
  "module_id" varchar not null,
  "position" varchar not null,
  "type" varchar not null default 'standard',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("module_id") references "modules"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "module_compatibilities"(
  "module_id" varchar not null,
  "compatible_module_id" varchar not null,
  foreign key("module_id") references "modules"("id") on delete cascade,
  foreign key("compatible_module_id") references "modules"("id") on delete cascade,
  primary key("module_id", "compatible_module_id")
);
CREATE TABLE IF NOT EXISTS "orders"(
  "id" varchar not null,
  "admin_id" varchar not null,
  "customer_name" varchar not null,
  "customer_email" varchar not null,
  "customer_phone" varchar,
  "type" varchar not null,
  "total_price" numeric not null,
  "status" varchar not null default 'pending',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "payment_status" varchar not null default 'pending',
  "paypal_transaction_id" varchar,
  "tracking_number" varchar,
  foreign key("admin_id") references "users"("id") on delete cascade,
  primary key("id")
);
CREATE INDEX "orders_admin_id_index" on "orders"("admin_id");
CREATE TABLE IF NOT EXISTS "order_items"(
  "id" integer primary key autoincrement not null,
  "order_id" varchar not null,
  "item_type" varchar not null,
  "item_id" varchar,
  "name" varchar not null,
  "quantity" integer not null,
  "price" numeric not null,
  "custom_data" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("order_id") references "orders"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "order_item_materials"(
  "order_item_id" integer not null,
  "material_id" varchar not null,
  foreign key("order_item_id") references "order_items"("id") on delete cascade,
  foreign key("material_id") references "materials"("id") on delete cascade,
  primary key("order_item_id", "material_id")
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" varchar not null,
  "email" varchar not null,
  "password" varchar not null,
  "name" varchar not null,
  "company_name" varchar not null,
  "slug" varchar not null,
  "site_published_at" datetime,
  "selected_mode_id" varchar,
  "logo" varchar,
  "language" varchar not null default('en'),
  "currency" varchar not null default('AMD'),
  "email_verified_at" datetime,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "selected_sub_mode_ids" text,
  "paypal_email" varchar,
  "email_verification_token" varchar,
  "plan_tier" varchar not null default 'free',
  "trial_ends_at" datetime,
  "usage_month_start" date,
  "image3d_generations_this_month" integer not null default '0',
  "ai_chat_messages_this_month" integer not null default '0',
  "planner_material_ids" text,
  "use_custom_planner_catalog" tinyint(1) not null default '0',
  "public_site_layout" varchar not null default 'tunzone-classic-light',
  "public_site_texts" text,
  "public_site_theme" text,
  "custom_design_key" varchar,
  "stripe_customer_id" varchar,
  "stripe_subscription_id" varchar,
  "image3d_bonus_anchor_at" datetime,
  foreign key("selected_mode_id") references modes("id") on delete set null on update no action,
  primary key("id")
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE UNIQUE INDEX "users_slug_unique" on "users"("slug");
CREATE TABLE IF NOT EXISTS "modules"(
  "id" varchar not null,
  "admin_id" varchar not null,
  "mode_id" varchar not null,
  "sub_mode_id" varchar not null,
  "name" varchar not null,
  "description" text not null,
  "width" numeric not null,
  "height" numeric not null,
  "depth" numeric not null,
  "dimension_unit" varchar not null default('cm'),
  "price" numeric not null,
  "currency" varchar not null default('USD'),
  "image_url" text,
  "category" varchar not null,
  "is_active" tinyint(1) not null default('1'),
  "created_at" datetime,
  "updated_at" datetime,
  "model_url" varchar,
  "model_job_id" varchar,
  "model_status" varchar,
  "model_error" text,
  "placement_type" varchar not null default('floor'),
  "default_cabinet_material_id" varchar,
  "default_door_material_id" varchar,
  "pricing_body_weight" numeric not null default '1',
  "pricing_door_weight" numeric not null default '1',
  "default_handle_id" varchar,
  "template_options" text,
  "allowed_handle_ids" text,
  "is_configurable_template" tinyint(1) not null default '0',
  foreign key("sub_mode_id") references sub_modes("id") on delete cascade on update no action,
  foreign key("mode_id") references modes("id") on delete cascade on update no action,
  foreign key("admin_id") references users("id") on delete cascade on update no action,
  foreign key("default_cabinet_material_id") references "materials"("id") on delete set null,
  foreign key("default_door_material_id") references "materials"("id") on delete set null,
  primary key("id")
);
CREATE INDEX "modules_admin_id_index" on "modules"("admin_id");
CREATE TABLE IF NOT EXISTS "material_templates"(
  "id" varchar not null,
  "manufacturer" varchar not null,
  "external_code" varchar,
  "name" varchar not null,
  "type" varchar not null,
  "categories" text not null,
  "category" varchar not null,
  "color" varchar not null,
  "color_hex" varchar,
  "color_code" varchar,
  "unit" varchar not null default 'sqm',
  "image_url" text,
  "source_url" text,
  "sheet_width_cm" numeric,
  "sheet_height_cm" numeric,
  "grain_direction" varchar,
  "kerf_mm" numeric,
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "types" text,
  primary key("id")
);
CREATE INDEX "material_templates_manufacturer_index" on "material_templates"(
  "manufacturer"
);
CREATE INDEX "material_templates_external_code_index" on "material_templates"(
  "external_code"
);
CREATE TABLE IF NOT EXISTS "audit_logs"(
  "id" varchar not null,
  "admin_id" varchar not null,
  "actor_user_id" varchar not null,
  "action" varchar not null,
  "subject_type" varchar,
  "subject_id" varchar,
  "metadata" text,
  "ip_address" varchar,
  "user_agent" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  primary key("id")
);
CREATE INDEX "audit_logs_admin_id_created_at_index" on "audit_logs"(
  "admin_id",
  "created_at"
);
CREATE INDEX "audit_logs_actor_user_id_created_at_index" on "audit_logs"(
  "actor_user_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "planner_requests"(
  "id" varchar not null,
  "admin_id" varchar not null,
  "text" text not null,
  "image_paths" text,
  "ai_interpretation" text,
  "result" text,
  "estimated_price" numeric not null default '0',
  "status" varchar not null default 'completed',
  "error" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("admin_id") references "users"("id") on delete cascade,
  primary key("id")
);
CREATE INDEX "planner_requests_admin_id_created_at_index" on "planner_requests"(
  "admin_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" varchar not null,
  "name" varchar not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE TABLE IF NOT EXISTS "stripe_webhook_events"(
  "id" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'0001_01_01_000003_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(5,'2024_01_01_000001_create_modes_table',1);
INSERT INTO migrations VALUES(6,'2024_01_01_000002_create_sub_modes_table',1);
INSERT INTO migrations VALUES(7,'2024_01_01_000003_create_catalog_items_table',1);
INSERT INTO migrations VALUES(8,'2024_01_01_000004_create_catalog_item_images_table',1);
INSERT INTO migrations VALUES(9,'2024_01_01_000005_create_catalog_item_colors_table',1);
INSERT INTO migrations VALUES(10,'2024_01_01_000006_create_materials_table',1);
INSERT INTO migrations VALUES(11,'2024_01_01_000007_create_modules_table',1);
INSERT INTO migrations VALUES(12,'2024_01_01_000008_create_module_images_table',1);
INSERT INTO migrations VALUES(13,'2024_01_01_000009_create_module_connection_points_table',1);
INSERT INTO migrations VALUES(14,'2024_01_01_000010_create_module_compatibilities_table',1);
INSERT INTO migrations VALUES(15,'2024_01_01_000011_create_orders_table',1);
INSERT INTO migrations VALUES(16,'2024_01_01_000012_create_order_items_table',1);
INSERT INTO migrations VALUES(17,'2024_01_01_000013_create_order_item_materials_table',1);
INSERT INTO migrations VALUES(18,'2024_01_01_000015_change_selected_sub_mode_to_json',1);
INSERT INTO migrations VALUES(19,'2024_01_01_000016_add_model_fields_to_catalog_items',1);
INSERT INTO migrations VALUES(20,'2024_01_01_000017_add_model_to_catalog_items',1);
INSERT INTO migrations VALUES(21,'2024_01_01_000018_add_paypal_and_payment_fields',1);
INSERT INTO migrations VALUES(22,'2024_01_01_000020_add_model_and_placement_to_modules',1);
INSERT INTO migrations VALUES(23,'2025_04_22_000001_add_email_verification_token_to_users_table',1);
INSERT INTO migrations VALUES(24,'2026_04_13_000001_add_module_template_fields_to_modules_table',1);
INSERT INTO migrations VALUES(25,'2026_04_15_000001_clear_catalog_materials',1);
INSERT INTO migrations VALUES(26,'2026_04_16_000001_add_categories_json_to_materials_table',1);
INSERT INTO migrations VALUES(27,'2026_04_17_000001_add_sheet_size_to_materials_table',1);
INSERT INTO migrations VALUES(28,'2026_04_19_000001_add_tracking_number_to_orders_table',1);
INSERT INTO migrations VALUES(29,'2026_04_21_000001_add_manufacturer_to_materials_table',1);
INSERT INTO migrations VALUES(30,'2026_04_21_000001_create_material_templates_table',1);
INSERT INTO migrations VALUES(31,'2026_04_21_000001_migrate_hy_language_to_en',1);
INSERT INTO migrations VALUES(32,'2026_04_21_100000_add_plan_and_usage_to_users_table',1);
INSERT INTO migrations VALUES(33,'2026_04_21_100001_create_audit_logs_table',1);
INSERT INTO migrations VALUES(34,'2026_04_21_120000_add_planner_material_ids_to_users_table',1);
INSERT INTO migrations VALUES(35,'2026_04_22_000001_add_use_custom_planner_catalog_to_users_table',1);
INSERT INTO migrations VALUES(36,'2026_04_22_000001_clear_material_templates_table',1);
INSERT INTO migrations VALUES(37,'2026_04_22_000001_update_360_280_sheet_sizes_to_360_180',1);
INSERT INTO migrations VALUES(38,'2026_04_23_000001_add_types_json_to_materials_table',1);
INSERT INTO migrations VALUES(39,'2026_04_24_000001_remove_default_material_templates',1);
INSERT INTO migrations VALUES(40,'2026_04_25_000001_add_types_json_to_material_templates_table',1);
INSERT INTO migrations VALUES(41,'2026_04_26_000001_set_existing_users_plan_tier_business_pro',1);
INSERT INTO migrations VALUES(42,'2026_04_26_010000_create_planner_requests_table',1);
INSERT INTO migrations VALUES(43,'2026_04_26_020000_add_public_site_config_to_users_table',1);
INSERT INTO migrations VALUES(44,'2026_04_28_120000_recreate_personal_access_tokens_for_uuid_tokenable',1);
INSERT INTO migrations VALUES(45,'2026_04_28_140000_add_stripe_billing_to_users_table',1);
INSERT INTO migrations VALUES(46,'2026_04_28_140001_create_stripe_webhook_events_table',1);
INSERT INTO migrations VALUES(47,'2026_04_28_160000_add_image3d_bonus_anchor_at_to_users_table',1);
INSERT INTO migrations VALUES(48,'2026_04_29_120000_add_additional_categories_to_catalog_items',1);
