<?php

use Hyungseok\PHPSwitcher\Console\SwitchCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends TestCase
{
    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        putenv('environment=testing');
    }

    /** @test */
    function five_point_six_version_execute()
    {
        $this->execute('5.6', '5.6.40');
    }

    /** @test */
    function seven_point_one_version_execute()
    {
        $this->execute('7.1', '7.1.29');
    }

    /** @test */
    function seven_point_two_version_execute()
    {
        $this->execute('7.2', '7.2.18');
    }

    /**
     * 주어진 버전의 커맨드 테스트를 실행합니다.
     *
     * @param string $version
     * @param string $fullVersion
     */
    protected function execute($version, $fullVersion)
    {
        $commandTester = new CommandTester(new SwitchCommand);

        $commandTester->execute([
            'version' => $version
        ]);

        $this->assertContains("PHP {$version} and Valet ready! Build something amazing.", $commandTester->getDisplay());
        // $this->assertEquals($fullVersion, phpversion());
    }
}