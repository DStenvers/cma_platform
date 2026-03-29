/**
 * SQL Utilities - Shared SQL formatting and syntax highlighting
 *
 * Usage:
 *   SqlUtils.formatSql(sql)      - Format/indent SQL query
 *   SqlUtils.highlightSql(sql)   - HTML syntax highlighting
 *   SqlUtils.escapeHtml(text)    - Escape HTML entities
 *   SqlUtils.highlightCss        - CSS rules for highlight classes
 */
(function() {
    'use strict';

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatSql(sql) {
        if (!sql) return sql;

        // Normalize whitespace
        let formatted = sql.replace(/\s+/g, ' ').trim();

        // Add newlines before major keywords
        const majorKeywords = ['SELECT', 'FROM', 'WHERE', 'ORDER BY', 'GROUP BY', 'HAVING', 'UNION'];
        majorKeywords.forEach(kw => {
            const regex = new RegExp(`\\s+(${kw})\\b`, 'gi');
            formatted = formatted.replace(regex, '\n$1');
        });

        // Indent AND/OR
        formatted = formatted.replace(/\s+(AND|OR)\s+/gi, '\n  $1 ');

        // Indent JOINs
        formatted = formatted.replace(/\s+(INNER JOIN|LEFT JOIN|RIGHT JOIN|FULL JOIN)\s+/gi, '\n  $1 ');

        // Handle ON clauses
        formatted = formatted.replace(/\s+ON\s+/gi, '\n    ON ');

        // Clean up multiple newlines
        formatted = formatted.replace(/\n{3,}/g, '\n\n');

        return formatted.trim();
    }

    function highlightSql(sql) {
        if (!sql) return '';

        const keywords = [
            'SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'NOT', 'IN', 'LIKE',
            'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'FULL', 'CROSS', 'ON', 'AS',
            'ORDER', 'BY', 'ASC', 'DESC', 'GROUP', 'HAVING',
            'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE',
            'CREATE', 'ALTER', 'DROP', 'TABLE', 'INDEX', 'VIEW',
            'DISTINCT', 'TOP', 'LIMIT', 'OFFSET',
            'NULL', 'IS', 'BETWEEN', 'EXISTS', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
            'UNION', 'ALL', 'EXCEPT', 'INTERSECT', 'WITH', 'RECURSIVE'
        ];

        const functions = [
            'COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'COALESCE', 'ISNULL', 'NULLIF',
            'CAST', 'CONVERT', 'DATEPART', 'DATEDIFF', 'DATEADD', 'GETDATE', 'NOW',
            'YEAR', 'MONTH', 'DAY', 'HOUR', 'MINUTE', 'SECOND',
            'UPPER', 'LOWER', 'LEN', 'LENGTH', 'SUBSTRING', 'SUBSTR', 'LEFT', 'RIGHT',
            'TRIM', 'LTRIM', 'RTRIM', 'CONCAT', 'REPLACE', 'CHARINDEX', 'INSTR',
            'ROUND', 'FLOOR', 'CEILING', 'ABS', 'POWER', 'SQRT', 'MOD',
            'IIF', 'CHOOSE', 'FORMAT'
        ];

        let result = escapeHtml(sql);

        // Protect strings first (to avoid highlighting keywords inside strings)
        const stringPlaceholders = [];
        result = result.replace(/'([^']*)'/g, (match) => {
            const placeholder = `__STRING_${stringPlaceholders.length}__`;
            stringPlaceholders.push(match);
            return placeholder;
        });

        // Protect comments
        const commentPlaceholders = [];
        result = result.replace(/--(.*?)(?:\n|$)/g, (match, content) => {
            const placeholder = `__COMMENT_${commentPlaceholders.length}__`;
            commentPlaceholders.push(`<span class="comment">${match}</span>`);
            return placeholder;
        });

        // Highlight keywords
        keywords.forEach(kw => {
            // Don't highlight LEFT/RIGHT when they might be functions
            if (kw === 'LEFT' || kw === 'RIGHT') {
                const regex = new RegExp(`\\b(${kw})\\b(?!\\s*\\()`, 'gi');
                result = result.replace(regex, '<span class="keyword">$1</span>');
            } else {
                const regex = new RegExp(`\\b(${kw})\\b`, 'gi');
                result = result.replace(regex, '<span class="keyword">$1</span>');
            }
        });

        // Highlight functions
        functions.forEach(fn => {
            const regex = new RegExp(`\\b(${fn})\\s*\\(`, 'gi');
            result = result.replace(regex, '<span class="function">$1</span>(');
        });

        // Highlight numbers
        result = result.replace(/\b(\d+(?:\.\d+)?)\b/g, '<span class="number">$1</span>');

        // Highlight brackets
        result = result.replace(/(\[|\])/g, '<span class="bracket">$1</span>');

        // Restore strings
        stringPlaceholders.forEach((str, i) => {
            result = result.replace(`__STRING_${i}__`, `<span class="string">${str}</span>`);
        });

        // Restore comments
        commentPlaceholders.forEach((comment, i) => {
            result = result.replace(`__COMMENT_${i}__`, comment);
        });

        return result;
    }

    const highlightCss = `
        .sql-highlight .keyword, .keyword { color: #0000ff; font-weight: 600; }
        .sql-highlight .function, .function { color: #795e26; }
        .sql-highlight .string, .string { color: #a31515; }
        .sql-highlight .number, .number { color: #098658; }
        .sql-highlight .comment, .comment { color: #008000; font-style: italic; }
        .sql-highlight .bracket, .bracket { color: #666; }
    `;

    window.SqlUtils = {
        formatSql: formatSql,
        highlightSql: highlightSql,
        escapeHtml: escapeHtml,
        highlightCss: highlightCss
    };
})();
