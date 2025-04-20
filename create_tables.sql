create or replace table cdv.users
(
    id                    int auto_increment
        primary key,
    username              varchar(16) not null,
    password              text        not null,
    public_key            text        not null,
    encrypted_private_key text        not null,
    initialization_vector text        not null,
    salt                  text        not null
);

create or replace table cdv.one_on_one_chat_id
(
    id          int auto_increment
        primary key,
    user_one_id int not null,
    user_two_id int not null,
    constraint one_on_one_chat_id_users_id_fk
        foreign key (user_one_id) references cdv.users (id)
            on update cascade on delete cascade,
    constraint one_on_one_chat_id_users_id_fk_2
        foreign key (user_two_id) references cdv.users (id)
            on update cascade on delete cascade
);

create or replace table cdv.one_on_one_messages
(
    chat_id               int                                   not null,
    message               text                                  not null,
    sender                int                                   not null,
    initialization_vector varchar(255)                          not null,
    timestamp             timestamp default current_timestamp() not null,
    constraint one_on_one_messages_one_on_one_chat_id_id_fk
        foreign key (chat_id) references cdv.one_on_one_chat_id (id)
            on update cascade on delete cascade,
    constraint one_on_one_messages_users_id_fk
        foreign key (sender) references cdv.users (id)
            on update cascade on delete cascade
);

