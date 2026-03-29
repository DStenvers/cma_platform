<?php

namespace Cma;

/**
 * Form Definition Field Enum
 *
 * Provides type-safe access to form definition columns.
 * Maps to the column names in the form definition array.
 *
 * Usage:
 *   $formDef->field(FormField::FieldName, $row)
 *   $formDef->field(FormField::ControlTypeId, $row)
 */
enum FormField: string
{
    // Form-level properties (typically row 0)
    case FkDatabase = 'fkDatabase';
    case FormIdField = 'FormIDField';
    case AfterPostUrl = 'AfterPostUrl';
    case SqlTableName = 'SqlTable';
    case MenuNew = 'MenuNew';
    case MenuDelete = 'MenuDelete';
    case PreviewUrl = 'previewUrl';
    case FormName = 'FormName';
    case SecurityByUser = 'blnSecurityByUser';
    case StoreLastModified = 'blnStoreLastModified';
    case CachePrefix = 'Cache_Prefix';

    // Control/field properties (per row)
    case ControlId = 'ControlID';
    case FieldName = 'FieldName';
    case ControlTypeId = 'ControlTypeID';
    case IsRequired = 'IsRequired';
    case Caption = 'Caption';
    case PostCaption = 'PostCaption';
    case BaseFieldName = 'BaseFieldname';
    case CtrlIdField = 'IDField';
    case ForeignIdField = 'ForeignIDField';
    case SourceTable = 'SourceTable';
    case SqlList = 'SqlList';
    case Height = 'Height';
    case HtmlTags = 'TagsAllowed';
    case ImgPath = 'ImgPath';
    case ImgWidthField = 'ImgWidthField';
    case ImgHeightField = 'ImgHeightField';
    case ImgResizeType = 'ImgResizeType';
    case ImgResizeHeight = 'ImgResizeHeight';
    case ImgResizeWidth = 'ImgResizeWidth';
    case FileRandom = 'blnFileRandomName';
    case CheckListWidth = 'CheckListWidth';
    case PassOnToPost = 'blnPassOnToPostUrl';
    case XmlSnippet = 'XMLSnippet';
    case DirFilename = 'dirFilename';
    case DirTemplate = 'dirTemplate';
    case DatabaseId = 'ControlDatabaseID';

    // Extra icons (form-level)
    case ExtraIconUrl = 'extraIconURL';
    case ExtraIconRes = 'extraIconResource';
    case ExtraIconTitle = 'extraIconTitle';
    case NoSpamJs = 'blnNoSpamJS';

    // Filter properties (form-level)
    case FilterFieldName = 'FilterFieldName';
    case FilterDescription = 'FilterCaption';
    case NewChangableOnly = 'blnNewChangableOnly';
    case ParentForm = 'fkParentForm';

    // Extra icons 2-5 (form-level)
    case ExtraIcon2Url = 'extraIcon2URL';
    case ExtraIcon2Res = 'extraIcon2Resource';
    case ExtraIcon2Title = 'extraIcon2Title';
    case ExtraIcon3Url = 'extraIcon3URL';
    case ExtraIcon3Res = 'extraIcon3Resource';
    case ExtraIcon3Title = 'extraIcon3Title';
    case ExtraIcon4Url = 'extraIcon4URL';
    case ExtraIcon4Res = 'extraIcon4Resource';
    case ExtraIcon4Title = 'extraIcon4Title';
    case ExtraIcon5Url = 'extraIcon5URL';
    case ExtraIcon5Res = 'extraIcon5Resource';
    case ExtraIcon5Title = 'extraIcon5Title';

    // Additional properties
    case OnLoadJs = 'onloadJS';
    case FilterIdName = 'filterIDName';
    case FieldReadOnly = 'blnReadOnly';
    case FieldLimitedHtml = 'blnLimitedHTML';
    case FieldMaxChars = 'intMaxChars';
    case QuickFields = 'quickfilterfields';
    case MenuCopy = 'MenuCopy';
    case KeepWithNext = 'bCombineWithNext';

    // Schema properties
    case SchemaDatePrecision = 'schema_date_prec';
    case SchemaDefault = 'schema_default';
    case SchemaCharMaxLength = 'schema_char_maxl';
    case SchemaNumPrecision = 'schema_num_prec';
    case SchemaDataType = 'schema_datatype';

    // Actions
    case Action = 'actie';
    case FormAction = 'FormActie';
    case IsBeheer = 'isBeheer';

    // Tree/grouping
    case Group1Field = 'Group1Field';
    case Group2Field = 'Group2Field';
    case Group3Field = 'Group3Field';
    case DetailField = 'DetailField';
    case NameQuery = 'NameQuery';
    case RecurseField = 'recurseField';

    /**
     * Get the column name as the database column string
     */
    public function columnName(): string
    {
        return $this->value;
    }
}
