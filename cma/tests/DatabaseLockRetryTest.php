<?php
/**
 * Database probeert een vergrendelde database opnieuw in plaats van meteen te falen.
 *
 * Aanleiding (mijn.rino.nl): "The database has been placed in a state by user 'Admin' on machine
 * … that prevents it from being opened or locked" — Access wordt gecomprimeerd of gekopieerd, of
 * een ander proces heeft hem even exclusief open. Na een korte pauze gaat hij meestal weer open.
 * Nu lopen het openen van de verbinding (createPDOConnection, native odbc_connect) en alle
 * prepare+execute (prepareAndExecute, openRS) via withLockRetry: tot 8 pogingen met
 * 250/500/.../1750 ms ertussen; elke andere fout gaat meteen en ongewijzigd door.
 *
 * Run met: php cma/tests/TestRunner.php DatabaseLockRetryTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Database;

class DatabaseLockRetryTest extends TestCase
{
    private const LOCK = "SQLSTATE[HY000]: General error: -1034 [Microsoft][ODBC Microsoft Access Driver] The database has been placed in a state by user 'Admin' on machine 'mijn-rino-01' that prevents it from being opened or locked.";

    private int $bewaardeWacht = 250;

    public function setUp(): void
    {
        $p = new \ReflectionProperty(Database::class, 'lockWaitMs');
        $p->setAccessible(true);
        $this->bewaardeWacht = $p->getValue();
        $p->setValue(null, 1);  // de test hoeft niet echt 2,5 s te wachten
    }

    public function tearDown(): void
    {
        $p = new \ReflectionProperty(Database::class, 'lockWaitMs');
        $p->setAccessible(true);
        $p->setValue(null, $this->bewaardeWacht);
    }

    /** Roep de private withLockRetry aan met een actie die $fouten keer $melding gooit. */
    private function probeer(int $fouten, string $melding, int &$pogingen)
    {
        $pogingen = 0;
        $m = new \ReflectionMethod(Database::class, 'withLockRetry');
        $m->setAccessible(true);
        return $m->invoke(null, function () use ($fouten, $melding, &$pogingen) {
            $pogingen++;
            if ($pogingen <= $fouten) {
                throw new \PDOException($melding);
            }
            return 'gelukt';
        });
    }

    public function testHerkentVergrendelingen(): void
    {
        $this->assertTrue(Database::isLockError(self::LOCK), 'placed in a state');
        $this->assertTrue(Database::isLockError('Could not lock file.'), 'could not lock file');
        $this->assertTrue(Database::isLockError("The Microsoft Access database engine cannot open or write to the file. It is already opened exclusively … Could not use 'x.mdb'; file already in use."), 'file already in use');
        $this->assertTrue(Database::isLockError('Could not update; currently locked.'), 'currently locked');
        $this->assertTrue(Database::isLockError('SQLSTATE[HY000]: General error: 5 database is locked'), 'sqlite: database is locked');
        // Nederlandstalig ODBC-stuurprogramma (Windows nl-NL), zoals op de ontwikkelmachine gemeten.
        $nl = "SQLSTATE[HY000]: General error: -1102 [Microsoft][ODBC Microsoft Access-stuurprogramma] Bijwerken is niet mogelijk. Het object wordt momenteel gebruikt door de gebruiker admin op LAPTOP_DS en is vergrendeld. (SQLExecute[-1102] at ext\\pdo_odbc\\odbc_stmt.c:267)";
        $this->assertTrue(Database::isLockError($nl), 'nl: -1102 bijwerken niet mogelijk');
        $this->assertTrue(Database::isLockError('Het object is vergrendeld.'), 'nl: is vergrendeld');
        $this->assertTrue(Database::isLockError('SQLSTATE[HY000]: General error: -1102 onbekende tekst'), 'foutcode -1102 alleen');
        // Bij parallelle processen (IIS + CLI) gemeten: de database is 'door een onbekende gebruiker' in een toestand gebracht; zonder naam, en bij het openen.
        $nl3810 = 'SQLSTATE[HY000] SQLDriverConnect: -3810 [Microsoft][ODBC Microsoft Access-stuurprogramma] De database is door een onbekende gebruiker in een toestand gebracht waarin deze niet kan worden geopend of vergrendeld.';
        $this->assertTrue(Database::isLockError($nl3810), 'nl: -3810 onbekende gebruiker bij het openen');
        $this->assertTrue(Database::isLockError('The database has been placed in a state by an unknown user that prevents it from being opened or locked.'), 'en: onbekende gebruiker');
        $this->assertTrue(Database::isLockError('SQLSTATE[HY000] SQLDriverConnect: -3810 onbekende tekst'), 'foutcode -3810 alleen');
        $pogingen = 0;
        $this->assertEquals('gelukt', $this->probeer(2, $nl3810, $pogingen));
        $this->assertEquals(3, $pogingen, 'de retry werkt ook voor -3810');
        $this->assertFalse(Database::isLockError('Syntaxisfout in FROM-clausule.'), 'nl: een SQL-fout is geen vergrendeling');
        $this->assertFalse(Database::isLockError('Syntax error in FROM clause.'), 'een SQL-fout is geen vergrendeling');
        $this->assertFalse(Database::isLockError("Too few parameters. Expected 1."), 'een ontbrekend veld ook niet');
    }

    public function testLuktNaVergrendelingMetNederlandseMelding(): void
    {
        $nl = "SQLSTATE[HY000]: General error: -1102 [Microsoft][ODBC Microsoft Access-stuurprogramma] Bijwerken is niet mogelijk. Het object wordt momenteel gebruikt door de gebruiker admin op LAPTOP_DS en is vergrendeld.";
        $pogingen = 0;
        $this->assertEquals('gelukt', $this->probeer(3, $nl, $pogingen));
        $this->assertEquals(4, $pogingen, 'de retry werkt ook voor de Nederlandse melding');
    }

    public function testLuktNaTweeVergrendelingen(): void
    {
        $pogingen = 0;
        $this->assertEquals('gelukt', $this->probeer(2, self::LOCK, $pogingen));
        $this->assertEquals(3, $pogingen, 'derde poging slaagt');
    }

    public function testBlijftVergrendeldGeeftNaAllePogingenDeFout(): void
    {
        $pogingen = 0;
        $fout = null;
        try {
            $this->probeer(99, self::LOCK, $pogingen);
        } catch (\PDOException $e) {
            $fout = $e;
        }
        $this->assertTrue($fout !== null && $fout->getMessage() === self::LOCK, 'de oorspronkelijke fout gaat door');
        $this->assertEquals(8, $pogingen, 'acht pogingen');
    }

    public function testAndereFoutMeteenDoor(): void
    {
        $pogingen = 0;
        $fout = null;
        try {
            $this->probeer(99, 'Syntax error in FROM clause.', $pogingen);
        } catch (\PDOException $e) {
            $fout = $e;
        }
        $this->assertTrue($fout !== null, 'de fout gaat door');
        $this->assertEquals(1, $pogingen, 'geen tweede poging bij een SQL-fout');
    }

    public function testWachttijdLooptOp(): void
    {
        $p = new \ReflectionProperty(Database::class, 'lockWaitMs');
        $p->setAccessible(true);
        $p->setValue(null, 20);
        $pogingen = 0;
        $start = microtime(true);
        $this->probeer(2, self::LOCK, $pogingen);
        $ms = (microtime(true) - $start) * 1000;
        $this->assertTrue($ms >= 55, "1 × 20 + 2 × 20 ms gewacht (gemeten: " . round($ms) . " ms)");
    }

    public function testOpenenEnQueriesLopenViaDeRetry(): void
    {
        $bron = (string) file_get_contents(__DIR__ . '/../../src/helpers/Database.php');
        $this->assertTrue(str_contains($bron, '$conn = self::withLockRetry(static fn () => new PDO($dsn'), 'createPDOConnection opent via withLockRetry');
        $this->assertFalse(
            (bool) preg_match('/\$stmt = \$conn->prepare\(self::forDriver\(\$conn, \$sql\)\);\s*\$stmt->execute\(self::paramsForDriver/',
                preg_replace('/private static function prepareAndExecute\(.*?^    \}/ms', '', $bron)),
            'geen kale prepare+execute meer buiten prepareAndExecute'
        );
        $this->assertTrue(substr_count($bron, 'self::prepareAndExecute($conn, $sql, $params)') >= 9, 'de query-methodes gebruiken prepareAndExecute');
        $this->assertTrue((bool) preg_match('/odbc_connect\(\$odbcConnStr.*?self::isLockError\(\(string\) @odbc_errormsg\(\)\)/s', $bron), 'native odbc_connect probeert ook opnieuw');
        $this->assertTrue(str_contains($bron, '$isLockError = self::isLockError($errorMsg);'), 'executeWithRetry gebruikt dezelfde herkenning');
        $this->assertFalse(str_contains($bron, '$odbcResult = @odbc_exec('), 'native odbc_exec loopt via odbcExecWithLockRetry');
        $this->assertEquals(2, substr_count($bron, '$odbcResult = self::odbcExecWithLockRetry($odbcConn, $sql);'), 'beide native odbc_exec-plekken in openRS');
    }
}
