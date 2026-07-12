create table if not exists settings (
    name varchar(120) primary key,
    value text null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists users (
    id bigint unsigned primary key auto_increment,
    name varchar(160) not null,
    email varchar(180) not null unique,
    password_hash varchar(255) not null,
    role varchar(80) not null default 'editor',
    is_active tinyint not null default 1,
    created_at varchar(32) not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists media (
    id bigint unsigned primary key auto_increment,
    path varchar(255) not null unique,
    original_name varchar(255) not null default '',
    extension varchar(20) not null default '',
    mime_type varchar(120) not null default '',
    size bigint unsigned not null default 0,
    width int unsigned null,
    height int unsigned null,
    modified_at varchar(32) not null,
    folder varchar(80) not null default '',
    alt_text varchar(160) not null default '',
    title varchar(160) not null default '',
    caption varchar(160) not null default '',
    description text null,
    uploaded_by bigint unsigned null,
    created_at varchar(32) not null,
    updated_at varchar(32) not null,
    index media_uploaded_by_index (uploaded_by),
    index media_folder_index (folder)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists pages (
    id bigint unsigned primary key auto_increment,
    created_by bigint unsigned null,
    title varchar(220) not null,
    slug varchar(180) not null unique,
    excerpt text null,
    template varchar(80) not null default 'default',
    blocks_json longtext not null,
    status varchar(40) not null default 'draft',
    sort_order int not null default 0,
    created_at varchar(32) not null,
    updated_at varchar(32) not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists news (
    id bigint unsigned primary key auto_increment,
    created_by bigint unsigned null,
    title varchar(220) not null,
    slug varchar(180) not null unique,
    category varchar(160) not null default 'Загальні',
    image_path varchar(255) null,
    body longtext not null,
    views_count bigint unsigned not null default 0,
    status varchar(40) not null default 'draft',
    published_at varchar(32) null,
    submitted_at varchar(32) null,
    submitted_by bigint unsigned null,
    reviewed_at varchar(32) null,
    reviewed_by bigint unsigned null,
    review_comment text null,
    version int unsigned not null default 1,
    created_at varchar(32) not null,
    updated_at varchar(32) not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists news_view_stats (
    news_id bigint unsigned not null,
    view_date date not null,
    views_count int unsigned not null default 0,
    primary key (news_id, view_date),
    constraint news_view_stats_news_id_foreign foreign key(news_id) references news(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists news_moderation_events (
    id bigint unsigned primary key auto_increment,
    news_id bigint unsigned not null,
    user_id bigint unsigned null,
    action varchar(40) not null,
    from_status varchar(40) not null,
    to_status varchar(40) not null,
    comment text null,
    created_at varchar(32) not null,
    index news_moderation_events_news_id_index (news_id),
    constraint news_moderation_events_news_id_foreign foreign key(news_id) references news(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists news_categories (
    id bigint unsigned primary key auto_increment,
    parent_id bigint unsigned null,
    title varchar(160) not null unique,
    slug varchar(180) not null unique,
    sort_order int not null default 100,
    created_at varchar(32) not null,
    updated_at varchar(32) not null,
    index news_categories_parent_id_index (parent_id),
    constraint news_categories_parent_id_foreign foreign key(parent_id) references news_categories(id) on delete set null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists news_category_links (
    news_id bigint unsigned not null,
    category_id bigint unsigned not null,
    primary key (news_id, category_id),
    index news_category_links_category_id_index (category_id),
    constraint news_category_links_news_id_foreign foreign key(news_id) references news(id) on delete cascade,
    constraint news_category_links_category_id_foreign foreign key(category_id) references news_categories(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists external_identities (
    id bigint unsigned primary key auto_increment,
    provider varchar(40) not null,
    external_user_id varchar(190) not null,
    user_id bigint unsigned not null,
    external_institution_id varchar(190) null,
    created_at varchar(32) not null,
    updated_at varchar(32) not null,
    unique key external_identities_provider_user_unique (provider, external_user_id),
    index external_identities_user_id_index (user_id),
    constraint external_identities_user_id_foreign foreign key(user_id) references users(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists external_auth_nonces (
    id bigint unsigned primary key auto_increment,
    provider varchar(40) not null,
    nonce_hash varchar(64) not null,
    expires_at varchar(32) not null,
    used_at varchar(32) not null,
    unique key external_auth_nonces_provider_nonce_unique (provider, nonce_hash),
    index external_auth_nonces_expires_at_index (expires_at)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists audit_logs (
    id bigint unsigned primary key auto_increment,
    user_id bigint unsigned null,
    action varchar(120) not null,
    entity varchar(120) not null,
    entity_id bigint unsigned null,
    details text null,
    created_at varchar(32) not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists forms (
    id bigint unsigned primary key auto_increment, created_by bigint unsigned null,
    title varchar(220) not null, slug varchar(180) not null unique, description text null,
    type varchar(40) not null default 'generic', fields_json longtext not null,
    settings_json longtext not null, status varchar(40) not null default 'draft',
    version int unsigned not null default 1, starts_at varchar(32) null, ends_at varchar(32) null,
    created_at varchar(32) not null, updated_at varchar(32) not null,
    index forms_status_index (status)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists form_submissions (
    id bigint unsigned primary key auto_increment, form_id bigint unsigned not null,
    form_version int unsigned not null, answers_json longtext not null,
    schema_snapshot_json longtext not null, context_json longtext not null,
    status varchar(40) not null default 'new', submitter_email varchar(180) null,
    ip_hash varchar(64) null, user_agent varchar(500) null,
    created_at varchar(32) not null, updated_at varchar(32) not null,
    index form_submissions_form_id_index (form_id), index form_submissions_status_index (status),
    constraint form_submissions_form_id_foreign foreign key(form_id) references forms(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;
