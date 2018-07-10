<?php

namespace DivineOmega\ArtisanMenu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\CliMenu;

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

        $this->mainMenu();

    }

    private function mainMenu()
    {

        $menu = new CliMenuBuilder();
        $menu->setTitle('Artisan Menu - '.$this->app->name.' '.$this->app->version);
        $menu->setMarginAuto();
        $menu->setBackgroundColour('black');
        $menu->setForegroundColour('white');

        foreach($this->namespaces as $namespace) {

            if ($namespace->id == '_global') {
                foreach($namespace->commands as $commandName) {

                    if (in_array($commandName, [$this->signature, 'list', 'help', 'inspire'])) {
                        continue;
                    }

                    $command = $this->commands->where('name', $commandName)->first();
                    $menu->addItem(ucfirst($command->description.' ('.$command->name.')'), function($menu) {
                        $this->commandSelected($menu);
                    });
                }
                $menu->addLineBreak();
                continue;
            }

            $menu->addItem(ucfirst($namespace->id), function($menu) {
                $this->namespaceSelected($menu);
            });
        }

        $menu->addLineBreak();

        $menu = $menu->build();

        $menu->open();
    }

    private function namespaceSelected(CliMenu $menu)
    {
        $selectedNamespace = $menu->getSelectedItem()->getText();
    }

    private function commandSelected(CliMenu $menu)
    {
        $selectedNamespace = $menu->getSelectedItem()->getText();
    }
}