CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    createdAt TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
    updatedAt TEXT DEFAULT NULL
);

CREATE TABLE files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    path TEXT NOT NULL UNIQUE,
    createdAt TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
    updatedAt TEXT DEFAULT NULL
);

CREATE TABLE file_tags (
    fileId INTEGER NOT NULL,
    tagId INTEGER NOT NULL,

    PRIMARY KEY (fileId, tagId),

    FOREIGN KEY (tagId) REFERENCES tags(id) ON DELETE CASCADE,
    FOREIGN KEY (fileId) REFERENCES files(id) ON DELETE CASCADE
);
