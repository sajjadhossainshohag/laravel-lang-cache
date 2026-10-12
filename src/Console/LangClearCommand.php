<?php

namespace SajjadHossainShohag\LangCache\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use SajjadHossainShohag\LangCache\LangCache;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lang:clear')]
class LangClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lang:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the translation cache file';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function handle(Filesystem $files)
    {
        $files->delete(LangCache::path($this->laravel));

        $this->components->info('Translation cache cleared successfully.');
    }
}
