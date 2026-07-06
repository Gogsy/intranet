<?php

use App\Filament\Resources\DocNodeResource\RelationManagers\AttachmentsRelationManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

function validateDocUpload(string $fileName): bool
{
    $file = UploadedFile::fake()->create($fileName, 10);

    return Validator::make(
        ['file' => $file],
        ['file' => [AttachmentsRelationManager::blockScriptsRule()]],
    )->passes();
}

it('allows common company assets (fonts, templates, archives, images, docs)', function (string $name) {
    expect(validateDocUpload($name))->toBeTrue();
})->with([
    'corporate-font.ttf',
    'corporate-font.otf',
    'webfont.woff2',
    'memorandum.dotx',
    'template.potx',
    'logo.png',
    'logo.svg',
    'brandbook.pdf',
    'assets.zip',
    'archive.rar',
    'artwork.indd',
    'vector.ai',
    'photo.psd',
    'data.csv',
    'notes.txt',
]);

it('blocks scripts and executables', function (string $name) {
    expect(validateDocUpload($name))->toBeFalse();
})->with([
    'shell.php',
    'shell.phtml',
    'setup.exe',
    'installer.msi',
    'run.bat',
    'run.cmd',
    'script.ps1',
    'script.sh',
    'macro.vbs',
    'page.html',
    'app.js',
    'tool.jar',
    'mobile.apk',
]);
