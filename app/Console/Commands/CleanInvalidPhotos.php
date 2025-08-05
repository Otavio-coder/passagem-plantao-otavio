<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\System\User;

class CleanInvalidPhotos extends Command
{
    protected $signature = 'users:clean-photos';
    protected $description = 'Clean invalid photo data from users';

    public function handle()
    {
        $users = User::whereNotNull('photo')->get();
        $cleaned = 0;

        foreach ($users as $user) {
            if (!$user->hasValidPhoto()) {
                $user->photo = null;
                $user->save();
                $cleaned++;
                $this->info("Cleaned photo for: {$user->name}");
            }
        }

        $this->info("Cleaned {$cleaned} invalid photos.");
        return 0;
    }
}