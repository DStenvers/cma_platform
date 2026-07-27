<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Database;
use App\Library\Html;
use App\Library\Request;
use App\Library\Server;
use App\Library\Str;
use App\Library\RecordSet;

$Table_CtrlID = 0;
// Data: you can either set the SQL and the Connection, of the Recordset
// Link: Set the LinkUrl or LinkTarget or set the OnClick ([ID] will be replaced by the actual value)
// 2do : use the more powerfull filtering option
if (class_exists('LibTable')) { return; }
#[\AllowDynamicProperties]
class LibTable
{
    // VBScript exposed public Property Let/Get/Set accessors (SQL, Recordset,
    // Connection, RowsPerPage, ShowIDField, ...) that stored into the private
    // Table* fields. The ASP→PHP conversion dropped those Property methods, so
    // callers doing `$oTable->SQL = $sql` / `$oTable->SQL()` / `$oTable->Recordset = $rs`
    // hit undefined-method / dynamic-property errors and the table never got its
    // SQL or recordset. These magic methods restore the accessors: public name
    // (case-insensitive) -> internal Table* field, preserving the ASP side effects.
    private const PROP_MAP = [
        'sql' => 'TableSQL', 'recordset' => 'TableRS', 'connection' => 'TableConnection',
        'rowsperpage' => 'TableRowsPerPage', 'activerowid' => 'TableActiveRowID',
        'fieldcaptions' => 'TableFieldCaptions', 'defaultsortcol' => 'TableDefaultSortCol',
        'idfield' => 'TableIDField', 'groupfield' => 'TableGroupField',
        'showcaptions' => 'TableShowCaptions', 'showidfield' => 'TableShowIDField',
        'attribtable' => 'TableAttrib', 'attribtd' => 'TableTDAttrib',
        'attribtr' => 'TableTRAttrib', 'fieldstyles' => 'TableStyles',
        'linkbaseurl' => 'TableLinkBaseURL', 'onclick' => 'TableOnClick',
        'filtered' => 'TableFiltered', 'name' => 'TableName',
        'emptymessage' => 'TableEmptyMessage',
        // RowClassField: veld waarvan de waarde de CSS-klasse van de RIJ wordt in plaats
        // van een eigen kolom. Rapporten die hun <tr> zelf schreven markeerden zo de
        // uitzonderingen ("kanniet", "voorlopig"); zonder dit konden ze niet naar deze
        // klasse over. FieldFilters: per kolom de waarde van data-filter op de <th>
        // ('N' = geen filterkeuzelijst, voor icoon-/actiekolommen).
        'rowclassfield' => 'TableRowClassField', 'fieldfilters' => 'TableFieldFilters',
        // Master-detail: onder elke rij van deze tabel één of meer eigen tabelletjes met
        // de bijbehorende regels. Zie Details hieronder.
        'details' => 'TableDetails',
        'detailsql' => 'TableDetailSQL', 'detailkeyfield' => 'TableDetailKeyField',
        'detailcaptions' => 'TableDetailCaptions', 'detailemptymessage' => 'TableDetailEmptyMessage',
    ];

    private function _setProp(string $name, $value): void
    {
        $key = strtolower($name);
        if (!isset(self::PROP_MAP[$key])) { $this->$name = $value; return; }
        $field = self::PROP_MAP[$key];
        if ($key === 'activerowid') { $value = $value . ''; }
        $this->$field = $value;
        if ($key === 'recordset') { $this->TableExternalRS = true; }
        if ($key === 'filtered' && $value) { $this->TableSortable = false; }
    }

    public function __set($name, $value) { $this->_setProp($name, $value); }

    public function __get($name)
    {
        $key = strtolower($name);
        return isset(self::PROP_MAP[$key]) ? ($this->{self::PROP_MAP[$key]} ?? null) : ($this->$name ?? null);
    }

    public function __call($name, $args)
    {
        // Property Get used as a method call, e.g. $oTable->SQL(), or a
        // Property Let used as a method call, e.g. $oTable->ShowIDField(false).
        $key = strtolower($name);
        if (isset(self::PROP_MAP[$key])) {
            if (count($args) === 0) { return $this->{self::PROP_MAP[$key]} ?? null; }
            $this->_setProp($name, $args[0]);
            return null;
        }
        throw new \BadMethodCallException('LibTable::' . $name . '() is not defined');
    }

    public $TableTRColor1;
    public $TableTRColor2;
    public $TableTRActiveColor;
    public $TableTRHoverColor;
    public $TableRS;
    /** Veld dat de rij-klasse levert (leeg = uit). Wordt niet als kolom getoond. */
    public $TableRowClassField = '';
    /** data-filter per kolomindex, bv. [0 => 'N', 1 => 'N']. */
    public $TableFieldFilters = [];

    /**
     * Master-detail: onder elke masterrij één of meer eigen tabellen.
     *
     *   $t->SQL     = 'SELECT ID, VolledigeNaam FROM tblDeelnemers ...';
     *   $t->Details = [
     *       ['title'    => 'Bijeenkomsten',
     *        'sql'      => 'SELECT fkDeelnemer, Datum, Status FROM tblPresentie
     *                       WHERE fkDeelnemer IN ([IDS]) ORDER BY Datum',
     *        'keyfield' => 'fkDeelnemer'],
     *       ['title'    => 'Toetsen',
     *        'sql'      => 'SELECT fkDeelnemer, Naam, Cijfer FROM tblToetsen WHERE fkDeelnemer IN ([IDS])',
     *        'keyfield' => 'fkDeelnemer',
     *        'captions' => ['Toets', 'Cijfer'],
     *        'emptymessage' => 'Nog geen toetsen'],
     *   ];
     *
     * Elk kind is een array met: sql (verplicht), keyfield (verplicht), en optioneel
     * title, captions en emptymessage. De kinderen verschijnen in de opgegeven volgorde
     * onder de masterrij, elk in een eigen rij over de volle breedte.
     *
     * Waarom [IDS] en niet [ID]: een query per masterrij is hetzelfde N+1-patroon dat de
     * omgezette rapporten traag maakt. Met [IDS] draait elke kindquery ÉÉN keer en worden
     * de regels hier op keyfield bij de juiste master gelegd. Wie per se per rij wil
     * bevragen gebruikt [ID]; dan draait die kindquery wel per masterrij.
     *
     * keyfield zelf wordt in de kindtabel niet als kolom getoond.
     *
     * @var array<int,array{sql:string,keyfield:string,title?:string,captions?:array,emptymessage?:string}>
     */
    public $TableDetails = [];
    /** Korte vorm voor één kind; wordt bij het renderen naar Details vertaald. */
    public $TableDetailSQL = '';
    /** Kolom in het detailresultaat die naar de master-ID wijst (korte vorm). */
    public $TableDetailKeyField = '';
    /** Optionele kopteksten voor de detailtabel (korte vorm). */
    public $TableDetailCaptions = [];
    /** Tekst als een masterrij geen detailregels heeft (korte vorm; leeg = geen rij). */
    public $TableDetailEmptyMessage = '';
    /** Per kind de regels, gegroepeerd op de waarde van keyfield (na het prefetchen). */
    private $TableDetailRows = [];
    /** ID's van de masterrijen die deze render toont (voor de [IDS]-prefetch). */
    private $TableMasterIDs = [];
    /** Staat deze tabel als kind ónder een masterrij? Dan geen eigen <lib-table>-wikkel. */
    protected $TableIsChild = false;
    /** Zet de kindmodus aan (alleen de mastertabel roept dit aan). */
    public function _alsKind(): void { $this->TableIsChild = true; }
    private $TableID;
    private $TableName = '';
    private $TableSQL;
    private $TableIDField;
    private $TableGroupField;
    private $TableActiveRowID;
    private $TableConnection;
    private $TableLinkBaseURL = '';
    private $TableLinkTarget = '';
    private $TableFieldCaptions = [];
    private $TableDefaultSortCol;
    private $TableRowsPerPage;
    private $TablePage;
    private $TableStyles;
    private $TableEmptyMessage;
    private $TableAttrib;
    private $TableTRClass;
    private $TableTHAttrib;
    private $TableTDAttrib;
    private $TableShowCaptions;
    private $TableShowIDField;
    private $TableFieldWidths = [];
    private $TableExternalRS;
    private $TableOnClick;
    private $TableDeleteField;
    private $TableDeleteAction;
    private $TableDeleteClassname;
    private $TableSortable;
    private $TableFiltered;
    /**
    * Render
    *
    */

    /**
     * data-filter voor de kop van kolom $kolom, of '' als er niets is opgegeven.
     * 'N' zet de filterkeuzelijst uit — op een icoon- of actiekolom valt niets te filteren.
     */
    private function _filterAttrib($kolom): string
    {
        if (!Arr::isArray($this->TableFieldFilters) || !isset($this->TableFieldFilters[$kolom])) {
            return '';
        }
        return ' data-filter="' . Server::htmlEncode((string)$this->TableFieldFilters[$kolom]) . '"';
    }

    /**
     * Extra CSS-klasse voor de huidige rij, uit RowClassField. Leeg als die eigenschap niet
     * gebruikt wordt. Alleen letters/cijfers/koppeltekens: een gegevenswaarde mag nooit een
     * attribuut de <TR> in schrijven.
     */
    private function _rijKlasse(): string
    {
        if ($this->TableRowClassField == '') {
            return '';
        }
        $waarde = trim((string)($this->TableRS->fields[$this->TableRowClassField] ?? ''));
        if ($waarde == '') {
            return '';
        }
        return ' ' . preg_replace('/[^A-Za-z0-9_\- ]/', '', $waarde);
    }

    /** Velden die gegevens dragen maar geen eigen kolom zijn: rijklasse en groepsveld. */
    private function _isVerborgenVeld($naam): bool
    {
        $naam = strtolower((string)$naam);
        if ($this->TableRowClassField != '' && $naam === strtolower((string)$this->TableRowClassField)) {
            return true;
        }
        return $this->TableGroupField != '' && $naam === strtolower((string)$this->TableGroupField);
    }


    /**
     * Aantal kolommen dat de master-tabel toont — nodig voor de colspan van een groepskop
     * of een detailrij.
     */
    private function _kolomAantal(): int
    {
        if (Arr::isArray($this->TableFieldCaptions) && count($this->TableFieldCaptions) > 0) {
            return count($this->TableFieldCaptions) + ($this->TableDeleteField != '' ? 1 : 0);
        }
        $n = 0;
        foreach ($this->TableRS->Fields() as $fld) {
            if ($this->_isVerborgenVeld($fld->name())) { continue; }
            if (!$this->TableShowIDField
                && strtolower((string)$fld->name()) === strtolower((string)$this->TableIDField)) { continue; }
            $n++;
        }
        return $n + ($this->TableDeleteField != '' ? 1 : 0);
    }

    /**
     * Groepskop: bij elke nieuwe waarde van GroupField een rij over de volle breedte met
     * die waarde. Het veld zelf is stuurinformatie en wordt geen kolom. Zo staat een
     * agenda per dag onder een datumkop zonder dat het rapport zijn eigen <table> hoeft
     * te schrijven.
     */
    private function _groepsKop(&$vorigeGroep): void
    {
        if ($this->TableGroupField == '') {
            return;
        }
        $waarde = (string)($this->TableRS->fields[$this->TableGroupField] ?? '');
        if ($waarde === $vorigeGroep) {
            return;
        }
        $vorigeGroep = $waarde;
        echo '<TR class="libTableGroup"><TH nowrap ' . $this->TableTHAttrib
            . ' colspan=' . $this->_kolomAantal() . '>'
            . (stripos($waarde, '<html>') === 0 ? substr($waarde, 6) : Server::htmlEncode($waarde))
            . '</TH></TR>' . PHP_EOL;
    }

    /**
     * De kinderen als lijst, met de korte vorm (DetailSQL/DetailKeyField) erin vertaald.
     *
     * @return array<int,array>
     */
    private function _kinderen(): array
    {
        $kinderen = Arr::isArray($this->TableDetails) ? array_values($this->TableDetails) : [];
        if ($this->TableDetailSQL != '') {
            array_unshift($kinderen, [
                'sql'          => $this->TableDetailSQL,
                'keyfield'     => $this->TableDetailKeyField,
                'captions'     => $this->TableDetailCaptions,
                'emptymessage' => $this->TableDetailEmptyMessage,
            ]);
        }
        $uit = [];
        foreach ($kinderen as $kind) {
            // Een kind mag een LibTableChild-OBJECT zijn: dan erft het alles wat deze
            // klasse kan (ruwe <html>-cellen, <sort:KEY>, FieldFilters, RowClassField,
            // en desnoods eigen kinderen). De array-vorm blijft werken voor het simpele
            // geval.
            if (is_object($kind)) {
                if (($kind->KeyField ?? '') == '') {
                    continue;
                }
                $uit[] = [
                    'object'   => $kind,
                    'sql'      => (string)($kind->SQL ?? ''),
                    'keyfield' => (string)$kind->KeyField,
                    'title'    => (string)($kind->Title ?? ''),
                    'recordset' => $kind->ChildRecordset ?? null,
                    'emptymessage' => (string)($kind->EmptyMessage ?? ''),
                ];
                continue;
            }
            if (!is_array($kind) || ($kind['keyfield'] ?? '') == '') {
                continue;
            }
            if (($kind['sql'] ?? '') == '' && !isset($kind['recordset'])) {
                continue;
            }
            $uit[] = $kind;
        }
        return $uit;
    }

    /** Draait deze tabel op een kindquery met [IDS]? Dan moeten de master-id's vooraf bekend zijn. */
    private function _heeftIdsPrefetch(): bool
    {
        foreach ($this->_kinderen() as $kind) {
            if (strpos((string)($kind['sql'] ?? ''), '[IDS]') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * De regels van kind $n bij master-ID $masterID. Met [IDS] komt alles uit één query
     * (eenmalig opgehaald en hier gegroepeerd), met [ID] draait de query per masterrij.
     *
     * @return array<int,array>
     */
    private function _detailRegels(int $n, array $kind, $masterID): array
    {
        // Kant-en-klare gegevens: één keer indelen op sleutel, daarna opzoeken.
        if (isset($kind['recordset']) && $kind['recordset'] !== null) {
            if (!isset($this->TableDetailRows[$n])) {
                $this->TableDetailRows[$n] = $this->_groepeer($this->_regelsUit($kind['recordset']), (string)$kind['keyfield']);
            }
            return $this->TableDetailRows[$n][(string)$masterID] ?? [];
        }
        if (strpos((string)($kind['sql'] ?? ''), '[IDS]') !== false) {
            if (!isset($this->TableDetailRows[$n])) {
                $this->TableDetailRows[$n] = $this->_detailPrefetch($kind);
            }
            return $this->TableDetailRows[$n][(string)$masterID] ?? [];
        }
        $sql = str_replace('[ID]', (string)(int)$masterID, (string)$kind['sql']);
        return $this->_regels($sql);
    }

    /** Eén kindquery voor alle getoonde master-id's, gegroepeerd op keyfield. */
    private function _detailPrefetch(array $kind): array
    {
        $ids = [];
        foreach ($this->TableMasterIDs as $id) {
            if ($id !== '' && $id !== null) { $ids[] = (int)$id; }
        }
        if (!$ids) {
            return [];
        }
        return $this->_groepeer(
            $this->_regels(str_replace('[IDS]', implode(',', $ids), (string)$kind['sql'])),
            (string)$kind['keyfield']
        );
    }

    /**
     * Deel regels in op de waarde van $sleutelveld. Eén keer doorlopen, daarna is opzoeken
     * bij een masterrij een directe treffer — geen lus per rij zoals een ADO-Find.
     */
    private function _groepeer(array $regels, string $sleutelveld): array
    {
        $uit = [];
        foreach ($regels as $rij) {
            $uit[(string)($rij[$sleutelveld] ?? '')][] = $rij;
        }
        return $uit;
    }

    /**
     * Regels uit een meegegeven recordset of array. Een recordset wordt hier ÉÉN keer
     * uitgelezen; de aanroeper hoeft er verder niets mee te doen.
     *
     * @param mixed $bron RecordSet of array van rijen
     */
    private function _regelsUit($bron): array
    {
        if (Arr::isArray($bron)) {
            return array_values($bron);
        }
        $regels = [];
        while ($bron !== null && $bron !== false && !$bron->EOF) {
            $regels[] = $bron->fields;
            $bron->MoveNext();
        }
        return $regels;
    }

    /** Alle rijen van een query als array. */
    private function _regels(string $sql): array
    {
        $regels = [];
        $rs = Database::openRS($sql, $this->TableConnection ?: 'data');
        while ($rs !== null && $rs !== false && !$rs->EOF) {
            $regels[] = $rs->fields;
            $rs->MoveNext();
        }
        return $regels;
    }

    /**
     * Render de kinderen van één masterrij: per kind een rij over de volle breedte met
     * een genest tabelletje.
     */
    private function _detailRij($masterID): void
    {
        $kinderen = $this->_kinderen();
        if (!$kinderen) {
            return;
        }
        $breedte = $this->_kolomAantal();
        foreach ($kinderen as $n => $kind) {
            $regels = $this->_detailRegels($n, $kind, $masterID);
            $titel  = (string)($kind['title'] ?? '');
            if (!$regels) {
                $leeg = (string)($kind['emptymessage'] ?? '');
                if ($leeg == '') {
                    continue;
                }
                echo '<TR class="libTableDetail"><TD ' . $this->TableTDAttrib . '1 colspan=' . $breedte . '>'
                    . ($titel != '' ? '<strong>' . Server::htmlEncode($titel) . '</strong> ' : '')
                    . '<em>' . Server::htmlEncode($leeg) . '</em></TD></TR>' . PHP_EOL;
                continue;
            }
            if (isset($kind['object'])) {
                // Het kind rendert zichzelf: zo gelden daar dezelfde cel-conventies en
                // opties als in de mastertabel, en kan het zelf weer kinderen hebben.
                $obj = $kind['object'];
                $obj->Recordset = new RecordSet(new \ArrayIterator($regels), false, true);
                $obj->_alsKind();
                echo '<TR class="libTableDetail"><TD ' . $this->TableTDAttrib . '1 colspan=' . $breedte . '>';
                if ($titel != '') {
                    echo '<div class="libTableDetailTitle">' . Server::htmlEncode($titel) . '</div>';
                }
                $obj->Render();
                echo '</TD></TR>' . PHP_EOL;
                continue;
            }
            $kolommen = array_values(array_filter(array_keys($regels[0]), function ($k) use ($kind) {
                return !is_int($k) && strtolower((string)$k) !== strtolower((string)$kind['keyfield']);
            }));
            $koppen = (Arr::isArray($kind['captions'] ?? null) && count($kind['captions']) > 0)
                ? $kind['captions'] : $kolommen;
            echo '<TR class="libTableDetail"><TD ' . $this->TableTDAttrib . '1 colspan=' . $breedte . '>';
            if ($titel != '') {
                echo '<div class="libTableDetailTitle">' . Server::htmlEncode($titel) . '</div>';
            }
            echo '<table class="libTableDetailTable"><thead><tr>';
            foreach ($koppen as $kop) {
                echo '<th nowrap>' . Server::htmlEncode(Str::firstUpper(str_replace('_', ' ', (string)$kop))) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($regels as $regel) {
                echo '<tr>';
                foreach ($kolommen as $kolom) {
                    $waarde = (string)($regel[$kolom] ?? '');
                    echo '<td>' . (stripos($waarde, '<html>') === 0 ? substr($waarde, 6) : Server::htmlEncode($waarde)) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></TD></TR>' . PHP_EOL;
        }
    }

    public function Render()
    {
        global $Table_CtrlID;
        $fld = null;
        $curID = null;
        $x = null;
        $fldTel = null;
        $blnShow = null;
        $intRow = 0;
        $blnPrevious = null;
        $blnNext = null;
        $blnAbort = null;
        $strColor = "";
        $sNiceField = null;
        $value = null;
        $curAttrib = null;
        if (is_object($this->TableRS)) {
            $this->TableExternalRS = true;
        }
        if (!$this->TableExternalRS) {
            $this->TableRS = Database::openRS($this->TableSQL, $this->TableConnection, adOpenForwardOnly);
            if ($this->TableRS === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            $this->TableRS->MoveNext();
        }
        if ($this->TableRS->eof && $this->TableEmptyMessage != '') {
            echo $this->TableEmptyMessage;
        } else {
            if ($Table_CtrlID == 1 && $this->TableSortable) {
                if ($this->TableShowCaptions) {
                    echo '<script src="' . Application::get('base_path', '') . 'library/classes/class_tablesort.js"></script>';
                }
                echo '<script src="' . Application::get('base_path', '') . 'library/classes/class_tablescroll.js"></script>';
            }
            echo '<script>var intActiveRowID=' . ($this->TableActiveRowID != '' ? $this->TableActiveRowID : '-1') . ';' . PHP_EOL . 'function row_act' . $Table_CtrlID . '(id){' . PHP_EOL . 'if (intActiveRowID!=id){' . PHP_EOL . '
            if (intActiveRowID!=-1){' . PHP_EOL . "    var row=document.getElementById('lt_row_' + intActiveRowID.toString());" . PHP_EOL . "
                if (row) {row.className='" . $this->TableTRClass . "'}" . PHP_EOL . '  }' . PHP_EOL . '}' . PHP_EOL . "row=document.getElementById('lt_row_' + id.toString() );
                " . PHP_EOL . 'if (row) {' . PHP_EOL . "  row.className='" . $this->TableTRClass . "_active';
                " . PHP_EOL . '  intActiveRowID=id;' . PHP_EOL . '}' . PHP_EOL . '}' . PHP_EOL;
                echo 'function row_hover' . $Table_CtrlID . '(id, bOn){' . PHP_EOL . '
                if (id!=intActiveRowID) {' . PHP_EOL . "    var x=document.getElementById('lt_row_'+id.toString());" . PHP_EOL . "
                    if(x){x.className=(bOn?'" . $this->TableTRClass . "_hover':'libTableTR')}" . PHP_EOL . '  }' . PHP_EOL . '}</script>' . PHP_EOL;
                    if (!$this->TableIsChild) {
                        echo '<lib-table resizable>';
                    }
                    echo'<TABLE data-name="' . substr(str_replace('?','', str_replace('/','', $this->TableName)), 0, max(0, min(30, strlen(str_replace('?','', str_replace('/','', $this->TableName)))))) . chr(34) . ' ' . $this->TableAttrib .($this->TableID !='' ?' id="' . $this->TableID . chr(34) : ($this->TableSortable ?' id=sorttable' . $Table_CtrlID :'')) .' onselectstart="return(false)"><THEAD><TR>';
                    if ($this->TableShowCaptions) {
                        if ($this->TableDeleteField != '') {
                            echo '<TH ' . $this->TableTHAttrib . '></TH>';
                        }
                        if (Arr::isArray($this->TableFieldCaptions) && count($this->TableFieldCaptions) > 0) {
                            for ($x = 0; $x <= (count($this->TableFieldCaptions) - 1); $x++) {
                                echo '<TH nowrap ' . $this->TableTHAttrib . $this->_filterAttrib($x) . '>' . Server::htmlEncode($this->TableFieldCaptions[$x]) . '</TH>';
                            }
                        } else {
                            $kopTel = 0;
                            // No explicit captions -> auto-generate headers from the
                            // field names (TableFieldCaptions defaults to [], which is
                            // Arr::isArray()==true but empty, so this fallback was skipped).
                            foreach ($this->TableRS->Fields() as $fld) {
                                if ($this->_isVerborgenVeld($fld->name())) { continue; }
                                if ((!!$this->TableShowIDField || (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableIDField) ? "" : strtolower($this->TableIDField))) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableGroupField) ? "" : strtolower($this->TableGroupField)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableDeleteField) ? "" : strtolower($this->TableDeleteField))) {
                                    $sNiceField = Str::firstUpper(str_replace('_', ' ', $fld->name()));
                                    echo '<TH nowrap ' . $this->TableTHAttrib . $this->_filterAttrib($kopTel) . '>' . Server::htmlEncode($sNiceField) . '</TH>';
                                    $kopTel++;
                                }
                            }
                        }
                    }
                    echo '</TR></THEAD><TBODY>';
                    // Master-detail met [IDS]: de detailquery draait één keer voor alle
                    // masterrijen, dus de id's moeten vooraf bekend zijn. Daarvoor lezen we
                    // de masterrijen hier in het geheugen en renderen we daaruit — anders
                    // zou de cursor al op de laatste rij staan als de lus begint.
                    if ($this->_heeftIdsPrefetch()) {
                        $masterRijen = [];
                        while (!$this->TableRS->EOF) {
                            $masterRijen[] = $this->TableRS->fields;
                            $this->TableRS->MoveNext();
                        }
                        $this->TableMasterIDs = [];
                        foreach ($masterRijen as $rij) {
                            $this->TableMasterIDs[] = $rij[$this->TableIDField] ?? '';
                        }
                        $this->TableRS = new RecordSet(new \ArrayIterator($masterRijen), false, true);
                    }
                    $vorigeGroep = null;
                    $intRow = 0;
                    $blnPrevious = false;
                    $blnNext = false;
                    $blnAbort = false;
                    $curAttrib = $this->TableTDAttrib . '1';
                    while (!$this->TableRS->EOF && !$blnAbort) {
                        $intRow = $intRow + 1;
                        $blnShow = true;
                        if ($this->TableRowsPerPage != -1) {
                            if ($intRow < $this->TablePage - 1 * $this->TableRowsPerPage + 1) {
                                $blnPrevious = true;
                                $blnShow = false;
                            }
                            if ($intRow > $this->TablePage * $this->TableRowsPerPage) {
                                $blnNext = true;
                                $blnShow = false;
                                $blnAbort = true;
                            }
                        }
                        if ($blnShow) {
                            $curID = $this->TableRS->Fields[$this->TableIDField];
                            $this->_groepsKop($vorigeGroep);
                            echo '<TR ID="lt_row_' . $curID . '" class="' . $this->TableTRClass . $this->_rijKlasse() . '" onmouseOver="row_hover' . $Table_CtrlID . '(' . $curID . ',true)" onmouseOut="row_hover' . $Table_CtrlID . '(' . $curID . ',false)" onclick="row_act' . $Table_CtrlID . '(' . $curID . ');' . str_replace('[ID]', $curID, $this->TableOnClick) . '">';
                            $fldTel = 0;
                            if ($this->TableDeleteField != '') {
                                if ($this->TableRS->fields[$this->TableDeleteField]!= '0') {
                                    $curAttrib = $this->TableTDAttrib . '_deleted"';
                                }
                                echo '<td ' . $curAttrib . ' ><a href="' . $this->TableDeleteAction . $curID . '"><img src="library/images/formbtn_del.gif"></a></td>';
                            }
                            foreach ($this->TableRS->Fields() as $fld) {
                                if ($this->_isVerborgenVeld($fld->name())) { continue; }
                                if ((!!$this->TableShowIDField || (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableIDField) ? "" : strtolower($this->TableIDField))) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableGroupField) ? "" : strtolower($this->TableGroupField)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableDeleteField) ? "" : strtolower($this->TableDeleteField))) {
                                    $raw = (string)$fld;
                                    // <sort:KEY> marker: sets this cell's sort key
                                    // (honoured by <lib-table> via the data-sort
                                    // attribute) and is itself NOT displayed.
                                    $sortKey = null;
                                    if (preg_match('/<sort:([^>]*)>/i', $raw, $mSort)) {
                                        $sortKey = $mSort[1];
                                        $raw = str_replace($mSort[0], '', $raw);
                                    }
                                    echo '<TD ' . $curAttrib;
                                    if (Arr::isArray($this->TableFieldWidths) && isset($this->TableFieldWidths[$fldTel])) {
                                        echo ' width=' . $this->TableFieldWidths[$fldTel];
                                    }
                                    if (Arr::isArray($this->TableStyles) && isset($this->TableStyles[$fldTel])) {
                                        echo ' style="' . $this->TableStyles[$fldTel] . chr(34);
                                    }
                                    if ($sortKey !== null) {
                                        echo ' data-sort="' . Server::htmlEncode($sortKey) . '"';
                                    }
                                    echo '>';
                                    if ($raw === '') {
                                        echo '&nbsp;';
                                    } elseif (substr($raw, 0, 6) === '<html>') {
                                        // Raw-HTML cell: strip the <html> marker and emit the
                                        // inner content unencoded. Checked on the RAW value so
                                        // fixUnicode can't mangle the marker.
                                        echo substr($raw, 6);
                                    } else {
                                        echo Server::htmlEncode(Lib_FixDateValue(Html::fixUnicode($raw)));
                                    }
                                    echo '</TD>';
                                }
                                $fldTel = $fldTel + 1;
                            }
                            echo '</TR>' . PHP_EOL;
                            $this->_detailRij($curID);
                            if ($curAttrib == $this->TableTDAttrib . '1') {
                                $curAttrib = $this->TableTDAttrib . '2';
                            } else {
                                $curAttrib = $this->TableTDAttrib . '1';
                            }
                        }
                        // Advance the cursor each iteration (the ASP->PHP conversion
                        // dropped the RS.MoveNext at the end of the Do-While body, so
                        // the loop re-rendered the first row until it hit RowsPerPage).
                        $this->TableRS->MoveNext();
                    }
                    echo '</TBODY>';
                    if ($blnPrevious || $blnNext) {
                        echo '<TFOOT><TR class=sortbottom><TH nowrap ' . $this->TableTHAttrib . ' colspan=99>';
                        $this->WriteNavButton('nav_rwnd.gif', $this->TablePage - 1, $blnPrevious);
                        $this->WriteNavButton('nav_fwd.gif', $this->TablePage + 1, $blnNext);
                        echo '</TH></TR></TFOOT>';
                    }
                    echo '</TABLE>';
                    if (!$this->TableIsChild) {
                        echo '</lib-table>';
                    }
                    if ($this->TableDefaultSortCol != -1 && $this->TableSortable) {
                        echo '<script>sortables_initial(' . $this->TableDefaultSortCol . ');</script>' . PHP_EOL;
                    }
                }
                if (!$this->TableExternalRS) {
                    $this->TableRS = null;
                }
            }
            /**
            * Deletesettings
            *
            */
            public function DeleteSettings($sField, $sAction, $sClass)
            {
                $this->TableDeleteField = $sField;
                $this->TableDeleteAction = $sAction;
                $this->TableDeleteClassname = $sClass;
            }
            /**
            * Writenavbutton
            *
            */
            public function WriteNavButton($cImg, $ToPage, $blnActive)
            {
                if ($blnActive) {
                    echo ' <A href="' . Request::addToURL('', 'TablePage', $ToPage) . '">';
                }
                echo '<img src=' . Application::get('base_path', '') . 'library/images/' . $cImg . ' width=17 height=16 hspace=3 ';
                if ($blnActive) {
                    echo 'border=0></a>';
                } else {
                    echo 'style=filter:alpha(opacity=50)>';
                }
            }
            /**
            *   Construct
            */
            public function __construct()
            {
                global $Table_CtrlID;
                $this->TableID = '';
                // generated
                $this->TableSQL = '';
                $this->TableIDField = 'ID';
                $this->TableGroupField = '';
                $this->TableActiveRowID = '';
                $this->TableConnection = 'data';
                $this->TableDefaultSortCol = -1;
                $this->TableRowsPerPage = 50;
                if (Request::query('TablePage', '') != '') {
                    $this->TablePage = intval(round(floatval(substr(Request::queryInt('TablePage'), 0, max(0, min(4, strlen(Request::queryInt('TablePage'))))))));
                } else {
                    $this->TablePage = 1;
                }
                $this->TableDeleteField = '';
                $this->TableDeleteAction = '';
                $this->TableDeleteClassname = '';
                // layout propertiues
                $this->TableTRColor1 = '#FFFFEE';
                $this->TableTRColor2 = '#FFFFFF';
                $this->TableAttrib = 'cellpadding="4" cellspacing="0" width="100%" class="libTable blockselect"';
                $this->TableTRClass = 'libTableTR';
                $this->TableTDAttrib = 'class=libTableTD';
                $this->TableTHAttrib = 'class=libTableTH';
                $this->TableShowCaptions = true;
                $this->TableShowIDField = true;
                $this->TableStyles = '';
                $this->TableExternalRS = false;
                $this->TableOnClick = '';
                $this->TableSortable = true;
                $this->TableFiltered = false;
                $this->TableEmptyMessage = 'Geen gegevens om weer te geven';
                $Table_CtrlID = $Table_CtrlID + 1;
            }
            /**
            *   Destruct
            */
            public function __destruct()
            {
                // Cleanup (if any)
            }
        }

/**
 * LibTableChild — een kindtabel onder een masterrij van LibTable.
 *
 * Het is een gewone LibTable: alles wat daar kan, kan hier ook (ruwe <html>-cellen,
 * <sort:KEY>, FieldCaptions/FieldFilters, RowClassField, en zelfs weer eigen kinderen).
 * Er komen twee dingen bij: KeyField (welke kolom naar de master-ID wijst) en Title
 * (kopje boven het tabelletje).
 *
 *   $kind = new LibTableChild();
 *   $kind->Title    = 'Bijeenkomsten';
 *   $kind->SQL      = 'SELECT fkDeelnemer, Datum, Status FROM tblPresentie
 *                      WHERE fkDeelnemer IN ([IDS]) ORDER BY Datum';
 *   $kind->KeyField = 'fkDeelnemer';
 *
 *   $master = new LibTable();
 *   $master->SQL     = 'SELECT ID, VolledigeNaam FROM tblDeelnemers ...';
 *   $master->Details = [$kind, $tweedeKind];
 *
 * [IDS] wordt vervangen door de id's van álle getoonde masterrijen: de kindquery draait
 * dus ÉÉN keer en de regels worden hier op KeyField bij de juiste master gelegd. Wie een
 * al opgehaalde recordset heeft, zet die op ChildRecordset — die wordt één keer
 * uitgelezen en op dezelfde manier ingedeeld.
 */
class LibTableChild extends LibTable
{
    /** Kolom in dit resultaat die naar de ID van de masterrij wijst. Verplicht. */
    public $KeyField = '';
    /** Kopje boven de kindtabel (leeg = geen kopje). */
    public $Title = '';
    /** Al opgehaalde regels/recordset in plaats van SQL. */
    public $ChildRecordset = null;
}

?>
