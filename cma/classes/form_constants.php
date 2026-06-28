<?php
// =========================================================================
// SECTION: Form Definition Constants (from formdef.inc)
// These use column names instead of numeric indices for robustness.
// The ColumnMajorArray class supports both numeric and string access.
//
// Single source of truth for the Q_* form-definition column constants.
// Loaded by bootstrap.inc for normal requests AND required directly by the
// Cma\ class layer (FormDefinition etc.) so those classes resolve Q_FIELDNAME
// even in code paths that don't run bootstrap.inc (e.g. the test harness).
// require_once makes a double-include a no-op, so no "already defined" clash.
// =========================================================================

// Form-level columns (same value for all rows)
define("Q_FKDATABASE", "fkDatabase");
define("Q_FRMIDFLD", "FormIDField");
define("Q_AFTERPOSTURL", "AfterPostUrl");
define("Q_SQLTABLENAME", "SqlTable");
define("Q_MENUNEW", "MenuNew");
define("Q_MENUDELETE", "MenuDelete");
define("Q_PREVIEWURL", "previewUrl");
define("Q_FORMNAME", "FormName");
define("Q_SECBYUSER", "blnSecurityByUser");
define("Q_STORELASTMOD", "blnStoreLastModified");
define("Q_CACHE_PREFIX", "Cache_Prefix");

// Control-level columns (different value per row/control)
define("Q_CONTROLID", "ControlID");
define("Q_FIELDNAME", "FieldName");
define("Q_CONTROLTYPEID", "ControlTypeID");
define("Q_ISREQUIRED", "IsRequired");
define("Q_CAPTION", "Caption");
define("Q_POSTCAPTION", "PostCaption");
define("Q_BASEFIELDNAME", "BaseFieldname");
define("Q_CTRLIDFIELD", "IDField");
define("Q_FOREIGNIDFIELD", "ForeignIDField");
define("Q_SOURCETABLE", "SourceTable");
define("Q_SQLLIST", "SqlList");
define("Q_HEIGHT", "Height");
define("Q_HTMLTAGS", "TagsAllowed");
define("Q_IMGPATH", "ImgPath");
define("Q_IMGWIDTHFLD", "ImgWidthField");
define("Q_IMGHEIGHTFLD", "ImgHeightField");
define("Q_IMGRESIZETYPE", "ImgResizeType");
define("Q_IMGRESIZEHEIGHT", "ImgResizeHeight");
define("Q_IMGRESIZEWIDTH", "ImgResizeWidth");
define("Q_FILERANDOM", "blnFileRandomName");
define("Q_CHKLISTWIDTH", "CheckListWidth");
define("Q_PASSONTOPOST", "blnPassOnToPostUrl");
define("Q_XMLSNIPPET", "XMLSnippet");
define("Q_DIRFILENAME", "dirFilename");
define("Q_DIRTEMPLATE", "dirTemplate");
define("Q_DATABASEID", "ControlDatabaseID");

// Extra icon columns
define("Q_EXTRAICONURL", "extraIconURL");
define("Q_EXTRAICONRES", "extraIconResource");
define("Q_EXTRAICONTITLE", "extraIconTitle");
define("Q_NOSPAMJS", "blnNoSpamJS");
define("Q_FILTERFIELDNAME", "FilterFieldName");
define("Q_FILTERDESCR", "FilterCaption");
define("Q_NEWCHANGABLEONLY", "blnNewChangableOnly");
define("Q_PARENTFORM", "fkParentForm");
define("Q_EXTRAICON2URL", "extraIcon2URL");
define("Q_EXTRAICON2RES", "extraIcon2Resource");
define("Q_EXTRAICON2TITLE", "extraIcon2Title");
define("Q_EXTRAICON3URL", "extraIcon3URL");
define("Q_EXTRAICON3RES", "extraIcon3Resource");
define("Q_EXTRAICON3TITLE", "extraIcon3Title");
define("Q_EXTRAICON4URL", "extraIcon4URL");
define("Q_EXTRAICON4RES", "extraIcon4Resource");
define("Q_EXTRAICON4TITLE", "extraIcon4Title");
define("Q_EXTRAICON5URL", "extraIcon5URL");
define("Q_EXTRAICON5RES", "extraIcon5Resource");
define("Q_EXTRAICON5TITLE", "extraIcon5Title");
define("Q_ONLOADJS", "onloadJS");
define("Q_FILTERIDNAME", "filterIDName");
define("Q_FLDREADONLY", "blnReadOnly");
define("Q_FLDLIMITEDHTML", "blnLimitedHTML");
define("Q_FLDMAXCHARS", "intMaxChars");
define("Q_QUICKFIELDS", "quickfilterfields");
define("Q_MENUCOPY", "MenuCopy");
define("Q_KEEPWITHNEXT", "bCombineWithNext");

// Schema columns
define("Q_SCHEMA_DATE_PREC", "schema_date_prec");
define("Q_SCHEMA_DEFAULT", "schema_default");
define("Q_SCHEMA_CHAR_MAXL", "schema_char_maxl");
define("Q_SCHEMA_NUM_PREC", "schema_num_prec");
define("Q_SCHEMA_DATATYPE", "schema_datatype");

// Action/behavior columns
define("Q_ACTIE", "actie");
define("Q_FORM_ACTIE", "FormActie");
define("Q_BEHEER", "isBeheer");

// Tree/grouping columns
define("Q_GROUP1FIELD", "Group1Field");
define("Q_GROUP2FIELD", "Group2Field");
define("Q_GROUP3FIELD", "Group3Field");
define("Q_DETAILFIELD", "DetailField");
define("Q_NAMEQUERY", "NameQuery");
define("Q_RECURSEFIELD", "recurseField");

// Custom renderer columns (for JSON forms)
define("Q_RENDERER", "Renderer");
define("Q_RENDEROPTIONS", "RendererOptions");
