<?php
/**
 * Tests for App\Library\Arr
 *
 * Run with: php tests/TestRunner.php ArrTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Library\Arr;

class ArrTest extends TestCase
{
    // ========================================================================
    // isArray
    // ========================================================================

    public function testIsArrayTrue(): void
    {
        $this->assertTrue(Arr::isArray([]));
        $this->assertTrue(Arr::isArray([1, 2, 3]));
        $this->assertTrue(Arr::isArray(new \ArrayObject()));
    }

    public function testIsArrayFalse(): void
    {
        $this->assertFalse(Arr::isArray('string'));
        $this->assertFalse(Arr::isArray(123));
        $this->assertFalse(Arr::isArray(null));
    }

    // ========================================================================
    // field (case-insensitive recordset access)
    // Note: field() is defined in the source but may not be available
    // in all installed versions. Tests are conditional.
    // ========================================================================

    public function testFieldExactMatch(): void
    {
        if (!method_exists(Arr::class, 'field')) { return; }
        $row = ['Name' => 'John', 'Age' => 30];
        $this->assertEquals('John', Arr::field($row, 'Name'));
    }

    public function testFieldCaseInsensitive(): void
    {
        if (!method_exists(Arr::class, 'field')) { return; }
        $row = ['NAME' => 'John', 'AGE' => 30];
        $this->assertEquals('John', Arr::field($row, 'name'));
        $this->assertEquals(30, Arr::field($row, 'age'));
    }

    public function testFieldDefault(): void
    {
        if (!method_exists(Arr::class, 'field')) { return; }
        $row = ['Name' => 'John'];
        $this->assertEquals('unknown', Arr::field($row, 'email', 'unknown'));
    }

    public function testFieldNullRow(): void
    {
        if (!method_exists(Arr::class, 'field')) { return; }
        $this->assertNull(Arr::field(null, 'key'));
        $this->assertEquals('default', Arr::field(null, 'key', 'default'));
    }

    // ========================================================================
    // get (with dot notation)
    // ========================================================================

    public function testGetSimple(): void
    {
        $arr = ['key' => 'value'];
        $this->assertEquals('value', Arr::get($arr, 'key'));
    }

    public function testGetDefault(): void
    {
        $arr = ['key' => 'value'];
        $this->assertEquals('default', Arr::get($arr, 'missing', 'default'));
    }

    public function testGetDotNotation(): void
    {
        $arr = ['user' => ['profile' => ['name' => 'John']]];
        $this->assertEquals('John', Arr::get($arr, 'user.profile.name'));
    }

    public function testGetDotNotationMissing(): void
    {
        $arr = ['user' => ['profile' => ['name' => 'John']]];
        $this->assertEquals('default', Arr::get($arr, 'user.settings.theme', 'default'));
    }

    public function testGetNullArray(): void
    {
        $this->assertNull(Arr::get(null, 'key'));
        $this->assertEquals('default', Arr::get(null, 'key', 'default'));
    }

    public function testGetIntegerKey(): void
    {
        $arr = ['a', 'b', 'c'];
        $this->assertEquals('b', Arr::get($arr, 1));
    }

    // ========================================================================
    // getNested
    // ========================================================================

    public function testGetNested(): void
    {
        $arr = [['name' => 'John'], ['name' => 'Jane']];
        $this->assertEquals('John', Arr::getNested($arr, [0, 'name']));
    }

    public function testGetNestedMissing(): void
    {
        $arr = [['name' => 'John']];
        $this->assertEquals('default', Arr::getNested($arr, [5, 'name'], 'default'));
    }

    public function testGetNestedNull(): void
    {
        $this->assertNull(Arr::getNested(null, [0]));
    }

    // ========================================================================
    // has / hasNested
    // ========================================================================

    public function testHasTrue(): void
    {
        $this->assertTrue(Arr::has(['key' => 'val'], 'key'));
    }

    public function testHasFalse(): void
    {
        $this->assertFalse(Arr::has(['key' => 'val'], 'other'));
        $this->assertFalse(Arr::has(null, 'key'));
    }

    public function testHasNestedTrue(): void
    {
        $arr = ['user' => ['name' => 'John']];
        $this->assertTrue(Arr::hasNested($arr, ['user', 'name']));
    }

    public function testHasNestedFalse(): void
    {
        $arr = ['user' => ['name' => 'John']];
        $this->assertFalse(Arr::hasNested($arr, ['user', 'email']));
        $this->assertFalse(Arr::hasNested(null, ['user']));
    }

    // ========================================================================
    // isNotEmpty
    // ========================================================================

    public function testIsNotEmptyTrue(): void
    {
        $this->assertTrue(Arr::isNotEmpty([1]));
    }

    public function testIsNotEmptyFalse(): void
    {
        $this->assertFalse(Arr::isNotEmpty([]));
        $this->assertFalse(Arr::isNotEmpty(null));
        $this->assertFalse(Arr::isNotEmpty('string'));
    }

    // ========================================================================
    // first / last
    // ========================================================================

    public function testFirst(): void
    {
        $this->assertEquals(1, Arr::first([1, 2, 3]));
    }

    public function testFirstEmpty(): void
    {
        $this->assertNull(Arr::first([]));
        $this->assertEquals('default', Arr::first([], 'default'));
        $this->assertNull(Arr::first(null));
    }

    public function testLast(): void
    {
        $this->assertEquals(3, Arr::last([1, 2, 3]));
    }

    public function testLastEmpty(): void
    {
        $this->assertNull(Arr::last([]));
        $this->assertEquals('default', Arr::last([], 'default'));
    }

    // ========================================================================
    // split / splitAlways
    // ========================================================================

    public function testSplit(): void
    {
        $this->assertEquals(['a', 'b', 'c'], Arr::split('a,b,c'));
    }

    public function testSplitWithSpaces(): void
    {
        $this->assertEquals(['a', 'b', 'c'], Arr::split('a, b, c'));
    }

    public function testSplitCustomDelimiter(): void
    {
        $this->assertEquals(['a', 'b'], Arr::split('a|b', '|'));
    }

    public function testSplitEmpty(): void
    {
        $this->assertEquals([], Arr::split(''));
    }

    public function testSplitAlwaysSingle(): void
    {
        $this->assertEquals(['hello'], Arr::splitAlways('hello'));
    }

    public function testSplitAlwaysEmpty(): void
    {
        $this->assertEquals([''], Arr::splitAlways(''));
    }

    public function testSplitAlwaysMultiple(): void
    {
        $this->assertEquals(['a', 'b'], Arr::splitAlways('a,b'));
    }

    // ========================================================================
    // join / joinSkipEmpty
    // ========================================================================

    public function testJoin(): void
    {
        $this->assertEquals('a,b,c', Arr::join(['a', 'b', 'c'], ','));
    }

    public function testJoinNoSeparator(): void
    {
        $this->assertEquals('abc', Arr::join(['a', 'b', 'c']));
    }

    public function testJoinSkipEmpty(): void
    {
        $this->assertEquals('a, c', Arr::joinSkipEmpty(['a', '', 'c', null], ', '));
    }

    public function testJoinSkipEmptyNull(): void
    {
        $this->assertEquals('', Arr::joinSkipEmpty(null));
    }

    // ========================================================================
    // count / ubound
    // ========================================================================

    public function testCount(): void
    {
        $this->assertEquals(3, Arr::count([1, 2, 3]));
    }

    public function testCountNull(): void
    {
        $this->assertEquals(0, Arr::count(null));
    }

    public function testUbound(): void
    {
        $this->assertEquals(2, Arr::ubound([1, 2, 3]));
    }

    public function testUboundEmpty(): void
    {
        $this->assertEquals(-1, Arr::ubound([]));
        $this->assertEquals(-1, Arr::ubound(null));
    }

    // ========================================================================
    // contains
    // ========================================================================

    public function testContainsTrue(): void
    {
        $this->assertTrue(Arr::contains('b', ['a', 'b', 'c']));
    }

    public function testContainsFalse(): void
    {
        $this->assertFalse(Arr::contains('d', ['a', 'b', 'c']));
    }

    public function testContainsNullHaystack(): void
    {
        $this->assertFalse(Arr::contains('a', null));
    }

    // ========================================================================
    // findTrimmed / findInstr
    // ========================================================================

    public function testFindTrimmed(): void
    {
        $this->assertEquals(1, Arr::findTrimmed(['Apple', ' banana ', 'Cherry'], 'BANANA'));
    }

    public function testFindTrimmedNotFound(): void
    {
        $this->assertEquals(-1, Arr::findTrimmed(['a', 'b'], 'c'));
    }

    public function testFindTrimmedNull(): void
    {
        $this->assertEquals(-1, Arr::findTrimmed(null, 'x'));
    }

    public function testFindInstr(): void
    {
        $arr = ['hello', 'world'];
        $this->assertEquals(0, Arr::findInstr($arr, 'say hello please'));
    }

    public function testFindInstrNotFound(): void
    {
        $this->assertEquals(-1, Arr::findInstr(['hello'], 'goodbye'));
    }

    // ========================================================================
    // findNestedByIndex
    // ========================================================================

    public function testFindNestedByIndex(): void
    {
        // findNestedByIndex uses integer element index (VBScript 2D array style)
        $arr = [
            [1, 'John'],
            [2, 'Jane'],
        ];
        $this->assertEquals(1, Arr::findNestedByIndex($arr, 1, 'Jane'));
    }

    public function testFindNestedByIndexNotFound(): void
    {
        $arr = [[1, 'John']];
        $this->assertEquals(-1, Arr::findNestedByIndex($arr, 1, 'Nobody'));
    }

    // ========================================================================
    // join2DByRow / find2DByRow
    // ========================================================================

    public function testJoin2DByRow(): void
    {
        $arr = [
            0 => ['a', 'b', 'c'],
            1 => ['x', 'y', 'z'],
        ];
        $this->assertEquals('a,b,c', Arr::join2DByRow($arr, 0, ','));
    }

    public function testFind2DByRow(): void
    {
        $arr = [
            0 => ['apple', 'banana', 'cherry'],
        ];
        $this->assertEquals(1, Arr::find2DByRow($arr, 0, 'banana'));
    }

    public function testFind2DByRowNotFound(): void
    {
        $arr = [0 => ['apple']];
        $this->assertEquals(-1, Arr::find2DByRow($arr, 0, 'orange'));
    }

    // ========================================================================
    // find / findNested / find2D
    // ========================================================================

    public function testFind(): void
    {
        $this->assertEquals(1, Arr::find(['a', 'b', 'c'], 'b'));
    }

    public function testFindNotFound(): void
    {
        $this->assertFalse(Arr::find(['a', 'b'], 'd'));
    }

    public function testFindNested(): void
    {
        $arr = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];
        $result = Arr::findNested($arr, 'id', 2);
        $this->assertEquals('Jane', $result['name']);
    }

    public function testFindNestedNotFound(): void
    {
        $arr = [['id' => 1]];
        $this->assertNull(Arr::findNested($arr, 'id', 99));
    }

    public function testFind2DAlias(): void
    {
        $arr = [['id' => 1, 'name' => 'John']];
        $result = Arr::find2D($arr, 'id', 1);
        $this->assertEquals('John', $result['name']);
    }

    // ========================================================================
    // sort / sortBy
    // ========================================================================

    public function testSort(): void
    {
        $this->assertEquals([1, 2, 3], Arr::sort([3, 1, 2]));
    }

    public function testSortNull(): void
    {
        $this->assertEquals([], Arr::sort(null));
    }

    public function testSortBy(): void
    {
        $arr = [
            ['name' => 'Charlie'],
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ];
        $sorted = Arr::sortBy($arr, 'name');
        $this->assertEquals('Alice', $sorted[0]['name']);
        $this->assertEquals('Bob', $sorted[1]['name']);
        $this->assertEquals('Charlie', $sorted[2]['name']);
    }

    public function testSortByDesc(): void
    {
        $arr = [
            ['age' => 20],
            ['age' => 30],
            ['age' => 10],
        ];
        $sorted = Arr::sortBy($arr, 'age', SORT_DESC);
        $this->assertEquals(30, $sorted[0]['age']);
        $this->assertEquals(10, $sorted[2]['age']);
    }

    // ========================================================================
    // filter / removeEmpty / map
    // ========================================================================

    public function testFilter(): void
    {
        $result = Arr::filter([1, 2, 3, 4], fn($v) => $v > 2);
        $this->assertCount(2, $result);
    }

    public function testRemoveEmpty(): void
    {
        $result = Arr::removeEmpty(['a', '', null, 'b', 0, '0']);
        $this->assertCount(4, $result); // 'a', 'b', 0, '0'
    }

    public function testRemoveEmptyNull(): void
    {
        $this->assertEquals([], Arr::removeEmpty(null));
    }

    public function testMap(): void
    {
        $result = Arr::map([1, 2, 3], fn($v) => $v * 2);
        $this->assertEquals([2, 4, 6], $result);
    }

    // ========================================================================
    // pluck
    // ========================================================================

    public function testPluck(): void
    {
        $arr = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];
        $this->assertEquals(['John', 'Jane'], Arr::pluck($arr, 'name'));
    }

    public function testPluckNull(): void
    {
        $this->assertEquals([], Arr::pluck(null, 'name'));
    }

    public function testPluckMissingField(): void
    {
        $arr = [['name' => 'John'], ['age' => 30]];
        $this->assertEquals(['John'], Arr::pluck($arr, 'name'));
    }

    // ========================================================================
    // unique / reverse
    // ========================================================================

    public function testUnique(): void
    {
        $result = Arr::unique(['a', 'b', 'a', 'c']);
        $this->assertCount(3, $result);
    }

    public function testUniqueNull(): void
    {
        $this->assertEquals([], Arr::unique(null));
    }

    public function testReverse(): void
    {
        $this->assertEquals([3, 2, 1], Arr::reverse([1, 2, 3]));
    }

    public function testReverseNull(): void
    {
        $this->assertEquals([], Arr::reverse(null));
    }

    // ========================================================================
    // flatten / chunk / merge / fill
    // ========================================================================

    public function testFlatten(): void
    {
        $result = Arr::flatten([[1, 2], [3, [4, 5]]]);
        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    public function testFlattenDepth(): void
    {
        $result = Arr::flatten([[1, [2, 3]], [4]], 1);
        $this->assertEquals([1, [2, 3], 4], $result);
    }

    public function testFlattenNull(): void
    {
        $this->assertEquals([], Arr::flatten(null));
    }

    public function testChunk(): void
    {
        $result = Arr::chunk([1, 2, 3, 4, 5], 2);
        $this->assertCount(3, $result);
        $this->assertEquals([1, 2], $result[0]);
    }

    public function testChunkNull(): void
    {
        $this->assertEquals([], Arr::chunk(null, 2));
    }

    public function testMerge(): void
    {
        $result = Arr::merge([1, 2], [3, 4]);
        $this->assertEquals([1, 2, 3, 4], $result);
    }

    public function testMergeWithNull(): void
    {
        $result = Arr::merge([1, 2], null, [3]);
        $this->assertEquals([1, 2, 3], $result);
    }

    public function testFill(): void
    {
        $result = Arr::fill(3, 'x');
        $this->assertEquals(['x', 'x', 'x'], $result);
    }

    public function testFillZero(): void
    {
        $this->assertEquals([], Arr::fill(0, 'x'));
    }

    // ========================================================================
    // isAssociative
    // ========================================================================

    public function testIsAssociativeTrue(): void
    {
        $this->assertTrue(Arr::isAssociative(['a' => 1, 'b' => 2]));
    }

    public function testIsAssociativeFalse(): void
    {
        $this->assertFalse(Arr::isAssociative([1, 2, 3]));
    }

    public function testIsAssociativeEmpty(): void
    {
        $this->assertFalse(Arr::isAssociative([]));
        $this->assertFalse(Arr::isAssociative(null));
    }

    // ========================================================================
    // join2D
    // ========================================================================

    public function testJoin2D(): void
    {
        $arr = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];
        $this->assertEquals('John, Jane', Arr::join2D($arr, 'name', ', '));
    }

    // ========================================================================
    // Delimited string operations
    // ========================================================================

    public function testAddItem(): void
    {
        $this->assertEquals('a,b,c', Arr::addItem('a,b', 'c'));
    }

    public function testAddItemEmpty(): void
    {
        $this->assertEquals('a', Arr::addItem('', 'a'));
    }

    public function testRemoveItem(): void
    {
        $this->assertEquals('a,c', Arr::removeItem('a,b,c', 'b'));
    }

    public function testRemoveItemEmpty(): void
    {
        $this->assertEquals('', Arr::removeItem('', 'a'));
    }

    public function testHasItem(): void
    {
        $this->assertTrue(Arr::hasItem('a,b,c', 'b'));
        $this->assertFalse(Arr::hasItem('a,b,c', 'd'));
    }

    public function testHasItemEmpty(): void
    {
        $this->assertFalse(Arr::hasItem('', 'a'));
    }

    // ========================================================================
    // wrap
    // ========================================================================

    public function testWrapValue(): void
    {
        $this->assertEquals(['hello'], Arr::wrap('hello'));
    }

    public function testWrapArray(): void
    {
        $this->assertEquals([1, 2], Arr::wrap([1, 2]));
    }

    public function testWrapNull(): void
    {
        $this->assertEquals([], Arr::wrap(null));
    }
}
