<?php

namespace App\Services;

use App\Models\Folder;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Cache;

/**
 * The folder hierarchy, cached as one small map so that visibility, breadcrumbs
 * and "everything under this brand" never need a query per level.
 */
#[Scoped]
class FolderTree
{
    private const string CACHE_KEY = 'folder-tree';

    /**
     * @var array<int, array{parent_id: int|null, is_visible: bool}>|null
     */
    private ?array $nodes = null;

    /**
     * @var list<int>|null
     */
    private ?array $visibleIds = null;

    /**
     * Folders customers can see: the folder and every folder above it are visible.
     *
     * @return list<int>
     */
    public function visibleIds(): array
    {
        return $this->visibleIds ??= array_values(array_filter(
            array_keys($this->nodes()),
            fn (int $folderId): bool => $this->isVisible($folderId),
        ));
    }

    public function isVisible(int $folderId): bool
    {
        $nodes = $this->nodes();

        foreach ($this->pathTo($folderId) as $id) {
            if (! $nodes[$id]['is_visible']) {
                return false;
            }
        }

        return isset($nodes[$folderId]);
    }

    /**
     * The folders above this one, top level first.
     *
     * @return list<int>
     */
    public function ancestorIds(int $folderId): array
    {
        return array_slice($this->pathTo($folderId), 0, -1);
    }

    /**
     * This folder and every folder inside it, at any depth.
     *
     * @return list<int>
     */
    public function descendantIds(int $folderId): array
    {
        $childrenOf = [];

        foreach ($this->nodes() as $id => $node) {
            $childrenOf[$node['parent_id'] ?? 0][] = $id;
        }

        $found = [];
        $pending = [$folderId];

        while ($pending !== []) {
            $id = array_pop($pending);

            if (isset($found[$id])) {
                continue;
            }

            $found[$id] = true;
            array_push($pending, ...($childrenOf[$id] ?? []));
        }

        return array_keys($found);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);

        $this->nodes = null;
        $this->visibleIds = null;
    }

    /**
     * The path from the top level down to this folder, ending with the folder itself.
     *
     * @return list<int>
     */
    private function pathTo(int $folderId): array
    {
        $nodes = $this->nodes();
        $path = [];

        // The admin form forbids cycles; the step limit only guards against bad data.
        for ($id = $folderId, $steps = 0; $id !== null && isset($nodes[$id]) && $steps <= count($nodes); $steps++) {
            array_unshift($path, $id);
            $id = $nodes[$id]['parent_id'];
        }

        return $path;
    }

    /**
     * @return array<int, array{parent_id: int|null, is_visible: bool}>
     */
    private function nodes(): array
    {
        /** @var array<int, array{parent_id: int|null, is_visible: bool}> */
        return $this->nodes ??= Cache::rememberForever(self::CACHE_KEY, fn (): array => Folder::query()
            ->toBase()
            ->get(['id', 'parent_id', 'is_visible'])
            ->mapWithKeys(fn (object $folder): array => [
                (int) $folder->id => [
                    'parent_id' => $folder->parent_id === null ? null : (int) $folder->parent_id,
                    'is_visible' => (bool) $folder->is_visible,
                ],
            ])
            ->all());
    }
}
