<?php

use App\Services\Drive\DriveId;

test('the Drive ID is read from share links and bare IDs', function (string $input) {
    expect(DriveId::fromInput($input))->toBe('1AbCdEfGh_IjK-LmN');
})->with([
    'file view link' => 'https://drive.google.com/file/d/1AbCdEfGh_IjK-LmN/view?usp=sharing',
    'folder link' => 'https://drive.google.com/drive/folders/1AbCdEfGh_IjK-LmN?usp=drive_link',
    'folder link for a second account' => 'https://drive.google.com/drive/u/1/folders/1AbCdEfGh_IjK-LmN',
    'open link' => 'https://drive.google.com/open?id=1AbCdEfGh_IjK-LmN',
    'download link' => 'https://drive.google.com/uc?export=download&id=1AbCdEfGh_IjK-LmN',
    'bare ID with spaces around it' => '  1AbCdEfGh_IjK-LmN ',
]);

test('anything that is not a Drive link or ID is refused', function (string $input) {
    expect(DriveId::fromInput($input))->toBeNull();
})->with([
    'empty' => '',
    'too short to be an ID' => 'abc123',
    'plain words' => 'Samsung firmware folder',
    'ID with a quote' => "1AbCdEfGh_IjK' or '1",
]);
