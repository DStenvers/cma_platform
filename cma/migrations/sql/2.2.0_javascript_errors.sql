-- Migration 2.2.0: Create JavaScript Error Logging Table
-- This table stores client-side JavaScript errors for debugging
-- MS Access compatible syntax

CREATE TABLE tblCMAJavascriptErrors (
    ID AUTOINCREMENT PRIMARY KEY,
    datestamp DATETIME DEFAULT Now(),
    error_message MEMO,
    error_url VARCHAR(500),
    error_line INTEGER DEFAULT 0,
    error_column INTEGER DEFAULT 0,
    error_stack MEMO,
    user_login VARCHAR(100),
    user_agent VARCHAR(500),
    page_url VARCHAR(500),
    extra_info MEMO
);
