-- CREATE DATABASE aylin_database;

-- USE aylin_database;

CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    createdAt TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S', 'now')),
    updatedAt TEXT DEFAULT NULL
);

CREATE TABLE files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    path TEXT NOT NULL,
    createdAt TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S', 'now')),
    updatedAt TEXT DEFAULT NULL
);

CREATE TABLE file_tags (
    fileId INTEGER,
    tagId INTEGER,

    PRIMARY KEY (fileId, tagId),

    FOREIGN KEY (tagId) REFERENCES tags(id),
    FOREIGN KEY (fileId) REFERENCES files(id)
);
