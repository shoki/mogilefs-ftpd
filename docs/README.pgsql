README.pgsql -- README for PostgreSQL users
-------------------------------------------

first you have to connect to the server:
 $ psql -U username database
(you can connect via a frontend as well, e.g. phpPgAdmin)

use this command to create a table for the user data:
---
CREATE TABLE users (
    username varchar(20) NOT NULL,
    password varchar(100) NOT NULL,
    uid int8 NOT NULL,
    gid int8 NOT NULL,
    UNIQUE (username)
)
---

 -- written by Phanatic <linux@psoftwares.hu>