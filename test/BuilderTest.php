<?hh // strict

namespace HackPack\Scanner\Test;

use HackPack\Scanner\Builder;
use HackPack\Scanner\Contract\Scanner;

class BuilderTest extends \PHPUnit_Framework_TestCase
{
    private Set<string> $filesFromBuilder = Set{};

    public function setUp() : void
    {
        $this->filesFromBuilder->clear();
    }

    private function makeBuilder() : Builder
    {
        return new Builder(($files, $set) ==> {
            $this->filesFromBuilder->addAll($files);
            return $this->getMock(Scanner::class);
        });
    }

    private function checkFileList(Set<string> $expected) : void
    {
        $missingFiles = $expected->toSet()->removeAll($this->filesFromBuilder);
        $extraFiles = $this->filesFromBuilder->toSet()->removeAll($expected);

        $this->assertTrue($missingFiles->isEmpty(), 'Builder did not find some files: ' . var_export($missingFiles, true));
        $this->assertTrue($extraFiles->isEmpty(), 'Builder found some extra files: ' . var_export($extraFiles, true));
    }

    public function testBuilderFindsAllFiles() : void
    {
        $this->makeBuilder()->addPath(__DIR__ . '/Fixture')->getScanner();

        $this->checkFileList(Set{
            __DIR__ . '/Fixture/more/file1',
            __DIR__ . '/Fixture/more/file2',
            __DIR__ . '/Fixture/more/file3.txt',
            __DIR__ . '/Fixture/sub/file1',
            __DIR__ . '/Fixture/sub/file2',
            __DIR__ . '/Fixture/sub/file3.txt',
            __DIR__ . '/Fixture/hack.hh',
            __DIR__ . '/Fixture/noextension',
            __DIR__ . '/Fixture/php.php',
            __DIR__ . '/Fixture/text.txt',
        });
    }
}
