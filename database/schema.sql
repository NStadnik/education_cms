create table if not exists settings (
    name varchar(120) primary key,
    value text null
);

create table if not exists users (
    id integer primary key autoincrement,
    name varchar(160) not null,
    email varchar(190) not null unique,
    password_hash varchar(255) not null,
    role varchar(80) not null default 'editor',
    is_active tinyint not null default 1,
    created_at varchar(32) not null
);

create table if not exists pages (
    id integer primary key autoincrement,
    title varchar(220) not null,
    slug varchar(220) not null unique,
    excerpt text null,
    blocks_json text not null,
    status varchar(40) not null default 'draft',
    sort_order integer not null default 0,
    created_at varchar(32) not null,
    updated_at varchar(32) not null
);

create table if not exists news (
    id integer primary key autoincrement,
    title varchar(220) not null,
    slug varchar(220) not null unique,
    body text not null,
    status varchar(40) not null default 'draft',
    published_at varchar(32) null,
    created_at varchar(32) not null,
    updated_at varchar(32) not null
);

create table if not exists documents (
    id integer primary key autoincrement,
    title varchar(220) not null,
    category varchar(160) not null default 'Загальні документи',
    file_path varchar(255) null,
    description text null,
    status varchar(40) not null default 'published',
    approved_at varchar(32) null,
    created_at varchar(32) not null,
    updated_at varchar(32) not null
);

create table if not exists public_info_sections (
    id integer primary key autoincrement,
    title varchar(220) not null,
    slug varchar(220) not null unique,
    description text null,
    is_required tinyint not null default 1,
    sort_order integer not null default 0
);

create table if not exists public_info_items (
    id integer primary key autoincrement,
    section_id integer not null,
    title varchar(220) not null,
    body text null,
    file_path varchar(255) null,
    status varchar(40) not null default 'missing',
    responsible varchar(160) null,
    approved_at varchar(32) null,
    published_at varchar(32) null,
    updated_at varchar(32) not null,
    foreign key(section_id) references public_info_sections(id)
);

create table if not exists audit_logs (
    id integer primary key autoincrement,
    user_id integer null,
    action varchar(120) not null,
    entity varchar(120) not null,
    entity_id integer null,
    details text null,
    created_at varchar(32) not null
);
