<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

class FileController extends Controller
{
    public function show($filename)
    {
        $filename = ltrim(str_replace('\\', '/', (string) $filename), '/');

        if ($filename === '' || str_contains($filename, '..')) {
            abort(404);
        }

        $relativePath = str_starts_with($filename, 'public/')
            ? substr($filename, strlen('public/'))
            : $filename;

        $basePath = realpath(storage_path('app/public'));
        $path = realpath(storage_path('app/public/' . $relativePath));

        if (!$basePath || !$path || !str_starts_with($path, $basePath . DIRECTORY_SEPARATOR) || !is_file($path)) {
            abort(404);
        }

        $file = File::get($path);
        $type = File::mimeType($path);

        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }
}
