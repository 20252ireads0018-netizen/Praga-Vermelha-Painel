<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Aba pública "Vídeos explicativos": lista de config/videos.php (só os que já têm arquivo em public/). */
class VideoController extends Controller
{
    public function index(): View
    {
        $videos = collect(config('videos', []))
            ->filter(fn ($v) => is_file(public_path($v['arquivo'])))
            ->map(function ($v) {
                $arq = public_path($v['arquivo']);

                return $v + ['versao' => @filemtime($arq) ?: 1, 'mb' => number_format(filesize($arq) / 1048576, 1, ',', '.')];
            })
            ->values();

        return view('videos', ['videos' => $videos]);
    }
}
