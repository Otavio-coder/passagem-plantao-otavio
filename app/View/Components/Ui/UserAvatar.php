<?php

namespace App\View\Components\Ui;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class UserAvatar extends Component
{
    public function __construct(
        public ?string $photo = null,
        public string $name = 'U',
        public string $format = 'jpeg'
    ) {}

    public function render(): View|Closure|string
    {
        $name = trim($this->name) !== '' ? trim($this->name) : 'U';
        $words = preg_split('/[\s.]+/', $name) ?: ['U'];

        $initials = strtoupper(substr((string) ($words[0] ?? 'U'), 0, 1));
        if (count($words) > 1) {
            $initials .= strtoupper(substr((string) end($words), 0, 1));
        }

        $palette = ['4F46E5', '0891B2', '059669', 'B45309', '7C3AED', '0284C7', 'BE185D', '0F766E'];
        $bgColor = '#'.$palette[abs(crc32($name)) % count($palette)];

        return view('components.ui.user-avatar', [
            'initials' => $initials,
            'bgColor' => $bgColor,
        ]);
    }
}
