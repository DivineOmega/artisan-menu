<?php

namespace DivineOmega\ArtisanMenu\Console\Commands;

use DivineOmega\JsonKeyValueStore\JsonKeyValueStore;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use PhpSchool\CliMenu\Action\GoBackAction;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\MenuStyle;
use PhpSchool\CliMenu\Terminal\TerminalFactory;

class ArtisanMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use Artisan via a beautiful console GUI';

    private $app;
    private $commands;
    private $namespaces;

    private $store;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $returnCode = Artisan::call('list', ['--format' => 'json']);

        if ($returnCode != 0) {
            $this->error('Error calling `artisan list` command.');
            exit();
        }

        $response = json_decode(Artisan::output());

        if (!$response) {
            $this->error('Error decoding `artisan list` response.');
            exit();
        }

        $this->app = $response->application;
        $this->commands = new Collection($response->commands);
        $this->namespaces = new Collection($response->namespaces);

        $this->store = new JsonKeyValueStore(storage_path('artisan-menu.json.gz'));

        $this->mainMenu();

    }

    private function mainMenu()
    {

        $menu = new CliMenuBuilder();
        $menu->setTitle('Artisan Menu - '.$this->app->name.' '.$this->app->version);
        $menu->setMarginAuto();
        $menu->setBackgroundColour('black');
        $menu->setForegroundColour('white');

        $menu->addLineBreak();

        $recentCommands = $this->store->get('recent_commands');

        if (is_array($recentCommands)) {
            foreach($recentCommands as $commandName) {
                $this->addCommandMenuItem($menu, $this->getCommandByName($commandName));
            }
        }

        $menu->addLineBreak();

        foreach($this->namespaces as $namespace) {
            $title = $namespace->id == '_global' ? 'Global' : ucfirst($namespace->id);
            $menu->addSubMenuFromBuilder($title, $this->getNamespaceMenuBuilder($namespace));
        }

        $menu->addLineBreak();

        $menu = $menu->build();

        $menu->open();
    }

    private function getNamespaceMenuBuilder(object $namespace)
    {
        $menu = CliMenuBuilder::newSubMenu(TerminalFactory::fromSystem());
        $menu->setTitle('Artisan Menu - '.ucfirst($namespace->id).' - '.$this->app->name.' '.$this->app->version);
        $menu->setMarginAuto();
        $menu->setBackgroundColour('black');
        $menu->setForegroundColour('white');

        $namespaceRootCommand = $this->commands->firstWhere('name', $namespace->id);

        if ($namespaceRootCommand) {
            $this->addCommandMenuItem($menu,$namespaceRootCommand);
        }

        foreach($namespace->commands as $commandName) {
            $this->addCommandMenuItem($menu, $this->getCommandByName($commandName));
        }


        $menu->addLineBreak();

        return $menu;
    }

    private function getCommandByName(string $commandName)
    {
        return $this->commands->where('name', $commandName)->first();
    }

    private function addCommandMenuItem(CliMenuBuilder $menu, object $command)
    {
        if (in_array($command->name, ['list', 'help', 'inspire', 'optimize', 'menu'])) {
            return;
        }

        $menu->addItem(ucfirst($command->description.' ('.$command->name.')'), function($menu) use ($command) {
            $this->commandSelected($menu, $command);
        });
    }

    private function commandSelected(CliMenu $menu, object $command)
    {
        $recentCommands = $this->store->get('recent_commands');

        if (!is_array($recentCommands)) {
            $recentCommands = [];
        }

        $key = array_search($command->name, $recentCommands);

        if ($key !== false) {
            unset($recentCommands[$key]);
        }

        array_unshift($recentCommands, $command->name);

        while(count($recentCommands) > 5) {
            array_pop($recentCommands);
        }

        $this->store->set('recent_commands', $recentCommands);


        $menuStyle = new MenuStyle();
        $menuStyle->setBg('white');
        $menuStyle->setFg('black');

        $arguments = [];

        foreach($command->definition->arguments as $argument) {
            if ($argument->is_required) {

                $result = $menu->askText($menuStyle)
                    ->setPromptText('Please enter '.lcfirst($argument->description ? $argument->description : $argument->name))
                    ->ask();

                $arguments[$argument->name] = $result->fetch();
            }
        }

        try {
            Artisan::call($command->name, $arguments);
            $output = Artisan::output();
            $success = true;
        } catch (\Exception $e) {
            $output = $e->getMessage();
            $success = false;
        }

        if (!$output) {
            $output = 'This command produced no output.';
        }

        $lines = explode(PHP_EOL, $output);

        $outputMenu = (new CliMenuBuilder);
        $outputMenu->setTitle($command->description.' ('.$command->name.')');
        $outputMenu->setMarginAuto();
        $outputMenu->setBackgroundColour($success ? 'black': 'red');
        $outputMenu->setForegroundColour('white');
        $outputMenu->setExitButtonText('OK');
        foreach($lines as $line) {
            $outputMenu->addStaticItem($line);
        }
        $outputMenu->addLineBreak();
        $outputMenu = $outputMenu->build();
        $outputMenu->open();

        $menu->close();

        $this->mainMenu();


    }
}