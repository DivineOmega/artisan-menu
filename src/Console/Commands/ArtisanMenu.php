<?php

namespace DivineOmega\ArtisanMenu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
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
        $returnCode = Artisan::call('list', ['format' => 'json');

        if ($returnCode != 0) {
            $this->error('Error calling `artisan list` command.');
            exit();
        }

        $response = json_decode(Artisan::output());

        if (!$response) {
            $this->error('Error decoding `artisan list` response.');
            exit();
        }

        $this->app= new Collection($response->application);
        $this->commands = new Collection($response->commands);
        $this->namespaces = new Collection($response->namespaces);

        $this->mainMenu();

    }

    private function mainMenu()
    {

        $menu = new CliMenuBuilder();
        $menu->setTitle('Artisan Menu - '.this->app->name.' '.$this->app->version);
        $menu->addLineBreak('-')
        $menu->setBorder(1, 2, 'yellow')
        $menu->setPadding(2, 4)
        $menu->setMarginAuto()

        foreach($this->namespaces as $namespace) {
            $menu->addItem($namespace->id, ['this', 'namespaceMenu']);
        }

        $menu->build();
    }

    private function namespaceSelected(CliMenu $menu)
    {
        $selectedNamespace = $menu->getSelectedItem()->getText();
    }
}