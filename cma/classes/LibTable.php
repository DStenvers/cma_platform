<?php
/**
 * LibTable - Table rendering class with sorting and filtering
 *
 * PHP port of ASP LibTable class
 * Enhanced with inline editing support
 */

namespace Cma;

use App\Library\Database;
use App\Library\Server;

class LibTable
{
    private static int $ctrlId = 0;

    // Data properties
    public string $SQL = '';
    public $Connection = null;
    public $Recordset = null;

    // Display properties
    public string $IDField = 'ID';
    public string $GroupField = '';
    public bool $ShowCaptions = true;
    public bool $ShowIDField = false;
    public string $EmptyMessage = '';
    public int $RowsPerPage = -1;
    public int $Page = 1;
    public int $DefaultSortCol = -1;

    // Link properties
    public string $LinkBaseUrl = '';
    public string $LinkTarget = '';
    public string $OnClick = '';

    // Style properties
    public bool $Sortable = true;
    public bool $Filtered = false;
    public string $ID = '';
    public string $Name = '';

    // Field customization
    public ?array $FieldCaptions = null;
    public ?array $FieldWidths = null;
    public ?array $FieldStyles = null;
    public ?array $FieldNames = null;  // For data-field attributes

    // Inline editing support
    public bool $InlineEdit = false;
    public int $FormId = 0;
    public int $AccessLevel = 0;
    public bool $CanAdd = true;
    public bool $CanEdit = true;
    public bool $CanCopy = false;
    public bool $CanDelete = true;
    public array $ExtraButtons = [];
    public array $FieldDefinitions = [];
    public bool $FixedHeader = true;  // Fixed header when filtering

    // CSS classes
    private string $tableClass = 'libTable';
    private string $trClass = 'libTableTR';
    private string $thClass = 'libTableTH';
    private string $tdClass = 'libTableTD';

    public function __construct()
    {
        self::$ctrlId++;
    }

    /**
     * Render the table
     */
    public function Render(): void
    {
        $ctrlId = self::$ctrlId;
        $externalRS = $this->Recordset !== null;

        // Open recordset if not provided externally
        if (!$externalRS) {
            $rs = Database::openRS($this->SQL, $this->Connection);
            if ($rs === null) {
                echo '<lib-message type="error">Query failed</lib-message>';
                return;
            }
        } else {
            $rs = $this->Recordset;
        }

        if ($rs->EOF) {
            if ($this->EmptyMessage !== '') {
                echo $this->EmptyMessage;
            } else {
                echo '<div class="no-data">Geen gegevens gevonden</div>';
            }
            return;
        }

        // Include sorting/filtering scripts on first table (not when inline editing)
        if ($ctrlId === 1 && !$this->InlineEdit) {
            if ($this->Sortable) {
                echo '<script src="../library/classes/class_tablesort.js"></script>';
            }
        }

        // Row activation/hover JavaScript (only if not inline editing)
        if (!$this->InlineEdit) {
            $this->renderJavaScript($ctrlId);
        }

        // Build table attributes
        $tableAttrs = [];
        if ($this->Name !== '') {
            $safeName = substr(str_replace(['/', '?'], '', $this->Name), 0, 30);
            $tableAttrs[] = 'data-name="' . Server::htmlEncode($safeName) . '"';
        }

        $classes = [$this->tableClass];
        if ($this->Filtered) {
            $classes[] = 'filtering';
            $this->Sortable = false; // Filtering disables sorting
        }
        if ($this->Filtered && $this->FixedHeader) {
            $classes[] = 'fixed-header-table';
        }
        if ($this->InlineEdit) {
            $classes[] = 'inline-editable';
        }
        $tableAttrs[] = 'class="' . implode(' ', $classes) . '"';

        if ($this->ID !== '') {
            $tableAttrs[] = 'id="' . Server::htmlEncode($this->ID) . '"';
        } elseif ($this->Sortable || $this->InlineEdit) {
            $tableAttrs[] = 'id="libTable' . $ctrlId . '"';
        }
        $tableAttrs[] = 'onselectstart="return(false)"';

        // Data attributes for inline editing
        if ($this->InlineEdit) {
            $tableAttrs[] = 'data-form-id="' . $this->FormId . '"';
            $tableAttrs[] = 'data-inline-edit="true"';
        }

        echo '<table ' . implode(' ', $tableAttrs) . '>';

        // Render header
        if ($this->ShowCaptions) {
            echo '<thead><tr>';
            if ($this->FieldCaptions !== null) {
                foreach ($this->FieldCaptions as $caption) {
                    echo '<th class="' . $this->thClass . '" nowrap>' . Server::htmlEncode($caption) . '</th>';
                }
            } else {
                foreach ($rs->fields as $key => $value) {
                    if (is_int($key)) continue;
                    if (!$this->ShowIDField && strtolower($key) === strtolower($this->IDField)) continue;
                    if (strtolower($key) === strtolower($this->GroupField)) continue;

                    $niceField = ucfirst(str_replace('_', ' ', $key));
                    echo '<th class="' . $this->thClass . '" nowrap>' . Server::htmlEncode($niceField) . '</th>';
                }
            }
            echo '</tr></thead>';
        }

        // Render body
        echo '<tbody>';
        $intRow = 0;
        $fldTel = 0;

        while (!$rs->EOF) {
            $intRow++;

            // Pagination
            $show = true;
            if ($this->RowsPerPage !== -1) {
                $start = (($this->Page - 1) * $this->RowsPerPage) + 1;
                $end = $this->Page * $this->RowsPerPage;
                if ($intRow < $start || $intRow > $end) {
                    $show = false;
                }
            }

            if ($show) {
                $curID = $rs->fields[$this->IDField] ?? $intRow;

                // Build row attributes based on mode
                if ($this->InlineEdit) {
                    // Inline edit mode - use data attributes, no inline handlers
                    echo '<tr id="lt_row_' . $curID . '" class="' . $this->trClass . '" data-id="' . $curID . '">';
                } else {
                    // Standard mode - build onclick handler
                    $OnClick = '';
                    if ($this->OnClick !== '') {
                        $OnClick = str_replace('[ID]', $curID, $this->OnClick);
                    } elseif ($this->LinkBaseUrl !== '') {
                        // Check if [ID] placeholder exists, if not append ID
                        if (strpos($this->LinkBaseUrl, '[ID]') !== false) {
                            $url = str_replace('[ID]', $curID, $this->LinkBaseUrl);
                        } else {
                            $url = $this->LinkBaseUrl . $curID;
                        }
                        $target = $this->LinkTarget !== '' ? $this->LinkTarget : '_self';
                        $OnClick = "window.open('{$url}', '{$target}')";
                    }

                    echo '<tr id="lt_row_' . $curID . '" class="' . $this->trClass . '" ';
                    echo 'onclick="row_act' . $ctrlId . '(' . $curID . ');' . $OnClick . '">';
                }

                $fldTel = 0;
                foreach ($rs->fields as $key => $value) {
                    if (is_int($key)) continue;
                    if (!$this->ShowIDField && strtolower($key) === strtolower($this->IDField)) continue;
                    if (strtolower($key) === strtolower($this->GroupField)) continue;

                    $style = '';
                    if ($this->FieldWidths !== null && isset($this->FieldWidths[$fldTel])) {
                        $style .= 'width:' . $this->FieldWidths[$fldTel] . ';';
                    }
                    if ($this->FieldStyles !== null && isset($this->FieldStyles[$fldTel])) {
                        $style .= $this->FieldStyles[$fldTel];
                    }

                    echo '<td class="' . $this->tdClass . '"';
                    if ($style !== '') {
                        echo ' style="' . $style . '"';
                    }
                    // Add data-field attribute for inline editing
                    if ($this->InlineEdit) {
                        $fieldName = $this->FieldNames[$fldTel] ?? $key;
                        echo ' data-field="' . Server::htmlEncode($fieldName) . '"';
                    }
                    echo '>';

                    // Format value
                    $displayValue = $value ?? '';
                    if (is_bool($displayValue)) {
                        $displayValue = $displayValue ? 'Ja' : 'Nee';
                    }
                    echo Server::htmlEncode((string)$displayValue);

                    echo '</td>';
                    $fldTel++;
                }

                echo '</tr>';
            }

            $rs->MoveNext();
        }

        echo '</tbody></table>';

        // Pagination controls
        if ($this->RowsPerPage !== -1) {
            $this->renderPagination($intRow);
        }

        // Initialize inline editing if enabled
        if ($this->InlineEdit) {
            $this->renderInlineEditInit($ctrlId);
        }
    }

    /**
     * Render inline edit initialization script
     */
    private function renderInlineEditInit(int $ctrlId): void
    {
        $tableId = $this->ID !== '' ? $this->ID : 'libTable' . $ctrlId;

        $config = [
            'tableSelector' => '#' . $tableId,
            'formId' => $this->FormId,
            'accessLevel' => $this->AccessLevel,
            'canAdd' => $this->CanAdd,
            'canEdit' => $this->CanEdit,
            'canCopy' => $this->CanCopy,
            'canDelete' => $this->CanDelete,
            'extraButtons' => $this->ExtraButtons,
            'idField' => $this->IDField,
            'fields' => $this->FieldDefinitions,
        ];

        // Skip inline edit init in AJAX/nomenu mode - form-controller.js handles it
        echo '<script>' . PHP_EOL;
        echo '$(document).ready(function() {' . PHP_EOL;
        echo '    // Skip if in AJAX mode (form-controller.js manages inline edit)' . PHP_EOL;
        echo '    if (typeof CMA_NOMENU_MODE !== "undefined" && CMA_NOMENU_MODE) return;' . PHP_EOL;
        echo '    if (typeof CmaInlineEdit !== "undefined") {' . PHP_EOL;
        echo '        new CmaInlineEdit(' . json_encode($config) . ');' . PHP_EOL;
        echo '    } else {' . PHP_EOL;
        echo '        cmaLog.error("CmaInlineEdit not loaded");' . PHP_EOL;
        echo '    }' . PHP_EOL;
        echo '});' . PHP_EOL;
        echo '</script>' . PHP_EOL;
    }

    /**
     * Render JavaScript for row activation/hover
     */
    private function renderJavaScript(int $ctrlId): void
    {
        echo '<script>';
        echo 'var intActiveRowID_' . $ctrlId . '=-1;';
        echo 'function row_act' . $ctrlId . '(id){';
        echo '  if(intActiveRowID_' . $ctrlId . '!=id){';
        echo '    if(intActiveRowID_' . $ctrlId . '!=-1){';
        echo '      var row=document.getElementById("lt_row_"+intActiveRowID_' . $ctrlId . '.toString());';
        echo '      if(row)row.className="' . $this->trClass . '"';
        echo '    }';
        echo '  }';
        echo '  row=document.getElementById("lt_row_"+id.toString());';
        echo '  if(row){';
        echo '    row.className="' . $this->trClass . '_active";';
        echo '    intActiveRowID_' . $ctrlId . '=id;';
        echo '  }';
        echo '}';
        echo '</script>';
    }

    /**
     * Render pagination controls
     */
    private function renderPagination(int $totalRows): void
    {
        $totalPages = ceil($totalRows / $this->RowsPerPage);
        if ($totalPages <= 1) return;

        echo '<div class="pagination">';
        if ($this->Page > 1) {
            echo '<a href="javascript:void(0)" onclick="goToPage(' . ($this->Page - 1) . ')">« Vorige</a>';
        }
        echo ' Pagina ' . $this->Page . ' van ' . $totalPages . ' ';
        if ($this->Page < $totalPages) {
            echo '<a href="javascript:void(0)" onclick="goToPage(' . ($this->Page + 1) . ')">Volgende »</a>';
        }
        echo '</div>';
    }
}
