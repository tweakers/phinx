<?php

namespace Test\Phinx\Console\Command;

use InvalidArgumentException;
use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Console\Command\Breakpoint;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

class BreakpointTest extends TestCase
{
    /**
     * @var ConfigInterface|array
     */
    protected $config = [];

    /**
     * @var InputInterface $input
     */
    protected $input;

    /**
     * @var OutputInterface $output
     */
    protected $output;

    /**
     * Default Test Environment
     */
    const DEFAULT_TEST_ENVIRONMENT = 'development';

    protected function setUp()
    {
        @mkdir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations', 0777, true);
        $this->config = new Config(
            [
                'paths' => [
                    'migrations' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations',
                ],
                'environments' => [
                    'default_migration_table' => 'phinxlog',
                    'default_database' => 'development',
                    'development' => [
                        'adapter' => 'mysql',
                        'host' => 'fakehost',
                        'name' => 'development',
                        'user' => '',
                        'pass' => '',
                        'port' => 3006,
                    ],
                ],
            ]
        );

        foreach ($this->config->getMigrationPaths() as $path) {
            foreach (glob($path . '/*.*') as $migration) {
                unlink($migration);
            }
        }

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    /**
     * @param string $testMethod
     * @param array $commandLine
     * @param int|null $version
     * @param bool $noVersionParameter
     *
     * @dataProvider provideBreakpointTests
     */
    public function testExecute($testMethod, $commandLine, $version = null, $noVersionParameter = false)
    {
        $application = new PhinxApplication('testing');
        $application->add(new Breakpoint());

        /** @var Breakpoint $command */
        $command = $application->find('breakpoint');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        if ($noVersionParameter) {
            $managerStub->expects($this->once())
                ->method($testMethod)
                ->with(self::DEFAULT_TEST_ENVIRONMENT);
        } else {
            $managerStub->expects($this->once())
                ->method($testMethod)
                ->with(self::DEFAULT_TEST_ENVIRONMENT, $version);
        }

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = array_merge(['command' => $command->getName()], $commandLine);
        $commandTester->execute($commandLine, ['decorated' => false]);
    }

    public function provideBreakpointTests()
    {
        return [
            'Toggle Breakpoint' => [
                'toggleBreakpoint',
                [],
            ],
            'Set Breakpoint without a target' => [
                'setBreakpoint',
                [
                    '--set' => true,
                ],
            ],
            'Set Breakpoint with a target' => [
                'setBreakpoint',
                [
                    '--set' => true,
                    '--target' => '123456',
                ],
                '123456',
            ],
            'Unset Breakpoint without a target' => [
                'unsetBreakpoint',
                [
                    '--unset' => true,
                ],
            ],
            'Unset Breakpoint with a target' => [
                'unsetBreakpoint',
                [
                    '--unset' => true,
                    '--target' => '123456',
                ],
                '123456',
            ],
            'Remove Breakpoints' => [
                'removeBreakpoints',
                [
                    '--remove-all' => true,
                ],
                null,
                true,
            ],
        ];
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Cannot toggle a breakpoint and remove all breakpoints at the same time.
     */
    public function testRemoveAllAndTargetThrowsException()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Breakpoint());

        /** @var Breakpoint $command */
        $command = $application->find('breakpoint');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandTester->execute(
            [
                'command' => $command->getName(),
                '--remove-all' => true,
                '--target' => '123456',
            ],
            ['decorated' => false]
        );
    }

    /**
     * @param array $commandLine
     *
     * @dataProvider provideCombinedParametersToCauseException
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Cannot use more than one of --set, --unset, or --remove-all at the same time.
     */
    public function testRemoveAllSetUnsetCombinedThrowsException($commandLine)
    {
        $application = new PhinxApplication('testing');
        $application->add(new Breakpoint());

        /** @var Breakpoint $command */
        $command = $application->find('breakpoint');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = array_merge(['command' => $command->getName()], $commandLine);
        $commandTester->execute($commandLine, ['decorated' => false]);
    }

    public function provideCombinedParametersToCauseException()
    {
        return [
            'Remove with Set' => [
                [
                    '--remove-all' => true,
                    '--set' => true,
                ]
            ],
            'Remove with Unset' => [
                [
                    '--remove-all' => true,
                    '--unset' => true,
                ]
            ],
            'Set with Unset' => [
                [
                    '--set' => true,
                    '--unset' => true,
                ]
            ],
        ];
    }
}
