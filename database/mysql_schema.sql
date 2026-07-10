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

create table if not exists audit_logs (
    id bigint unsigned primary key auto_increment,
    user_id bigint unsigned null,
    action varchar(120) not null,
    entity varchar(120) not null,
    entity_id bigint unsigned null,
    details text null,
    created_at varchar(32) not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;
