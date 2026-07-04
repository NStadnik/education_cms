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

create table if not exists pages (
    id bigint unsigned primary key auto_increment,
    title varchar(220) not null,
    slug varchar(180) not null unique,
    excerpt text null,
    blocks_json longtext not null,
    status varchar(40) not null default 'draft',
    sort_order int not null default 0,
    created_at varchar(32) not null,
    updated_at varchar(32) not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists news (
    id bigint unsigned primary key auto_increment,
    title varchar(220) not null,
    slug varchar(180) not null unique,
    body longtext not null,
    status varchar(40) not null default 'draft',
    published_at varchar(32) null,
    created_at varchar(32) not null,
    updated_at varchar(32) not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists documents (
    id bigint unsigned primary key auto_increment,
    title varchar(220) not null,
    category varchar(160) not null default 'Загальні документи',
    file_path varchar(255) null,
    description text null,
    status varchar(40) not null default 'published',
    approved_at varchar(32) null,
    created_at varchar(32) not null,
    updated_at varchar(32) not null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists public_info_sections (
    id bigint unsigned primary key auto_increment,
    title varchar(220) not null,
    slug varchar(180) not null unique,
    description text null,
    is_required tinyint not null default 1,
    sort_order int not null default 0
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists public_info_items (
    id bigint unsigned primary key auto_increment,
    section_id bigint unsigned not null,
    title varchar(220) not null,
    body text null,
    file_path varchar(255) null,
    status varchar(40) not null default 'missing',
    responsible varchar(160) null,
    approved_at varchar(32) null,
    published_at varchar(32) null,
    updated_at varchar(32) not null,
    constraint public_info_items_section_id_foreign foreign key(section_id) references public_info_sections(id)
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
