-- auto-generated definition

drop table if exists luminee_schedule_chain_record;

create table luminee_schedule_chain_record
(
    id     int unsigned auto_increment
        primary key,
    name   varchar(255) not null,
    period varchar (255) not null,
    status tinyint      not null
);

create index luminee_schedule_chain_record_name_index
    on luminee_schedule_chain_record (name);


-- auto-generated definition

drop table if exists luminee_schedule_chain_schedule_record;

create table luminee_schedule_chain_schedule_record
(
    id       int unsigned auto_increment
        primary key,
    name     varchar(255) not null,
    chain_id int          not null,
    status   tinyint      not null,
    has_redo tinyint      not null
);

create index luminee_schedule_chain_schedule_record_chain_id_index
    on luminee_schedule_chain_schedule_record (chain_id);

create index luminee_schedule_chain_schedule_record_name_index
    on luminee_schedule_chain_schedule_record (name);

