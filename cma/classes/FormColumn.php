<?php

namespace Cma;

/**
 * Form Definition Column Names
 *
 * These constants map to the actual database column names returned by GetFormDef().
 * Use these instead of numeric Q_ constants for safer, more maintainable code.
 *
 * Usage:
 *   $arrRep[FormColumn::FIELD_NAME][$row]
 *   $arrRep[FormColumn::CONTROL_TYPE_ID][$row]
 *
 * Or use FormDefinition class methods for type-safe access:
 *   $formDef->getFieldName($row)
 *   $formDef->getControlTypeId($row)
 */
class FormColumn
{
    // Form-level columns (same value for all rows)
    public const FK_DATABASE = 'fkDatabase';
    public const FORM_ID_FIELD = 'FormIDField';
    public const AFTER_POST_URL = 'AfterPostUrl';
    public const SQL_TABLE = 'SqlTable';
    public const MENU_NEW = 'MenuNew';
    public const MENU_DELETE = 'MenuDelete';
    public const PREVIEW_URL = 'previewUrl';
    public const FORM_NAME = 'FormName';
    public const SECURITY_BY_USER = 'blnSecurityByUser';
    public const STORE_LAST_MODIFIED = 'blnStoreLastModified';
    public const CACHE_PREFIX = 'Cache_Prefix';

    // Control-level columns (different value per row/control)
    public const CONTROL_ID = 'ControlID';
    public const FIELD_NAME = 'FieldName';
    public const CONTROL_TYPE_ID = 'ControlTypeID';
    public const IS_REQUIRED = 'IsRequired';
    public const CAPTION = 'Caption';
    public const POST_CAPTION = 'PostCaption';
    public const BASE_FIELD_NAME = 'BaseFieldname';
    public const ID_FIELD = 'IDField';
    public const FOREIGN_ID_FIELD = 'ForeignIDField';
    public const SOURCE_TABLE = 'SourceTable';
    public const SQL_LIST = 'SqlList';
    public const HEIGHT = 'Height';
    public const TAGS_ALLOWED = 'TagsAllowed';
    public const IMG_PATH = 'ImgPath';
    public const IMG_WIDTH_FIELD = 'ImgWidthField';
    public const IMG_HEIGHT_FIELD = 'ImgHeightField';
    public const IMG_RESIZE_TYPE = 'ImgResizeType';
    public const IMG_RESIZE_HEIGHT = 'ImgResizeHeight';
    public const IMG_RESIZE_WIDTH = 'ImgResizeWidth';
    public const FILE_RANDOM_NAME = 'blnFileRandomName';
    public const CHECK_LIST_WIDTH = 'CheckListWidth';
    public const PASS_ON_TO_POST_URL = 'blnPassOnToPostUrl';
    public const XML_SNIPPET = 'XMLSnippet';
    public const DIR_FILENAME = 'dirFilename';
    public const DIR_TEMPLATE = 'dirTemplate';
    public const CONTROL_DATABASE_ID = 'ControlDatabaseID';

    // Extra icon columns
    public const EXTRA_ICON_URL = 'extraIconURL';
    public const EXTRA_ICON_RESOURCE = 'extraIconResource';
    public const EXTRA_ICON_TITLE = 'extraIconTitle';
    public const EXTRA_ICON2_URL = 'extraIcon2URL';
    public const EXTRA_ICON2_RESOURCE = 'extraIcon2Resource';
    public const EXTRA_ICON2_TITLE = 'extraIcon2Title';
    public const EXTRA_ICON3_URL = 'extraIcon3URL';
    public const EXTRA_ICON3_RESOURCE = 'extraIcon3Resource';
    public const EXTRA_ICON3_TITLE = 'extraIcon3Title';
    public const EXTRA_ICON4_URL = 'extraIcon4URL';
    public const EXTRA_ICON4_RESOURCE = 'extraIcon4Resource';
    public const EXTRA_ICON4_TITLE = 'extraIcon4Title';
    public const EXTRA_ICON5_URL = 'extraIcon5URL';
    public const EXTRA_ICON5_RESOURCE = 'extraIcon5Resource';
    public const EXTRA_ICON5_TITLE = 'extraIcon5Title';

    // More form/control columns
    public const NO_SPAM_JS = 'blnNoSpamJS';
    public const FILTER_FIELD_NAME = 'FilterFieldName';
    public const FILTER_CAPTION = 'FilterCaption';
    public const NEW_CHANGABLE_ONLY = 'blnNewChangableOnly';
    public const PARENT_FORM = 'fkParentForm';
    public const ON_LOAD_JS = 'onloadJS';
    public const FILTER_ID_NAME = 'filterIDName';
    public const READ_ONLY = 'blnReadOnly';
    public const LIMITED_HTML = 'blnLimitedHTML';
    public const MAX_CHARS = 'intMaxChars';
    public const QUICK_FILTER_FIELDS = 'quickfilterfields';
    public const MENU_COPY = 'MenuCopy';
    public const COMBINE_WITH_NEXT = 'bCombineWithNext';

    // Schema columns
    public const SCHEMA_DATE_PREC = 'schema_date_prec';
    public const SCHEMA_DEFAULT = 'schema_default';
    public const SCHEMA_CHAR_MAX_LENGTH = 'schema_char_maxl';
    public const SCHEMA_NUM_PRECISION = 'schema_num_prec';
    public const SCHEMA_DATA_TYPE = 'schema_datatype';

    // Action/behavior columns
    public const ACTION = 'actie';
    public const FORM_ACTION = 'FormActie';
    public const IS_BEHEER = 'isBeheer';

    // Tree/grouping columns
    public const GROUP1_FIELD = 'Group1Field';
    public const GROUP2_FIELD = 'Group2Field';
    public const GROUP3_FIELD = 'Group3Field';
    public const DETAIL_FIELD = 'DetailField';
    public const NAME_QUERY = 'NameQuery';
    public const RECURSE_FIELD = 'recurseField';

    /**
     * Legacy mapping: Q_ constant index => column name
     * Use this for gradual migration from numeric indices
     */
    public const LEGACY_MAP = [
        0 => self::FK_DATABASE,
        1 => self::FORM_ID_FIELD,
        2 => self::AFTER_POST_URL,
        3 => self::SQL_TABLE,
        4 => self::MENU_NEW,
        5 => self::MENU_DELETE,
        6 => self::PREVIEW_URL,
        7 => self::FORM_NAME,
        8 => self::SECURITY_BY_USER,
        9 => self::STORE_LAST_MODIFIED,
        10 => self::CACHE_PREFIX,
        11 => self::CONTROL_ID,
        12 => self::FIELD_NAME,
        13 => self::CONTROL_TYPE_ID,
        14 => self::IS_REQUIRED,
        15 => self::CAPTION,
        16 => self::POST_CAPTION,
        17 => self::BASE_FIELD_NAME,
        18 => self::ID_FIELD,
        19 => self::FOREIGN_ID_FIELD,
        20 => self::SOURCE_TABLE,
        21 => self::SQL_LIST,
        22 => self::HEIGHT,
        23 => self::TAGS_ALLOWED,
        24 => self::IMG_PATH,
        25 => self::IMG_WIDTH_FIELD,
        26 => self::IMG_HEIGHT_FIELD,
        27 => self::IMG_RESIZE_TYPE,
        28 => self::IMG_RESIZE_HEIGHT,
        29 => self::IMG_RESIZE_WIDTH,
        30 => self::FILE_RANDOM_NAME,
        31 => self::CHECK_LIST_WIDTH,
        32 => self::PASS_ON_TO_POST_URL,
        33 => self::XML_SNIPPET,
        34 => self::DIR_FILENAME,
        35 => self::DIR_TEMPLATE,
        36 => self::CONTROL_DATABASE_ID,
        37 => self::EXTRA_ICON_URL,
        38 => self::EXTRA_ICON_RESOURCE,
        39 => self::EXTRA_ICON_TITLE,
        40 => self::NO_SPAM_JS,
        41 => self::FILTER_FIELD_NAME,
        42 => self::FILTER_CAPTION,
        43 => self::NEW_CHANGABLE_ONLY,
        44 => self::PARENT_FORM,
        45 => self::EXTRA_ICON2_URL,
        46 => self::EXTRA_ICON2_RESOURCE,
        47 => self::EXTRA_ICON2_TITLE,
        48 => self::EXTRA_ICON3_URL,
        49 => self::EXTRA_ICON3_RESOURCE,
        50 => self::EXTRA_ICON3_TITLE,
        51 => self::EXTRA_ICON4_URL,
        52 => self::EXTRA_ICON4_RESOURCE,
        53 => self::EXTRA_ICON4_TITLE,
        54 => self::EXTRA_ICON5_URL,
        55 => self::EXTRA_ICON5_RESOURCE,
        56 => self::EXTRA_ICON5_TITLE,
        57 => self::ON_LOAD_JS,
        58 => self::FILTER_ID_NAME,
        59 => self::READ_ONLY,
        60 => self::LIMITED_HTML,
        61 => self::MAX_CHARS,
        62 => self::QUICK_FILTER_FIELDS,
        63 => self::MENU_COPY,
        64 => self::COMBINE_WITH_NEXT,
        65 => self::SCHEMA_DATE_PREC,
        66 => self::SCHEMA_DEFAULT,
        67 => self::SCHEMA_CHAR_MAX_LENGTH,
        68 => self::SCHEMA_NUM_PRECISION,
        69 => self::SCHEMA_DATA_TYPE,
        70 => self::ACTION,
        71 => self::FORM_ACTION,
        72 => self::IS_BEHEER,
        73 => self::GROUP1_FIELD,
        74 => self::GROUP2_FIELD,
        75 => self::GROUP3_FIELD,
        76 => self::DETAIL_FIELD,
        77 => self::NAME_QUERY,
        78 => self::RECURSE_FIELD,
    ];
}
