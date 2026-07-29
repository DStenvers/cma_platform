<?php
/**
 * Tests for Cma\CmaRepository::getDefaultDatabaseId() — which database the CMA
 * selectors (SQL uitvoeren, Databasestructuur, rapportontwerper) open on.
 *
 * Run with: php cma/tests/TestRunner.php DatabaseDefaultSelectionTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';
require_once __DIR__ . '/../classes/CmaRepository.php';

use Cma\ConfigLoader;
use Cma\CmaRepository;

class DatabaseDefaultSelectionTest extends TestCase
{
    private string $tmpRoot = '';

    private function writeDatabases(array $entries): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/db-default-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0755, true);
        file_put_contents(
            $this->tmpRoot . '/databases.json',
            json_encode(['databases' => $entries], JSON_PRETTY_PRINT)
        );
        ConfigLoader::setConfigPath($this->tmpRoot);
    }

    private function entry(int $id, string $name, string $file, bool $default = false): array
    {
        $entry = [
            'id' => $id,
            'name' => $name,
            'type' => 'access',
            'connectionString' => 'Provider=Microsoft.Jet.OLEDB.4.0;Data Source=[db/' . $file . ']',
        ];
        if ($default) {
            $entry['default'] = true;
        }
        return $entry;
    }

    public function testExplicitDefaultWins(): void
    {
        // The flagged entry is neither named 'data' nor one of the filename
        // patterns the heuristic knows — without the flag it would lose.
        $this->writeDatabases([
            $this->entry(6, 'data', 'main.mdb'),
            $this->entry(9, 'archief', 'archief2024.mdb', true),
        ]);
        $this->assertSame(9, CmaRepository::getDefaultDatabaseId());
    }

    public function testFallsBackToDataDatabaseWithoutFlag(): void
    {
        $this->writeDatabases([
            $this->entry(9, 'archief', 'archief2024.mdb'),
            $this->entry(6, 'data', 'main.mdb'),
        ]);
        $this->assertSame(6, CmaRepository::getDefaultDatabaseId());
    }

    public function testFirstFlaggedEntryWinsWhenSeveralAreMarked(): void
    {
        $this->writeDatabases([
            $this->entry(9, 'archief', 'archief2024.mdb', true),
            $this->entry(7, 'extra', 'extra.mdb', true),
        ]);
        $this->assertSame(9, CmaRepository::getDefaultDatabaseId());
    }

    public function testFlagIsExposedOnEveryOption(): void
    {
        $this->writeDatabases([
            $this->entry(9, 'archief', 'archief2024.mdb', true),
            $this->entry(6, 'data', 'main.mdb'),
        ]);
        $options = CmaRepository::getSelectableDatabases();
        $flags = [];
        foreach ($options as $opt) {
            $flags[$opt['id']] = $opt['isDefault'];
        }
        $this->assertSame([9 => true, 6 => false], $flags);
    }

    public function testSyntheticFallbackIsItsOwnDefault(): void
    {
        $this->writeDatabases([]);
        $options = CmaRepository::getSelectableDatabases();
        $this->assertCount(1, $options);
        $this->assertTrue($options[0]['isDefault']);
        $this->assertSame(0, CmaRepository::getDefaultDatabaseId());
    }
}
